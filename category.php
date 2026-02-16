<?php
include __DIR__ . '/config/db.php';
include __DIR__ . '/includes/category-functions.php';
include_once __DIR__ . '/includes/seo.php';

/* ===== CATEGORY FETCH ===== */
$slug = $_GET['slug'] ?? '';
if (!$slug) {
    include __DIR__ . '/includes/404.php';
    exit;
}

$cq = mysqli_query($conn,
    "SELECT * FROM categories WHERE slug='".mysqli_real_escape_string($conn,$slug)."' LIMIT 1"
);
$category = mysqli_fetch_assoc($cq);

if (!$category) {
    include __DIR__ . '/includes/404.php';
    exit;
}

/* ===== BREADCRUMBS ===== */
$breadcrumbs = getCategoryBreadcrumb($category['id']);

/* ===== SEO (MUST BE BEFORE HEADER) ===== */
$seo = seoCategory($category, $breadcrumbs);

/* ===== HEADER ===== */
include __DIR__ . '/includes/header.php';

/* ===== CHILD CATEGORIES ===== */
$childCats = mysqli_query($conn,
    "SELECT * FROM categories WHERE parent='{$category['id']}' ORDER BY name"
);

/* ===== PRODUCTS (SELF + CHILDREN) ===== */
$catIds = getCategoryDescendants($category['id']);
$idStr = implode(',', array_map('intval',$catIds));

$pq = mysqli_query($conn,
    "SELECT * FROM products
     WHERE category_id IN ($idStr)
     ORDER BY id DESC"
);
?>

<div class="container mt-4">

<!-- BREADCRUMBS -->
<nav aria-label="breadcrumb">
<ol class="breadcrumb">
    <li class="breadcrumb-item">
        <a href="/asm-crockery/">Home</a>
    </li>
    <?php foreach ($breadcrumbs as $bc): ?>
        <li class="breadcrumb-item">
            <a href="/asm-crockery/category/<?php echo $bc['slug']; ?>">
                <?php echo htmlspecialchars($bc['name']); ?>
            </a>
        </li>
    <?php endforeach; ?>
</ol>
</nav>

<h1 class="category-title"><?php echo htmlspecialchars($category['name']); ?></h1>

<!-- SUB CATEGORIES -->
<?php if (mysqli_num_rows($childCats) > 0): ?>
<div class="mb-4">
    <div class="d-flex flex-wrap gap-2">
        <?php while ($c = mysqli_fetch_assoc($childCats)): ?>
            <a class="btn btn-outline-secondary btn-sm"
               href="/asm-crockery/category/<?php echo $c['slug']; ?>">
                <?php echo htmlspecialchars($c['name']); ?>
            </a>
        <?php endwhile; ?>
    </div>
</div>
<?php endif; ?>

<!-- PRODUCTS -->
<div class="product-grid">
<?php if (mysqli_num_rows($pq) === 0): ?>
    <p>No products found.</p>
<?php endif; ?>

<?php while ($p = mysqli_fetch_assoc($pq)): ?>
<?php
$isVariable = $p['product_type'] === 'variable';
$displayPrice = $p['regular_price'];

if ($isVariable) {
    $vq = mysqli_query(
        $conn,
        "SELECT MIN(price_inr) AS min_price
         FROM product_variations
         WHERE product_id='{$p['id']}'"
    );
    $vr = mysqli_fetch_assoc($vq);
    if ($vr && $vr['min_price'] !== null) {
        $displayPrice = $vr['min_price'];
    }
}

$imgs = array_filter(explode(',', $p['images']));
$img  = $imgs[0] ?? 'placeholder.webp';
$isSale = $p['sale_price'] !== null && $p['sale_price'] < $p['regular_price'];
?>
<div class="product-card">
    <?php if ($isSale): ?>
        <div class="sale-badge">SALE</div>
    <?php endif; ?>

    <a href="/asm-crockery/product/<?php echo $p['slug']; ?>">
        <img src="/asm-crockery/assets/uploads/<?php echo $img; ?>" loading="lazy">
    </a>

    <div class="product-card-body">
        <div class="product-title"><?php echo htmlspecialchars($p['title']); ?></div>

        <div class="price-wrap">
        <?php if ($isVariable): ?>
            <span class="price-normal">
                ₹<?php echo number_format($displayPrice, 2); ?>
            </span>
        <?php else: ?>
            <?php if ($isSale): ?>
                <span class="price-regular">
                    ₹<?php echo number_format($p['regular_price'],2); ?>
                </span>
                <span class="price-sale">
                    ₹<?php echo number_format($p['sale_price'],2); ?>
                </span>
            <?php else: ?>
                <span class="price-normal">
                    ₹<?php echo number_format($p['regular_price'],2); ?>
                </span>
            <?php endif; ?>
        <?php endif; ?>
        </div>
    </div>
</div>
<?php endwhile; ?>
</div>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
