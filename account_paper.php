<?php
define('APP', 1);
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/engine.php';
require_login();
$ACCOUNT_KIND = 'paper';
$NAV_ACTIVE = 'paper';
require __DIR__ . '/inc/account_page.php';
