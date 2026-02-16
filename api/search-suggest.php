<?php
include '../config/db.php';

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$qEsc = mysqli_real_escape_string($conn, $q);

$res = mysqli_query($conn,"
    SELECT title, slug, price_inr, images
    FROM products
    WHERE title LIKE '%$qEsc%'
    ORDER BY id DESC
    LIMIT 3
");

$out = [];
while ($p = mysqli_fetch_assoc($res)) {
    $imgs = explode(',', $p['images']);
    $out[] = [
        'title' => $p['title'],
        'slug'  => $p['slug'],
        'price' => $p['price_inr'],
        'img'   => $imgs[0] ?? ''
    ];
}

echo json_encode($out);
