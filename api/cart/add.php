<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

$productId   = intval($_POST['product_id'] ?? 0);
$variationId = isset($_POST['variation_id']) && $_POST['variation_id'] !== ''
    ? intval($_POST['variation_id'])
    : null;
$qty = max(1, intval($_POST['qty'] ?? 1));

if (!$productId) {
    echo json_encode(['success' => false, 'message' => 'Invalid product']);
    exit;
}

$userId    = getLoggedInUserId();
$sessionId = getSessionId();

/* =====================
   DETERMINE PRICE
===================== */
$price = 0;

if ($variationId) {
    $q = mysqli_query(
        $conn,
        "SELECT regular_price, sale_price
         FROM product_variations
         WHERE id='$variationId' LIMIT 1"
    );
    if ($v = mysqli_fetch_assoc($q)) {
        $price = ($v['sale_price'] > 0) ? $v['sale_price'] : $v['regular_price'];
    }
} else {
    $q = mysqli_query(
        $conn,
        "SELECT regular_price, sale_price
         FROM products
         WHERE id='$productId' LIMIT 1"
    );
    if ($p = mysqli_fetch_assoc($q)) {
        $price = ($p['sale_price'] > 0) ? $p['sale_price'] : $p['regular_price'];
    }
}

if ($price <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid price']);
    exit;
}

/* =====================
   CHECK EXISTING CART ROW
===================== */
if ($userId) {
    $where = "user_id='".intval($userId)."'";
} else {
    $sid = mysqli_real_escape_string($conn, $sessionId);
    $where = "session_id='$sid'";
}

$checkSql = "
    SELECT id, qty FROM cart
    WHERE $where
      AND product_id='$productId'
      AND ".($variationId ? "variation_id='$variationId'" : "variation_id IS NULL")."
    LIMIT 1
";

$check = mysqli_query($conn, $checkSql);

if ($row = mysqli_fetch_assoc($check)) {

    $newQty = $row['qty'] + $qty;
    mysqli_query($conn,"
        UPDATE cart
        SET qty='$newQty'
        WHERE id='".intval($row['id'])."'
    ");

} else {

    mysqli_query($conn,"
        INSERT INTO cart
        (user_id, session_id, product_id, variation_id, qty, price_inr)
        VALUES (
            ".($userId ? intval($userId) : "NULL").",
            ".($userId ? "NULL" : "'$sessionId'").",
            '$productId',
            ".($variationId ? "'$variationId'" : "NULL").",
            '$qty',
            '$price'
        )
    ");
}

echo json_encode(['success' => true]);
