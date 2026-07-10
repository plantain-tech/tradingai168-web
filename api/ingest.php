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
        doc_set($k, $v);
        $saved[] = $k;
    }
}
echo json_encode(['saved' => $saved, 'at' => date('c')]);
