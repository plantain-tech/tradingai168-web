<?php
// Engine settings feed: GET with "Authorization: Bearer <api_token>" -> JSON.
define('APP', 1);
require __DIR__ . '/../inc/auth.php';

header('Content-Type: application/json');
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$given = preg_match('/Bearer\s+(\S+)/', $auth, $m) ? $m[1] : '';
$s = get_settings();
if (empty($s['api_token']) || !hash_equals($s['api_token'], $given)) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}
unset($s['api_token']);                       // never echo the token back
echo json_encode(['settings' => $s, 'served_at' => date('c')]);
