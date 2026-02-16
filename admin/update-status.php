<?php
include '../config/db.php';
include 'auth-check.php';

$id = intval($_POST['id']);
$status = mysqli_real_escape_string($conn, $_POST['status']);

mysqli_query($conn,
    "UPDATE orders SET status='$status' WHERE id='$id'"
);

header("Location: order-view.php?id=".$id);
exit;
