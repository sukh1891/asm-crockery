<?php
// includes/mailer.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/keys.php';
require_once __DIR__ . '/functions.php'; // for convertToUSD etc, and DB

/**
 * sendOrderConfirmation
 * Sends HTML order confirmation to customer and admin.
 *
 * @param int $order_id
 * @return array ['success'=>bool, 'msg'=>string]
 */
function sendOrderConfirmation($order_id) {
    global $conn, $SMTP_HOST, $SMTP_USER, $SMTP_PASS, $SMTP_PORT, $SMTP_SECURE, $FROM_EMAIL, $FROM_NAME, $ADMIN_EMAIL;

    $order_id = intval($order_id);
    if (!$order_id) return ['success'=>false, 'msg'=>'Invalid order id'];

    // Fetch order
    $oq = mysqli_query($conn, "SELECT * FROM orders WHERE id='$order_id' LIMIT 1");
    if (mysqli_num_rows($oq) == 0) return ['success'=>false, 'msg'=>'Order not found'];

    $order = mysqli_fetch_assoc($oq);

    // Fetch items
    $items_q = mysqli_query($conn,
        "SELECT oi.*, p.title AS product_name
         FROM order_items oi
         LEFT JOIN products p ON oi.product_id = p.id
         WHERE oi.order_id='$order_id'"
    );

    // Build order items HTML
    $items_html = '';
    $grand_total = 0;
    while ($it = mysqli_fetch_assoc($items_q)) {
        $line_total = $it['qty'] * $it['price'];
        $grand_total += $line_total;

        // Variation label
        $variation_label = '-';
        if ($it['variation_id']) {
            $vq = mysqli_query($conn, "SELECT attributes_json FROM product_variations WHERE id='".intval($it['variation_id'])."' LIMIT 1");
            if (mysqli_num_rows($vq)) {
                $vr = mysqli_fetch_assoc($vq);
                $variation_label = htmlspecialchars(json_encode(json_decode($vr['attributes_json']), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
            }
        }

        $items_html .= "<tr>
            <td style='padding:8px;border:1px solid #eee;'>".htmlspecialchars($it['product_name'])."</td>
            <td style='padding:8px;border:1px solid #eee;'>".$variation_label."</td>
            <td style='padding:8px;border:1px solid #eee;text-align:center;'>".$it['qty']."</td>
            <td style='padding:8px;border:1px solid #eee;text-align:right;'>₹".number_format($it['price'],2)."</td>
            <td style='padding:8px;border:1px solid #eee;text-align:right;'>₹".number_format($line_total,2)."</td>
        </tr>";
    }

    $currency_symbol = ($order['currency'] == "INR") ? "₹" : "$";
    $display_total = $currency_symbol . number_format($order['amount'], 2);

    // Build HTML email
    $email_html = "
    <div style='font-family:Arial,Helvetica,sans-serif;line-height:1.4;color:#333;'>
      <h2 style='color:#333;'>Thank you for your order — ASM Crockery</h2>
      <p>Hi ".htmlspecialchars($order['name']).",</p>
      <p>We have received your order <strong>#{$order_id}</strong>. Below are the details:</p>

      <table style='width:100%;border-collapse:collapse;margin-top:16px;'>
        <thead>
          <tr>
            <th style='padding:8px;border:1px solid #eee;background:#f6f6f6;text-align:left;'>Product</th>
            <th style='padding:8px;border:1px solid #eee;background:#f6f6f6;text-align:left;'>Variation</th>
            <th style='padding:8px;border:1px solid #eee;background:#f6f6f6;text-align:center;'>Qty</th>
            <th style='padding:8px;border:1px solid #eee;background:#f6f6f6;text-align:right;'>Price</th>
            <th style='padding:8px;border:1px solid #eee;background:#f6f6f6;text-align:right;'>Total</th>
          </tr>
        </thead>
        <tbody>
          {$items_html}
          <tr>
            <td colspan='4' style='padding:8px;border:1px solid #eee;text-align:right;font-weight:bold;'>Grand Total</td>
            <td style='padding:8px;border:1px solid #eee;text-align:right;font-weight:bold;'>{$display_total}</td>
          </tr>
        </tbody>
      </table>

      <p style='margin-top:18px;'>
        <strong>Shipping to:</strong><br>
        ".nl2br(htmlspecialchars($order['address'])).", ".htmlspecialchars($order['city'])." - ".htmlspecialchars($order['zip'])."<br>
        Phone: ".htmlspecialchars($order['phone'])."
      </p>

      <p>If you have any queries, reply to this email or contact us at {$ADMIN_EMAIL}.</p>

      <p style='color:#888;margin-top:24px;font-size:13px;'>This is an automated message. Please do not reply to this address.</p>
    </div>
    ";

    // PHPMailer config & send
    $mail = new PHPMailer(true);
    try {
        //Server settings
        $mail->isSMTP();
        $mail->Host       = $SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = $SMTP_USER;
        $mail->Password   = $SMTP_PASS;
        $mail->SMTPSecure = $SMTP_SECURE;
        $mail->Port       = $SMTP_PORT;

        $mail->setFrom($FROM_EMAIL, $FROM_NAME);
        $mail->addAddress($order['email'], $order['name']);       // customer
        $mail->addBCC($ADMIN_EMAIL);                              // admin copy

        $mail->isHTML(true);
        $mail->Subject = "Order Confirmation #{$order_id} — ASM Crockery";
        $mail->Body    = $email_html;
        $mail->AltBody = "Thank you for your order #{$order_id}. Total: {$display_total}";

        $mail->send();
        return ['success'=>true,'msg'=>'Email sent'];
    } catch (Exception $e) {
        // Log error for debugging
        file_put_contents(__DIR__ . "/../logs/mail_error.log", date("c") . " Mailer Error: " . $mail->ErrorInfo . PHP_EOL, FILE_APPEND);
        return ['success'=>false,'msg'=>'Mail error: ' . $mail->ErrorInfo];
    }
}
