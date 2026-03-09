<?php
include '../config/db.php';
include '../includes/homepage-config.php';
include 'header.php';

$msg = '';
$settings = getHomepageSettings($conn);
$currentWatchBuyItems = getWatchBuyItems($settings['watch_buy_videos'] ?? '');

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
    
    $watchBuyItems = [];
    for ($i = 1; $i <= 4; $i++) {
        $existingVideo = trim((string)($_POST['watch_buy_existing_video'][$i] ?? ''));

        $productUrl = trim((string)($_POST['watch_buy_product_url'][$i] ?? ''));
        $videoPath = $existingVideo;

        if (!empty($_FILES['watch_buy_video']['name'][$i]) && is_uploaded_file($_FILES['watch_buy_video']['tmp_name'][$i])) {
            $uploadDir = '../assets/uploads/homepage/videos';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }

            $ext = strtolower(pathinfo($_FILES['watch_buy_video']['name'][$i], PATHINFO_EXTENSION));
            if ($ext === 'mp4') {
                $uniqueSuffix = time() . '_' . mt_rand(1000, 9999);
                $finalFile = 'watch_buy_' . $i . '_' . $uniqueSuffix . '.mp4';
                $finalPath = $uploadDir . '/' . $finalFile;
                if (move_uploaded_file($_FILES['watch_buy_video']['tmp_name'][$i], $finalPath)) {
                    $videoPath = 'homepage/videos/' . $finalFile;
                }
            }
        }

        if ($videoPath !== '' && $productUrl !== '') {
            $watchBuyItems[] = [
                'video' => $videoPath,
                'product_url' => $productUrl,
            ];
        }
    }

    $heroImageEsc = $heroImage ? mysqli_real_escape_string($conn, $heroImage) : '';
    $heroUrlEsc = mysqli_real_escape_string($conn, $heroUrl);
    $categoryCsv = mysqli_real_escape_string($conn, idsArrayToCsv($selectedCategories));
    $brandCsv = mysqli_real_escape_string($conn, idsArrayToCsv($selectedBrands));
    $watchBuyEsc = mysqli_real_escape_string($conn, json_encode($watchBuyItems));

    mysqli_query($conn, "
        UPDATE homepage_settings
        SET hero_image=" . ($heroImage ? "'$heroImageEsc'" : "NULL") . ",
            hero_url=" . ($heroUrl !== '' ? "'$heroUrlEsc'" : "NULL") . ",
            category_ids='$categoryCsv',
            brand_ids='$brandCsv',
            watch_buy_videos='$watchBuyEsc'
        WHERE id=1
    ");

    $msg = 'Homepage settings updated.';
    $settings = getHomepageSettings($conn);
    $currentWatchBuyItems = getWatchBuyItems($settings['watch_buy_videos'] ?? '');
}

$allCategories = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name ASC");
$currentCategoryIds = csvIdsToArray($settings['category_ids'] ?? '');
$currentBrandIds = csvIdsToArray($settings['brand_ids'] ?? '');
?>

<h3>Homepage Layout</h3>

<?php if ($msg): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data" id="homepageSettingsForm">
    <div class="card mb-3">
        <div class="card-header">Top Banner</div>
        <div class="card-body">
            <?php if (!empty($settings['hero_image'])): ?>
                <div class="mb-2">
                    <img src="/assets/uploads/<?php echo htmlspecialchars($settings['hero_image']); ?>" alt="Banner" style="max-height:120px; border:1px solid #ddd; padding:4px;">
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
        <div class="card-header">Watch &amp; Buy Section</div>
        <div class="card-body">
            <p class="text-muted">Upload up to 4 vertical videos.</p>
            <?php for ($i = 1; $i <= 4; $i++): ?>
                <?php $item = $currentWatchBuyItems[$i - 1] ?? ['video' => '', 'preview_video' => '', 'product_url' => '']; ?>
                <div class="border rounded p-3 mb-3">
                    <h6>Video <?php echo $i; ?></h6>
                    <?php if (!empty($item['video'])): ?>
                        <p class="mb-2"><a target="_blank" href="/assets/uploads/<?php echo htmlspecialchars($item['video']); ?>">Current video</a></p>
                    <?php endif; ?>
                    <input type="hidden" name="watch_buy_existing_video[<?php echo $i; ?>]" value="<?php echo htmlspecialchars($item['video'] ?? ''); ?>">
                    <label class="form-label">MP4 video file</label>
                    <input type="file" name="watch_buy_video[<?php echo $i; ?>]" accept="video/mp4" class="form-control mb-2">
                    <label class="form-label">Product URL</label>
                    <input type="url" name="watch_buy_product_url[<?php echo $i; ?>]" class="form-control" placeholder="https://example.com/product" value="<?php echo htmlspecialchars($item['product_url'] ?? ''); ?>">
                </div>
            <?php endfor; ?>
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
