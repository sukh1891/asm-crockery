<?php
require_once 'includes/header.php';
require_once 'includes/functions.php';

$cart = getCartSummary($conn);
?>

<div class="container mt-4">
<h2>Your Cart</h2>

<?php if (empty($cart['items'])): ?>
<p>Your cart is empty.</p>
<?php else: ?>

<table class="table">
<thead>
<tr>
<th>Product</th>
<th width="120">Qty</th>
<th width="80"></th>
</tr>
</thead>

<tbody>
<?php foreach ($cart['items'] as $item): ?>
<tr data-id="<?php echo $item['id']; ?>">
<td><?php echo htmlspecialchars($item['title']); ?></td>

<td>
<input type="number"
       min="1"
       value="<?php echo intval($item['qty']); ?>"
       class="form-control form-control-sm qtyInput">
</td>

<td>
<button class="btn btn-sm btn-danger removeBtn">✕</button>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<div class="text-end">
<p>Shipping: ₹<?php echo number_format($cart['shipping'],2); ?></p>
<h4>Total: ₹<?php echo number_format($cart['total'],2); ?></h4>
</div>

<a href="/asm-crockery/checkout.php" class="btn btn-success">
Proceed to Checkout
</a>

<?php endif; ?>
</div>

<script>
document.querySelectorAll('.qtyInput').forEach(input => {
    input.addEventListener('change', async () => {
        const tr = input.closest('tr');
        const cartId = tr.dataset.id;

        const fd = new FormData();
        fd.append('cart_id', cartId);
        fd.append('qty', input.value);

        await fetch('/asm-crockery/api/cart/update.php', {
            method: 'POST',
            body: fd
        });

        location.reload();
    });
});

document.querySelectorAll('.removeBtn').forEach(btn => {
    btn.addEventListener('click', async () => {
        const tr = btn.closest('tr');
        const cartId = tr.dataset.id;

        const fd = new FormData();
        fd.append('cart_id', cartId);

        await fetch('/asm-crockery/api/cart/remove.php', {
            method: 'POST',
            body: fd
        });

        tr.remove();
        location.reload();
    });
});
</script>
