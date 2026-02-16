<?php
if (session_status() === PHP_SESSION_NONE) session_start();

include '../../config/db.php';
include '../../includes/functions.php';

header('Content-Type: application/json');

$user_id = getLoggedInUserId();
if (!$user_id) {
    echo json_encode([
        'success' => false,
        'login_required' => true,
        'msg' => 'Please log in to use wishlist'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$product_id = intval($input['product_id'] ?? 0);

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'msg' => 'Invalid product']);
    exit;
}

$uid = intval($user_id);

// check if already in wishlist
$q = mysqli_query($conn,
    "SELECT id FROM wishlist 
     WHERE user_id='$uid' AND product_id='$product_id' LIMIT 1"
);

if (mysqli_num_rows($q) > 0) {
    // remove
    $row = mysqli_fetch_assoc($q);
    mysqli_query($conn, "DELETE FROM wishlist WHERE id='".intval($row['id'])."'");
    echo json_encode(['success' => true, 'action' => 'removed']);
} else {
    // add
    mysqli_query($conn,
        "INSERT IGNORE INTO wishlist (user_id, product_id)
         VALUES ('$uid', '$product_id')"
    );
    echo json_encode(['success' => true, 'action' => 'added']);
}

exit;
