<?php
include '../config/db.php';
include 'header.php';

// ---- SUMMARY CARDS ----

// Total Orders
$total_orders = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS c FROM orders"
))['c'];

// Total Revenue
$total_revenue = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT SUM(amount) AS t FROM orders WHERE status='paid' OR status='delivered'"
))['t'] ?? 0;

// Today's Revenue
$today = date("Y-m-d");
$today_revenue = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT SUM(amount) AS t FROM orders
     WHERE DATE(created_at) = '$today'
     AND (status='paid' OR status='delivered')"
))['t'] ?? 0;

// Last 30 Days
$last30_revenue = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT SUM(amount) AS t FROM orders
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     AND (status='paid' OR status='delivered')"
))['t'] ?? 0;

// Total Customers (distinct phones)
$total_customers = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(DISTINCT phone) AS c FROM orders"
))['c'];


// ---- MONTHLY SALES DATA ----
$months = [];
$revenues = [];

$monthly_q = mysqli_query($conn,
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, SUM(amount) AS total
     FROM orders
     WHERE (status='paid' OR status='delivered')
     GROUP BY ym
     ORDER BY ym ASC
     LIMIT 12"
);

while ($m = mysqli_fetch_assoc($monthly_q)) {
    $months[] = $m['ym'];
    $revenues[] = $m['total'];
}


// ---- LATEST ORDERS ----
$latest_orders_q = mysqli_query($conn,
    "SELECT * FROM orders ORDER BY id DESC LIMIT 10"
);


// ---- LOW STOCK PRODUCTS ----
$low_stock_q = mysqli_query($conn,
    "SELECT id, title, stock FROM products WHERE stock <= 5 ORDER BY stock ASC"
);

?>

    <h2 class="mb-4">Dashboard Overview</h2>

    <!-- SUMMARY CARDS -->
    <div class="row g-3">

        <div class="col-md-3">
            <div class="card p-3 shadow-sm">
                <h5>Total Orders</h5>
                <h2><?php echo $total_orders; ?></h2>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3 shadow-sm">
                <h5>Total Revenue</h5>
                <h2>₹<?php echo number_format($total_revenue,2); ?></h2>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3 shadow-sm">
                <h5>Today's Revenue</h5>
                <h2>₹<?php echo number_format($today_revenue,2); ?></h2>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card p-3 shadow-sm">
                <h5>Last 30 Days</h5>
                <h2>₹<?php echo number_format($last30_revenue,2); ?></h2>
            </div>
        </div>

    </div>

    <div class="row g-3 mt-4">

        <!-- Revenue Chart -->
        <div class="col-md-8">
            <div class="card p-3 shadow-sm">
                <h5>Revenue (Last 12 Months)</h5>
                <canvas id="revenueChart" height="120"></canvas>
            </div>
        </div>

        <!-- Customers -->
        <div class="col-md-4">
            <div class="card p-3 shadow-sm">
                <h5>Total Customers</h5>
                <h2><?php echo $total_customers; ?></h2>

                <h6 class="mt-4">Low Stock Alerts</h6>
                <ul class="list-group">
                    <?php while($ls = mysqli_fetch_assoc($low_stock_q)): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <?php echo $ls['title']; ?>
                        <span class="badge bg-danger"><?php echo $ls['stock']; ?></span>
                    </li>
                    <?php endwhile; ?>
                </ul>
            </div>
        </div>

    </div>

    <!-- Latest Orders -->
    <div class="card p-3 mt-4 shadow-sm">
        <h5>Latest Orders</h5>

        <table class="table table-bordered mt-2">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Payment ID</th>
                    <th>Date</th>
                    <th>View</th>
                </tr>
            </thead>

            <tbody>
                <?php while($o = mysqli_fetch_assoc($latest_orders_q)): ?>
                <tr>
                    <td>#<?php echo $o['id']; ?></td>
                    <td><?php echo $o['name']; ?></td>
                    <td>₹<?php echo number_format($o['amount'],2); ?></td>
                    <td><span class="badge bg-info"><?php echo $o['status']; ?></span></td>
                    <td><?php echo $o['payment_id']; ?></td>
                    <td><?php echo $o['created_at']; ?></td>
                    <td>
                        <a href="order-view.php?id=<?php echo $o['id']; ?>"
                           class="btn btn-dark btn-sm">View</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

    </div>

<script>
// MONTHLY REVENUE CHART
const ctx = document.getElementById('revenueChart').getContext('2d');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($months); ?>,
        datasets: [{
            label: 'Revenue (₹)',
            data: <?php echo json_encode($revenues); ?>,
            borderColor: '#007bff',
            borderWidth: 2,
            fill: false,
            tension: 0.3
        }]
    }
});
</script>

<?php include 'footer.php'; ?>