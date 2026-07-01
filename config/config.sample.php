<?php
// Copy this to config.php and fill in your Hostinger MySQL details.
// config.php is gitignored and NEVER committed. On Hostinger, PHP + MySQL are on
// the same host, so db_host is usually 'localhost'.
if (!defined('APP')) { http_response_code(403); exit('Forbidden'); }

return [
    'db_host'    => 'localhost',
    'db_name'    => 'uXXXXXXXX_tradingai168',   // from hPanel -> MySQL Databases
    'db_user'    => 'uXXXXXXXX_app',
    'db_pass'    => 'CHANGE_ME',
    'db_charset' => 'utf8mb4',
    'app_name'   => 'TradingAI168',
];
