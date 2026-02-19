<?php
/**
 * WooCommerce -> ASM Crockery product importer
 *
 * Usage:
 * php scripts/import_woocommerce_products.php \
 *   --store-url="https://asmcrockery.com" \
 *   --consumer-key="ck_xxx" \
 *   --consumer-secret="cs_xxx" \
 *   [--per-page=50] \
 *   [--max-products=10] \
 *   [--delete-existing=0]
 */

if (php_sapi_name() !== 'cli') {
    exit("This script can only be run from CLI.\n");
}

require_once __DIR__ . '/../config/db.php';

function parseCliOptions(array $argv): array
{
    $opts = [];
    $count = count($argv);

    for ($i = 1; $i < $count; $i++) {
        $arg = trim((string)$argv[$i]);

        if ($arg === '' || $arg === '\\') {
            continue;
        }

        if (strpos($arg, '--') !== 0) {
            continue;
        }

        $eqPos = strpos($arg, '=');
        if ($eqPos !== false) {
            $key = substr($arg, 2, $eqPos - 2);
            $value = substr($arg, $eqPos + 1);
            $opts[$key] = $value;
            continue;
        }

        $key = substr($arg, 2);
        $next = $argv[$i + 1] ?? null;

        if ($next !== null && strpos(trim((string)$next), '--') !== 0 && trim((string)$next) !== '\\') {
            $opts[$key] = trim((string)$next);
            $i++;
        } else {
            $opts[$key] = '1';
        }
    }

    return $opts;
}

$options = parseCliOptions($argv);

$storeUrl = rtrim((string)($options['store-url'] ?? ''), '/');
$consumerKey = (string)($options['consumer-key'] ?? '');
$consumerSecret = (string)($options['consumer-secret'] ?? '');
$perPage = max(1, min(100, intval($options['per-page'] ?? 50)));
$maxProducts = isset($options['max-products']) ? max(1, intval($options['max-products'])) : 0;
$deleteExisting = isset($options['delete-existing']) ? (bool)intval($options['delete-existing']) : false;

if (!$storeUrl || !$consumerKey || !$consumerSecret) {
    $example = "php scripts/import_woocommerce_products.php --store-url=\"https://asmcrockery.com\" --consumer-key=\"ck_xxx\" --consumer-secret=\"cs_xxx\" --max-products=10\n";
    exit("Missing required options. Required: --store-url, --consumer-key, --consumer-secret\nExample: {$example}");
}

$uploadDir = realpath(__DIR__ . '/../assets') . '/uploads';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true)) {
    exit("Could not create upload directory: {$uploadDir}\n");
}


function isWooItemInStock(array $item): int
{
    $stockStatus = strtolower(trim((string)($item['stock_status'] ?? '')));
    if ($stockStatus !== '') {
        return in_array($stockStatus, ['instock', 'onbackorder'], true) ? 1 : 0;
    }

    if (array_key_exists('in_stock', $item)) {
        return filter_var($item['in_stock'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    }

    if (isset($item['stock_quantity']) && is_numeric($item['stock_quantity'])) {
        return intval($item['stock_quantity']) > 0 ? 1 : 0;
    }

    return 0;
}

function apiGet(string $url, string $consumerKey, string $consumerSecret): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_USERPWD => $consumerKey . ':' . $consumerSecret,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        throw new RuntimeException("cURL error for {$url}: {$error}");
    }

    if ($status < 200 || $status >= 300) {
        throw new RuntimeException("API request failed ({$status}) for {$url}: {$response}");
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException("Invalid JSON for {$url}");
    }

    return $decoded;
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text !== '' ? $text : 'product';
}

function uniqueSlug(mysqli $conn, string $base): string
{
    $slug = $base;
    $i = 1;

    while (true) {
        $safe = mysqli_real_escape_string($conn, $slug);
        $exists = mysqli_query($conn, "SELECT id FROM products WHERE slug='{$safe}' LIMIT 1");
        if ($exists && mysqli_num_rows($exists) === 0) {
            return $slug;
        }
        $slug = $base . '-' . $i;
        $i++;
    }
}

