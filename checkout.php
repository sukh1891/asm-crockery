<?php
require_once 'config/db.php';
require_once 'config/keys.php';
require_once 'includes/functions.php';
require_once 'includes/countries.php';
require_once 'includes/header.php';

$cartSummary = getCartSummary($conn);

$appliedCouponSummary = getAppliedCouponForSubtotal($cartSummary['subtotal'], $_SESSION['user_id'] ?? null);
$couponDiscount = floatval($appliedCouponSummary['discount'] ?? 0);
$grandTotal = round($cartSummary['total'] - $couponDiscount, 2);

$checkoutCountryCode = getUserCountry();
$isDefaultIndia = strtoupper($checkoutCountryCode) === 'IN';
$displayInUSD = !$isDefaultIndia;
$currencySymbol = $displayInUSD ? '$' : '₹';
$countries = getAllCountries();
$selectedCountry = countryCodeToName($checkoutCountryCode);
if (!in_array($selectedCountry, $countries, true)) {
    $selectedCountry = $isDefaultIndia ? 'India' : 'United States';
}

$totalWeightKg = 0;
foreach ($cartSummary['items'] as $item) {
    $qty = max(1, intval($item['qty'] ?? 1));
    $weight = isset($item['variation_weight']) && $item['variation_weight'] !== null
        ? floatval($item['variation_weight'])
        : floatval($item['product_weight'] ?? 0);
    $totalWeightKg += ($weight * $qty);
}

$userLoggedIn = isset($_SESSION['user_id']);
$user = ['name'=>'','email'=>'','phone'=>'','address'=>''];

if ($userLoggedIn) {
    $uid = intval($_SESSION['user_id']);
    $q = mysqli_query($conn,"SELECT * FROM users WHERE id='$uid' LIMIT 1");
    $user = mysqli_fetch_assoc($q);
}
?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<div class="container mt-4">
<h2>Checkout</h2>

<div class="row">
<div class="col-md-6">

<h5>Billing Details</h5>
<form>
<input name="name" class="form-control mb-2" value="<?php echo htmlspecialchars($user['name']); ?>" placeholder="Full Name">
<input name="phone" class="form-control mb-2" value="<?php echo htmlspecialchars($user['phone']); ?>" placeholder="Phone" maxlength="10">
<input name="email" class="form-control mb-2" value="<?php echo htmlspecialchars($user['email']); ?>" placeholder="Email">
<textarea name="address" class="form-control mb-2" placeholder="Address"><?php echo htmlspecialchars($user['address']); ?></textarea>
<select name="country" class="form-select mb-2">
    <?php foreach ($countries as $countryName): ?>
        <option value="<?php echo htmlspecialchars($countryName); ?>" <?php echo $countryName === $selectedCountry ? "selected" : ""; ?>>
            <?php echo htmlspecialchars($countryName); ?>
        </option>
    <?php endforeach; ?>
</select>

</div>

<div class="col-md-6">
<h5>Order Summary</h5>

<table class="table">
<?php foreach ($cartSummary['items'] as $i): ?>
<tr>
<td><?php echo htmlspecialchars($i['title']); ?></td>
<td class="text-end checkout-item-price" data-price-inr="<?php echo htmlspecialchars((string)($i['qty']*$i['price_inr'])); ?>"><?php echo $currencySymbol . number_format($displayInUSD ? convertToUSD($i['qty']*$i['price_inr']) : ($i['qty']*$i['price_inr']),2); ?></td>
</tr>
<?php endforeach; ?>
<tr>
<th>Subtotal</th>
<th class="text-end" id="checkoutSubtotal" data-price-inr="<?php echo htmlspecialchars((string)$cartSummary['subtotal']); ?>"><?php echo $currencySymbol . number_format($displayInUSD ? convertToUSD($cartSummary['subtotal']) : $cartSummary['subtotal'],2); ?></th>
</tr>
<tr>
<th>Shipping</th>
<th class="text-end" id="checkoutShipping" data-price-inr="<?php echo htmlspecialchars((string)$cartSummary['shipping']); ?>"><?php echo $currencySymbol . number_format($displayInUSD ? convertToUSD($cartSummary['shipping']) : $cartSummary['shipping'],2); ?></th>
</tr>
<tr>
<th>Coupon Discount</th>
<th class="text-end text-success" id="checkoutDiscount" data-price-inr="<?php echo htmlspecialchars((string)$couponDiscount); ?>">-<?php echo $currencySymbol . number_format($displayInUSD ? convertToUSD($couponDiscount) : $couponDiscount,2); ?></th>
</tr>
<tr>
<th>Total</th>
<th class="text-end" id="checkoutTotal" data-price-inr="<?php echo htmlspecialchars((string)$grandTotal); ?>"><?php echo $currencySymbol . number_format($displayInUSD ? convertToUSD($grandTotal) : $grandTotal,2); ?></th>
</tr>
</table>

