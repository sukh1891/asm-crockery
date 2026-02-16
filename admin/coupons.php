<?php
include '../config/db.php';
include 'header.php';
?>

    <div class="d-flex justify-content-between mb-3">
        <h2>Coupons</h2>
        <a href="coupon-add.php" class="btn btn-primary">Add Coupon</a>
    </div>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Code</th>
                <th>Type</th>
                <th>Amount</th>
                <th>Used</th>
                <th>Limit</th>
                <th>Validity</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php
        $q = mysqli_query($conn, "SELECT * FROM coupons ORDER BY id DESC");
        while ($c = mysqli_fetch_assoc($q)):
        ?>
            <tr>
                <td><?php echo $c['code']; ?></td>
                <td><?php echo ucfirst($c['type']); ?></td>
                <td><?php echo $c['type']=='percent' ? $c['amount'].'%' : '₹'.$c['amount']; ?></td>
                <td><?php echo $c['used_count']; ?></td>
                <td><?php echo $c['usage_limit'] ?: '-'; ?></td>
                <td><?php echo $c['start_date']." to ".$c['end_date']; ?></td>
                <td><?php echo $c['status'] ? "Active" : "Inactive"; ?></td>
                <td>
                    <a href="coupon-edit.php?id=<?php echo $c['id']; ?>" class="btn btn-sm btn-dark">Edit</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>

<?php include 'footer.php'; ?>