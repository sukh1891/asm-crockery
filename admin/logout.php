<?php
session_start();
include '../config/db.php';

if (!empty($_SESSION['admin_id'])) {
    $id = $_SESSION['admin_id'];

    mysqli_query($conn,
        "UPDATE admins SET remember_token=NULL WHERE id='$id'"
    );
}

// Destroy session
session_unset();
session_destroy();

// Remove cookie
setcookie('admin_remember', '', time() - 3600, '/');

header("Location: login.php");
exit;
