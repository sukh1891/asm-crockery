<?php
session_start();

require_once '../../config/db.php';
require_once '../../config/keys.php';
require_once '../../includes/functions.php';
require_once '../../vendor/autoload.php';

use Razorpay\Api\Api;

header('Content-Type: application/json');

$userId = getLoggedInUserId();
if (!$userId) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Please login to continue checkout'
    ]);
    exit;
}

$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$address = trim($_POST['address'] ?? '');
$country = trim($_POST['country'] ?? 'India');

if ($name === '' || $phone === '' || $address === '') {
    http_response_code(422);
    echo json_encode([
        'success' => false,
        'message' => 'Please fill all required checkout details'
    ]);
    exit;
}

$cartItems = getCartItems();
if (!$cartItems) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Your cart is empty'
    ]);
    exit;
}

$subtotal = 0;
$totalWeight = 0;

foreach ($cartItems as $item) {
    $qty = max(1, intval($item['qty']));
    $price = floatval($item['price_inr'] ?? 0);
    $subtotal += ($price * $qty);

    $weight = isset($item['variation_weight']) && $item['variation_weight'] !== null
        ? floatval($item['variation_weight'])
        : floatval($item['product_weight'] ?? 0);
    $totalWeight += ($weight * $qty);
}

$shipping = calculateShippingCharge($country, $totalWeight);
$amount = round($subtotal + $shipping, 2);

if ($amount <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid order amount'
    ]);
    exit;
}

try {
    $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);

    $razorpayOrder = $api->order->create([
        'receipt' => 'asm_' . time() . '_' . $userId,
        'amount' => intval(round($amount * 100)),
        'currency' => 'INR',
        'payment_capture' => 1
    ]);

    $gatewayOrderId = $razorpayOrder['id'];

    $stmt = $conn->prepare(
        "INSERT INTO orders
        (user_id, name, phone, email, address, country, total_amount, shipping_amount, amount, currency, status, gateway_order_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'INR', 'pending', ?)"
    );

    $stmt->bind_param(
        'isssssddds',
        $userId,
        $name,
        $phone,
        $email,
        $address,
        $country,
        $subtotal,
        $shipping,
        $amount,
        $gatewayOrderId
    );
    $stmt->execute();

    $orderId = $conn->insert_id;
    $orderNumber = 'ASM-' . str_pad($orderId, 6, '0', STR_PAD_LEFT);

    $stmt = $conn->prepare('UPDATE orders SET order_number=? WHERE id=?');
    $stmt->bind_param('si', $orderNumber, $orderId);
    $stmt->execute();

    $_SESSION['pending_order_id'] = $orderId;

    echo json_encode([
        'success' => true,
        'order_id' => $gatewayOrderId,
        'amount' => intval(round($amount * 100)),
        'currency' => 'INR',
        'local_order_id' => $orderId,
        'order_number' => $orderNumber
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to initiate payment'
    ]);
}
