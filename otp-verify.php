<?php
session_start();
include 'config/db.php';
// Save checkout form data on first entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SESSION['checkout_data'])) {
    $_SESSION['checkout_data'] = [
        'name'    => $_POST['name'] ?? '',
        'phone'   => $_POST['phone'] ?? '',
        'email'   => $_POST['email'] ?? '',
        'address' => $_POST['address'] ?? '',
        'country' => $_POST['country'] ?? 'India'
    ];
}

$data = $_SESSION['checkout_data'] ?? [];
if (empty($data['phone']) && empty($data['email'])) {
    header("Location: /asm-crockery/checkout.php");
    exit;
}

/* ===== OTP generation ===== */
if (!isset($_SESSION['otp_code'])) {
    $otp = rand(1000,9999);
    $_SESSION['otp_code'] = $otp;
    $_SESSION['otp_time'] = time();
    $_SESSION['otp_resends'] = 0;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (isset($_POST['otp'])) {

        if ($_POST['otp'] == $_SESSION['otp_code']) {
    
            $_SESSION['otp_verified'] = true;
    
            $data = $_SESSION['checkout_data'];
    
            $name    = mysqli_real_escape_string($conn, $data['name']);
            $phone   = mysqli_real_escape_string($conn, $data['phone']);
            $email   = mysqli_real_escape_string($conn, $data['email']);
            $country = $data['country'];
    
            // Decide login identifier
            if ($country === 'India' && !empty($phone)) {
                $userQ = mysqli_query($conn,
                    "SELECT * FROM users WHERE phone='$phone' LIMIT 1"
                );
            } else {
                $userQ = mysqli_query($conn,
                    "SELECT * FROM users WHERE email='$email' LIMIT 1"
                );
            }
    
            if (mysqli_num_rows($userQ) > 0) {
                // Existing user → login
                $user = mysqli_fetch_assoc($userQ);
                $_SESSION['user_id'] = $user['id'];
    
            } else {
                // New user → create
                mysqli_query($conn,"
                    INSERT INTO users (name, phone, email, created_at)
                    VALUES ('$name','$phone','$email',NOW())
                ");
    
                $_SESSION['user_id'] = mysqli_insert_id($conn);
            }
    
            // Clean OTP data (important)
            unset($_SESSION['otp_code'], $_SESSION['otp_time'], $_SESSION['otp_resends']);
    
            header("Location: /asm-crockery/api/place-order.php");
            exit;
    
        } else {
            $error = "Invalid OTP";
        }
    }


    if (isset($_POST['resend'])) {
        $otp = rand(1000,9999);
        $_SESSION['otp_code'] = $otp;
        $_SESSION['otp_time'] = time();
        $_SESSION['otp_resends']++;
    }

    if (isset($_POST['skip'])) {

        $_SESSION['otp_skipped'] = true;
    
        $data = $_SESSION['checkout_data'];
    
        $phone = $data['phone'] ?? '';
        $email = $data['email'] ?? '';
    
        // Attempt soft user creation
        if (!empty($phone) || !empty($email)) {
    
            $check = !empty($phone)
                ? mysqli_query($conn,"SELECT id FROM users WHERE phone='$phone' LIMIT 1")
                : mysqli_query($conn,"SELECT id FROM users WHERE email='$email' LIMIT 1");
    
            if (mysqli_num_rows($check) > 0) {
                $u = mysqli_fetch_assoc($check);
                $_SESSION['user_id'] = $u['id'];
            } else {
                mysqli_query($conn,"
                    INSERT INTO users (name, phone, email, created_at)
                    VALUES (
                        '".mysqli_real_escape_string($conn,$data['name'])."',
                        '".mysqli_real_escape_string($conn,$phone)."',
                        '".mysqli_real_escape_string($conn,$email)."',
                        NOW()
                    )
                ");
                $_SESSION['user_id'] = mysqli_insert_id($conn);
            }
        }
    
        header("Location: /asm-crockery/api/place-order.php");
        exit;
    }
}
include 'includes/header.php';
?>
<div class="container mt-4">
<h2>OTP Verification</h2>
<div class="row">
<div class="col-md-6">
<p>OTP sent to your <?php echo ($data['country']==='India')?'mobile':'email'; ?></p>
<?php echo $otp; ?>
<?php if ($error): ?><p style="color:red"><?php echo $error; ?></p><?php endif; ?>

<form method="post">
<input name="otp" class="form-control mb-2" placeholder="Enter OTP" required>
<button class="btn btn-primary w-100">Verify OTP</button>
</form>

<?php if (time() - $_SESSION['otp_time'] > 30): ?>
<form method="post" class="mt-3">
<button name="resend" class="btn btn-secondary w-100">Resend OTP</button>
</form>
<?php endif; ?>
</div>
</div>
</div>
<?php if ($_SESSION['otp_resends'] >= 1 && time() - $_SESSION['otp_time'] > 30): ?>
<form method="post" class="mt-3">
<button name="skip" class="btn btn-link">Proceed without verification</button>
</form>
<?php endif; ?>
