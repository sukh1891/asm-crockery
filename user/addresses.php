<?php
include '../includes/header.php';
include '../includes/functions.php';
include 'auth-check.php';

$user_id = intval($_SESSION['user_id']);

// Handle POST - add/edit/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $label = mysqli_real_escape_string($conn, $_POST['label']);
        $name  = mysqli_real_escape_string($conn, $_POST['name']);
        $phone = mysqli_real_escape_string($conn, $_POST['phone']);
        $address = mysqli_real_escape_string($conn, $_POST['address']);
        $city = mysqli_real_escape_string($conn, $_POST['city']);
        $zip = mysqli_real_escape_string($conn, $_POST['zip']);
        $is_default = isset($_POST['is_default']) ? 1 : 0;

        if ($is_default) {
            mysqli_query($conn, "UPDATE user_addresses SET is_default=0 WHERE user_id='$user_id'");
        }

        mysqli_query($conn, "INSERT INTO user_addresses (user_id,label,name,phone,address,city,zip,is_default) VALUES ('$user_id','$label','$name','$phone','$address','$city','$zip','$is_default')");
        header("Location: addresses.php");
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id']);
        mysqli_query($conn, "DELETE FROM user_addresses WHERE id='$id' AND user_id='$user_id'");
        header("Location: addresses.php");
        exit;
    }

    if ($action === 'set_default') {
        $id = intval($_POST['id']);
        mysqli_query($conn, "UPDATE user_addresses SET is_default=0 WHERE user_id='$user_id'");
        mysqli_query($conn, "UPDATE user_addresses SET is_default=1 WHERE id='$id' AND user_id='$user_id'");
        header("Location: addresses.php");
        exit;
    }
}

// Fetch addresses
$res = mysqli_query($conn, "SELECT * FROM user_addresses WHERE user_id='$user_id' ORDER BY is_default DESC, id DESC");

?>

<div class="container">
  <h2>My Addresses</h2>

  <div class="row">
    <div class="col-md-6">
      <h5>Saved Addresses</h5>
      <?php while ($a = mysqli_fetch_assoc($res)): ?>
        <div class="card p-3 mb-2">
          <strong><?php echo htmlspecialchars($a['label']); ?> <?php if($a['is_default']): ?><span class="badge bg-success">Default</span><?php endif; ?></strong>
          <p><?php echo nl2br(htmlspecialchars($a['address'])); ?><br><?php echo htmlspecialchars($a['city']); ?> - <?php echo htmlspecialchars($a['zip']); ?><br>Phone: <?php echo htmlspecialchars($a['phone']); ?></p>

          <form method="post" style="display:inline-block">
            <input type="hidden" name="action" value="set_default">
            <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
            <button class="btn btn-sm btn-outline-primary">Set Default</button>
          </form>

          <form method="post" style="display:inline-block">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?php echo $a['id']; ?>">
            <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete address?')">Delete</button>
          </form>
        </div>
      <?php endwhile; ?>
    </div>

    <div class="col-md-6">
      <h5>Add New Address</h5>
      <form method="post">
        <input type="hidden" name="action" value="add">
        <div class="mb-2"><input class="form-control" name="label" placeholder="Label (Home / Office)"></div>
        <div class="mb-2"><input class="form-control" name="name" placeholder="Full name"></div>
        <div class="mb-2"><input class="form-control" name="phone" placeholder="Phone"></div>
        <div class="mb-2"><textarea class="form-control" name="address" placeholder="Address"></textarea></div>
        <div class="mb-2"><input class="form-control" name="city" placeholder="City"></div>
        <div class="mb-2"><input class="form-control" name="zip" placeholder="ZIP / PIN"></div>
        <div class="mb-2"><label><input type="checkbox" name="is_default"> Set as default</label></div>
        <button class="btn btn-primary">Save Address</button>
      </form>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
