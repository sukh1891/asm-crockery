<?php
// login.php
include 'config/db.php';
include 'includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// If already logged in
$redirect = trim($_GET['redirect'] ?? '/asm-crockery/user/dashboard.php');
if ($redirect === '' || $redirect[0] !== '/' || strpos($redirect, 'http') === 0) {
    $redirect = '/asm-crockery/user/dashboard.php';
}

if (getLoggedInUserId()) {
    header("Location: $redirect");
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<div class="container" style="max-width:500px; margin-top:40px;">
    <h2>Login / Signup</h2>
    <p>Enter your mobile number to continue</p>
    <div id="loginMessage" class="alert d-none"></div>
    <form id="phoneForm">
        <input type="text" id="phone" placeholder="Mobile Number" maxlength="10" class="form-control mb-3" required>
        <button class="btn btn-primary w-100" id="sendOtpBtn">Send OTP</button>
    </form>

    <form id="otpForm" class="mt-3 d-none">
        <input type="text" id="otp" placeholder="Enter OTP" maxlength="6" class="form-control mb-3" required>
        <button class="btn btn-success w-100">Verify & Login</button>
    </form>
</div>
<script>
const redirectUrl = <?php echo json_encode($redirect); ?>;
const phoneForm = document.getElementById('phoneForm');
const otpForm = document.getElementById('otpForm');
const phoneInput = document.getElementById('phone');
const otpInput = document.getElementById('otp');
const msgBox = document.getElementById('loginMessage');

function showMessage(message, isError = true) {
    msgBox.classList.remove('d-none', 'alert-danger', 'alert-success');
    msgBox.classList.add(isError ? 'alert-danger' : 'alert-success');
    msgBox.textContent = message;
}

phoneForm.addEventListener('submit', async function (e) {
    e.preventDefault();

    const phone = phoneInput.value.trim();
    if (!/^\d{10}$/.test(phone)) {
        showMessage('Enter a valid 10-digit mobile number');
        return;
    }

    const res = await fetch('/asm-crockery/api/auth/send-otp.php', {
        method: 'POST',
        body: new URLSearchParams({ phone })
    });

    const data = await res.json();

    if (!data.success) {
        showMessage(data.message || 'Unable to send OTP');
        return;
    }

    showMessage('OTP sent successfully', false);
    otpForm.classList.remove('d-none');
    otpInput.focus();
});

otpForm.addEventListener('submit', async function (e) {
    e.preventDefault();

    const fd = new FormData();
    fd.append('phone', phoneInput.value.trim());
    fd.append('otp', otpInput.value.trim());

    const res = await fetch('/asm-crockery/api/auth/verify-otp.php', {
        method: 'POST',
        body: fd
    });

    const data = await res.json();

    if (!data.success) {
        showMessage(data.message || 'Invalid OTP');
        return;
    }

    window.location.href = redirectUrl;
});
</script>
</body>
</html>
