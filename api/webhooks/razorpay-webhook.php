<?php
require_once '../../config/db.php';
require_once '../../config/keys.php';

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

/* =====================
   READ RAW BODY
===================== */
$payload = file_get_contents('php://input');
$headers = getallheaders();
$signature = $headers['X-Razorpay-Signature'] ?? '';

if (!$payload || !$signature) {
    http_response_code(400);
    exit('Invalid webhook');
}

/* =====================
   VERIFY SIGNATURE
===================== */
try {
    $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);

    $api->utility->verifyWebhookSignature(
        $payload,
        $signature,
        RAZORPAY_WEBHOOK_SECRET
    );

} catch (SignatureVerificationError $e) {
    http_response_code(401);
    exit('Invalid signature');
}

/* =====================
   PARSE EVENT
===================== */
$data = json_decode($payload, true);
$event = $data['event'] ?? '';

/* =====================
   HANDLE ONLY RELEVANT EVENTS
===================== */
if (!in_array($event, ['payment.captured', 'payment.failed'])) {
    http_response_code(200);
    exit('Event ignored');
}

$payment = $data['payload']['payment']['entity'] ?? null;

if (!$payment) {
    http_response_code(400);
    exit('Invalid payload');
}

$paymentId = $payment['id'];
$orderId   = $payment['order_id'] ?? null;
$status    = $payment['status'];

/* =====================
   FIND ORDER
===================== */
$stmt = $conn->prepare("
    SELECT id, status, payment_id 
    FROM orders 
    WHERE gateway_order_id = ?
    LIMIT 1
");
$stmt->bind_param("s", $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    http_response_code(200);
    exit('Order not found');
}

/* =====================
   IDMPOTENCY CHECK
===================== */
if ($order['status'] === 'paid') {
    http_response_code(200);
    exit('Order already paid');
}

/* =====================
   UPDATE ORDER BASED ON EVENT
===================== */
if ($event === 'payment.captured' && $status === 'captured') {

    $stmt = $conn->prepare("
        UPDATE orders 
        SET status='paid', payment_id=?
        WHERE id=?
    ");
    $stmt->bind_param("si", $paymentId, $order['id']);
    $stmt->execute();

} elseif ($event === 'payment.failed') {

    $stmt = $conn->prepare("
        UPDATE orders 
        SET status='failed'
        WHERE id=?
    ");
    $stmt->bind_param("i", $order['id']);
    $stmt->execute();
}

/* =====================
   SUCCESS
===================== */
http_response_code(200);
echo 'OK';
