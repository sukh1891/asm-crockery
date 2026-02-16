<?php
include '../includes/header.php';
include '../includes/functions.php';
include 'auth-check.php';

$user_id = intval($_SESSION['user_id']);

// basic user
$u = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM users WHERE id='$user_id'"));

// quick stats
$total_orders = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders WHERE user_id='$user_id'"))['c'] ?? 0;
$total_spent  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) AS s FROM orders WHERE user_id='$user_id' AND (status='paid' OR status='delivered')"))['s'] ?? 0;

// recent orders (5)
$recent_q = mysqli_query($conn, "SELECT * FROM orders WHERE user_id='$user_id' ORDER BY id DESC LIMIT 5");

?>

<div class="container">
  <h2>My Account</h2>

  <div class="row">
    <div class="col-md-4">
      <div class="card p-3 mb-3">
        <h5><?php echo htmlspecialchars($u['name'] ?: ''); ?></h5>
        <p><?php echo htmlspecialchars($u['phone']); ?><br><?php echo htmlspecialchars($u['email']); ?></p>
        <a href="/user/addresses.php" class="btn btn-sm btn-outline-primary">Manage Addresses</a>
      </div>

      <div class="card p-3">
        <h6>Quick Stats</h6>
        <p>Total Orders: <strong><?php echo $total_orders; ?></strong></p>
        <p>Total Spent: <strong>₹<?php echo number_format($total_spent,2); ?></strong></p>
      </div>
    </div>

    <div class="col-md-8">
      <div class="card p-3">
        <h5>Recent Orders</h5>
        <?php if (mysqli_num_rows($recent_q) == 0): ?>
          <p>No orders yet. <a href="/">Start shopping</a></p>
        <?php else: ?>
          <table class="table">
            <thead><tr><th>Order</th><th>Date</th><th>Total</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <?php while ($o = mysqli_fetch_assoc($recent_q)): ?>
                <tr>
                  <td>#<?php echo $o['id']; ?></td>
                  <td><?php echo $o['created_at']; ?></td>
                  <td>₹<?php echo number_format($o['amount'],2); ?></td>
                  <td><?php echo ucfirst($o['status']); ?></td>
                  <td><a href="/user/order-details.php?id=<?php echo $o['id']; ?>" class="btn btn-sm btn-primary">View</a></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
          <a href="/user/orders.php" class="btn btn-sm btn-outline-secondary">View All Orders</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
