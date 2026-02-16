<?php
include '../config/db.php';
include 'header.php';

/* ===== UPDATE MENU VISIBILITY ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['menu'])) {
    foreach ($_POST['menu'] as $id => $val) {
        mysqli_query($conn,
            "UPDATE categories SET show_in_menu='" . intval($val) . "' WHERE id='" . intval($id) . "'"
        );
    }
}

/* ===== FETCH CATEGORIES TREE ===== */
function getCategories($parent = 0, $level = 0) {
    global $conn;
    $q = mysqli_query($conn,
        "SELECT * FROM categories WHERE parent='$parent' ORDER BY name ASC"
    );
    $rows = [];
    while ($c = mysqli_fetch_assoc($q)) {
        $c['level'] = $level;
        $rows[] = $c;
        $rows = array_merge($rows, getCategories($c['id'], $level + 1));
    }
    return $rows;
}

$categories = getCategories();
?>

<h3>Categories</h3>

<form method="post">
<table class="table table-bordered align-middle">
<thead class="table-dark">
<tr>
    <th>Name</th>
    <th>Slug</th>
    <th class="text-center">Show in Menu</th>
    <th>Actions</th>
</tr>
</thead>
<tbody>

<?php foreach ($categories as $c): ?>
<tr>
    <td><?php echo str_repeat('— ', $c['level']) . htmlspecialchars($c['name']); ?></td>
    <td><?php echo htmlspecialchars($c['slug']); ?></td>

    <td class="text-center">
        <input type="hidden" name="menu[<?php echo $c['id']; ?>]" value="0">
        <input type="checkbox"
               name="menu[<?php echo $c['id']; ?>]"
               value="1"
               <?php if ($c['show_in_menu']) echo 'checked'; ?>>
    </td>

    <td>
        <a href="category-edit.php?id=<?php echo $c['id']; ?>"
           class="btn btn-sm btn-dark">Edit</a>
    </td>
</tr>
<?php endforeach; ?>

</tbody>
</table>

<button class="btn btn-primary">Save Menu Settings</button>
<a href="category-add.php" class="btn btn-secondary">Add Category</a>
</form>

<?php include 'footer.php'; ?>