<?php include 'includes/header.php'; ?>
<?php
include_once __DIR__ . '/includes/seo.php';
$seo = seoHome();
?>
<div class="container mt-4">

<section class="hero">
    <h1>Premium Crockery, Kitchenware & Cookware</h1>
    <p>
        Shop the finest collection curated for your home & restaurants.
    </p>
</section>

<section class="home-categories">
    <h2>Shop by Category</h2>

    <div class="category-grid">
        <?php foreach ($menuCategories as $cat): ?>
            <a href="/asm-crockery/category/<?php echo $cat['slug']; ?>"
               class="category-card">
                <div class="category-name">
                    <?php echo htmlspecialchars($cat['name']); ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

</div>

<?php include 'includes/footer.php'; ?>