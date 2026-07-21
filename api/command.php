<?php
// Command queue. Two callers:
//   Engine (Bearer token):  GET -> pending commands | POST {"done": id} -> ack
//   Dashboard (session):    POST {"action","ticker"} -> create approval command
define('APP', 1);
require __DIR__ . '/../inc/engine.php';
header('Content-Type: application/json');

if (bearer_ok()) {                                    // ---- engine mode ----
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode(['pending' => commands_pending()]);
        exit;
    }
    $b = json_decode(file_get_contents('php://input'), true);
    if (!empty($b['done'])) {
        command_done((int) $b['done']);
        echo '{"ok":true}';
        exit;
    }
    if (!empty($b['expire'])) {
        command_expire((int) $b['expire']);
        echo '{"ok":true}';
        exit;
    }
    http_response_code(400); echo '{"error":"bad engine request"}'; exit;
}

boot_session();                                       // ---- dashboard mode ----
if (empty($_SESSION['uid'])) { http_response_code(401); echo '{"error":"login"}'; exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo '{"error":"POST only"}'; exit;
}
$b = json_decode(file_get_contents('php://input'), true);
if (($b['csrf'] ?? '') !== ($_SESSION['csrf'] ?? null)) {
    http_response_code(403); echo '{"error":"csrf"}'; exit;
}
$action = $b['action'] ?? '';
$ticker = strtoupper(preg_replace('/[^A-Za-z\-]/', '', $b['ticker'] ?? ''));
if ($action === 'RUN_ANALYSIS') { $ticker = 'ALL'; }
if (!in_array($action, ['APPROVE_BUY', 'APPROVE_DCA', 'HOLD_DCA',
                        'APPROVE_SELL_ALL', 'CANCEL_CAMPAIGN',
                        'RUN_ANALYSIS'], true) || !$ticker) {
    http_response_code(400); echo '{"error":"bad command"}'; exit;
}
$analysisSource = (($b['analysis_source'] ?? '') === 'historical') ? 'historical' : 'current';
$analysisRunId = preg_match('/^analysis-[a-zA-Z0-9-]{12,80}$/',
    (string) ($b['analysis_run_id'] ?? '')) ? (string) $b['analysis_run_id'] : '';
$note = 'one-click from dashboard by ' . $_SESSION['email'];
if ($action === 'APPROVE_DCA') {
    $quantity = filter_var($b['quantity'] ?? null, FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 10000]]);
    if ($quantity === false) {
        http_response_code(400); echo '{"error":"quantity must be a whole number from 1 to 10000"}'; exit;
    }
    $note .= ';requested_qty=' . $quantity;
}
if ($action === 'APPROVE_BUY') {
    $note .= ';analysis_source=' . $analysisSource;
    if ($analysisRunId !== '') { $note .= ';analysis_run_id=' . $analysisRunId; }
}
$runId = null;
if ($action === 'RUN_ANALYSIS') {
    $currentStatus = doc_get('analysis_status');
    $currentState = strtolower((string) ($currentStatus['data']['state'] ?? ''));
    $currentAge = max(0, time() - (int) ($currentStatus['updated_epoch'] ?? 0));
    $currentHealth = doc_get('engine_health');
    $healthData = $currentHealth['data'] ?? [];
    $lastSeen = strtotime((string) ($healthData['last_seen_at'] ?? '')) ?: 0;
    $staleAfter = max(30, (int) ($healthData['stale_after_seconds'] ?? 95));
    $engineOnline = (($healthData['status'] ?? '') === 'running' && $lastSeen
                     && time() - $lastSeen <= $staleAfter);
    if (in_array($currentState, ['starting', 'running'], true)
            && $currentAge <= 35 * 60 && $engineOnline) {
        $currentRunId = (string) ($currentStatus['data']['run_id'] ?? '');
        if (!$currentRunId) {
            $uiCurrent = doc_get('analysis_ui_run');
            $uiState = strtolower((string) ($uiCurrent['data']['state'] ?? ''));
            if (in_array($uiState, ['starting', 'running'], true)) {
                $currentRunId = (string) ($uiCurrent['data']['run_id'] ?? '');
            }
        }
        if (analysis_run_doc_key($currentRunId)) {
            echo json_encode(['ok' => true, 'queued' => "$action $ticker",
                              'run_id' => $currentRunId, 'already_running' => true]);
            exit;
        }
        http_response_code(409);
        echo json_encode(['error' => 'analysis already running', 'already_running' => true]);
        exit;
    }
    foreach (commands_pending() as $pending) {
        if (($pending['action'] ?? '') !== 'RUN_ANALYSIS') { continue; }
        if (preg_match('/(?:^|;)run_id=([a-zA-Z0-9_-]{12,80})(?:;|$)/',
                       (string) ($pending['note'] ?? ''), $m)) {
            echo json_encode(['ok' => true, 'queued' => "$action $ticker",
                              'run_id' => $m[1], 'already_queued' => true]);
            exit;
        }
    }
    try { $entropy = bin2hex(random_bytes(5)); }
    catch (Exception $e) { $entropy = substr(hash('sha256', uniqid('', true)), 0, 10); }
    $runId = 'analysis-' . gmdate('Ymd-His') . '-' . $entropy;
    $statusBefore = doc_get('analysis_status');
    $pickBefore = doc_get('daily_pick');
    $errorBefore = doc_get('analysis_error');
    $note = 'run_id=' . $runId . ';dashboard by ' . $_SESSION['email'];
    // Establish the run and its baselines before making it visible to the engine.
    $runRecord = [
        'run_id' => $runId,
        'state' => 'queued',
        'queued_at' => gmdate('c'),
        'status_epoch_before' => (int) ($statusBefore['updated_epoch'] ?? 0),
        'pick_epoch_before' => (int) ($pickBefore['updated_epoch'] ?? 0),
        'error_epoch_before' => (int) ($errorBefore['updated_epoch'] ?? 0),
    ];
    doc_set('analysis_ui_run', $runRecord);
    doc_set(analysis_run_doc_key($runId), $runRecord);
    prune_analysis_run_docs(60);
}
$created = command_create($action, $ticker, $note);
if ($runId) {
    $uiRun = doc_get('analysis_ui_run');
    $runData = $uiRun['data'] ?? [];
    $runData['command_id'] = (int) $created['id'];
    $runData['command_created'] = (bool) $created['created'];
    doc_set('analysis_ui_run', $runData);
    doc_set(analysis_run_doc_key($runId), $runData);
}
echo json_encode(['ok' => true, 'queued' => "$action $ticker",
                  'command_id' => (int) $created['id'], 'run_id' => $runId]);
