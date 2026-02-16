<?php
include '../config/db.php';

function resolveSuggestionPrice($conn, $product) {
    $basePrice = ($product['sale_price'] !== null && floatval($product['sale_price']) > 0)
        ? floatval($product['sale_price'])
        : floatval($product['regular_price']);

    if (($product['product_type'] ?? '') !== 'variable') {
        return $basePrice;
    }

    $productId = intval($product['id']);
    $vq = mysqli_query(
        $conn,
        "SELECT MIN(v.effective_price) AS min_variation_price
         FROM (
            SELECT CASE
                WHEN sale_price IS NOT NULL AND sale_price > 0 THEN sale_price
                WHEN regular_price IS NOT NULL AND regular_price > 0 THEN regular_price
                ELSE NULL
            END AS effective_price
            FROM product_variations
            WHERE product_id='$productId'
         ) v
         WHERE v.effective_price IS NOT NULL"
    );

    if ($vq && ($vr = mysqli_fetch_assoc($vq))) {
        $variationPrice = floatval($vr['min_variation_price'] ?? 0);
        if ($variationPrice > 0) {
            return $variationPrice;
        }
    }

    return $basePrice;
}

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$qEsc = mysqli_real_escape_string($conn, $q);

$res = mysqli_query($conn,"
    SELECT id, title, slug, images, product_type, regular_price, sale_price
    FROM products
    WHERE title LIKE '%$qEsc%'
    ORDER BY id DESC
    LIMIT 3
");

$out = [];
while ($p = mysqli_fetch_assoc($res)) {
    $imgs = explode(',', $p['images']);
    $price = resolveSuggestionPrice($conn, $p);
    $out[] = [
        'title' => $p['title'],
        'slug'  => $p['slug'],
        'price' => number_format($price, 2, '.', ''),
        'img'   => $imgs[0] ?? ''
    ];
}

echo json_encode($out);
