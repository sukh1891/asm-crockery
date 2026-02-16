<?php
// File: /api/webhooks/paypal-webhook.php

include '../../config/db.php';
include '../../config/keys.php';

// PayPal sends JSON POST
$payload = file_get_contents('php://input');
$data = json_decode($payload, true);

$headers = getallheaders();

// Required PayPal headers
$transmission_id  = $headers['Paypal-Transmission-Id'] ?? null;
$transmission_sig = $headers['Paypal-Transmission-Sig'] ?? null;
$transmission_time = $headers['Paypal-Transmission-Time'] ?? null;
$cert_url = $headers['Paypal-Cert-Url'] ?? null;
$auth_algo = $headers['Paypal-Auth-Algo'] ?? null;
$webhook_id = $PAYPAL_WEBHOOK_ID;

// -------------------------------------------------------------
// STEP 1: Verify webhook signature using PayPal API
// -------------------------------------------------------------

$verify_url = "https://api-m.paypal.com/v1/notifications/verify-webhook-signature";

// For sandbox testing:
/// $verify_url = "https://api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature";

// Get PayPal Access Token
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, "https://api-m.paypal.com/v1/oauth2/token");
curl_setopt($ch, CURLOPT_USERPWD, $PAYPAL_CLIENT_ID . ":" . $PAYPAL_SECRET);
curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$res = curl_exec($ch);
curl_close($ch);

$token = json_decode($res, true)['access_token'] ?? null;

if (!$token) {
    http_response_code(400);
    echo "Invalid PayPal token";
    exit;
}

// Build verification request
$verify_payload = [
    "transmission_id"    => $transmission_id,
    "transmission_time"  => $transmission_time,
    "cert_url"           => $cert_url,
    "auth_algo"          => $auth_algo,
    "transmission_sig"   => $transmission_sig,
    "webhook_id"         => $webhook_id,
    "webhook_event"      => $data
];

$ch = curl_init($verify_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "Authorization: Bearer ".$token
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($verify_payload));

$verify_res = curl_exec($ch);
curl_close($ch);

$verify_data = json_decode($verify_res, true);

if (($verify_data['verification_status'] ?? '') !== "SUCCESS") {
    http_response_code(401);
    echo "Signature verification failed";
    exit;
}

// -------------------------------------------------------------
// STEP 2: Log webhook (optional)
// -------------------------------------------------------------

file_put_contents(__DIR__ . "/paypal_webhook_log.txt",
    "=== ".date("Y-m-d H:i:s")." ===\n".$payload."\n\n",
    FILE_APPEND
);

// -------------------------------------------------------------
// STEP 3: Handle PayPal events
// -------------------------------------------------------------

$event = $data['event_type'];

// Capture info
$paypal_payment_id = $data['resource']['id'] ?? null;

switch ($event) {

    // -------------------------------
    // PAYMENT COMPLETED
    // -------------------------------
    case "PAYMENT.CAPTURE.COMPLETED":

        mysqli_query($conn,
            "UPDATE orders SET status='paid'
             WHERE payment_id='".mysqli_real_escape_string($conn, $paypal_payment_id)."'"
        );

        http_response_code(200);
        echo "Payment completed updated";
        break;


    // -------------------------------
    // PAYMENT FAILED / DENIED
    // -------------------------------
    case "PAYMENT.CAPTURE.DENIED":

        mysqli_query($conn,
            "UPDATE orders SET status='failed'
             WHERE payment_id='".mysqli_real_escape_string($conn, $paypal_payment_id)."'"
        );

        http_response_code(200);
        echo "Payment denied updated";
        break;


    // -------------------------------
    // REFUND PROCESSED
    // -------------------------------
    case "PAYMENT.CAPTURE.REFUNDED":

        $refund_amount = $data['resource']['amount']['value'];

        mysqli_query($conn,
            "UPDATE orders SET refund_amount='$refund_amount', status='refunded'
             WHERE payment_id='".mysqli_real_escape_string($conn, $paypal_payment_id)."'"
        );

        http_response_code(200);
        echo "Refund processed";
        break;


    default:

        file_put_contents(
            __DIR__ . "/paypal_webhook_log.txt",
            "Unhandled event: ".$event."\n",
            FILE_APPEND
        );

        http_response_code(200);
        echo "Unhandled event received";
        break;
}

exit;
?>
