<?php
define('APP', 1);
require __DIR__ . '/inc/auth.php';
boot_session();
session_destroy();
header('Location: login.php');
