<?php
include '../config/db.php';
include 'header.php';

function makeSlug($str) {
    return trim(preg_replace('/[^a-z0-9]+/','-',strtolower($str)),'-');
}

$cats = mysqli_query($conn,"SELECT * FROM categories WHERE parent=0 ORDER BY name");

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $name = trim($_POST['name']);
    $parent = intval($_POST['parent']);
    $slug = makeSlug($name);

    mysqli_query($conn,
        "INSERT INTO categories (name, slug, parent)
         VALUES ('".mysqli_real_escape_string($conn,$name)."','$slug','$parent')"
    );

    header("Location: categories.php");
    exit;
}
?>

<h3>Add Category</h3>

<form method="post" style="max-width:500px">

<div class="mb-3">
<label>Category Name</label>
<input name="name" class="form-control" required>
</div>

<div class="mb-3">
<label>Parent Category</label>
<select name="parent" class="form-select">
    <option value="0">None (Top Level)</option>
    <?php while($c=mysqli_fetch_assoc($cats)): ?>
        <option value="<?php echo $c['id']; ?>">
            <?php echo htmlspecialchars($c['name']); ?>
        </option>
    <?php endwhile; ?>
</select>
</div>

<button class="btn btn-primary">Save</button>
<a href="categories.php" class="btn btn-secondary">Cancel</a>

</form>

<?php include 'footer.php'; ?>
