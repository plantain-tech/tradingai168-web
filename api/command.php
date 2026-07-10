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
if (!in_array($action, ['APPROVE_BUY', 'APPROVE_SELL_ALL'], true) || !$ticker) {
    http_response_code(400); echo '{"error":"bad command"}'; exit;
}
command_create($action, $ticker, 'one-click from dashboard by ' . $_SESSION['email']);
echo json_encode(['ok' => true, 'queued' => "$action $ticker"]);
