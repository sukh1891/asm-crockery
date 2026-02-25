<?php
include '../config/db.php';
include '../includes/homepage-config.php';
include 'header.php';

$msg = '';
$settings = getHomepageSettings($conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $heroUrl = trim($_POST['hero_url'] ?? '');
    $selectedCategories = $_POST['category_ids'] ?? [];
    $selectedBrands = $_POST['brand_ids'] ?? [];

    $heroImage = $settings['hero_image'] ?? null;

    if (!empty($_FILES['hero_image']['name']) && is_uploaded_file($_FILES['hero_image']['tmp_name'])) {
        $uploadDir = '../assets/uploads/homepage';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $ext = strtolower(pathinfo($_FILES['hero_image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowed, true)) {
            $fileName = 'hero_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
            if (move_uploaded_file($_FILES['hero_image']['tmp_name'], $uploadDir . '/' . $fileName)) {
                $heroImage = 'homepage/' . $fileName;
            }
        }
    }

    $heroImageEsc = $heroImage ? mysqli_real_escape_string($conn, $heroImage) : '';
    $heroUrlEsc = mysqli_real_escape_string($conn, $heroUrl);
    $categoryCsv = mysqli_real_escape_string($conn, idsArrayToCsv($selectedCategories));
    $brandCsv = mysqli_real_escape_string($conn, idsArrayToCsv($selectedBrands));

    mysqli_query($conn, "
        UPDATE homepage_settings
        SET hero_image=" . ($heroImage ? "'$heroImageEsc'" : "NULL") . ",
            hero_url=" . ($heroUrl !== '' ? "'$heroUrlEsc'" : "NULL") . ",
            category_ids='$categoryCsv',
            brand_ids='$brandCsv'
        WHERE id=1
    ");

    $msg = 'Homepage settings updated.';
    $settings = getHomepageSettings($conn);
}

$allCategories = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name ASC");
$currentCategoryIds = csvIdsToArray($settings['category_ids'] ?? '');
$currentBrandIds = csvIdsToArray($settings['brand_ids'] ?? '');
?>

<h3>Homepage Layout</h3>

<?php if ($msg): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
    <div class="card mb-3">
        <div class="card-header">Top Banner</div>
        <div class="card-body">
            <?php if (!empty($settings['hero_image'])): ?>
                <div class="mb-2">
                    <img src="/asm-crockery/assets/uploads/<?php echo htmlspecialchars($settings['hero_image']); ?>" alt="Banner" style="max-height:120px; border:1px solid #ddd; padding:4px;">
                </div>
            <?php endif; ?>
            <label class="form-label">Banner Image</label>
            <input type="file" name="hero_image" accept="image/*" class="form-control mb-3">

            <label class="form-label">Banner URL</label>
            <input type="url" name="hero_url" class="form-control" placeholder="https://example.com" value="<?php echo htmlspecialchars($settings['hero_url'] ?? ''); ?>">
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Shop by Category Section</div>
        <div class="card-body">
            <label class="form-label">Select Categories to Display</label>
            <select name="category_ids[]" multiple size="10" class="form-select">
                <?php mysqli_data_seek($allCategories, 0); ?>
                <?php while ($c = mysqli_fetch_assoc($allCategories)): ?>
                    <option value="<?php echo (int)$c['id']; ?>" <?php echo in_array((int)$c['id'], $currentCategoryIds, true) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <small class="text-muted">Hold Ctrl/Cmd to select multiple.</small>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">Shop by Brand Section</div>
        <div class="card-body">
            <label class="form-label">Select Brand Categories to Display</label>
            <select name="brand_ids[]" multiple size="10" class="form-select">
                <?php mysqli_data_seek($allCategories, 0); ?>
                <?php while ($c = mysqli_fetch_assoc($allCategories)): ?>
                    <option value="<?php echo (int)$c['id']; ?>" <?php echo in_array((int)$c['id'], $currentBrandIds, true) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($c['name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
            <small class="text-muted">Brands are chosen from existing categories.</small>
        </div>
    </div>

    <button class="btn btn-primary">Save Homepage Settings</button>
</form>

</div>
</body>
</html>
