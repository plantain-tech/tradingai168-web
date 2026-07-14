<?php
// Read-only Moomoo account snapshots published by the PC engine.
define('APP', 1);
require __DIR__ . '/../inc/engine.php';
header('Content-Type: application/json');

boot_session();
if (empty($_SESSION['uid'])) { http_response_code(401); echo '{"error":"login"}'; exit; }

echo json_encode(['paper' => doc_get('account_paper'),
                  'live' => doc_get('account_live')]);
