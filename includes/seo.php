<?php

define('SITE_NAME', 'ASM Crockery');

/* ---------- HOME ---------- */
function seoHome() {
    return [
        'title' => 'Premium Crockery & Dinnerware Online | ' . SITE_NAME,
        'desc'  => 'Buy premium crockery, dinner sets, plates, bowls and serveware online at best prices. Trusted quality by ' . SITE_NAME . '.',
        'canonical' => '/asm-crockery/'
    ];
}

/* ---------- CATEGORY ---------- */
function seoCategory($category, $breadcrumbs) {

    $names = array_map(fn($c) => $c['name'], $breadcrumbs);
    $path  = implode(' › ', $names);

    $title = $path . ' | Buy Online | ' . SITE_NAME;

    $desc = 'Shop ' . strtolower($category['name']) .
            ' and related crockery online. Browse premium quality products with best prices at ' .
            SITE_NAME . '.';

    return [
        'title' => $title,
        'desc'  => substr($desc, 0, 160),
        'canonical' => '/asm-crockery/category/' . $category['slug']
    ];
}

/* ---------- PRODUCT ---------- */
function seoProduct($product, $breadcrumbs) {

    $catPath = implode(', ', array_map(fn($c) => $c['name'], $breadcrumbs));

    $title = $product['title'] . ' | ' . SITE_NAME;

    $price = number_format($product['price_inr'], 2);

    $desc = $product['title'] .
        ' available online at ₹' . $price .
        '. Buy now from ' . SITE_NAME .
        '. Suitable for ' . strtolower($catPath) . '.';

    return [
        'title' => substr($title, 0, 60),
        'desc'  => substr($desc, 0, 160),
        'canonical' => '/asm-crockery/product/' . $product['slug']
    ];
}
