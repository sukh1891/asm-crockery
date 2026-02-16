<?php
include '../config/db.php';
include 'header.php';

$search = mysqli_real_escape_string($conn, $_GET['search'] ?? '');
$status = mysqli_real_escape_string($conn, $_GET['status'] ?? '');

$where = "WHERE 1";

if ($search) {
    $where .= " AND (
        order_number LIKE '%$search%' OR
        name LIKE '%$search%' OR
        phone LIKE '%$search%'
    )";
}

if ($status) {
    $where .= " AND status='$status'";
}

$orders = mysqli_query($conn,"
    SELECT id, order_number, name, phone, country,
           total_amount, shipping_amount, amount,
           status, created_at
    FROM orders
    $where
    ORDER BY id DESC
");
?>

<div class="container mt-4">
<h2>Orders</h2>

<form method="get" class="row g-2 mb-3">
    <div class="col-md-4">
        <input type="text" name="search"
               value="<?php echo htmlspecialchars($search); ?>"
               class="form-control"
               placeholder="Search Order No / Name / Phone">
    </div>

    <div class="col-md-3">
        <select name="status" class="form-select">
            <option value="">All Status</option>
            <option value="pending" <?php if($status=='pending') echo 'selected'; ?>>Pending</option>
            <option value="paid" <?php if($status=='paid') echo 'selected'; ?>>Paid</option>
            <option value="shipped" <?php if($status=='shipped') echo 'selected'; ?>>Shipped</option>
            <option value="cancelled" <?php if($status=='cancelled') echo 'selected'; ?>>Cancelled</option>
        </select>
    </div>

    <div class="col-md-2">
        <button class="btn btn-primary w-100">Filter</button>
    </div>

    <div class="col-md-2">
        <a href="orders.php" class="btn btn-secondary w-100">Reset</a>
    </div>
</form>

<table class="table table-bordered table-striped">
<thead>
<tr>
    <th>ID</th>
    <th>Order No</th>
    <th>Name</th>
    <th>Phone</th>
    <th>Amount</th>
    <th>Status</th>
    <th>Date</th>
    <th>Action</th>
</tr>
</thead>

<tbody>
<?php while ($o = mysqli_fetch_assoc($orders)): ?>
<tr>
    <td><?php echo $o['id']; ?></td>
    <td><strong><?php echo $o['order_number']; ?></strong></td>
    <td><?php echo htmlspecialchars($o['name']); ?></td>
    <td><?php echo htmlspecialchars($o['phone']); ?></td>
    <td><strong>₹<?php echo number_format($o['amount'],2); ?></strong></td>
    <td>
        <span class="badge bg-<?php
            echo $o['status']=='paid' ? 'success' :
                 ($o['status']=='shipped' ? 'info' :
                 ($o['status']=='cancelled' ? 'danger' : 'secondary'));
        ?>">
            <?php echo ucfirst($o['status']); ?>
        </span>
    </td>
    <td><?php echo date('d M Y, h:i A', strtotime($o['created_at'])); ?></td>
    <td>
        <a href="order-view.php?id=<?php echo $o['id']; ?>"
           class="btn btn-sm btn-primary">View</a>
    </td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
</div>

<?php include 'footer.php'; ?>
