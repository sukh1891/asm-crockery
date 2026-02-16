<?php
include 'includes/header.php';
//include 'includes/functions.php';
$orderNo = $_GET['order'] ?? '';
/*
if (!isset($_GET['id']) || !intval($_GET['id'])) {
    echo "<h2>Invalid Order</h2>";
    include 'includes/footer.php';
    exit;
}

$order_id = intval($_GET['id']);

// Fetch order
$q = mysqli_query($conn, "SELECT * FROM orders WHERE id='$order_id' LIMIT 1");
if (mysqli_num_rows($q) == 0) {
    echo "<h2>Order not found</h2>";
    include 'includes/footer.php';
    exit;
}

$order = mysqli_fetch_assoc($q);
$currency = $order['currency'];
$amount = floatval($order['amount']);*/
?>

<style>
.success-container {
    width: 95%;
    max-width: 700px;
    margin: 40px auto;
    background: #fff;
    padding: 30px;
    border-radius: 12px;
    text-align: center;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.success-icon {
    font-size: 70px;
    color: #28a745;
    margin-bottom: 10px;
}
.success-title {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 10px;
}
.success-msg {
    font-size: 18px;
    color: #444;
    margin-bottom: 25px;
}
.order-box {
    background: #f7f7f7;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 25px;
}
.order-id {
    font-size: 20px;
    font-weight: 600;
    word-break: break-all;
}
.success-amount {
    font-size: 18px;
    margin-top: 10px;
    font-weight: 500;
}
.btn-shop {
    display: inline-block;
    background: #007bff;
    padding: 12px 24px;
    color: #fff;
    border-radius: 6px;
    text-decoration: none;
    font-size: 16px;
    transition: 0.2s;
}
.btn-shop:hover {
    background: #0056b3;
}
</style>
<h3>Order Placed Successfully!</h3>
<p>Your order number is <strong><?php echo htmlspecialchars($orderNo); ?></strong></p>

<div class="success-container">

    <div class="success-icon">✔</div>

    <div class="success-title">Thank You for Your Order!</div>

    <div class="success-msg">
        Your payment was successful and your order has been placed.
    </div>

    <a href="/" class="btn-shop">Continue Shopping</a>

</div>

<script>
function copyOrderId() {
    let id = document.getElementById("orderIdBox").innerText;
    navigator.clipboard.writeText(id);
    alert("Order ID copied!");
}
</script>

<?php include 'includes/footer.php'; ?>
