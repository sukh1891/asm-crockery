<?php
require_once 'includes/header.php';
require_once 'includes/functions.php';

$cart = getCartSummary($conn);
$appliedCoupon = strtoupper(trim($_SESSION['applied_coupon'] ?? ''));
$couponDiscount = 0;
$couponMessage = '';

$today = date('Y-m-d');
$availableCoupons = [];
$couponResult = mysqli_query(
    $conn,
    "SELECT code, type, amount, min_order, end_date
     FROM coupons
     WHERE status=1
       AND (start_date IS NULL OR start_date <= '$today')
       AND (end_date IS NULL OR end_date >= '$today')
     ORDER BY id DESC"
);

if ($couponResult) {
    while ($coupon = mysqli_fetch_assoc($couponResult)) {
        $availableCoupons[] = $coupon;

        if ($appliedCoupon && strtoupper($coupon['code']) === $appliedCoupon) {
            if ($cart['subtotal'] >= floatval($coupon['min_order'])) {
                if ($coupon['type'] === 'percent') {
                    $couponDiscount = round($cart['subtotal'] * (floatval($coupon['amount']) / 100), 2);
                } else {
                    $couponDiscount = round(floatval($coupon['amount']), 2);
                }
                $couponDiscount = min($couponDiscount, $cart['subtotal']);
                $couponMessage = 'Coupon ' . htmlspecialchars($appliedCoupon) . ' applied successfully.';
            } else {
                $_SESSION['applied_coupon'] = '';
                $appliedCoupon = '';
                $couponMessage = 'Applied coupon was removed because minimum order is not met.';
            }
        }
    }
}

$grandTotal = max(0, $cart['total'] - $couponDiscount);
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
<th width="120">Total</th>
<th width="80"></th>
</tr>
</thead>

<tbody>
<?php foreach ($cart['items'] as $item): ?>
<tr data-id="<?php echo $item['id']; ?>">
<td>
<?php echo htmlspecialchars($item['title']); ?>
<?php if (!empty($item['variation_label'])): ?>
    <div class="text-muted small"><?php echo htmlspecialchars($item['variation_label']); ?></div>
<?php endif; ?>
</td>

<td>
<input type="number"
       min="1"
       value="<?php echo intval($item['qty']); ?>"
       class="form-control form-control-sm qtyInput">
</td>
<td>₹<?php echo number_format(((float)$item['qty'] * (float)$item['price_inr']),2); ?></td>

<td>
<button class="btn btn-sm btn-danger removeBtn">✕</button>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<div class="card p-3 mb-3">
    <label for="couponCode" class="form-label fw-bold">Apply Coupon</label>
    <div class="input-group mb-2">
        <input type="text" id="couponCode" class="form-control" placeholder="Enter coupon code" value="<?php echo htmlspecialchars($appliedCoupon); ?>">
        <button class="btn btn-outline-primary" id="applyCouponBtn" type="button">Apply</button>
    </div>
    <div id="couponMsg" class="small <?php echo $couponDiscount > 0 ? 'text-success' : 'text-muted'; ?>">
        <?php echo $couponMessage ?: 'Enter a coupon code or use one from available offers below.'; ?>
    </div>

    <?php if (!empty($availableCoupons)): ?>
    <hr>
    <div class="small text-muted mb-2">Available Coupons</div>
    <div class="d-flex flex-wrap gap-2">
        <?php foreach ($availableCoupons as $coupon): ?>
            <?php
                $label = $coupon['type'] === 'percent'
                    ? rtrim(rtrim($coupon['amount'], '0'), '.') . '% OFF'
                    : '₹' . rtrim(rtrim($coupon['amount'], '0'), '.') . ' OFF';
            ?>
            <button
                type="button"
                class="btn btn-sm btn-light border applyAvailableCouponBtn"
                data-code="<?php echo htmlspecialchars($coupon['code']); ?>"
                title="Min order ₹<?php echo number_format($coupon['min_order'], 2); ?>">
                <strong><?php echo htmlspecialchars($coupon['code']); ?></strong>
                <span class="text-muted">(<?php echo $label; ?>)</span>
            </button>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<div class="text-end">
<p>Subtotal: ₹<?php echo number_format($cart['subtotal'],2); ?></p>
<p>Shipping: ₹<?php echo number_format($cart['shipping'],2); ?></p>
<p>Coupon Discount: -₹<span id="couponDiscountValue"><?php echo number_format($couponDiscount,2); ?></span></p>
<h4>Total: ₹<span id="cartGrandTotal"><?php echo number_format($grandTotal,2); ?></span></h4>
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
async function applyCoupon(code) {
    const couponInput = document.getElementById('couponCode');
    const couponMsg = document.getElementById('couponMsg');
    if (!couponInput || !couponMsg) return;

    const finalCode = (code || couponInput.value || '').trim();
    if (!finalCode) {
        couponMsg.className = 'small text-danger';
        couponMsg.textContent = 'Please enter a coupon code.';
        return;
    }

    const fd = new FormData();
    fd.append('code', finalCode);

    try {
        const res = await fetch('/asm-crockery/api/coupon/apply.php', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();

        if (!data.success) {
            couponMsg.className = 'small text-danger';
            couponMsg.textContent = data.msg || 'Unable to apply coupon';
            return;
        }

        await fetch('/asm-crockery/api/coupon/save-to-session.php', {
            method: 'POST',
            body: new URLSearchParams({ code: data.code })
        });

        couponMsg.className = 'small text-success';
        couponMsg.textContent = 'Coupon ' + data.code + ' applied.';
        couponInput.value = data.code;
        location.reload();
    } catch (err) {
        couponMsg.className = 'small text-danger';
        couponMsg.textContent = 'Something went wrong while applying coupon.';
    }
}

const applyCouponBtn = document.getElementById('applyCouponBtn');
if (applyCouponBtn) {
    applyCouponBtn.addEventListener('click', () => applyCoupon());
}

document.querySelectorAll('.applyAvailableCouponBtn').forEach(btn => {
    btn.addEventListener('click', () => {
        const code = btn.dataset.code || '';
        document.getElementById('couponCode').value = code;
        applyCoupon(code);
    });
});
</script>
<?php include 'includes/footer.php'; ?>