<button type="button" id="checkoutBtn" class="btn btn-success w-100">

<?php echo $userLoggedIn ? 'Proceed to Pay' : 'Proceed'; ?>
</button>
</form>
</div>
</div>
</div>

<!-- OTP MODAL -->
<div class="modal fade" id="otpModal" tabindex="-1">
<div class="modal-dialog">
<div class="modal-content p-3">
<h5>Verify Mobile</h5>
<input id="otpInput" class="form-control mb-2" placeholder="Enter OTP">
<button type="button" id="verifyOtpBtn" class="btn btn-primary w-100">Verify OTP</button>
</div>
</div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    console.log("Checkout script loaded");

    const checkoutBtn = document.getElementById('checkoutBtn');
    if (!checkoutBtn) return;

    const countrySelect = document.querySelector('[name="country"]');
    const totalWeightKg = <?php echo json_encode((float)$totalWeightKg); ?>;
    const subtotalInr = parseFloat(document.getElementById('checkoutSubtotal')?.getAttribute('data-price-inr') || '0');
    const discountInr = parseFloat(document.getElementById('checkoutDiscount')?.getAttribute('data-price-inr') || '0');

    const conversionRate = <?php echo json_encode((float)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT rate FROM currency_rates WHERE currency='INR_TO_USD' LIMIT 1"))['rate'] ?? 0.012)); ?>;

    const formatMoney = (amount, useUSD) => {
        const value = useUSD ? (amount * conversionRate) : amount;
        return (useUSD ? '$' : '₹') + value.toFixed(2);
    };

    const updateDisplayedCheckoutPrices = () => {
        const selectedCountry = (countrySelect?.value || '').toLowerCase();
        const useUSD = selectedCountry !== 'india';

        const shippingInr = selectedCountry === 'india' ? 0 : (Math.ceil(Math.max(0, totalWeightKg)) * 1000);
        const totalInr = Math.max(0, subtotalInr + shippingInr - discountInr);

        const shippingEl = document.getElementById('checkoutShipping');
        const totalEl = document.getElementById('checkoutTotal');
        if (shippingEl) shippingEl.setAttribute('data-price-inr', String(shippingInr));
        if (totalEl) totalEl.setAttribute('data-price-inr', String(totalInr));

        document.querySelectorAll('[data-price-inr]').forEach((el) => {
            const amount = parseFloat(el.getAttribute('data-price-inr') || '0');
            const isDiscount = el.id === 'checkoutDiscount';
            const formatted = formatMoney(amount, useUSD);
            el.textContent = isDiscount ? ('-' + formatted) : formatted;
        });
    };

    if (countrySelect) {
        countrySelect.addEventListener('change', updateDisplayedCheckoutPrices);
        updateDisplayedCheckoutPrices();
    }
    
    const loggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
    
    const resetCheckoutButton = function () {
        checkoutBtn.disabled = false;
        checkoutBtn.innerText = "Proceed to Pay";
    };

    checkoutBtn.addEventListener('click', async function (e) {
        e.preventDefault();

        const name    = document.querySelector('[name="name"]').value.trim();
        const phone   = document.querySelector('[name="phone"]').value.trim();
        const email   = document.querySelector('[name="email"]').value.trim();
        const address = document.querySelector('[name="address"]').value.trim();
        const country = document.querySelector('[name="country"]').value;

        if (!name || !phone || !address) {
            alert("Please fill required fields.");
            return;
        }

        /* =========================
           NOT LOGGED IN → OTP
        ========================== */
        if (!loggedIn) {

            const res = await fetch('/asm-crockery/api/auth/send-otp.php', {
                method: 'POST',
                body: new URLSearchParams({ phone })
            });

            const data = await res.json();

            if (!data.success) {
                alert(data.message || "Unable to send OTP");
                return;
            }

            const modalElement = document.getElementById('otpModal');

            if (typeof bootstrap === "undefined") {
                alert("Bootstrap JS not loaded");
                return;
            }

            const modal = new bootstrap.Modal(modalElement);
            modal.show();
            return;
        }

        /* =========================
           LOGGED IN → RAZORPAY
        ========================== */

        checkoutBtn.disabled = true;
        checkoutBtn.innerText = "Processing...";

        const fd = new FormData();
        fd.append('name', name);
        fd.append('phone', phone);
        fd.append('email', email);
        fd.append('address', address);
        fd.append('country', country);

        let order;
        try {
            const res = await fetch('/asm-crockery/api/checkout/create-order.php', {
                method: 'POST',
                body: fd
            });
            const raw = await res.text();
            try {
                order = JSON.parse(raw);
            } catch (parseErr) {
                throw new Error(raw || 'Unable to create order. Please try again.');
            }
        } catch (err) {
            alert(err.message || "Unable to create order. Please try again.");
            resetCheckoutButton();
            return;
        }

        if (!order.success) {
            alert(order.message || "Unable to create order");
            resetCheckoutButton();
            return;
        }

        const rzp = new Razorpay({
            key: "<?php echo RAZORPAY_KEY_ID; ?>",
            amount: order.amount,
            currency: order.currency,
            order_id: order.order_id,
            handler: async function (response) {

                const fd2 = new FormData();
                fd2.append('razorpay_payment_id', response.razorpay_payment_id);
                fd2.append('razorpay_order_id', response.razorpay_order_id);
                fd2.append('razorpay_signature', response.razorpay_signature);

                let result;
                try {
                    const res2 = await fetch('/asm-crockery/api/checkout/process.php', {
                        method: 'POST',
                        body: fd2
                    });
                    result = await res2.json();
                } catch (err) {
                    alert("Payment verification failed. Please try again.");
                    resetCheckoutButton();
                    return;
                }

                if (result.success) {
                    const orderNo = encodeURIComponent(result.order_number || '');
                    window.location.href = '/asm-crockery/order-success.php?order=' + orderNo;
                } else {
                    alert(result.message || "Payment failed");
                    resetCheckoutButton();
                }
            },
            modal: {
                ondismiss: function () {
                    resetCheckoutButton();
                }
            }
        });

        rzp.on('payment.failed', function () {
            alert('Payment failed. Please try again.');
            resetCheckoutButton();
        });

        rzp.open();
    });


    /* =========================
       VERIFY OTP
    ========================== */

    const verifyOtpBtn = document.getElementById('verifyOtpBtn');

    if (verifyOtpBtn) {
        verifyOtpBtn.addEventListener('click', async function () {

            const fd = new FormData();
            fd.append('otp', document.getElementById('otpInput').value);
            fd.append('name', document.querySelector('[name="name"]').value);
            fd.append('phone', document.querySelector('[name="phone"]').value);
            fd.append('email', document.querySelector('[name="email"]').value);
            fd.append('address', document.querySelector('[name="address"]').value);
            fd.append('country', document.querySelector('[name="country"]').value);

            const res = await fetch('/asm-crockery/api/auth/verify-otp.php', {
                method: 'POST',
                body: fd
            });

            const data = await res.json();

            if (data.success) {
                location.reload();
            } else {
                alert(data.message || "Invalid OTP");
            }
        });
    }

});
</script>

<?php include 'includes/footer.php'; ?>
