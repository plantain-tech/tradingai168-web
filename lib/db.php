<?php
// PDO connection helper. Guarded so it can't be hit directly over the web.
if (!defined('APP')) { http_response_code(403); exit('Forbidden'); }

function db_connect(array $cfg): ?PDO {
    try {
        $dsn = "mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset={$cfg['db_charset']}";
        return new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        return null;   // caller decides how to surface the failure
    }
}
