<?php
if (session_status()==PHP_SESSION_NONE) session_start();
include '../../config/db.php';
header("Content-Type: application/json");

$code = strtoupper(trim($_POST['code'] ?? ''));

if (!$code) {
    echo json_encode(['success'=>false,'msg'=>'Enter a coupon code']);
    exit;
}

// check coupon
$today = date('Y-m-d');
$cq = mysqli_query(
    $conn,
    "SELECT * FROM coupons
     WHERE code='$code'
       AND status=1
       AND (start_date IS NULL OR start_date <= '$today')
       AND (end_date IS NULL OR end_date >= '$today')
     LIMIT 1"
);

if (!$cq || !mysqli_num_rows($cq)) {
    echo json_encode(['success'=>false,'msg'=>'Invalid or expired coupon']);
    exit;
}

$c = mysqli_fetch_assoc($cq);

// cart total
$session_id = session_id();
$user_id = $_SESSION['user_id'] ?? null;

$where = $user_id ? "user_id=".intval($user_id) : "session_id='".mysqli_real_escape_string($conn, $session_id)."'";

$cart_total = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT SUM(price_inr * qty) AS t FROM cart WHERE $where"
))['t'] ?? 0;

$cart_total = floatval($cart_total);

if ($cart_total <= 0) {
    echo json_encode(['success'=>false,'msg'=>'Your cart is empty']);
    exit;
}

if ($cart_total < floatval($c['min_order'])) {
    echo json_encode(['success'=>false,'msg'=>'Minimum order ₹'.number_format($c['min_order'], 2).' required']);
    exit;
}

// user limit
if ($user_id && !empty($c['user_limit'])) {
    $hasCouponColumn = mysqli_query($conn, "SHOW COLUMNS FROM orders LIKE 'coupon_code'");
    if ($hasCouponColumn && mysqli_num_rows($hasCouponColumn) > 0) {
        $used = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT COUNT(*) AS c FROM orders
             WHERE user_id='".intval($user_id)."' AND coupon_code='".mysqli_real_escape_string($conn, $code)."'"
        ))['c'];

        if (intval($used) >= intval($c['user_limit'])) {
            echo json_encode(['success'=>false,'msg'=>'Exceeded usage limit']);
            exit;
        }
    }
}

// global usage limit
if (!empty($c['usage_limit']) && intval($c['used_count']) >= intval($c['usage_limit'])) {
    echo json_encode(['success'=>false,'msg'=>'Coupon usage limit reached']);
    exit;
}

// calculate discount
$discount = $c['type']=='percent'
    ? ($cart_total * floatval($c['amount']) / 100)
    : floatval($c['amount']);
$discount = min($discount, $cart_total);

$_SESSION['applied_coupon'] = $code;

echo json_encode([
    'success'=>true,
    'discount'=>$discount,
    'code'=>$code
]);
