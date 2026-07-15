<?php
define('APP', 1);
require __DIR__ . '/inc/auth.php';
require_login();
$NAV_ACTIVE = 'auto-live';
require __DIR__ . '/inc/auto_trade_live_page.php';
