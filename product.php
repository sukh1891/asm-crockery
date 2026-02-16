<?php
include __DIR__ . '/config/db.php';
include __DIR__ . '/includes/category-functions.php';
include_once __DIR__ . '/includes/seo.php';

/* ===== FETCH PRODUCT ===== */
$slug = $_GET['slug'] ?? '';
if (!$slug) {
    include __DIR__ . '/includes/404.php';
    exit;
}

$pq = mysqli_query(
    $conn,
    "SELECT * FROM products
     WHERE slug='".mysqli_real_escape_string($conn,$slug)."'
     LIMIT 1"
);
$product = mysqli_fetch_assoc($pq);

if (!$product) {
    include __DIR__ . '/includes/404.php';
    exit;
}

/* ===== BREADCRUMBS ===== */
$breadcrumbs = getCategoryBreadcrumb($product['category_id']);

/* ===== SEO (BEFORE HEADER) ===== */
$seo = seoProduct($product, $breadcrumbs);

/* ===== HEADER ===== */
include __DIR__ . '/includes/header.php';

/* ===== IMAGES ===== */
$images = array_filter(explode(',', $product['images']));
$mainImg = $images[0] ?? 'placeholder.webp';

/* ===== VARIATIONS ===== */
$variations = [];
$defaultVar = null;

if ($product['product_type'] === 'variable') {
    $vq = mysqli_query(
        $conn,
        "SELECT *
         FROM product_variations
         WHERE product_id='{$product['id']}'
         ORDER BY price_inr ASC"
    );

    while ($v = mysqli_fetch_assoc($vq)) {
        $variations[] = $v;

        // cheapest in-stock variation
        if ($v['stock'] == 1 && !$defaultVar) {
            $defaultVar = $v;
        }
    }
}

/* ===== STOCK STATUS ===== */
$isSimpleInStock = ($product['product_type'] === 'simple' && $product['stock'] == 1);
?>

<div class="container mt-4">

<!-- BREADCRUMBS -->
<nav aria-label="breadcrumb">
<ol class="breadcrumb">
    <li class="breadcrumb-item"><a href="/asm-crockery/">Home</a></li>
    <?php foreach ($breadcrumbs as $bc): ?>
        <li class="breadcrumb-item">
            <a href="/asm-crockery/category/<?php echo $bc['slug']; ?>">
                <?php echo htmlspecialchars($bc['name']); ?>
            </a>
        </li>
    <?php endforeach; ?>
    <li class="breadcrumb-item active"><?php echo htmlspecialchars($product['title']); ?></li>
</ol>
</nav>

<div class="row">

<!-- LEFT: IMAGES -->
<div class="col-md-6 position-relative mb-3">

    <?php if ($product['sale_price'] && $product['sale_price'] < $product['regular_price']): ?>
        <div class="sale-badge">SALE</div>
    <?php endif; ?>

    <div class="product-gallery">

        <?php if (count($images) > 1): ?>
        <div class="gallery-thumbs">
            <?php foreach ($images as $i => $img): ?>
                <img src="/asm-crockery/assets/uploads/<?php echo $img; ?>"
                     class="<?php echo $i===0?'active':''; ?>"
                     onclick="setMainImage(this)">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="gallery-main">
            <img id="mainProductImage"
                 src="/asm-crockery/assets/uploads/<?php echo $mainImg; ?>">
        </div>

    </div>
</div>

<!-- RIGHT: DETAILS -->
<div class="col-md-6">

<h1 class="product-title-lg"><?php echo htmlspecialchars($product['title']); ?></h1>

<?php if (!empty($product['short_description'])): ?>
<div class="product-short-desc mb-3">
    <?php echo $product['short_description']; ?>
</div>
<?php endif; ?>

<!-- PRICE -->
<div class="mb-3">
<?php if ($product['product_type'] === 'simple'): ?>

    <?php if ($product['sale_price'] && $product['sale_price'] < $product['regular_price']): ?>
        <div class="price-wrap">
            <span class="price-regular">₹<?php echo number_format($product['regular_price'],2); ?></span>
            <span class="price-sale fs-4">₹<?php echo number_format($product['sale_price'],2); ?></span>
        </div>
    <?php else: ?>
        <div class="price-normal fs-4">
            ₹<?php echo number_format($product['regular_price'],2); ?>
        </div>
    <?php endif; ?>

<?php else: ?>
    <div id="varPrice" class="price-normal fs-4">
        <?php echo $defaultVar ? '₹'.number_format($defaultVar['price_inr'],2) : 'Out of stock'; ?>
    </div>
<?php endif; ?>
</div>

<!-- VARIATIONS -->
<?php if ($product['product_type'] === 'variable'): ?>
<div class="mb-3">
<label>Select Option</label>
<select class="form-select" id="variationSelect">
    <?php foreach ($variations as $v): ?>
        <option value="<?php echo $v['id']; ?>"
                data-price="<?php echo $v['price_inr']; ?>"
                data-stock="<?php echo $v['stock']; ?>"
                <?php if ($defaultVar && $v['id']===$defaultVar['id']) echo 'selected'; ?>>
            <?php echo htmlspecialchars($v['attributes_json']); ?>
        </option>
    <?php endforeach; ?>
