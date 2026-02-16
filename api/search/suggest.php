<?php
// /api/search/suggest.php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

function getProductDisplayPrice($conn, $product) {
    $basePrice = ($product['sale_price'] !== null && floatval($product['sale_price']) > 0)
        ? floatval($product['sale_price'])
        : floatval($product['regular_price']);

    if ($product['product_type'] !== 'variable') {
        return $basePrice;
    }

    $productId = intval($product['id']);
    $vq = mysqli_query(
        $conn,
        "SELECT sale_price, regular_price
        FROM product_variations
        WHERE product_id='$productId'"
    );

    $variationPrice = null;
    if ($vq && ($vr = mysqli_fetch_assoc($vq))) {
        $variationPrice = isset($vr['sale_price']) ? floatval($vr['sale_price']) : floatval($vr['regular_price']);
    }

    if ($variationPrice !== null && $variationPrice > 0) {
        return $variationPrice;
    }

    return $basePrice;
}

function buildProductSuggestionItem($conn, $row) {
    $img = explode(',', $row['images'])[0] ?? '';
    $displayPrice = getProductDisplayPrice($conn, $row);

    return [
        'type' => 'product',
        'id' => intval($row['id']),
        'label' => $row['title'],
        'image' => $img,
        'sale_price' => $displayPrice,
        'regular_price' => $displayPrice,
        'product_type' => $row['product_type']
    ];
}

$q = trim($_GET['q'] ?? '');
$q_safe = mysqli_real_escape_string($conn, $q);

if (strlen($q) < 2) {
    echo json_encode(['success'=>true,'items'=>[]]);
    exit;
}

// Limit sizes
$max = 8;
$items = [];

// 1) Product title prefix (best suggestions)
$prod_q = mysqli_query($conn,
    "SELECT id, title, images, regular_price, sale_price, product_type
     FROM products
     WHERE title LIKE '". $q_safe ."%' 
     LIMIT $max"
);
while ($r = mysqli_fetch_assoc($prod_q)) {
    $items[] = buildProductSuggestionItem($conn, $r);
}

// 2) Product title / description partial matches (if still space)
if (count($items) < $max) {
    $remaining = $max - count($items);
    $prod_q2 = mysqli_query($conn,
        "SELECT id, title, images, regular_price, sale_price, product_type
         FROM products
         WHERE (title LIKE '%$q_safe%' OR description LIKE '%$q_safe%')
         AND title NOT LIKE '". $q_safe ."%' 
         LIMIT $remaining"
    );
    while ($r = mysqli_fetch_assoc($prod_q2)) {
        $items[] = buildProductSuggestionItem($conn, $r);
    }
}

// 3) Category suggestions
if (count($items) < $max) {
    $remaining = $max - count($items);
    $cat_q = mysqli_query($conn,
        "SELECT id, name FROM categories WHERE name LIKE '%$q_safe%' LIMIT $remaining"
    );
    while ($c = mysqli_fetch_assoc($cat_q)) {
        $items[] = [
            'type' => 'category',
            'id' => intval($c['id']),
            'label' => $c['name']
        ];
    }
}

// 4) Synonyms (token-aware, partial) — suggest synonyms matching the token
if (count($items) < $max) {
    $remaining = $max - count($items);
    // split q into tokens
    $tokens = preg_split('/\s+/', $q_safe, -1, PREG_SPLIT_NO_EMPTY);
    $synonyms = [];
    foreach ($tokens as $t) {
        $t = mysqli_real_escape_string($conn, $t);
        $sq = mysqli_query($conn,
            "SELECT DISTINCT synonym, word FROM synonyms
             WHERE word LIKE '%$t%' OR synonym LIKE '%$t%'
             LIMIT 5"
        );
        while ($s = mysqli_fetch_assoc($sq)) {
            $label = $s['synonym'];
            if (!in_array($label, $synonyms, true)) $synonyms[] = $label;
        }
    }
    foreach ($synonyms as $s) {
        if (count($items) >= $max) break;
        $items[] = ['type' => 'synonym', 'label' => $s];
    }
}

echo json_encode(['success'=>true,'items'=>$items]);
