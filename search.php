<?php
include 'includes/header.php';
include 'includes/functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();


// inside search.php — improved query building
$q_raw = trim($_GET['q'] ?? '');
$category_id = intval($_GET['cat'] ?? 0);
$min_price_raw = $_GET['min_price'] ?? '';
$max_price_raw = $_GET['max_price'] ?? '';
$min_price = $min_price_raw !== '' ? floatval($min_price_raw) : null;
$max_price = $max_price_raw !== '' ? floatval($max_price_raw) : null;
$sort = $_GET['sort'] ?? 'relevance';

$country = getUserCountry();
$use_usd = ($country !== 'IN');

// tokenization: split on non-word characters, ignore short tokens
$raw_tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($q_raw), -1, PREG_SPLIT_NO_EMPTY);
$tokens = array_filter(array_map('trim', $raw_tokens), function($t){ return mb_strlen($t) >= 2; });

$all_terms = []; // will contain tokens + synonyms
if (!empty($tokens)) {
    foreach ($tokens as $tok) $all_terms[] = mysqli_real_escape_string($conn, $tok);

    // find synonyms for any token (partial match)
    $syns = [];
    foreach ($tokens as $tok) {
        $t = mysqli_real_escape_string($conn, $tok);
        $sq = mysqli_query($conn,
            "SELECT DISTINCT synonym, word FROM synonyms
             WHERE word LIKE '%$t%' OR synonym LIKE '%$t%'
             LIMIT 10"
        );
        while ($s = mysqli_fetch_assoc($sq)) {
            $label = trim($s['synonym']);
            if ($label && !in_array($label, $all_terms, true)) $all_terms[] = mysqli_real_escape_string($conn, $label);
        }
    }
}

// Build WHERE clause: require that each original token must match either title OR description OR synonym mapping (AND across tokens)
// This reduces noisy matches and improves relevance for multi-word queries
$whereParts = [];
if (!empty($tokens)) {
    foreach ($tokens as $tok) {
        $t = mysqli_real_escape_string($conn, $tok);
        $subclauses = [];
        $subclauses[] = "p.title LIKE '%$t%'";
        $subclauses[] = "p.description LIKE '%$t%'";
        // also check variations attributes_json for token
        $subclauses[] = "EXISTS (SELECT 1 FROM product_variations pv WHERE pv.product_id = p.id AND pv.attributes_json LIKE '%$t%')";
        // also check synonyms table: product is matched if title/desc contains a synonym term
        // We'll join via a LIKE against synonyms (this is approximate but effective)
        $subclauses[] = "EXISTS (
            SELECT 1 FROM synonyms s
            WHERE (p.title LIKE CONCAT('%',s.word,'%') OR p.description LIKE CONCAT('%',s.word,'%'))
            AND (s.word LIKE '%$t%' OR s.synonym LIKE '%$t%')
        )";
        $whereParts[] = '(' . implode(' OR ', $subclauses) . ')';
    }
}

$where = " WHERE 1 ";
if (!empty($whereParts)) {
    $where .= " AND " . implode(" AND ", $whereParts);
}

if ($category_id > 0) {
    $where .= " AND p.category_id = " . intval($category_id);
}

// price filter uses effective price (product.price or min variation price)
if ($min_price !== null) {
    $where .= " AND COALESCE(p.price_inr, (SELECT MIN(price_inr) FROM product_variations pv WHERE pv.product_id = p.id)) >= " . floatval($min_price);
}
if ($max_price !== null) {
    $where .= " AND COALESCE(p.price_inr, (SELECT MIN(price_inr) FROM product_variations pv WHERE pv.product_id = p.id)) <= " . floatval($max_price);
}

// ORDER / relevance: compute a simple score: title matches (per token) get higher weight
$orderBy = " ORDER BY ";
if (!empty($tokens)) {
    // compute score expression
    $score_parts = [];
    foreach ($tokens as $tok) {
        $t = mysqli_real_escape_string($conn, $tok);
        $score_parts[] = "(CASE WHEN p.title LIKE '%$t%' THEN 3 WHEN p.description LIKE '%$t%' THEN 1 ELSE 0 END)";
    }
    $score_expr = implode(' + ', $score_parts);
    $orderBy .= " ($score_expr) DESC, p.created_at DESC";
} else {
    if ($sort === 'price_asc') $orderBy .= "COALESCE(p.price_inr, (SELECT MIN(price_inr) FROM product_variations pv WHERE pv.product_id = p.id)) ASC";
    elseif ($sort === 'price_desc') $orderBy .= "COALESCE(p.price_inr, (SELECT MIN(price_inr) FROM product_variations pv WHERE pv.product_id = p.id)) DESC";
    elseif ($sort === 'newest') $orderBy .= "p.created_at DESC";
    else $orderBy .= "p.created_at DESC";
}

