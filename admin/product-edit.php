<?php
include '../config/db.php';
include 'header.php';

function makeSlug($str) {
    $str = strtolower(trim($str));
    $str = preg_replace('/[^a-z0-9]+/', '-', $str);
    return trim($str, '-');
}

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    die('Invalid product');
}

/* ===== FETCH PRODUCT ===== */
$pq = mysqli_query($conn, "SELECT * FROM products WHERE id='$id' LIMIT 1");
$product = mysqli_fetch_assoc($pq);
if (!$product) {
    die('Product not found');
}

/* ===== FETCH VARIATIONS ===== */
$variations = [];
$vq = mysqli_query(
    $conn,
    "SELECT * FROM product_variations WHERE product_id='$id'"
);
while ($v = mysqli_fetch_assoc($vq)) {
    $variations[] = $v;
}

/* ===== UPDATE PRODUCT ===== */
$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title      = mysqli_real_escape_string($conn, $_POST['title']);
    $short_desc = mysqli_real_escape_string($conn, $_POST['short_description']);
    $desc       = mysqli_real_escape_string($conn, $_POST['description']);

    $regular    = floatval($_POST['regular_price']);
    $sale       = $_POST['sale_price'] !== '' ? floatval($_POST['sale_price']) : null;
    $stock      = isset($_POST['stock']) ? 1 : 0;
    $type       = $_POST['product_type'];
    $category   = intval($_POST['category_id']);
    $weight     = floatval($_POST['weight']);

    $slug = makeSlug($title);

    mysqli_query($conn,"
        UPDATE products SET
            title='$title',
            slug='$slug',
            short_description='$short_desc',
            description='$desc',
            regular_price='$regular',
            sale_price=".($sale!==null?"'$sale'":"NULL").",
            stock='$stock', weight='$weight',
            product_type='$type',
            category_id='$category'
        WHERE id='$id'
    ");

    /* ===== IMAGES ===== */
    if (!empty($_POST['processed_images'])) {
        $imgs = [];
        $images = json_decode($_POST['processed_images'], true);
        foreach ($images as $i => $img64) {
            if (strpos($img64, 'data:image/webp') === 0) {
                $img64 = base64_decode(
                    str_replace('data:image/webp;base64,','',$img64)
                );
                $filename = time()."_$i.webp";
                file_put_contents("../assets/uploads/$filename", $img64);
                $imgs[] = $filename;
            } else {
                $imgs[] = $img64; // existing filename
            }
        }
        mysqli_query($conn,"
            UPDATE products SET images='".implode(',',$imgs)."'
            WHERE id='$id'
        ");
    }

    /* ===== VARIATIONS ===== */
    mysqli_query($conn,"DELETE FROM product_variations WHERE product_id='$id'");
    
    if ($type === 'variable' && !empty($_POST['var_regular_price'])) {
    
        foreach ($_POST['var_regular_price'] as $i => $rp) {
    
            $regular_price = floatval($rp);
    
            $sale_price = !empty($_POST['var_sale_price'][$i])
                ? floatval($_POST['var_sale_price'][$i])
                : null;
    
            $weight = !empty($_POST['var_weight'][$i])
                ? floatval($_POST['var_weight'][$i])
                : null;
    
            $stock = isset($_POST['var_stock'][$i]) ? 1 : 0;
    
            $label = mysqli_real_escape_string(
                $conn,
                $_POST['var_attr'][$i]
            );
    
            mysqli_query($conn,"
                INSERT INTO product_variations
                (product_id, regular_price, sale_price, price_inr, stock, weight, attributes_json)
                VALUES (
                    '$id',
                    '$regular_price',
                    ".($sale_price !== null ? "'$sale_price'" : "NULL").",
                    '$regular_price',
                    '$stock',
                    ".($weight !== null ? "'$weight'" : "NULL").",
                    '$label'
                )
            ");
        }
    }

    $msg = "Product updated successfully!";
}

/* ===== CATEGORIES ===== */
$cats = mysqli_query($conn,"SELECT * FROM categories ORDER BY name ASC");
$existingImages = array_filter(explode(',',$product['images']));
?>

<h3>Edit Product</h3>

<?php if($msg): ?>
<div class="alert alert-success"><?php echo $msg; ?></div>
<?php endif; ?>

<form method="post">

<input name="title" class="form-control mb-2"
       value="<?php echo htmlspecialchars($product['title']); ?>" required>

<!-- SHORT DESCRIPTION -->
<label class="mt-2">Short Description</label>
<div id="editorShort" class="quill-editor"></div>
<input type="hidden" name="short_description" id="shortDescInput">

<!-- FULL DESCRIPTION -->
<label class="mt-3">Full Description</label>
<div id="editorDesc" class="quill-editor"></div>
<input type="hidden" name="description" id="descInput">

<select name="category_id" class="form-select mt-3">
<?php while($c=mysqli_fetch_assoc($cats)): ?>
<option value="<?php echo $c['id']; ?>"
    <?php if($product['category_id']==$c['id']) echo 'selected'; ?>>
    <?php echo $c['name']; ?>
</option>
<?php endwhile; ?>
</select>

<select name="product_type" id="ptype" class="form-select mt-3">
<option value="simple" <?php if($product['product_type']==='simple') echo 'selected'; ?>>
Simple
</option>
<option value="variable" <?php if($product['product_type']==='variable') echo 'selected'; ?>>
Variable
</option>
</select>

<!-- SIMPLE PRODUCT -->
<div id="simpleFields" class="mt-3">
<input name="regular_price" class="form-control mb-2"
       value="<?php echo $product['regular_price']; ?>" placeholder="Regular Price">

<input name="sale_price" class="form-control mb-2"
       value="<?php echo $product['sale_price']; ?>" placeholder="Sale Price">

<div class="form-check form-switch">
<input class="form-check-input" type="checkbox"
       name="stock" value="1"
       <?php if($product['stock']==1) echo 'checked'; ?>>
<label class="form-check-label">In Stock</label>

<div class="mb-3">
  <label>Weight (kg)</label>
  <input type="number" step="0.01" name="weight"
         class="form-control" value="<?php echo $product['weight']; ?>" required>
</div>
</div>
</div>

<!-- VARIABLE PRODUCT -->
<div id="variationFields" class="mt-3" style="display:none">
<h5>Variations</h5>
<div id="varWrap">
<?php foreach($variations as $v): ?>
<div class="border p-2 mb-2">
    <input name="var_attr[]" class="form-control mb-1"
           value="<?php echo htmlspecialchars($v['attributes_json']); ?>"
           placeholder="Variation label">

    <input name="var_regular_price[]" class="form-control mb-1"
           value="<?php echo $v['regular_price']; ?>"
           placeholder="Regular Price INR">

    <input name="var_sale_price[]" class="form-control mb-1"
           value="<?php echo $v['sale_price']; ?>"
           placeholder="Sale Price INR (optional)">

    <input name="var_weight[]" class="form-control mb-1"
           value="<?php echo $v['weight']; ?>"
           step="0.01"
           placeholder="Weight (kg)">

    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox"
               name="var_stock[]" value="1"
               <?php if($v['stock']==1) echo 'checked'; ?>>
        <label class="form-check-label">In Stock</label>
    </div>
</div>
<?php endforeach; ?>
</div>
<button type="button" class="btn btn-sm btn-secondary"
        onclick="addVar()">Add Variation</button>
</div>
<!-- IMAGES -->
<div class="mt-4">
<label>Product Images</label>
<input type="file" id="imageInput" multiple class="form-control mb-2">
<div id="preview" class="d-flex gap-2 flex-wrap"></div>
<input type="hidden" name="processed_images" id="processedImages">
</div>

<button class="btn btn-primary mt-4">Update Product</button>
</form>

<!-- ================= IMAGE HANDLING (UNCHANGED) ================= -->
<script>
let imageData = <?php echo json_encode($existingImages); ?>;
const preview = document.getElementById('preview');
const hidden = document.getElementById('processedImages');

function renderImages(){
    preview.innerHTML='';
    imageData.forEach((src,i)=>{
        const d=document.createElement('div');
        d.className='image-thumb border p-1';
        d.draggable=true;
        d.innerHTML=`<button type="button" class="remove-btn">&times;</button>
                     <img src="/asm-crockery/assets/uploads/${src}" width="100">`;
        d.querySelector('.remove-btn').onclick=e=>{
            e.stopPropagation();imageData.splice(i,1);renderImages();
        };
        d.ondragstart=e=>e.dataTransfer.setData('i',i);
        d.ondragover=e=>e.preventDefault();
        d.ondrop=e=>{
            const from=e.dataTransfer.getData('i');
            imageData.splice(i,0,imageData.splice(from,1)[0]);
            renderImages();
        };
        preview.appendChild(d);
    });
    hidden.value = JSON.stringify(imageData);
}
renderImages();
</script>

<!-- ================= QUILL ================= -->
<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>

<script>
const toolbar = [
  ['bold','italic'],
  [{ list: 'ordered' }, { list: 'bullet' }],
  [{ header: [2,3,4,false] }]
];

const qShort = new Quill('#editorShort',{ theme:'snow', modules:{toolbar} });
const qDesc  = new Quill('#editorDesc',{ theme:'snow', modules:{toolbar} });

qShort.root.innerHTML = <?php echo json_encode($product['short_description']); ?>;
qDesc.root.innerHTML  = <?php echo json_encode($product['description']); ?>;

document.querySelector('form').addEventListener('submit',()=>{
    document.getElementById('shortDescInput').value = qShort.root.innerHTML;
    document.getElementById('descInput').value      = qDesc.root.innerHTML;
});
</script>

<script>
document.getElementById('ptype').onchange=e=>{
    simpleFields.style.display=e.target.value==='simple'?'block':'none';
    variationFields.style.display=e.target.value==='variable'?'block':'none';
};
document.getElementById('ptype').dispatchEvent(new Event('change'));

function addVar(){
    varWrap.insertAdjacentHTML('beforeend',`
    <div class="border p-2 mb-2">
        <input name="var_attr[]" class="form-control mb-1"
               placeholder="Variation label">

        <input name="var_regular_price[]" class="form-control mb-1"
               placeholder="Regular Price INR">

        <input name="var_sale_price[]" class="form-control mb-1"
               placeholder="Sale Price INR (optional)">

        <input name="var_weight[]" class="form-control mb-1"
               step="0.01"
               placeholder="Weight (kg)">

        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox"
                   name="var_stock[]" value="1" checked>
            <label class="form-check-label">In Stock</label>
        </div>
    </div>`);
}
</script>

<?php include 'footer.php'; ?>