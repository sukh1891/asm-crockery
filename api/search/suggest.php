<?php
// /api/search/suggest.php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

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
    "SELECT id, title, images, price_inr, product_type
     FROM products
     WHERE title LIKE '". $q_safe ."%' 
     LIMIT $max"
);
while ($r = mysqli_fetch_assoc($prod_q)) {
    $img = explode(',', $r['images'])[0] ?? '';
    $items[] = [
        'type' => 'product',
        'id' => intval($r['id']),
        'label' => $r['title'],
        'image' => $img,
        'price_inr' => floatval($r['price_inr']),
        'product_type' => $r['product_type']
    ];
}

// 2) Product title / description partial matches (if still space)
if (count($items) < $max) {
    $remaining = $max - count($items);
    $prod_q2 = mysqli_query($conn,
        "SELECT id, title, images, price_inr, product_type
         FROM products
         WHERE (title LIKE '%$q_safe%' OR description LIKE '%$q_safe%')
         AND title NOT LIKE '". $q_safe ."%' 
         LIMIT $remaining"
    );
    while ($r = mysqli_fetch_assoc($prod_q2)) {
        $img = explode(',', $r['images'])[0] ?? '';
        $items[] = [
            'type' => 'product',
            'id' => intval($r['id']),
            'label' => $r['title'],
            'image' => $img,
            'price_inr' => floatval($r['price_inr']),
            'product_type' => $r['product_type']
        ];
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