// final query: join with computed min variation price for display
$sql = "
SELECT p.*,
       (SELECT MIN(price_inr) FROM product_variations pv WHERE pv.product_id = p.id) AS min_variation_price
FROM products p
$where
$orderBy
LIMIT 200
";

$result = mysqli_query($conn, $sql);


// Categories for filter
$cat_res = mysqli_query($conn, "SELECT id, name FROM categories ORDER BY name ASC");
?>

<div class="container">

    <h1>Search</h1>

    <form class="search-filters" method="get" action="/search.php">
        <div class="row g-2">
            <div class="col-md-4">
                <label class="form-label">Keyword</label>
                <input type="text" name="q" class="form-control"
                       value="<?php echo htmlspecialchars($q_raw); ?>"
                       placeholder="e.g. dinner set, glass, plate">
            </div>

            <div class="col-md-3">
                <label class="form-label">Category</label>
                <select name="cat" class="form-select">
                    <option value="0">All Categories</option>
                    <?php while ($c = mysqli_fetch_assoc($cat_res)): ?>
                        <option value="<?php echo $c['id']; ?>"
                            <?php if ($category_id == $c['id']) echo 'selected'; ?>>
                            <?php echo htmlspecialchars($c['name']); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Min Price (₹)</label>
                <input type="number" name="min_price" class="form-control" min="0" step="1"
                       value="<?php echo $min_price !== null ? htmlspecialchars($min_price) : ''; ?>">
            </div>

            <div class="col-md-2">
                <label class="form-label">Max Price (₹)</label>
                <input type="number" name="max_price" class="form-control" min="0" step="1"
                       value="<?php echo $max_price !== null ? htmlspecialchars($max_price) : ''; ?>">
            </div>

            <div class="col-md-3 mt-2">
                <label class="form-label">Sort By</label>
                <select name="sort" class="form-select">
                    <option value="relevance"  <?php if($sort=='relevance')  echo 'selected'; ?>>Relevance</option>
                    <option value="price_asc"  <?php if($sort=='price_asc')  echo 'selected'; ?>>Price: Low to High</option>
                    <option value="price_desc" <?php if($sort=='price_desc') echo 'selected'; ?>>Price: High to Low</option>
                    <option value="newest"     <?php if($sort=='newest')     echo 'selected'; ?>>Newest</option>
                </select>
            </div>

            <div class="col-md-2 mt-4">
                <button class="btn btn-primary w-100" style="margin-top:6px;">Apply</button>
            </div>
        </div>
    </form>

    <hr>

    <?php if ($q_raw !== ''): ?>
        <p>Showing results for: <strong><?php echo htmlspecialchars($q_raw); ?></strong></p>
    <?php endif; ?>

    <div class="product-grid mt-3">
        <?php
        if (mysqli_num_rows($result) == 0) {
            echo "<p>No products found matching your search.</p>";
        } else {
            while ($p = mysqli_fetch_assoc($result)) {

                // Determine effective price
                $base_price_inr = floatval($p['price_inr'] ?? 0);
                $variation_price_inr = floatval($p['min_variation_price'] ?? 0);
                $price_inr = $variation_price_inr > 0 ? $variation_price_inr : $base_price_inr;
                if ($price_inr <= 0) $price_inr = 0;

                $price_display = $use_usd
                    ? "$" . convertToUSD($price_inr)
                    : "₹" . number_format($price_inr, 2);

                $images = explode(',', $p['images'] ?? '');
                $img    = $images[0] ?? 'default.png';

                echo "<div class='product-card'>
                        <a href='product.php?id=".$p['id']."'>
                            <img src='/assets/uploads/".htmlspecialchars($img)."' alt='".htmlspecialchars($p['title'])."'>
                            <h3>".htmlspecialchars($p['title'])."</h3>
                            <p class='price'>".$price_display."</p>
                        </a>
                      </div>";
            }
        }
        ?>
    </div>

</div>

<?php include 'includes/footer.php'; ?>
