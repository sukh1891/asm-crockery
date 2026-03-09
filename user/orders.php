<?php
include '../includes/header.php';
include '../includes/functions.php';
include 'auth-check.php';

$user_id = intval($_SESSION['user_id']);

$search = mysqli_real_escape_string($conn, $_GET['q'] ?? '');
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 10;
$offset = ($page-1)*$per_page;

$where = "WHERE user_id='$user_id'";
if ($search !== '') {
    $where .= " AND (id LIKE '%$search%' OR payment_id LIKE '%$search%')";
}

// total count
$total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders $where"))['c'] ?? 0;
$pages = ceil($total / $per_page);

$q = mysqli_query($conn, "SELECT * FROM orders $where ORDER BY id DESC LIMIT $per_page OFFSET $offset");
?>

<div class="container">
  <h2>My Orders</h2>

  <form class="mb-3">
    <div class="input-group">
      <input name="q" value="<?php echo htmlspecialchars($search); ?>" class="form-control" placeholder="Search order id / payment id">
      <button class="btn btn-primary">Search</button>
    </div>
  </form>

  <?php if ($total == 0): ?>
    <p>No orders found.</p>
  <?php else: ?>
    <table class="table">
      <thead><tr><th>#</th><th>Date</th><th>Total</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
        <?php while ($o = mysqli_fetch_assoc($q)): ?>
          <tr>
            <td>#<?php echo $o['id']; ?></td>
            <td><?php echo $o['created_at']; ?></td>
            <td>₹<?php echo number_format($o['amount'],2); ?></td>
            <td><?php echo ucfirst($o['status']); ?></td>
            <td>
              <a href="/user/order-details.php?id=<?php echo $o['id']; ?>" class="btn btn-sm btn-primary">View</a>
            </td>
          </tr>
        <?php endwhile; ?>
      </tbody>
    </table>

    <!-- pagination -->
    <nav>
      <ul class="pagination">
        <?php for ($i=1;$i<=$pages;$i++): ?>
          <li class="page-item <?php if($i==$page) echo 'active'; ?>">
            <a class="page-link" href="?page=<?php echo $i; ?>&q=<?php echo urlencode($search); ?>"><?php echo $i; ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
  <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