function downloadImage(string $imageUrl, string $uploadDir): ?string
{
    $parsed = parse_url($imageUrl);
    $path = $parsed['path'] ?? '';
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($ext, $allowed, true)) {
        $ext = 'jpg';
    }

    $filename = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $filePath = $uploadDir . '/' . $filename;

    $ch = curl_init($imageUrl);
    $fp = fopen($filePath, 'w');
    if (!$fp) {
        return null;
    }

    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_FAILONERROR => true,
    ]);

    $ok = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if (!$ok || $status < 200 || $status >= 300) {
        @unlink($filePath);
        return null;
    }

    return $filename;
}

function upsertCategory(mysqli $conn, array $wooCategory, array &$catMap): int
{
    $wooId = intval($wooCategory['id']);
    if (isset($catMap[$wooId])) {
        return $catMap[$wooId];
    }

    $name = trim((string)($wooCategory['name'] ?? 'Uncategorized'));
    $slug = trim((string)($wooCategory['slug'] ?? slugify($name)));

    $safeSlug = mysqli_real_escape_string($conn, $slug);
    $existing = mysqli_query($conn, "SELECT id FROM categories WHERE slug='{$safeSlug}' LIMIT 1");
    if ($existing && ($row = mysqli_fetch_assoc($existing))) {
        $catMap[$wooId] = intval($row['id']);
        return $catMap[$wooId];
    }

    $parentId = 0;
    if (!empty($wooCategory['parent']) && intval($wooCategory['parent']) > 0) {
        $parentWooId = intval($wooCategory['parent']);
        if (isset($catMap[$parentWooId])) {
            $parentId = $catMap[$parentWooId];
        }
    }

    $safeName = mysqli_real_escape_string($conn, $name);
    mysqli_query($conn, "INSERT INTO categories (name, slug, parent, show_in_menu) VALUES ('{$safeName}', '{$safeSlug}', {$parentId}, 1)");
    $newId = intval(mysqli_insert_id($conn));
    $catMap[$wooId] = $newId;

    return $newId;
}

