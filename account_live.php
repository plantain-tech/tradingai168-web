<?php
define('APP', 1);
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/engine.php';
require_login();
$ACCOUNT_KIND = 'live';
$NAV_ACTIVE = 'live';
require __DIR__ . '/inc/account_page.php';
