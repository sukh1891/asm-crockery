<?php
session_start();
include '../config/db.php';
//include 'auth-check.php';

$error = "";

// Already logged in
if (!empty($_SESSION['admin_id'])) {
    header("Location: orders.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = "Please enter email and password.";
    } else {

        $email_safe = mysqli_real_escape_string($conn, $email);

        $q = mysqli_query($conn,
            "SELECT * FROM admins WHERE email='$email_safe' LIMIT 1"
        );

        if (mysqli_num_rows($q) === 1) {

            $admin = mysqli_fetch_assoc($q);

            if (password_verify($password, $admin['password'])) {
            
                // Session login
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_email'] = $admin['email'];
            
                // === REMEMBER ME TOKEN ===
                $token = bin2hex(random_bytes(32));
                $token_hash = hash('sha256', $token);
            
                mysqli_query($conn,
                    "UPDATE admins SET remember_token='$token_hash'
                     WHERE id='".$admin['id']."'"
                );
            
                // Cookie valid for 30 days
                setcookie(
                    'admin_remember',
                    $admin['id'] . ':' . $token,
                    time() + (30 * 24 * 60 * 60),
                    '/',
                    '',
                    isset($_SERVER['HTTPS']),
                    true
                );
            
                header("Location: orders.php");
                exit;
            } else {
                $error = "Invalid login credentials.";
            }

        } else {
            $error = "Invalid login credentials.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<title>Admin Login</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">

<div class="container mt-5" style="max-width:420px;">
    <h3 class="mb-3">Admin Login</h3>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">

        <div class="mb-3">
            <label>Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>

        <div class="mb-3">
            <label>Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>

        <button class="btn btn-primary w-100">Login</button>
    </form>

</div>

</body>
</html>
