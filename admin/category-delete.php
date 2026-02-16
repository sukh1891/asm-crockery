<?php
include '../config/db.php';
include 'auth-check.php';

$id = intval($_GET['id']);

// check children
$child = mysqli_fetch_assoc(
    mysqli_query($conn,"SELECT COUNT(*) c FROM categories WHERE parent='$id'")
)['c'];

// check products
$prod = mysqli_fetch_assoc(
    mysqli_query($conn,"SELECT COUNT(*) c FROM products WHERE category_id='$id'")
)['c'];

if ($child==0 && $prod==0) {
    mysqli_query($conn,"DELETE FROM categories WHERE id='$id'");
}

header("Location: categories.php");
exit;
