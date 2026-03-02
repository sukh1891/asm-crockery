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
        $existingPreviewVideo = trim((string)($_POST['watch_buy_existing_preview_video'][$i] ?? ''));
        $videoBase64 = trim((string)($_POST['watch_buy_video_base64'][$i] ?? ''));
        $previewBase64 = trim((string)($_POST['watch_buy_preview_base64'][$i] ?? ''));

        $productUrl = trim((string)($_POST['watch_buy_product_url'][$i] ?? ''));
        $videoPath = $existingVideo;
        $previewVideoPath = ($existingPreviewVideo !== '' ? $existingPreviewVideo : $existingVideo);

        if ($videoBase64 !== '') {
            $uploadDir = '../assets/uploads/homepage/videos';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }

            $uniqueSuffix = time() . '_' . mt_rand(1000, 9999);
            $finalFile = 'watch_buy_' . $i . '_' . $uniqueSuffix . '.webm';
            $previewFile = 'watch_buy_preview_' . $i . '_' . $uniqueSuffix . '.webm';

            $finalPath = $uploadDir . '/' . $finalFile;
            $previewPath = $uploadDir . '/' . $previewFile;
            
            $decodedVideo = base64_decode($videoBase64, true);
            if ($decodedVideo !== false && strlen($decodedVideo) > 0 && file_put_contents($finalPath, $decodedVideo) !== false) {
                $videoPath = 'homepage/videos/' . $finalFile;
                $previewVideoPath = $videoPath;

                $decodedPreview = base64_decode($previewBase64, true);
                if ($decodedPreview !== false && strlen($decodedPreview) > 0 && file_put_contents($previewPath, $decodedPreview) !== false) {
                    $previewVideoPath = 'homepage/videos/' . $previewFile;
                }
            }
        }

        if ($videoPath !== '' && $productUrl !== '') {
            $watchBuyItems[] = [
                'video' => $videoPath,
                'preview_video' => $previewVideoPath,
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
        <div class="card-header">Watch &amp; Buy Section</div>
        <div class="card-body">
            <p class="text-muted">Upload up to 4 vertical videos.</p>
            <?php for ($i = 1; $i <= 4; $i++): ?>
                <?php $item = $currentWatchBuyItems[$i - 1] ?? ['video' => '', 'preview_video' => '', 'product_url' => '']; ?>
                <div class="border rounded p-3 mb-3">
                    <h6>Video <?php echo $i; ?></h6>
                    <?php if (!empty($item['video'])): ?>
                        <p class="mb-2"><a target="_blank" href="/asm-crockery/assets/uploads/<?php echo htmlspecialchars($item['video']); ?>">Current video</a></p>
                    <?php endif; ?>
                    <input type="hidden" name="watch_buy_existing_video[<?php echo $i; ?>]" value="<?php echo htmlspecialchars($item['video'] ?? ''); ?>">
                    <input type="hidden" name="watch_buy_existing_preview_video[<?php echo $i; ?>]" value="<?php echo htmlspecialchars($item['preview_video'] ?? ''); ?>">
                    <label class="form-label">Video file</label>
                    <input type="file" accept="video/*" class="form-control mb-2 watch-buy-video-input" data-slot="<?php echo $i; ?>">
                    <input type="hidden" name="watch_buy_video_base64[<?php echo $i; ?>]" class="watch-buy-video-base64" data-slot="<?php echo $i; ?>">
                    <input type="hidden" name="watch_buy_preview_base64[<?php echo $i; ?>]" class="watch-buy-preview-base64" data-slot="<?php echo $i; ?>">
                    <div class="form-text mb-2 watch-buy-status" data-slot="<?php echo $i; ?>"></div>
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
<script src="https://unpkg.com/@ffmpeg/ffmpeg@0.12.10/dist/umd/ffmpeg.js"></script>
<script src="https://unpkg.com/@ffmpeg/util@0.12.1/dist/umd/index.js"></script>
<script>
(async function () {
    const form = document.getElementById('homepageSettingsForm');
    const inputs = document.querySelectorAll('.watch-buy-video-input');
    if (!inputs.length || !window.FFmpegWASM || !window.FFmpegUtil) return;

    const { FFmpeg } = window.FFmpegWASM;
    const { fetchFile } = window.FFmpegUtil;
    const ffmpeg = new FFmpeg();
    let ffmpegLoaded = false;

    const ensureLoaded = async () => {
        if (ffmpegLoaded) return true;
        try {
            await ffmpeg.load({
                coreURL: 'https://unpkg.com/@ffmpeg/core@0.12.6/dist/umd/ffmpeg-core.js',
                wasmURL: 'https://unpkg.com/@ffmpeg/core@0.12.6/dist/umd/ffmpeg-core.wasm'
            });
            ffmpegLoaded = true;
            return true;
        } catch (e) {
            console.warn('FFmpeg WASM load failed', e);
            return false;
        }
    };

    const uint8ToBase64 = (bytes) => {
        let binary = '';
        const chunkSize = 0x8000;
        for (let i = 0; i < bytes.length; i += chunkSize) {
            binary += String.fromCharCode.apply(null, bytes.subarray(i, i + chunkSize));
        }
        return btoa(binary);
    };

    const setStatus = (slot, text, isError = false) => {
        const el = document.querySelector('.watch-buy-status[data-slot="' + slot + '"]');
        if (!el) return;
        el.textContent = text;
        el.style.color = isError ? '#c62828' : '#2e7d32';
    };

    for (const input of inputs) {
        input.addEventListener('change', async function () {
            const file = this.files && this.files[0];
            const slot = this.dataset.slot;
            const fullHidden = document.querySelector('.watch-buy-video-base64[data-slot="' + slot + '"]');
            const previewHidden = document.querySelector('.watch-buy-preview-base64[data-slot="' + slot + '"]');
            if (!fullHidden || !previewHidden) return;

            fullHidden.value = '';
            previewHidden.value = '';
            if (!file) {
                setStatus(slot, '');
                return;
            }

            setStatus(slot, 'Preparing video...');
            const ok = await ensureLoaded();
            if (!ok) {
                setStatus(slot, 'Unable to load FFmpeg WebAssembly. Please check internet and try again.', true);
                return;
            }

            const stamp = Date.now();
            const inputName = 'input-' + slot + '-' + stamp + '.mp4';
            const fullName = 'full-' + slot + '-' + stamp + '.webm';
            const previewName = 'preview-' + slot + '-' + stamp + '.webm';

            try {
                await ffmpeg.writeFile(inputName, await fetchFile(file));

                setStatus(slot, 'Converting full video to WebM...');
                await ffmpeg.exec([
                    '-i', inputName,
                    '-c:v', 'libvpx-vp9', '-b:v', '0', '-crf', '34', '-vf', 'scale=720:-2', '-row-mt', '1', '-deadline', 'good', '-cpu-used', '4',
                    '-c:a', 'libopus', '-b:a', '96k',
                    fullName
                ]);

                setStatus(slot, 'Generating 3-second preview...');
                await ffmpeg.exec([
                    '-i', inputName,
                    '-t', '3', '-an',
                    '-c:v', 'libvpx-vp9', '-b:v', '0', '-crf', '36', '-vf', 'scale=480:-2', '-row-mt', '1', '-deadline', 'good', '-cpu-used', '4',
                    previewName
                ]);

                const fullData = await ffmpeg.readFile(fullName);
                const previewData = await ffmpeg.readFile(previewName);

                const fullBytes = fullData instanceof Uint8Array ? fullData : new Uint8Array(fullData.buffer);
                const previewBytes = previewData instanceof Uint8Array ? previewData : new Uint8Array(previewData.buffer);

                fullHidden.value = uint8ToBase64(fullBytes);
                previewHidden.value = uint8ToBase64(previewBytes);

                setStatus(slot, 'Video and preview prepared. Ready to save.');
            } catch (error) {
                console.warn('Client-side conversion failed.', error);
                fullHidden.value = '';
                previewHidden.value = '';
                setStatus(slot, 'Video conversion failed. Please try a smaller file or different format.', true);
            } finally {
                try { await ffmpeg.deleteFile(inputName); } catch (e) {}
                try { await ffmpeg.deleteFile(fullName); } catch (e) {}
                try { await ffmpeg.deleteFile(previewName); } catch (e) {}
            }
        });
    }

    if (form) {
        form.addEventListener('submit', function (e) {
            const pending = Array.from(document.querySelectorAll('.watch-buy-status')).some((el) => {
                const t = (el.textContent || '').toLowerCase();
                return t.includes('preparing') || t.includes('converting') || t.includes('generating');
            });
            if (pending) {
                e.preventDefault();
                alert('Please wait for video conversion to finish before saving.');
            }
        });
    }
})();
</script>

</body>
</html>
