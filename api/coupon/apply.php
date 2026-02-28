<?php
if (session_status()==PHP_SESSION_NONE) session_start();
include '../../config/db.php';
include '../../includes/functions.php';
header("Content-Type: application/json");

$code = strtoupper(trim($_POST['code'] ?? ''));

if (!$code) {
    echo json_encode(['success'=>false,'msg'=>'Enter a coupon code']);
    exit;
}

$_SESSION['applied_coupon'] = $code;

// cart total
$userId = $_SESSION['user_id'] ?? null;
$session_id = session_id();
$where = $userId
    ? "user_id=".intval($userId)
    : "session_id='".mysqli_real_escape_string($conn, $session_id)."'";

$cart_total = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT SUM(price_inr * qty) AS t FROM cart WHERE $where"
))['t'] ?? 0;

$couponSummary = getAppliedCouponForSubtotal(floatval($cart_total), $userId);

if ($cart_total <= 0) {
    echo json_encode(['success'=>false,'msg'=>'Your cart is empty']);
    exit;
}

if ($cart_total < floatval($c['min_order'])) {
    echo json_encode(['success'=>false,'msg'=>'Minimum order ₹'.number_format($c['min_order'], 2).' required']);
    exit;
}

// user limit
if (empty($couponSummary['valid'])) {
    echo json_encode([
        'success'=>false,
        'msg'=>'Invalid or expired coupon'
    ]);
    exit;
}

echo json_encode([
    'success'=>true,
    'discount'=>floatval($couponSummary['discount']),
    'code'=>$couponSummary['code']
]);
