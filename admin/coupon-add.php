<?php
include '../config/db.php';
include 'header.php';

if ($_SERVER['REQUEST_METHOD']=='POST') {
    $code = strtoupper(trim($_POST['code']));
    $type = $_POST['type'];
    $amount = floatval($_POST['amount']);
    $min_order = floatval($_POST['min_order']);
    $usage_limit = $_POST['usage_limit'] ?: "NULL";
    $user_limit = $_POST['user_limit'] ?: "NULL";
    $start = $_POST['start_date'] ?: "NULL";
    $end = $_POST['end_date'] ?: "NULL";
    $status = intval($_POST['status']);

    mysqli_query($conn, "INSERT INTO coupons 
        (code,type,amount,min_order,usage_limit,user_limit,start_date,end_date,status)
        VALUES
        ('$code','$type','$amount','$min_order',$usage_limit,$user_limit,".
        ($start=='NULL'?'NULL':"'$start'").",".
        ($end=='NULL'?'NULL':"'$end'").",$status)");

    header("Location: coupons.php?added=1");
    exit;
}
?>
    <h2>Add Coupon</h2>

    <form method="post">

        <div class="mb-3"><label>Coupon Code</label>
            <input name="code" class="form-control" required>
        </div>

        <div class="mb-3"><label>Type</label>
            <select name="type" class="form-select">
                <option value="fixed">Fixed (₹)</option>
                <option value="percent">Percent (%)</option>
            </select>
        </div>

        <div class="mb-3"><label>Amount</label>
            <input name="amount" class="form-control" required>
        </div>

        <div class="mb-3"><label>Minimum Order</label>
            <input name="min_order" class="form-control">
        </div>

        <div class="mb-3"><label>Usage Limit (optional)</label>
            <input name="usage_limit" class="form-control">
        </div>

        <div class="mb-3"><label>Per User Limit (optional)</label>
            <input name="user_limit" class="form-control">
        </div>

        <div class="mb-3"><label>Start Date</label>
            <input type="date" name="start_date" class="form-control">
        </div>

        <div class="mb-3"><label>End Date</label>
            <input type="date" name="end_date" class="form-control">
        </div>

        <div class="mb-3"><label>Status</label>
            <select name="status" class="form-select">
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
        </div>

        <button class="btn btn-primary">Save</button>
    </form>

<?php include 'footer.php'; ?>