<?php
include '../includes/header.php';
include '../includes/functions.php';
include 'auth-check.php';   // ensures user is logged in

$user_id = getLoggedInUserId();
$items   = getWishlistItems();

$country = getUserCountry();
$use_usd = ($country !== 'IN');
?>

<div class="container">
  <h2>My Wishlist</h2>

  <?php if (empty($items)): ?>
    <p>Your wishlist is empty. <a href="/">Start shopping</a></p>
  <?php else: ?>
    <div class="product-grid">
      <?php foreach ($items as $w):

        if (!$w['title']) continue; // product deleted?

        $images = explode(',', $w['images'] ?? '');
        $img    = $images[0] ?? 'default.png';

        // price display (for simple: direct; for variable: min variation)
        if ($w['product_type'] === 'variable') {
            $pid = intval($w['product_id']);
            $var_q = mysqli_query($conn,
                "SELECT MIN(price_inr) AS min_price FROM product_variations WHERE product_id='$pid'"
            );
            $var = mysqli_fetch_assoc($var_q);
            $price_inr = floatval($var['min_price'] ?? 0);
        } else {
            $price_inr = floatval($w['price_inr']);
        }

        $price_display = $use_usd
            ? '$' . convertToUSD($price_inr)
            : '₹' . number_format($price_inr, 2);
      ?>

        <div class="product-card" data-product="<?php echo $w['product_id']; ?>">

          <a href="/product.php?id=<?php echo $w['product_id']; ?>">
            <img src="/assets/uploads/<?php echo htmlspecialchars($img); ?>" alt="">
            <h3><?php echo htmlspecialchars($w['title']); ?></h3>
          </a>

          <p class="price"><?php echo $price_display; ?></p>

          <div class="actions">
            <button class="btn btn-sm btn-outline-primary add-to-cart-btn"
                    data-product="<?php echo $w['product_id']; ?>">
              Add to Cart
            </button>

            <button class="btn btn-sm btn-outline-danger remove-wishlist-btn"
                    data-product="<?php echo $w['product_id']; ?>">
              Remove
            </button>
          </div>
        </div>

      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
// Remove from wishlist
document.querySelectorAll('.remove-wishlist-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    const pid = this.dataset.product;

    fetch('/api/user/wishlist-toggle.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({product_id: pid})
    })
    .then(r=>r.json())
    .then(data=>{
      if (data.success) {
        location.reload();
      } else if (data.login_required) {
        window.location.href = '/login.php?redirect=' + encodeURIComponent(window.location.href);
      } else {
        alert(data.msg || 'Could not update wishlist');
      }
    });
  });
});

// Move to cart
document.querySelectorAll('.add-to-cart-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    const pid = this.dataset.product;

    fetch('/api/cart/add.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({product_id: pid, qty: 1})
    })
    .then(r=>r.json())
    .then(data=>{
      if (data.success) {
        alert('Added to cart');
        window.location.href = '/cart.php';
      } else {
        alert(data.msg || 'Could not add to cart');
      }
    });
  });
});
</script>

<?php include '../includes/footer.php'; ?>
