<?php
include '../config/db.php';
include 'header.php';

$q = mysqli_query($conn,
    "SELECT p.*, c.name AS category
     FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     ORDER BY p.id DESC"
);
?>

<div class="d-flex justify-content-between mb-3">
    <h3>Products</h3>
    <a href="product-add.php" class="btn btn-primary">Add Product</a>
</div>

<table class="table table-bordered table-hover">
<thead class="table-dark">
<tr>
    <th>ID</th>
    <th>Name</th>
    <th>Type</th>
    <th>Price</th>
    <th>Stock</th>
    <th>Category</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>
<?php while ($p = mysqli_fetch_assoc($q)): ?>
<tr>
    <td><?php echo $p['id']; ?></td>
    <td><?php echo htmlspecialchars($p['title']); ?></td>
    <td><?php echo ucfirst($p['product_type']); ?></td>
    <td>
        <?php
        if ($p['product_type'] === 'simple') {
            echo '₹'.$p['price_inr'];
        } else {
            echo '—';
        }
        ?>
    </td>
    <td><?php echo $p['stock']; ?></td>
    <td><?php echo htmlspecialchars($p['category'] ?? '-'); ?></td>
    <td>
        <a href="product-edit.php?id=<?php echo $p['id']; ?>"
           class="btn btn-sm btn-dark">Edit</a>

        <a href="product-delete.php?id=<?php echo $p['id']; ?>"
           onclick="return confirm('Delete this product?')"
           class="btn btn-sm btn-danger">Delete</a>
    </td>
</tr>
<?php endwhile; ?>
</tbody>
</table>
<?php include 'footer.php'; ?>