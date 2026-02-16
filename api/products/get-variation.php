<?php
include '../../config/db.php';
include '../../includes/functions.php';

$input = json_decode(file_get_contents("php://input"), true);

$product_id = intval($input['product_id']);
$attributes = $input['attributes'];

$attributes_json = json_encode($attributes);

// Find variation
$q = mysqli_query($conn,
    "SELECT * FROM product_variations
     WHERE product_id='$product_id'
     AND attributes_json = '$attributes_json'"
);

if (mysqli_num_rows($q) == 0) {
    echo json_encode(["success" => false, "msg" => "Variation not found"]);
    exit;
}

$var = mysqli_fetch_assoc($q);

$country = getUserCountry();
$use_usd = ($country !== "IN");

$price = $use_usd
    ? convertToUSD($var['price_inr'])
    : $var['price_inr'];

echo json_encode([
    "success" => true,
    "variation_id" => $var['id'],
    "price" => $use_usd ? "$".$price : "₹".$price,
    "stock" => $var['stock']
]);
