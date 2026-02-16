<?php
include '../config/db.php';
include 'header.php';

function makeSlug($str) {
    $str = strtolower(trim($str));
    $str = preg_replace('/[^a-z0-9]+/', '-', $str);
    return trim($str, '-');
}

$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title       = mysqli_real_escape_string($conn, $_POST['title']);
    $short_desc  = mysqli_real_escape_string($conn, $_POST['short_description']);
    $desc        = mysqli_real_escape_string($conn, $_POST['description']);
    $reg_price   = floatval($_POST['regular_price']);
    $sale_price  = $_POST['sale_price'] !== '' ? floatval($_POST['sale_price']) : null;
    $stock       = isset($_POST['stock']) ? 1 : 0;
    $type        = $_POST['product_type'];
    $category    = intval($_POST['category_id']);
    $weight      = floatval($_POST['weight']);
    $slug = makeSlug($title);
    $baseSlug = $slug;
    $i = 1;
    while (mysqli_num_rows(mysqli_query($conn,"SELECT id FROM products WHERE slug='$slug'")) > 0) {
        $slug = $baseSlug.'-'.$i++;
    }

    /* ===== Images ===== */
    $imgs = [];
    if (!empty($_POST['processed_images'])) {
        $images = json_decode($_POST['processed_images'], true);
        foreach ($images as $i => $img64) {
            $img64 = base64_decode(str_replace('data:image/webp;base64,','',$img64));
            $filename = time()."_$i.webp";
            file_put_contents("../assets/uploads/$filename",$img64);
            $imgs[] = $filename;
        }
    }
    $img_str = implode(',',$imgs);

    mysqli_query($conn,"
        INSERT INTO products
        (title,slug,short_description,description,regular_price,sale_price,stock, weight, product_type,category_id,images)
        VALUES
        ('$title','$slug','$short_desc','$desc','$reg_price',
         ".($sale_price!==null?"'$sale_price'":"NULL").",
         '$stock','$weight','$type','$category','$img_str')
    ");

    $product_id = mysqli_insert_id($conn);

    /* ===== Variations ===== */
    /* ===== Variations ===== */
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
                    '$product_id',
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

    $msg = "Product added successfully!";
}

$cats = mysqli_query($conn,"SELECT * FROM categories ORDER BY name ASC");
?>

<h3>Add Product</h3>

<?php if($msg): ?>
<div class="alert alert-success"><?php echo $msg; ?></div>
<?php endif; ?>

<form method="post">

<input name="title" class="form-control mb-2" placeholder="Product Name" required>

Short Description
<div id="editorShortDesc" class="quill-editor"></div>
<input type="hidden" name="short_description" id="shortDescInput">

Full Description
<div id="editorDescription" class="quill-editor"></div>
<input type="hidden" name="description" id="descriptionInput">

<select name="category_id" class="form-select mb-2">
<?php while($c=mysqli_fetch_assoc($cats)): ?>
<option value="<?php echo $c['id']; ?>"><?php echo $c['name']; ?></option>
<?php endwhile; ?>
</select>

<select name="product_type" id="ptype" class="form-select mb-3">
<option value="simple">Simple</option>
<option value="variable">Variable</option>
</select>

<div id="simpleFields">
<input name="regular_price" class="form-control mb-2" placeholder="Regular Price">
<input name="sale_price" class="form-control mb-2" placeholder="Sale Price (optional)">
<input type="number" step="0.01" name="weight" placeholder="Weight (kg)" class="form-control">
<div class="form-check form-switch">
<input class="form-check-input" type="checkbox" name="stock" value="1" checked>
<label class="form-check-label">In Stock</label>
</div>
</div>

<div id="variationFields" style="display:none">
<h5>Variations</h5>
<div id="varWrap"></div>
<button type="button" class="btn btn-sm btn-secondary"
        onclick="addVar()">Add Variation</button>
</div>

<!-- IMAGE UPLOAD -->
<div class="mb-3 mt-3">
<label>Product Images</label>
<input type="file" id="imageInput" multiple accept="image/*"
       class="form-control mb-2">
