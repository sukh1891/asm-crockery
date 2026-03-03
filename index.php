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
        
        $countQ = mysqli_query($conn, "SELECT COUNT(*) AS total_items FROM products WHERE category_id='$id'");
        $countR = $countQ ? mysqli_fetch_assoc($countQ) : null;

        $tiles[] = [
            'name' => $cat['name'],
            'slug' => $cat['slug'],
            'image' => $img,
            'total_items' => (int)($countR['total_items'] ?? 0)
        ];
    }
    return $tiles;
}

function truncateWords(string $text, int $maxWords = 8): string {
    $words = preg_split('/\s+/', trim($text));
    if (!$words) return '';
    if (count($words) <= $maxWords) return implode(' ', $words);
    return implode(' ', array_slice($words, 0, $maxWords)) . '...';
}

function getProductCardPricing(mysqli $conn, array $product): array {
    $isVariable = ($product['product_type'] ?? '') === 'variable';

    if ($isVariable) {
        $productId = (int)($product['id'] ?? 0);
        $vq = mysqli_query(
            $conn,
            "SELECT MIN(price_inr) AS min_price FROM product_variations WHERE product_id='$productId'"
        );
        $vr = $vq ? mysqli_fetch_assoc($vq) : null;
        $displayPrice = (float)($vr['min_price'] ?? 0);

        return [
            'is_variable' => true,
            'is_sale' => false,
            'regular_price' => $displayPrice,
            'sale_price' => null,
            'off' => 0,
        ];
    }

    $regularPrice = (float)($product['regular_price'] ?? 0);
    $salePrice = isset($product['sale_price']) ? (float)$product['sale_price'] : null;
    $isSale = $salePrice !== null && $salePrice > 0 && $salePrice < $regularPrice;

    $off = 0;
    if ($isSale && $regularPrice > 0) {
        $off = (int)round((($regularPrice - $salePrice) / $regularPrice) * 100);
    }

    return [
        'is_variable' => false,
        'is_sale' => $isSale,
        'regular_price' => $regularPrice,
        'sale_price' => $salePrice,
        'off' => $off,
    ];
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
$watchBuyItems = getWatchBuyItems($settings['watch_buy_videos'] ?? '');
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
        <?php foreach ($categoryTiles as $index => $cat): ?>
                <a href="/asm-crockery/category/<?php echo $cat['slug']; ?>" class="home-tile-card home-tile-gradient-1">
                    <div class="home-tile-content">
                        <div class="home-tile-text">
                            <div class="home-tile-title"><?php echo htmlspecialchars($cat['name']); ?></div>
                            <div class="home-tile-count"><?php echo (int)$cat['total_items']; ?> items</div>
                        </div>
                        <img src="/asm-crockery/assets/uploads/<?php echo htmlspecialchars($cat['image']); ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>">
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    
    <?php if (!empty($watchBuyItems)): ?>
    <section class="home-categories mt-4">
        <h2>Watch &amp; Buy</h2>
        <div class="home-grid-watch-buy">
            <?php foreach ($watchBuyItems as $i => $item): ?>
                <button
                    type="button"
                    class="watch-buy-card"
                    data-video-src="/asm-crockery/assets/uploads/<?php echo htmlspecialchars($item['video']); ?>"
                    data-product-url="<?php echo htmlspecialchars($item['product_url']); ?>"
                >   
                    <video muted playsinline webkit-playsinline preload="metadata" autoplay loop>
                        <source src="/asm-crockery/assets/uploads/<?php echo htmlspecialchars($item['video']); ?>" type="video/mp4">
                    </video>
                </button>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="watch-buy-modal" id="watchBuyModal" aria-hidden="true">
        <button type="button" class="watch-buy-close" id="watchBuyClose" aria-label="Close">&times;</button>
        <video id="watchBuyPlayer" controls playsinline webkit-playsinline preload="metadata"></video>
        <a id="watchBuyLink" href="#" class="watch-buy-btn" target="_self">Buy Now</a>
    </div>
    <?php endif; ?>
    
    <section class="home-categories mt-4">
        <h2>Shop by Brand</h2>
        <div class="home-grid-tiles">
        <?php foreach ($brandTiles as $index => $cat): ?>
                <a href="/asm-crockery/category/<?php echo $cat['slug']; ?>" class="home-tile-card home-tile-gradient-1">
                    <div class="home-tile-content">
                        <div class="home-tile-text">
                            <div class="home-tile-title"><?php echo htmlspecialchars($cat['name']); ?></div>
                            <div class="home-tile-count"><?php echo (int)$cat['total_items']; ?> items</div>
                        </div>
                        <img src="/asm-crockery/assets/uploads/<?php echo htmlspecialchars($cat['image']); ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>">
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

<?php
function renderHomeProductSection(mysqli $conn, array $products, string $title): void {
?>
<section class="mt-4">
    <h2><?php echo htmlspecialchars($title); ?></h2>
    <div class="home-grid-products">
        <?php foreach ($products as $p): ?>
            <?php
            $imgs = array_filter(explode(',', $p['images'] ?? ''));
            $img = $imgs[0] ?? 'placeholder.webp';
            $pricing = getProductCardPricing($conn, $p);
            ?>
            <div class="product-card">
                <?php if ($pricing['off'] > 0): ?><div class="sale-badge"><?php echo $pricing['off']; ?>% OFF</div><?php endif; ?>
                <a href="/asm-crockery/product/<?php echo htmlspecialchars($p['slug']); ?>">
                    <img src="/asm-crockery/assets/uploads/<?php echo htmlspecialchars($img); ?>" loading="lazy" alt="<?php echo htmlspecialchars($p['title']); ?>">
                </a>
                <div class="product-card-body">
                    <a class="product-title" href="/asm-crockery/product/<?php echo htmlspecialchars($p['slug']); ?>">
                        <?php echo htmlspecialchars(truncateWords((string)$p['title'])); ?>
                    </a>
                    <div class="price-wrap">
                        <?php if ($pricing['is_variable']): ?>
                            <span class="price-normal">₹<?php echo number_format((float)$pricing['regular_price'],2); ?></span>
                        <?php elseif ($pricing['is_sale']): ?>
                            <span class="price-regular">₹<?php echo number_format((float)$pricing['regular_price'],2); ?></span>
                            <span class="price-sale">₹<?php echo number_format((float)$pricing['sale_price'],2); ?></span>
                        <?php else: ?>
                            <span class="price-normal">₹<?php echo number_format((float)$pricing['regular_price'],2); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php
}

renderHomeProductSection($conn, $recentProducts, 'Recently Added');
renderHomeProductSection($conn, $discountProducts, 'Biggest Discount');
renderHomeProductSection($conn, $recommendedProducts, 'Recommended for You');
?>

</div>
<script>
(function () {
    const cards = document.querySelectorAll('.watch-buy-card');
    if (!cards.length) return;

    const modal = document.getElementById('watchBuyModal');
    const closeBtn = document.getElementById('watchBuyClose');
    const player = document.getElementById('watchBuyPlayer');
    const buyLink = document.getElementById('watchBuyLink');

    const openModal = (videoSrc, productUrl) => {
        player.innerHTML = '<source src="' + videoSrc + '" type="video/mp4">';
        player.load();
        player.play().catch(() => {});
        buyLink.href = productUrl;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    };

    const closeModal = () => {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        player.pause();
        player.removeAttribute('src');
        player.innerHTML = '';
        document.body.style.overflow = '';
    };

    cards.forEach((card) => {
        const preview = card.querySelector('video');
        const previewSrc = card.dataset.videoSrc;
        if (preview && previewSrc) {
            preview.innerHTML = '<source src="' + previewSrc + '" type="video/mp4">';
            preview.load();
            preview.play().catch(() => {});
        }
        card.addEventListener('click', () => openModal(card.dataset.videoSrc, card.dataset.productUrl));
    });

    closeBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });
})();
</script>
<?php include 'includes/footer.php'; ?>
