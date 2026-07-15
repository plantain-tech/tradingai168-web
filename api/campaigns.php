<?php
// Live campaign feed for the Monitor page (session auth): all campaign docs.
define('APP', 1);
require __DIR__ . '/../inc/engine.php';
header('Content-Type: application/json');

boot_session();
if (empty($_SESSION['uid'])) { http_response_code(401); echo '{"error":"login"}'; exit; }

$out = [];
foreach (docs_all('campaign_') as $k => $c) {
    $d = $c['data'];
    $d['updated_at'] = $c['updated_at'];
    $out[] = $d;
}
$pend = [];
foreach (commands_pending() as $cmd) { $pend[] = ['t' => $cmd['ticker'], 'a' => $cmd['action']]; }
$marks = doc_get('broker_marks');
$health = doc_get('engine_health');
echo json_encode(['campaigns' => $out, 'pending' => $pend,
                  'broker_marks' => $marks ?: null,
                  'engine_health' => $health ?: null]);