<div id="preview" class="d-flex gap-2 flex-wrap"></div>
<input type="hidden" name="processed_images" id="processedImages">
<small class="text-muted">Drag to reorder. First image is main.</small>
</div>

<button class="btn btn-primary mt-3">Save Product</button>
</form>

<!-- ===== IMAGE HANDLING JS (UNCHANGED, RESTORED) ===== -->
<script>
let imageData=[];
const input=document.getElementById('imageInput');
const preview=document.getElementById('preview');
const hidden=document.getElementById('processedImages');

input.addEventListener('change', async ()=>{
    for(let file of [...input.files]){
        const webp=await convertToWebP(file);
        imageData.push(await blobToBase64(webp));
    }
    render();
});

function render(){
    preview.innerHTML='';
    imageData.forEach((src,i)=>{
        const d=document.createElement('div');
        d.className='image-thumb border p-1';
        d.draggable=true;
        d.innerHTML=`<button type="button" class="remove-btn">&times;</button>
                     <img src="${src}" width="100">`;
        d.querySelector('.remove-btn').onclick=e=>{
            e.stopPropagation();imageData.splice(i,1);render();
        };
        d.ondragstart=e=>e.dataTransfer.setData('i',i);
        d.ondragover=e=>e.preventDefault();
        d.ondrop=e=>{
            const from=e.dataTransfer.getData('i');
            imageData.splice(i,0,imageData.splice(from,1)[0]);
            render();
        };
        preview.appendChild(d);
    });
    hidden.value=JSON.stringify(imageData);
}

function convertToWebP(file){
    return new Promise(r=>{
        const i=new Image();
        i.onload=()=>{
            const c=document.createElement('canvas');
            c.width=i.width;c.height=i.height;
            c.getContext('2d').drawImage(i,0,0);
            c.toBlob(b=>r(b),'image/webp',0.8);
        };
        i.src=URL.createObjectURL(file);
    });
}
function blobToBase64(b){
    return new Promise(r=>{
        const f=new FileReader();
        f.onloadend=()=>r(f.result);
        f.readAsDataURL(b);
    });
}
</script>

<!-- ===== CKEDITOR (SECURE + MINIMAL) ===== -->
<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script>
const quillDesc = new Quill('#editorDescription', {
  theme: 'snow',
  modules: {
    toolbar: [
      ['bold','italic'],
      [{ 'list': 'ordered' }, { 'list': 'bullet' }],
      [{ 'header': [2, 3, 4, false] }]
    ]
  }
});

const quillShort = new Quill('#editorShortDesc', {
  theme: 'snow',
  modules: {
    toolbar: [
      ['bold','italic'],
      [{ 'list': 'ordered' }, { 'list': 'bullet' }],
      [{ 'header': [2, 3, 4, false] }]
    ]
  }
});

// On form submit, copy HTML back to hidden inputs
document.querySelector('form').addEventListener('submit', function() {
  document.getElementById('descriptionInput').value = quillDesc.root.innerHTML;
  document.getElementById('shortDescInput').value = quillShort.root.innerHTML;
});
</script>

<script>
document.getElementById("ptype").onchange=e=>{
    simpleFields.style.display=e.target.value==='simple'?'block':'none';
    variationFields.style.display=e.target.value==='variable'?'block':'none';
};
function addVar(){
    varWrap.insertAdjacentHTML("beforeend",`
    <div class="border p-2 mb-2">
        <input name="var_attr[]" class="form-control mb-1"
               placeholder="Variation label (e.g. Size: M / Color: Red)" required>

        <input name="var_regular_price[]" class="form-control mb-1"
               placeholder="Regular Price INR" required>

        <input name="var_sale_price[]" class="form-control mb-1"
               placeholder="Sale Price INR (optional)">

        <input name="var_weight[]" class="form-control mb-1"
               placeholder="Weight (kg)" step="0.01">

        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox"
                   name="var_stock[]" value="1" checked>
            <label class="form-check-label">In Stock</label>
        </div>
    </div>`);
}
</script>

<?php include 'footer.php'; ?>