function importVariations(mysqli $conn, string $storeUrl, string $consumerKey, string $consumerSecret, int $localProductId, int $wooProductId): array
{
    $endpoint = $storeUrl . '/wp-json/wc/v3/products/' . $wooProductId . '/variations?per_page=100';
    $vars = apiGet($endpoint, $consumerKey, $consumerSecret);

    $prices = [];

    foreach ($vars as $var) {
        $regular = floatval($var['regular_price'] ?? 0);
        $sale = ($var['sale_price'] ?? '') !== '' ? floatval($var['sale_price']) : null;
        $price = $sale !== null ? $sale : $regular;
        $prices[] = $price;

        $attrs = [];
        if (!empty($var['attributes']) && is_array($var['attributes'])) {
            foreach ($var['attributes'] as $a) {
                $n = trim((string)($a['name'] ?? ''));
                $o = trim((string)($a['option'] ?? ''));
                $attrs[] = $n !== '' ? ($n . ': ' . $o) : $o;
            }
        }

        $attrLabel = mysqli_real_escape_string($conn, implode(', ', array_filter($attrs)));
        $sku = mysqli_real_escape_string($conn, trim((string)($var['sku'] ?? '')));
        $stock = isWooItemInStock($var);
        $weight = ($var['weight'] ?? '') !== '' ? floatval($var['weight']) : null;

        $saleSql = $sale !== null ? "'{$sale}'" : 'NULL';
        $weightSql = $weight !== null ? "'{$weight}'" : 'NULL';

        mysqli_query($conn, "
            INSERT INTO product_variations
                (product_id, variation_sku, regular_price, sale_price, price_inr, stock, weight, attributes_json)
            VALUES
                ({$localProductId}, " . ($sku !== '' ? "'{$sku}'" : 'NULL') . ", '{$regular}', {$saleSql}, '{$price}', '{$stock}', {$weightSql}, '{$attrLabel}')
        ");
    }

    return $prices;
}

if ($deleteExisting) {
    mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=0');
    mysqli_query($conn, 'TRUNCATE TABLE product_variations');
    mysqli_query($conn, 'TRUNCATE TABLE products');
    mysqli_query($conn, 'TRUNCATE TABLE categories');
    mysqli_query($conn, 'SET FOREIGN_KEY_CHECKS=1');
    echo "Deleted existing categories/products/variations.\n";
}

$catMap = [];
$imported = 0;
$page = 1;

while (true) {
    $endpoint = $storeUrl . '/wp-json/wc/v3/products?per_page=' . $perPage . '&page=' . $page . '&status=publish';
    $products = apiGet($endpoint, $consumerKey, $consumerSecret);

    if (count($products) === 0) {
        break;
    }

    foreach ($products as $p) {
        $title = trim((string)($p['name'] ?? 'Untitled'));
        $shortDesc = (string)($p['short_description'] ?? '');
        $desc = (string)($p['description'] ?? '');
        $sku = trim((string)($p['sku'] ?? ''));
        $type = (($p['type'] ?? 'simple') === 'variable') ? 'variable' : 'simple';
        $stock = isWooItemInStock($p);
        $weight = ($p['weight'] ?? '') !== '' ? floatval($p['weight']) : null;

        $regular = ($p['regular_price'] ?? '') !== '' ? floatval($p['regular_price']) : 0.0;
        $sale = ($p['sale_price'] ?? '') !== '' ? floatval($p['sale_price']) : null;
        $priceInr = $sale !== null ? $sale : $regular;

        $slugBase = slugify((string)($p['slug'] ?? $title));
        $slug = uniqueSlug($conn, $slugBase);

        $categoryId = null;
        if (!empty($p['categories']) && is_array($p['categories'])) {
            $first = $p['categories'][0];
            $categoryId = upsertCategory($conn, $first, $catMap);
        }

        $filenames = [];
        if (!empty($p['images']) && is_array($p['images'])) {
            foreach ($p['images'] as $img) {
                $src = $img['src'] ?? '';
                if ($src === '') {
                    continue;
                }
                $saved = downloadImage($src, $uploadDir);
                if ($saved) {
                    $filenames[] = $saved;
                }
            }
        }

        $safeTitle = mysqli_real_escape_string($conn, $title);
        $safeShort = mysqli_real_escape_string($conn, $shortDesc);
        $safeDesc = mysqli_real_escape_string($conn, $desc);
        $safeSku = mysqli_real_escape_string($conn, $sku);
        $safeSlug = mysqli_real_escape_string($conn, $slug);
        $safeImages = mysqli_real_escape_string($conn, implode(',', $filenames));

        $categorySql = $categoryId !== null ? (string)intval($categoryId) : 'NULL';
        $saleSql = $sale !== null ? "'{$sale}'" : 'NULL';
        $weightSql = $weight !== null ? "'{$weight}'" : 'NULL';

        $ok = mysqli_query($conn, "
            INSERT INTO products
                (title, short_description, description, category_id, sku, product_type, price_inr, stock, weight, images, slug, regular_price, sale_price)
            VALUES
                ('{$safeTitle}', '{$safeShort}', '{$safeDesc}', {$categorySql}, " . ($safeSku !== '' ? "'{$safeSku}'" : 'NULL') . ", '{$type}', '{$priceInr}', '{$stock}', {$weightSql}, '{$safeImages}', '{$safeSlug}', '{$regular}', {$saleSql})
        ");

        if (!$ok) {
            echo "Failed product {$title}: " . mysqli_error($conn) . "\n";
            continue;
        }

        $localId = intval(mysqli_insert_id($conn));

        if ($type === 'variable') {
            $varPrices = importVariations($conn, $storeUrl, $consumerKey, $consumerSecret, $localId, intval($p['id']));
            if (count($varPrices) > 0) {
                sort($varPrices);
                $minPrice = floatval($varPrices[0]);

                $inStockVarCount = 0;
                $vrs = mysqli_query($conn, "SELECT COUNT(*) AS c FROM product_variations WHERE product_id='{$localId}' AND stock=1");
                if ($vrs && ($vr = mysqli_fetch_assoc($vrs))) {
                    $inStockVarCount = intval($vr['c'] ?? 0);
                }

                $parentStock = $inStockVarCount > 0 ? 1 : 0;
                mysqli_query($conn, "UPDATE products SET price_inr='{$minPrice}', stock='{$parentStock}' WHERE id='{$localId}'");
            }
        }

        $imported++;
        echo "Imported #{$imported}: {$title}\n";

        if ($maxProducts > 0 && $imported >= $maxProducts) {
            break 2;
        }
    }

    $page++;
}

echo "Done. Total imported products: {$imported}\n";
