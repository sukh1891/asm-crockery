<?php
require_once '../../config/db.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

$phone = trim($_POST['phone'] ?? '');

if (!$phone || strlen($phone) < 10) {
    echo json_encode(['success' => false, 'message' => 'Invalid phone']);
    exit;
}

// generate 6-digit OTP
$otp = rand(100000, 999999);

// check if user exists
$q = mysqli_query($conn, "SELECT id FROM users WHERE phone='".mysqli_real_escape_string($conn,$phone)."' LIMIT 1");

if ($u = mysqli_fetch_assoc($q)) {
    // update OTP
    mysqli_query($conn,
        "UPDATE users SET otp='$otp' WHERE id='".intval($u['id'])."'"
    );
} else {
    // create temp user with OTP
    mysqli_query($conn,
        "INSERT INTO users (phone, otp) VALUES ('$phone','$otp')"
    );
}

$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => "https://api.authkey.io/request?authkey=2fad3772b741b2e6&mobile=" . $phone . "&country_code=91&sid=15645&company=ASM%20Crockery%20account&otp=" . $otp,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => "GET",
));
curl_exec($curl);
curl_close($curl);

echo json_encode(['success' => true]);
