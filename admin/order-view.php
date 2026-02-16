<?php
include '../config/db.php';
include 'header.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header("Location: orders.php");
    exit;
}

/* ===== UPDATE STATUS / TRACKING ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $tracking = mysqli_real_escape_string($conn, $_POST['tracking_id']);

    mysqli_query($conn,"
        UPDATE orders
        SET status='$status',
            tracking_id='$tracking'
        WHERE id='$id'
    ");
}

$orderQ = mysqli_query($conn,"SELECT * FROM orders WHERE id='$id'");
$order = mysqli_fetch_assoc($orderQ);

if (!$order) {
    echo "<p>Order not found.</p>";
    exit;
}

$items = mysqli_query($conn,"
    SELECT 
        oi.*,
        p.title AS product_title,
        pv.attributes_json AS variation_name
    FROM order_items oi
    LEFT JOIN products p 
        ON p.id = oi.product_id
    LEFT JOIN product_variations pv 
        ON pv.id = oi.variation_id
    WHERE oi.order_id='$id'
");
?>

<div class="container mt-4">
<h2>Order Details</h2>

<form method="post" class="card mb-3">
<div class="card-body">

<p><strong>Order No:</strong> <?php echo $order['order_number']; ?></p>
<p><strong>Name:</strong> <?php echo htmlspecialchars($order['name']); ?></p>
<p><strong>Phone:</strong> <?php echo htmlspecialchars($order['phone']); ?></p>
<p><strong>Email:</strong> <?php echo htmlspecialchars($order['email']); ?></p>
<p><strong>Country:</strong> <?php echo htmlspecialchars($order['country']); ?></p>
<p><strong>Address:</strong><br><?php echo nl2br(htmlspecialchars($order['address'])); ?></p>

<div class="row mt-3">
    <div class="col-md-4">
        <label>Status</label>
        <select name="status" class="form-select">
            <option value="pending" <?php if($order['status']=='pending') echo 'selected'; ?>>Pending</option>
            <option value="paid" <?php if($order['status']=='paid') echo 'selected'; ?>>Paid</option>
            <option value="shipped" <?php if($order['status']=='shipped') echo 'selected'; ?>>Shipped</option>
            <option value="cancelled" <?php if($order['status']=='cancelled') echo 'selected'; ?>>Cancelled</option>
        </select>
    </div>

    <div class="col-md-6">
        <label>Shipment Tracking ID</label>
        <input type="text" name="tracking_id"
               value="<?php echo htmlspecialchars($order['tracking_id']); ?>"
               class="form-control"
               placeholder="Courier Tracking / AWB">
    </div>

    <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-success w-100">Update</button>
    </div>
</div>

</div>
</form>

<h5>Order Items</h5>

<table class="table table-bordered">
<thead>
<tr>
    <th>Product</th>
    <th>Qty</th>
    <th>Price</th>
    <th>Total</th>
</tr>
</thead>

<tbody>
<?php while ($it = mysqli_fetch_assoc($items)): ?>
<tr>
    <td>
        <?php echo htmlspecialchars($it['product_title'] ?? 'Product Deleted'); ?>
    
        <?php if (!empty($it['variation_name'])): ?>
            <br>
            <small class="text-muted">
                Variation: <?php echo htmlspecialchars($it['variation_name']); ?>
            </small>
        <?php endif; ?>
    </td>
    <td><?php echo $it['qty']; ?></td>
    <td>₹<?php echo number_format($it['price'],2); ?></td>
    <td>₹<?php echo number_format($it['price'] * $it['qty'],2); ?></td>
</tr>
<?php endwhile; ?>
</tbody>
</table>

<div class="card mt-3">
<div class="card-body">
<p><strong>Product Total:</strong> ₹<?php echo number_format($order['total_amount'],2); ?></p>
<p><strong>Shipping:</strong> ₹<?php echo number_format($order['shipping_amount'],2); ?></p>
<p><strong>Final Amount:</strong>
<strong>₹<?php echo number_format($order['amount'],2); ?></strong></p>
</div>
</div>

<a href="orders.php" class="btn btn-secondary mt-3">Back to Orders</a>
</div>

<?php include 'footer.php'; ?>