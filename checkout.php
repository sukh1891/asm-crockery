<?php
require_once 'config/db.php';
require_once 'config/keys.php';
require_once 'includes/functions.php';
require_once 'includes/header.php';

$cartSummary = getCartSummary($conn);

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
    <option value="India">India</option>
</select>

</div>

<div class="col-md-6">
<h5>Order Summary</h5>

<table class="table">
<?php foreach ($cartSummary['items'] as $i): ?>
<tr>
<td><?php echo htmlspecialchars($i['title']); ?></td>
<td class="text-end">₹<?php echo number_format($i['qty']*$i['price_inr'],2); ?></td>
</tr>
<?php endforeach; ?>
<tr>
<th>Total</th>
<th class="text-end">₹<?php echo number_format($cartSummary['total'],2); ?></th>
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

    const loggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;

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

        const res = await fetch('/asm-crockery/api/checkout/create-order.php', {
            method: 'POST',
            body: fd
        });

        const order = await res.json();

        if (!order.success) {
            alert(order.message || "Unable to create order");
            checkoutBtn.disabled = false;
            checkoutBtn.innerText = "Proceed to Pay";
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

                const res2 = await fetch('/asm-crockery/api/checkout/process.php', {
                    method: 'POST',
                    body: fd2
                });

                const result = await res2.json();

                if (result.success) {
                    window.location.href = '/asm-crockery/order-success.php';
                } else {
                    alert(result.message || "Payment failed");
                    checkoutBtn.disabled = false;
                    checkoutBtn.innerText = "Proceed to Pay";
                }
            }
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