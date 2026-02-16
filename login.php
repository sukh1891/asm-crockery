<?php
// login.php
include 'config/db.php';
include 'includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// If already logged in
if (getLoggedInUserId()) {
    header("Location: /user/dashboard.php");
    exit;
}

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');

    if (strlen($phone) < 10) {
        $msg = "Enter a valid mobile number";
    } else {
        // Rate limit rules
        $cooldown_seconds = 60;      // 1 minute between OTPs
        $daily_limit = 5;            // Max OTPs per day per phone
        
        // Check cooldown
        $cool_q = mysqli_query($conn,
            "SELECT created_at FROM otp_log
             WHERE phone='".mysqli_real_escape_string($conn,$phone)."'
             ORDER BY id DESC LIMIT 1"
        );
        
        if (mysqli_num_rows($cool_q)) {
            $last = mysqli_fetch_assoc($cool_q)['created_at'];
            $seconds_since = time() - strtotime($last);
        
            if ($seconds_since < $cooldown_seconds) {
                $wait = $cooldown_seconds - $seconds_since;
                $msg = "Please wait {$wait} seconds before requesting another OTP.";
                goto render;
            }
        }
        
        // Check daily limit
        $today = date("Y-m-d");
        $daily_q = mysqli_query($conn,
            "SELECT COUNT(*) AS c FROM otp_log
             WHERE phone='".mysqli_real_escape_string($conn,$phone)."'
             AND DATE(created_at) = '$today'"
        );
        $daily_count = mysqli_fetch_assoc($daily_q)['c'];
        
        if ($daily_count >= $daily_limit) {
            $msg = "OTP request limit reached. Try again tomorrow.";
            goto render;
        }
        
        // Generate OTP
        $otp = rand(100000, 999999);
        $expires = date("Y-m-d H:i:s", strtotime("+5 minutes"));
        
        mysqli_query($conn,
            "INSERT INTO otp_log (phone, otp, expires_at)
             VALUES ('".mysqli_real_escape_string($conn,$phone)."', '$otp', '$expires')"
        );
        
        $_SESSION['otp_phone'] = $phone;
        $_SESSION['otp_sent_at'] = time();
        
        // SEND OTP (SMS API hook)
        error_log("OTP for $phone is $otp");
        
        header("Location: otp-verify.php");
        exit;
        
        render:
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<div class="container">
    <h2>Login / Signup</h2>
    <p>Enter your mobile number to continue</p>

    <?php if ($msg): ?>
        <div class="alert alert-danger"><?php echo $msg; ?></div>
    <?php endif; ?>

    <form method="post">
        <input type="text" name="phone" placeholder="Mobile Number"
               class="form-control mb-3" required>
        <button class="btn btn-primary w-100">Send OTP</button>
    </form>
</div>

</body>
</html>
