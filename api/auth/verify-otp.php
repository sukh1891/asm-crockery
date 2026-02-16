<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

$phone   = trim($_POST['phone'] ?? '');
$otp     = trim($_POST['otp'] ?? '');
$name    = trim($_POST['name'] ?? '');
$email   = trim($_POST['email'] ?? '');
$address = trim($_POST['address'] ?? '');
$country = trim($_POST['country'] ?? 'India');

if (!$phone || !$otp) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit;
}

// verify OTP
$q = mysqli_query($conn,
    "SELECT * FROM users
     WHERE phone='".mysqli_real_escape_string($conn,$phone)."'
     AND otp='".mysqli_real_escape_string($conn,$otp)."'
     LIMIT 1"
);

$user = mysqli_fetch_assoc($q);

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'Invalid OTP']);
    exit;
}

$userId = intval($user['id']);

// if new user, update profile
if (empty($user['name']) && $name) {
    mysqli_query($conn,"
        UPDATE users SET
            name='".mysqli_real_escape_string($conn,$name)."',
            email='".mysqli_real_escape_string($conn,$email)."',
            address='".mysqli_real_escape_string($conn,$address)."',
            country='".mysqli_real_escape_string($conn,$country)."'
        WHERE id='$userId'
    ");
}

// clear OTP
mysqli_query($conn, "UPDATE users SET otp=NULL WHERE id='$userId'");

// login
$_SESSION['user_id'] = $userId;

// merge cart
mergeGuestCartToUser($userId);

echo json_encode(['success' => true]);
