<?php
// /api/user/reorder.php
if (session_status() === PHP_SESSION_NONE) session_start();
include '../../config/db.php';
include '../../includes/functions.php';
header('Content-Type: application/json');

$user_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : null;
if (!$user_id) {
    echo json_encode(['success'=>false,'msg'=>'Not logged in']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$order_id = intval($input['order_id'] ?? 0);
if (!$order_id) {
    echo json_encode(['success'=>false,'msg'=>'Invalid order id']);
    exit;
}

// verify order belongs to user
$q = mysqli_query($conn, "SELECT * FROM orders WHERE id='$order_id' AND user_id='$user_id' LIMIT 1");
if (mysqli_num_rows($q) == 0) {
    echo json_encode(['success'=>false,'msg'=>'Order not found']);
    exit;
}

// fetch order items
$items_q = mysqli_query($conn, "SELECT * FROM order_items WHERE order_id='$order_id'");
$session_id = getSessionId();

// iterate and add to cart (respecting stock)
while ($it = mysqli_fetch_assoc($items_q)) {
    $product_id = intval($it['product_id']);
    $variation_id = $it['variation_id'] ? intval($it['variation_id']) : null;
    $qty = intval($it['qty']);
    $price_snapshot = floatval($it['price']);

    // check stock
    if ($variation_id) {
        $vq = mysqli_query($conn, "SELECT stock, price_inr FROM product_variations WHERE id='$variation_id' LIMIT 1");
        if (mysqli_num_rows($vq)) {
            $vr = mysqli_fetch_assoc($vq);
            $stock = intval($vr['stock']);
            $price = floatval($vr['price_inr']);
        } else { $stock = 0; }
    } else {
        $pq = mysqli_query($conn, "SELECT stock, price_inr FROM products WHERE id='$product_id' LIMIT 1");
        if (mysqli_num_rows($pq)) {
            $pr = mysqli_fetch_assoc($pq);
            $stock = intval($pr['stock']);
            $price = floatval($pr['price_inr']);
        } else { $stock = 0; }
    }

    if ($stock <= 0) continue;

    if ($qty > $stock) $qty = $stock;

    // insert or update cart row for current session/user
    $owner_clause = $user_id ? "user_id='".intval($user_id)."'" : "session_id='".mysqli_real_escape_string($conn,$session_id)."'";
    $where = ($user_id ? "user_id='".intval($user_id)."'" : "session_id='".mysqli_real_escape_string($conn,$session_id)."'") . " AND product_id='$product_id' AND ";
    $where .= $variation_id ? "variation_id='$variation_id'" : "variation_id IS NULL";

    $ex = mysqli_query($conn, "SELECT * FROM cart WHERE $where LIMIT 1");
    if (mysqli_num_rows($ex)) {
        $row = mysqli_fetch_assoc($ex);
        $newQty = min($row['qty'] + $qty, $stock);
        mysqli_query($conn, "UPDATE cart SET qty='$newQty', price_inr='".floatval($price)."' WHERE id='".intval($row['id'])."'");
    } else {
        $sess_val = $user_id ? "NULL" : "'" . mysqli_real_escape_string($conn,$session_id) . "'";
        $user_val = $user_id ? intval($user_id) : "NULL";
        mysqli_query($conn, "INSERT INTO cart (session_id, user_id, product_id, variation_id, qty, price_inr) VALUES ($sess_val, $user_val, '$product_id', " . ($variation_id ? "'$variation_id'" : "NULL") . ", '$qty', '".floatval($price)."')");
    }
}

echo json_encode(['success'=>true]);