</select>
</div>
<?php endif; ?>

<!-- ADD TO CART / STOCK STATUS -->
<form method="post" action="/asm-crockery/cart.php">
<input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
<input type="hidden" name="variation_id" id="variationId"
       value="<?php echo $defaultVar['id'] ?? ''; ?>">

<?php if (
    ($product['product_type']==='simple' && !$isSimpleInStock) ||
    ($product['product_type']==='variable' && !$defaultVar)
): ?>
    <div class="out-of-stock-label">Out of Stock</div>
<?php else: ?>
    <button id="addToCartBtn" type="button" class="btn btn-primary">Add to Cart</button>
<?php endif; ?>
</form>

</div>
</div>

<!-- FULL DESCRIPTION -->
<?php if (!empty($product['description'])): ?>
<div class="mt-5">
<h3>Description</h3>
<div class="product-description">
    <?php echo $product['description']; ?>
</div>
</div>
<?php endif; ?>

<!-- RELATED PRODUCTS -->
<?php
$related = [];

/* Same category */
$rq = mysqli_query(
    $conn,
    "SELECT * FROM products
     WHERE category_id='{$product['category_id']}'
     AND id!='{$product['id']}'
     LIMIT 12"
);
while ($r = mysqli_fetch_assoc($rq)) {
    $related[$r['id']] = $r;
}

/* Similar title */
if (count($related) < 12) {
    $words = explode(' ', $product['title']);
    $kw = mysqli_real_escape_string($conn, $words[0]);

    $rq2 = mysqli_query(
        $conn,
        "SELECT * FROM products
         WHERE title LIKE '%$kw%'
         AND id!='{$product['id']}'
         LIMIT 12"
    );
    while ($r = mysqli_fetch_assoc($rq2)) {
        $related[$r['id']] = $r;
    }
}

/* Fill random */
if (count($related) < 12) {
    $rq3 = mysqli_query(
        $conn,
        "SELECT * FROM products
         WHERE id!='{$product['id']}'
         ORDER BY RAND()
         LIMIT 12"
    );
    while ($r = mysqli_fetch_assoc($rq3)) {
        $related[$r['id']] = $r;
        if (count($related) >= 12) break;
    }
}
?>

<?php if ($related): ?>
<div class="mt-5">
<h3>Related Products</h3>
<div class="product-grid">
<?php foreach (array_slice($related,0,12) as $rp): ?>
<?php
$imgs = explode(',',$rp['images']);
$img = $imgs[0] ?? 'placeholder.webp';
?>
<div class="product-card">
<a href="/asm-crockery/product/<?php echo $rp['slug']; ?>">
<img src="/asm-crockery/assets/uploads/<?php echo $img; ?>">
</a>
<div class="product-title"><?php echo htmlspecialchars($rp['title']); ?></div>
</div>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>

</div>

<script>
function setMainImage(el) {
    document.getElementById('mainProductImage').src = el.src;
    document.querySelectorAll('.gallery-thumbs img')
        .forEach(i=>i.classList.remove('active'));
    el.classList.add('active');
}

const select = document.getElementById('variationSelect');
if (select) {
    select.addEventListener('change', () => {
        variableOptions();
    });
}
function variableOptions() {
    if (!select) return;

    const opt = select.options[select.selectedIndex];
    if (!opt) return;

    const priceBox = document.getElementById('varPrice');
    if (priceBox && opt.dataset.price) {
        priceBox.textContent = '₹' + parseFloat(opt.dataset.price).toFixed(2);
    }

    const vidInput = document.getElementById('variationId');
    if (vidInput) {
        vidInput.value = opt.value;
    }

    const addBtn = document.getElementById('addToCartBtn');
    if (addBtn) {
        addBtn.disabled = (opt.dataset.stock !== "1");
    }
}

if (select) {
    variableOptions();
}

    
const addBtn = document.getElementById('addToCartBtn');
if (addBtn) {
    addBtn.addEventListener('click', async (e) => {
        e.preventDefault();

        const fd = new FormData();
        fd.append('product_id', '<?php echo $product['id']; ?>');
        fd.append('qty', 1);

        const variation = document.getElementById('variationId');
        if (variation && variation.value) {
            fd.append('variation_id', variation.value);
        }

        const res = await fetch('/asm-crockery/api/cart/add.php', {
            method: 'POST',
            body: fd
        });

        const data = await res.json();
        if (data.success) {
            window.location.href = '/asm-crockery/cart.php';
        } else {
            alert(data.message || 'Failed to add to cart');
        }
    });
}

</script>

<?php include __DIR__ . '/includes/footer.php'; ?>