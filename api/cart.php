<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$action = $_POST['action'] ?? '';

/* ========== ADD TO CART ========== */
if ($action === 'add') {

    $product_id   = intval($_POST['product_id']);
    $variation_id = intval($_POST['variation_id'] ?? 0);
    $qty          = max(1, intval($_POST['qty'] ?? 1));

    /* Product */
    $p = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT title, price_inr, images FROM products WHERE id='$product_id'"
    ));
    if (!$p) exit;

    $price = $p['price_inr'];
    $variationName = '';

    /* Variation */
    if ($variation_id > 0) {
        $v = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT price_inr, attributes_json
             FROM product_variations
             WHERE id='$variation_id'"
        ));
        if ($v) {
            $price = $v['price_inr'];
            $variationName = $v['attributes_json'];
        }
    }

    $image = '';
    if (!empty($p['images'])) {
        $imgs = explode(',', $p['images']);
        $image = $imgs[0];
    }

    /* Merge item */
    foreach ($_SESSION['cart'] as &$item) {
        if ($item['product_id'] == $product_id &&
            $item['variation_id'] == $variation_id) {
            $item['qty'] += $qty;
            echo json_encode(['status'=>'ok']);
            exit;
        }
    }

    $_SESSION['cart'][] = [
        'product_id'   => $product_id,
        'variation_id' => $variation_id,
        'variation'    => $variationName,
        'title'        => $p['title'],
        'price'        => $price,
        'qty'          => $qty,
        'image'        => $image
    ];

    echo json_encode([
        'status' => 'ok',
        'count'  => array_sum(array_column($_SESSION['cart'], 'qty'))
    ]);
    exit;
}

/* ========== UPDATE QTY ========== */
if ($action === 'update') {
    $key = intval($_POST['key']);
    $qty = max(1, intval($_POST['qty']));
    if (isset($_SESSION['cart'][$key])) {
        $_SESSION['cart'][$key]['qty'] = $qty;
    }
    exit;
}

/* ========== REMOVE ITEM ========== */
if ($action === 'remove') {
    $key = intval($_POST['key']);
    unset($_SESSION['cart'][$key]);
    $_SESSION['cart'] = array_values($_SESSION['cart']);
    exit;
}
