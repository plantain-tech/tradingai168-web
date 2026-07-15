<?php
// Freshness poll for the Analyze overlay: GET ?k=daily_pick -> {updated_at}.
define('APP', 1);
require __DIR__ . '/../inc/engine.php';
header('Content-Type: application/json');

boot_session();
if (empty($_SESSION['uid'])) { http_response_code(401); echo '{"error":"login"}'; exit; }

$k = $_GET['k'] ?? '';
if (!in_array($k, ['daily_pick', 'market_table', 'candidates', 'analysis_error', 'analysis_status'], true)) {
    http_response_code(400); echo '{"error":"bad key"}'; exit;
}
$doc = doc_get($k);
$out = ['k' => $k, 'updated_at' => $doc['updated_at'] ?? null];
if (in_array($k, ['analysis_error', 'analysis_status'], true) && $doc) { $out['data'] = $doc['data']; }
echo json_encode($out);
