<?php
include '../includes/header.php';
include '../includes/functions.php';
include 'auth-check.php';

$user_id = intval($_SESSION['user_id']);
$order_id = intval($_GET['id'] ?? 0);

$q = mysqli_query($conn, "SELECT * FROM orders WHERE id='$order_id' AND user_id='$user_id' LIMIT 1");
if (mysqli_num_rows($q) == 0) {
  echo "<div class='container'><h3>Order not found</h3></div>";
  include '../includes/footer.php';
  exit;
}
$order = mysqli_fetch_assoc($q);

$items_q = mysqli_query($conn, "SELECT oi.*, p.title AS product_name FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id='$order_id'");

?>

<div class="container">
  <h2>Order #<?php echo $order_id; ?></h2>

  <div class="row">
    <div class="col-md-8">
      <h5>Items</h5>
      <table class="table">
        <thead><tr><th>Product</th><th>Variation</th><th>Qty</th><th>Price</th></tr></thead>
        <tbody>
          <?php while ($it = mysqli_fetch_assoc($items_q)): ?>
            <tr>
              <td><?php echo htmlspecialchars($it['product_name']); ?></td>
              <td><?php
                  if ($it['variation_id']) {
                      $vq = mysqli_fetch_assoc(mysqli_query($conn, "SELECT attributes_json FROM product_variations WHERE id='".intval($it['variation_id'])."'"));
                      echo htmlspecialchars(json_encode(json_decode($vq['attributes_json']), JSON_UNESCAPED_UNICODE));
                  } else {
                      echo '-';
                  }
                ?></td>
              <td><?php echo intval($it['qty']); ?></td>
              <td>₹<?php echo number_format($it['price'],2); ?></td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>

      <a href="/admin/invoice.php?id=<?php echo $order_id; ?>" class="btn btn-outline-secondary" target="_blank">Download Invoice (PDF)</a>
    </div>

    <div class="col-md-4">
      <div class="card p-3">
        <p><strong>Status:</strong> <?php echo ucfirst($order['status']); ?></p>
        <p><strong>Total:</strong> ₹<?php echo number_format($order['amount'],2); ?></p>
        <p><strong>Payment:</strong> <?php echo htmlspecialchars($order['payment_id'] ?: 'N/A'); ?></p>
        <p><strong>Shipping to:</strong><br><?php echo nl2br(htmlspecialchars($order['address'])); ?><br><?php echo htmlspecialchars($order['city']); ?> - <?php echo htmlspecialchars($order['zip']); ?></p>

        <hr>

        <button id="reorderBtn" data-order="<?php echo $order_id; ?>" class="btn btn-primary w-100">Reorder</button>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('reorderBtn').addEventListener('click', function(){
  if (!confirm('Add items from this order into your cart?')) return;
  fetch('/api/user/reorder.php', {
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body: JSON.stringify({ order_id: this.dataset.order })
  }).then(r=>r.json()).then(data=>{
    if (data.success) {
      alert('Items added to cart');
      window.location.href = '/cart.php';
    } else {
      alert(data.msg || 'Could not reorder');
    }
  });
});
</script>

<?php include '../includes/footer.php'; ?>
