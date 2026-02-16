<?php
require '../config/db.php';
include 'header.php';

// Load DOMPDF
require '../vendor/autoload.php';

use Dompdf\Dompdf;

// Validate order ID
$order_id = intval($_GET['id']);

$q = mysqli_query($conn,
    "SELECT * FROM orders WHERE id='$order_id' LIMIT 1"
);
if (mysqli_num_rows($q) == 0) {
    die("Invalid Order");
}

$order = mysqli_fetch_assoc($q);

// Fetch items
$items_q = mysqli_query($conn,
    "SELECT oi.*, p.title AS product_name
     FROM order_items oi
     LEFT JOIN products p ON oi.product_id = p.id
     WHERE oi.order_id='$order_id'"
);

// Convert currency
$currency_symbol = ($order['currency']=="INR" ? "₹" : "$");

// Prepare HTML
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Invoice #<?php echo $order_id; ?></title>

<style>
body { font-family: DejaVu Sans, sans-serif; }
.invoice-box {
    max-width: 800px;
    padding: 20px;
    font-size: 14px;
    line-height: 20px;
    color: #000;
}
table { width: 100%; border-collapse: collapse; }
table td, table th { padding: 8px; border: 1px solid #ddd; }
.heading { background: #f0f0f0; font-weight: bold; }
.title { font-size: 24px; margin-bottom: 20px; }
.text-right { text-align: right; }
.text-center { text-align: center; }
</style>
</head>

<body>
<div class="invoice-box">

    <h2 class="title">INVOICE</h2>

    <table>
        <tr>
            <td>
                <strong>Seller:</strong><br>
                ASM Crockery<br>
                Zirakpur, Punjab<br>
                India<br>
            </td>

            <td>
                <strong>Invoice #:</strong> <?php echo $order_id; ?><br>
                <strong>Date:</strong> <?php echo $order['created_at']; ?><br>
                <strong>Payment:</strong> <?php echo strtoupper($order['status']); ?><br>
                <strong>Method:</strong> <?php echo $order['payment_id'] ? "Online" : "N/A"; ?><br>
            </td>
        </tr>
    </table>

    <br>

    <strong>Bill To:</strong><br>
    <?php echo $order['name']; ?><br>
    <?php echo $order['phone']; ?><br>
    <?php echo $order['email']; ?><br>
    <?php echo $order['address']; ?>, <?php echo $order['city']; ?> - <?php echo $order['zip']; ?>
    <br><br>

    <table>
        <tr class="heading">
            <th>Product</th>
            <th>Variation</th>
            <th>Qty</th>
            <th>Price</th>
            <th>Total</th>
        </tr>

        <?php
        $grand_total = 0;
        while ($it = mysqli_fetch_assoc($items_q)):
            $total = $it['qty'] * $it['price'];
            $grand_total += $total;
        ?>
        <tr>
            <td><?php echo $it['product_name']; ?></td>
            <td><?php echo $it['variation_id'] ?: "-"; ?></td>
            <td><?php echo $it['qty']; ?></td>
            <td><?php echo $currency_symbol . number_format($it['price'], 2); ?></td>
            <td><?php echo $currency_symbol . number_format($total, 2); ?></td>
        </tr>
        <?php endwhile; ?>

        <tr class="heading">
            <td colspan="4" class="text-right">Grand Total</td>
            <td><?php echo $currency_symbol . number_format($grand_total, 2); ?></td>
        </tr>
    </table>

    <br><br>
    <div class="text-center">
        Thank you for shopping with ASM Crockery!
    </div>

</div>
</body>
</html>
<?php
$html = ob_get_clean();

// Generate PDF
$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Download
$dompdf->stream("invoice-$order_id.pdf", ["Attachment" => true]);
exit;
