<?php
// Engine push endpoint: POST {"docs": {"campaign_TGT": {...}, "daily_pick": {...}}}
// with Authorization: Bearer <api_token>.
define('APP', 1);
require __DIR__ . '/../inc/engine.php';
header('Content-Type: application/json');

if (!bearer_ok()) { http_response_code(401); echo '{"error":"unauthorized"}'; exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo '{"error":"POST only"}'; exit;
}
$body = json_decode(file_get_contents('php://input'), true);
$docs = $body['docs'] ?? null;
if (!is_array($docs)) { http_response_code(400); echo '{"error":"no docs"}'; exit; }

$saved = [];
foreach ($docs as $k => $v) {
    if (preg_match('/^[a-zA-Z0-9_\-]{1,64}$/', $k)) {
        // The Python engine remains schema-agnostic. Correlate its analysis
        // publications with the web-created run at the authenticated bridge.
        if (in_array($k, ['analysis_status', 'analysis_error', 'daily_pick'], true)
                && is_array($v) && empty($v['run_id'])) {
            $uiRun = doc_get('analysis_ui_run');
            $activeRunId = (string) ($uiRun['data']['run_id'] ?? '');
            if (preg_match('/^analysis-[a-zA-Z0-9-]{12,80}$/', $activeRunId)) {
                $v['run_id'] = $activeRunId;
            }
        }
        if (in_array($k, ['analysis_status', 'analysis_error', 'daily_pick'], true)
                && is_array($v) && !empty($v['run_id'])) {
            $runKey = analysis_run_doc_key((string) $v['run_id']);
            $runDoc = $runKey ? doc_get($runKey) : null;
            $run = $runDoc['data'] ?? ['run_id' => $v['run_id']];
            if ($k === 'analysis_error') {
                $run['error'] = [
                    'stage' => substr((string) ($v['stage'] ?? ''), 0, 120),
                    'reason' => substr((string) ($v['reason'] ?? ''), 0, 500),
                    'hints' => array_slice(array_map(
                        fn($hint) => substr((string) $hint, 0, 300),
                        is_array($v['hints'] ?? null) ? $v['hints'] : []), 0, 8),
                ];
            } elseif ($k === 'daily_pick') {
                $run['result_published'] = true;
                $run['chosen'] = substr((string) ($v['chosen'] ?? ''), 0, 16);
            } else {
                $incomingState = strtolower((string) ($v['state'] ?? 'running'));
                if ($incomingState === 'failed' && analysis_is_no_pick($run['error'] ?? null)) {
                    $incomingState = 'completed_no_pick';
                    $v['state'] = $incomingState;
                    $v['message'] = 'Analysis completed: no stock passed every required strategy gate.';
                }
                $run['state'] = $incomingState;
                $run['message'] = substr((string) ($v['message'] ?? ''), 0, 500);
                foreach (['started_at', 'at', 'duration_seconds', 'chosen',
                          'phase', 'progress_percent', 'progress_current',
                          'progress_total', 'ticker', 'provider_state'] as $field) {
                    if (array_key_exists($field, $v)) { $run[$field] = $v[$field]; }
                }
                if (is_array($v['audit'] ?? null)) {
                    $encodedAudit = json_encode($v['audit']);
                    $run['audit'] = (is_string($encodedAudit) && strlen($encodedAudit) <= 20000)
                        ? $v['audit']
                        : ['truncated' => true,
                           'reason' => 'The published audit exceeded the web retention limit.'];
                }
            }
            $run['last_event_at'] = gmdate('c');
            if ($runKey) { doc_set($runKey, $run); }
            $uiRun = doc_get('analysis_ui_run');
            if (($uiRun['data']['run_id'] ?? '') === ($v['run_id'] ?? '')) {
                doc_set('analysis_ui_run', $run);
            }
        }
        doc_set($k, $v);
        $saved[] = $k;
    }
}
echo json_encode(['saved' => $saved, 'at' => date('c')]);
