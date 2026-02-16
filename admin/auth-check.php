<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../config/db.php';

// Already logged in via session
if (!empty($_SESSION['admin_id'])) {
    return;
}

// Try cookie login
if (!empty($_COOKIE['admin_remember'])) {

    [$id, $token] = explode(':', $_COOKIE['admin_remember'], 2);

    $id = intval($id);
    $token_hash = hash('sha256', $token);

    $q = mysqli_query($conn,
        "SELECT * FROM admins
         WHERE id='$id' AND remember_token='$token_hash'
         LIMIT 1"
    );

    if (mysqli_num_rows($q) === 1) {

        $admin = mysqli_fetch_assoc($q);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_email'] = $admin['email'];
        return;
    }
}

// Not authenticated
header("Location: login.php");
exit;
