<?php
session_start();
include '../config/db.php';
if (
    !isset($_SESSION['user_id']) &&
    !isset($_SESSION['otp_verified']) &&
    !isset($_SESSION['otp_skipped'])
) {
    header("Location: /asm-crockery/checkout.php");
    exit;
}
if (isset($_POST['skip'])) {
    $_SESSION['otp_skipped'] = true;
}

$cart = $_SESSION['cart'] ?? [];
if (!$cart) {
    header("Location: /asm-crockery/cart.php");
    exit;
}

/* ===== Billing Info ===== */
$data = $_SESSION['checkout_data'] ?? [];

$name    = mysqli_real_escape_string($conn, $data['name'] ?? '');
$phone   = mysqli_real_escape_string($conn, $data['phone'] ?? '');
$email   = mysqli_real_escape_string($conn, $data['email'] ?? '');
$address = mysqli_real_escape_string($conn, $data['address'] ?? '');
$country = mysqli_real_escape_string($conn, $data['country'] ?? 'India');

/* ===== Calculate Product Total ===== */
$total = 0;
foreach ($cart as $item) {
    $total += $item['price'] * $item['qty'];
}

/* ===== Calculate Total Weight ===== */
$totalWeight = 0;
foreach ($cart as $item) {
    $pid = intval($item['product_id']);
    $q = mysqli_query($conn, "SELECT weight FROM products WHERE id='$pid'");
    $p = mysqli_fetch_assoc($q);
    $totalWeight += ($p['weight'] ?? 0) * $item['qty'];
}

/* ===== Shipping Calculation ===== */
$shipping = 0;
if ($country !== 'India') {
    $shipping = ceil($totalWeight) * 1000;
}

$finalAmount = $total + $shipping;

/* ===== Create Order (WITHOUT order_number first) ===== */
mysqli_query($conn,"
    INSERT INTO orders
    (name, phone, email, address, country,
     total_amount, shipping_amount, amount, currency, status)
    VALUES
    ('$name','$phone','$email','$address','$country',
     '$total','$shipping','$finalAmount','INR','pending')
");

$order_id = mysqli_insert_id($conn);

/* ===== Generate SERIAL Order Number ===== */
$orderNumber = 'ASM-' . str_pad($order_id, 6, '0', STR_PAD_LEFT);

/* ===== Update Order Number ===== */
mysqli_query($conn,"
    UPDATE orders
    SET order_number = '$orderNumber'
    WHERE id = '$order_id'
");

/* ===== Insert Order Items ===== */
foreach ($cart as $item) {
    $pid = intval($item['product_id']);
    $vid = $item['variation_id'] ? intval($item['variation_id']) : NULL;
    $qty = intval($item['qty']);
    $price = floatval($item['price']);

    mysqli_query($conn,"
        INSERT INTO order_items
        (order_id, product_id, variation_id, qty, price)
        VALUES
        ('$order_id','$pid',
         ".($vid ? "'$vid'" : "NULL").",
         '$qty','$price')
    ");
}

/* ===== Clear Cart ===== */
unset($_SESSION['cart']);
unset($_SESSION['checkout_data']);
unset($_SESSION['otp_code'], $_SESSION['otp_verified'], $_SESSION['otp_skipped']);
/* ===== Redirect ===== */
header("Location: /asm-crockery/order-success.php?order=$orderNumber");
exit;
