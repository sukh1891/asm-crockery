<?php
include '../config/db.php';
include 'auth-check.php';

$id = intval($_GET['id'] ?? 0);
if ($id) {

    mysqli_query($conn,
        "DELETE FROM product_variations WHERE product_id='$id'"
    );

    mysqli_query($conn,
        "DELETE FROM products WHERE id='$id'"
    );
}

header("Location: products.php");
exit;
