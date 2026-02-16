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
$today = date("Y-m-d");
$cq = mysqli_query($conn, 
"SELECT * FROM coupons 
 WHERE code='$code'
 AND status=1
 AND (start_date IS NULL OR start_date <= '$today')
 AND (end_date IS NULL OR end_date >= '$today')
 LIMIT 1");

if (!mysqli_num_rows($cq)) {
    echo json_encode(['success'=>false,'msg'=>'Invalid or expired coupon']);
    exit;
}

$c = mysqli_fetch_assoc($cq);

// cart total
$session_id = session_id();
$user_id = $_SESSION['user_id'] ?? null;

$where = $user_id ? "user_id=$user_id" : "session_id='$session_id'";

$cart_total = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT SUM(price_inr * qty) AS t FROM cart WHERE $where"
))['t'] ?? 0;

if ($cart_total < $c['min_order']) {
    echo json_encode(['success'=>false,'msg'=>"Minimum order ₹{$c['min_order']} required"]);
    exit;
}

// user limit
if ($user_id && $c['user_limit']) {
    $used = mysqli_fetch_assoc(mysqli_query($conn, 
        "SELECT COUNT(*) AS c FROM orders 
         WHERE user_id='$user_id' AND coupon_code='$code'"
    ))['c'];
    if ($used >= $c['user_limit']) {
        echo json_encode(['success'=>false,'msg'=>"Exceeded usage limit"]);
        exit;
    }
}

// global usage limit
if ($c['usage_limit'] && $c['used_count'] >= $c['usage_limit']) {
    echo json_encode(['success'=>false,'msg'=>"Coupon usage limit reached"]);
    exit;
}

// calculate discount
$discount = $c['type']=='percent'
    ? ($cart_total * $c['amount']/100)
    : $c['amount'];

echo json_encode([
    'success'=>true,
    'discount'=>$discount,
    'code'=>$code
]);
