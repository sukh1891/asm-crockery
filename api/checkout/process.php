<?php
session_start();

require_once '../../config/db.php';
require_once '../../config/keys.php';
require_once '../../includes/functions.php';

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

header('Content-Type: application/json');

/* =====================
   BASIC VALIDATION
===================== */
$paymentId   = $_POST['razorpay_payment_id'] ?? '';
$orderId    = $_POST['razorpay_order_id'] ?? '';
$signature  = $_POST['razorpay_signature'] ?? '';

if (!$paymentId || !$orderId || !$signature) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid payment response'
    ]);
    exit;
}

/* =====================
   FETCH ORDER
===================== */
$stmt = $conn->prepare("
    SELECT * FROM orders
    WHERE gateway_order_id = ?
    LIMIT 1
");
$stmt->bind_param("s", $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => 'Order not found'
    ]);
    exit;
}

/* =====================
   DUPLICATE PAYMENT CHECK
===================== */
if ($order['status'] === 'paid') {
    echo json_encode([
        'success' => true,
        'message' => 'Order already processed'
    ]);
    exit;
}

/* =====================
   VERIFY SIGNATURE
===================== */
try {
    $api = new Api(RAZORPAY_KEY_ID, RAZORPAY_KEY_SECRET);

    $api->utility->verifyPaymentSignature([
        'razorpay_order_id'   => $orderId,
        'razorpay_payment_id' => $paymentId,
        'razorpay_signature'  => $signature
    ]);

} catch (SignatureVerificationError $e) {

    // Mark order failed
    $conn->query("
        UPDATE orders 
        SET status='failed' 
        WHERE id='".intval($order['id'])."'
    ");

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Payment verification failed'
    ]);
    exit;
}

/* =====================
   TRANSACTION START
===================== */
$conn->begin_transaction();

try {

    /* =====================
       UPDATE ORDER
    ===================== */
    $stmt = $conn->prepare("
        UPDATE orders
        SET status='paid', payment_id=?
        WHERE id=?
    ");
    $stmt->bind_param("si", $paymentId, $order['id']);
    $stmt->execute();

    /* =====================
       INSERT ORDER ITEMS
    ===================== */
    $cartItems = getCartItems();

    foreach ($cartItems as $item) {

        $productId   = intval($item['product_id']);
        $variationId = !empty($item['variation_id']) ? intval($item['variation_id']) : null;
        $qty         = max(1, intval($item['qty']));
        $price       = 0;

        // VARIABLE PRODUCT
        if ($item['product_type'] === 'variable' && $variationId) {

            $vq = mysqli_query(
                $conn,
                "SELECT regular_price, sale_price 
                 FROM product_variations 
                 WHERE id='$variationId' LIMIT 1"
            );
            $v = mysqli_fetch_assoc($vq);

            $price = ($v['sale_price'] !== null && $v['sale_price'] > 0)
                ? floatval($v['sale_price'])
                : floatval($v['regular_price']);

            // reduce stock (if applicable)
            mysqli_query(
                $conn,
                "UPDATE product_variations 
                 SET stock = GREATEST(stock - $qty, 0)
                 WHERE id='$variationId'"
            );

        } 
        // SIMPLE PRODUCT
        else {

            $pq = mysqli_query(
                $conn,
                "SELECT regular_price, sale_price 
                 FROM products 
                 WHERE id='$productId' LIMIT 1"
            );
            $p = mysqli_fetch_assoc($pq);

            $price = ($p['sale_price'] !== null && $p['sale_price'] > 0)
                ? floatval($p['sale_price'])
                : floatval($p['regular_price']);

            mysqli_query(
                $conn,
                "UPDATE products 
                 SET stock = GREATEST(stock - $qty, 0)
                 WHERE id='$productId'"
            );
        }

        // insert order_items
        $stmt = $conn->prepare("
            INSERT INTO order_items
            (order_id, product_id, variation_id, price, qty)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "iiidi",
            $order['id'],
            $productId,
            $variationId,
            $price,
            $qty
        );
        $stmt->execute();
    }

    /* =====================
       CLEAR CART
    ===================== */
    $userId = getLoggedInUserId();

    if ($userId) {
        $conn->query("DELETE FROM cart WHERE user_id='".intval($userId)."'");
    } else {
        $sess = getSessionId();
        $conn->query(
            "DELETE FROM cart WHERE session_id='".mysqli_real_escape_string($conn,$sess)."'"
        );
    }

    unset($_SESSION['pending_order_id']);

    $conn->commit();

} catch (Exception $e) {

    $conn->rollback();

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Order processing failed'
    ]);
    exit;
}

/* =====================
   SUCCESS RESPONSE
===================== */
echo json_encode([
    'success' => true,
    'order_id' => $order['id']
]);
