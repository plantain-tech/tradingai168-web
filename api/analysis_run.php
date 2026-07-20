<?php
// Run-aware Dashboard polling. A run ID is created before its command is
// queued, then attached to engine publications by api/ingest.php.
define('APP', 1);
require __DIR__ . '/../inc/engine.php';
header('Content-Type: application/json');
header('Cache-Control: no-store, max-age=0');

boot_session();
if (empty($_SESSION['uid'])) { http_response_code(401); echo '{"error":"login"}'; exit; }

$runId = (string) ($_GET['run_id'] ?? '');
if (!preg_match('/^analysis-[a-zA-Z0-9-]{12,80}$/', $runId)) {
    http_response_code(400); echo '{"error":"bad run id"}'; exit;
}
$runKey = analysis_run_doc_key($runId);
$runDoc = $runKey ? doc_get($runKey) : null;
$u = $runDoc['data'] ?? [];
if (($u['run_id'] ?? '') !== $runId) {
    http_response_code(404); echo '{"error":"run not found"}'; exit;
}
$error = is_array($u['error'] ?? null) ? $u['error'] : [];
$healthDoc = doc_get('engine_health');
$health = $healthDoc['data'] ?? [];
$command = !empty($u['command_id']) ? command_get((int) $u['command_id']) : null;

$lastSeen = strtotime((string) ($health['last_seen_at'] ?? '')) ?: 0;
$staleAfter = max(30, (int) ($health['stale_after_seconds'] ?? 95));
$healthAge = $lastSeen ? max(0, time() - $lastSeen) : null;
$engineOnline = (($health['status'] ?? '') === 'running'
                 && $healthAge !== null && $healthAge <= $staleAfter);

$state = 'queued';
$message = 'Command queued; waiting for the PC engine.';
$raw = strtolower((string) ($u['state'] ?? 'queued'));
if (in_array($raw, ['queued', 'starting', 'running', 'completed',
                    'completed_no_pick', 'failed'], true)) {
    if ($raw !== 'queued') {
        $state = $raw;
        $message = (string) ($u['message'] ?? $message);
    }
    if ($state === 'failed' && analysis_is_no_pick($error)) {
        $state = 'completed_no_pick';
        $message = 'Analysis completed: no stock passed every required strategy gate.';
    }
} elseif (($command['status'] ?? '') === 'done') {
    $state = 'starting';
    $message = 'The PC engine accepted the command; waiting for its worker status.';
}

$queuedAt = strtotime((string) ($u['queued_at'] ?? '')) ?: time();
$elapsed = max(0, time() - $queuedAt);
echo json_encode([
    'ok' => true,
    'run_id' => $runId,
    'state' => $state,
    'message' => $message,
    'stage' => $error['stage'] ?? null,
    'reason' => $error['reason'] ?? null,
    'hints' => array_slice(is_array($error['hints'] ?? null) ? $error['hints'] : [], 0, 8),
    'audit' => is_array($u['audit'] ?? null) ? $u['audit'] : null,
    'elapsed_seconds' => $elapsed,
    'duration_seconds' => $u['duration_seconds'] ?? null,
    'engine' => [
        'online' => $engineOnline,
        'status' => $health['status'] ?? 'unknown',
        'last_seen_at' => $health['last_seen_at'] ?? null,
        'age_seconds' => $healthAge,
        'stale_after_seconds' => $staleAfter,
    ],
]);
