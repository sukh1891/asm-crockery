<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

if ((!isset($_SESSION['user_id']) || !intval($_SESSION['user_id'])) && function_exists('tryRememberUserLogin')) {
    tryRememberUserLogin();
}

if (!isset($_SESSION['user_id']) || !intval($_SESSION['user_id'])) {
    // Not logged in — redirect to login (you have OTP login page)
    header("Location: /login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}
