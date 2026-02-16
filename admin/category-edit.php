<?php
include '../config/db.php';
include 'header.php';

$id = intval($_GET['id']);
$cat = mysqli_fetch_assoc(mysqli_query($conn,"SELECT * FROM categories WHERE id='$id'"));
if (!$cat) { header("Location: categories.php"); exit; }

function makeSlug($str) {
    return trim(preg_replace('/[^a-z0-9]+/','-',strtolower($str)),'-');
}

$parents = mysqli_query($conn,"SELECT * FROM categories WHERE parent=0 AND id!='$id'");

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $name = trim($_POST['name']);
    $parent = intval($_POST['parent']);
    $slug = makeSlug($name);

    mysqli_query($conn,
        "UPDATE categories SET
         name='".mysqli_real_escape_string($conn,$name)."',
         slug='$slug',
         parent='$parent'
         WHERE id='$id'"
    );

    header("Location: categories.php");
    exit;
}
?>

<h3>Edit Category</h3>

<form method="post" style="max-width:500px">

<div class="mb-3">
<label>Name</label>
<input name="name" class="form-control"
       value="<?php echo htmlspecialchars($cat['name']); ?>" required>
</div>

<div class="mb-3">
<label>Parent</label>
<select name="parent" class="form-select">
    <option value="0">None (Top Level)</option>
    <?php while($p=mysqli_fetch_assoc($parents)): ?>
        <option value="<?php echo $p['id']; ?>"
        <?php if($cat['parent']==$p['id']) echo 'selected'; ?>>
            <?php echo htmlspecialchars($p['name']); ?>
        </option>
    <?php endwhile; ?>
</select>
</div>

<button class="btn btn-primary">Update</button>
<a href="categories.php" class="btn btn-secondary">Cancel</a>

</form>

<?php include 'footer.php'; ?>
