<?php include 'includes/header.php'; ?>
<?php
include_once __DIR__ . '/includes/seo.php';
include_once __DIR__ . '/includes/homepage-config.php';
$seo = seoHome();
$settings = getHomepageSettings($conn);

$categoryIds = csvIdsToArray($settings['category_ids'] ?? '');
$brandIds = csvIdsToArray($settings['brand_ids'] ?? '');

function getCategoryTiles(mysqli $conn, array $ids): array {
    if (empty($ids)) return [];
    $tiles = [];
    foreach ($ids as $id) {
        $id = (int)$id;
        $cq = mysqli_query($conn, "SELECT id, name, slug FROM categories WHERE id='$id' LIMIT 1");
        $cat = $cq ? mysqli_fetch_assoc($cq) : null;
        if (!$cat) continue;

        $pq = mysqli_query($conn, "
            SELECT images FROM products
            WHERE category_id='$id' AND images IS NOT NULL AND images <> ''
            ORDER BY id DESC
            LIMIT 1
        ");
        $pr = $pq ? mysqli_fetch_assoc($pq) : null;
        $img = 'placeholder.webp';
        if (!empty($pr['images'])) {
            $imgs = array_filter(explode(',', $pr['images']));
            $img = $imgs[0] ?? $img;
        }

        $tiles[] = [
            'name' => $cat['name'],
            'slug' => $cat['slug'],
            'image' => $img,
        ];
    }
    return $tiles;
}

function getHomeProducts(mysqli $conn, string $mode): array {
    if ($mode === 'recent') {
        $sql = "SELECT * FROM products ORDER BY created_at DESC, id DESC LIMIT 8";
    } elseif ($mode === 'discount') {
        $sql = "
            SELECT *, ((regular_price - sale_price) / NULLIF(regular_price, 0)) AS off_ratio
            FROM products
            WHERE sale_price IS NOT NULL AND sale_price < regular_price
            ORDER BY off_ratio DESC, id DESC
            LIMIT 8
        ";
    } else {
        $sql = "SELECT * FROM products ORDER BY RAND() LIMIT 8";
    }

    $res = mysqli_query($conn, $sql);
    $items = [];
    if (!$res) return $items;
    while ($row = mysqli_fetch_assoc($res)) {
        $items[] = $row;
    }
    return $items;
}

$categoryTiles = getCategoryTiles($conn, $categoryIds);
$brandTiles = getCategoryTiles($conn, $brandIds);
$recentProducts = getHomeProducts($conn, 'recent');
$discountProducts = getHomeProducts($conn, 'discount');
$recommendedProducts = getHomeProducts($conn, 'recommended');
?>
<div class="container mt-4">
    <?php if (!empty($settings['hero_image'])): ?>
    <section class="home-banner mb-4">
        <?php $bannerHref = trim((string)($settings['hero_url'] ?? '')); ?>
        <?php if ($bannerHref !== ''): ?><a href="<?php echo htmlspecialchars($bannerHref); ?>"><?php endif; ?>
            <img src="/asm-crockery/assets/uploads/<?php echo htmlspecialchars($settings['hero_image']); ?>" alt="Homepage banner">
        <?php if ($bannerHref !== ''): ?></a><?php endif; ?>
    </section>
    <?php endif; ?>
    
    <section class="home-categories">
        <h2>Shop by Category</h2>
        <div class="home-grid-tiles">
            <?php foreach ($categoryTiles as $cat): ?>
                <a href="/asm-crockery/category/<?php echo $cat['slug']; ?>" class="home-tile-card">
                    <img src="/asm-crockery/assets/uploads/<?php echo htmlspecialchars($cat['image']); ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>">
                    <div class="home-tile-title"><?php echo htmlspecialchars($cat['name']); ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    
    <section class="home-categories mt-4">
        <h2>Shop by Brand</h2>
        <div class="home-grid-tiles">
            <?php foreach ($brandTiles as $cat): ?>
                <a href="/asm-crockery/category/<?php echo $cat['slug']; ?>" class="home-tile-card">
                    <img src="/asm-crockery/assets/uploads/<?php echo htmlspecialchars($cat['image']); ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>">
                    <div class="home-tile-title"><?php echo htmlspecialchars($cat['name']); ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

<?php
function renderHomeProductSection(array $products, string $title): void {
?>
<section class="mt-4">
    <h2><?php echo htmlspecialchars($title); ?></h2>
    <div class="home-grid-products">
        <?php foreach ($products as $p): ?>
            <?php
            $imgs = array_filter(explode(',', $p['images'] ?? ''));
            $img = $imgs[0] ?? 'placeholder.webp';
            $isSale = $p['sale_price'] !== null && (float)$p['sale_price'] < (float)$p['regular_price'];
            $off = 0;
            if ($isSale && (float)$p['regular_price'] > 0) {
                $off = (int)round((((float)$p['regular_price'] - (float)$p['sale_price']) / (float)$p['regular_price']) * 100);
            }
            ?>
            <div class="product-card">
                <?php if ($off > 0): ?><div class="sale-badge"><?php echo $off; ?>% OFF</div><?php endif; ?>
                <a href="/asm-crockery/product/<?php echo htmlspecialchars($p['slug']); ?>">
                    <img src="/asm-crockery/assets/uploads/<?php echo htmlspecialchars($img); ?>" loading="lazy" alt="<?php echo htmlspecialchars($p['title']); ?>">
                </a>
                <div class="product-card-body">
                    <div class="product-title"><?php echo htmlspecialchars($p['title']); ?></div>
                    <div class="price-wrap">
                        <?php if ($isSale): ?>
                            <span class="price-regular">₹<?php echo number_format((float)$p['regular_price'],2); ?></span>
                            <span class="price-sale">₹<?php echo number_format((float)$p['sale_price'],2); ?></span>
                        <?php else: ?>
                            <span class="price-normal">₹<?php echo number_format((float)$p['regular_price'],2); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php
}

renderHomeProductSection($recentProducts, 'Recently Added');
renderHomeProductSection($discountProducts, 'Biggest Discount');
renderHomeProductSection($recommendedProducts, 'Recommended for You');
?>

</div>

<?php include 'includes/footer.php'; ?>
