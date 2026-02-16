<?php
if (session_status()==PHP_SESSION_NONE) session_start();
$_SESSION['applied_coupon'] = $_POST['code'];
echo "ok";
