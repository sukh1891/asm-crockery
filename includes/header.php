<?php
//session_start();
include_once __DIR__ . '/seo.php';
$seo = $seo ?? seoHome();
include 'config/db.php';
//include 'includes/functions.php';
if (session_status() == PHP_SESSION_NONE) session_start();
$wishlist_count = function_exists('getWishlistCount') ? getWishlistCount() : 0;
include_once 'includes/category-functions.php';
$categoryTree = getMenuCategories();
$menuCategories = getMenuCategories();
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo htmlspecialchars($seo['title']); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($seo['desc']); ?>">
    <link rel="canonical" href="https://asmcrockery.com<?php echo $seo['canonical']; ?>">
    <link rel="stylesheet" href="/asm-crockery/assets/css/style.css">
    <link rel="stylesheet" href="/asm-crockery/assets/css/store.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body>
<base href="/asm-crockery/">
<header class="site-header">
    <div class="container header-inner">

        <div class="logo">
            <a href="/asm-crockery/">ASM Crockery</a>
        </div>

        <nav class="main-nav">
            <a href="/asm-crockery/">Home</a>
            <?php foreach ($menuCategories as $cat): ?>
                <a href="/asm-crockery/category/<?php echo $cat['slug']; ?>">
                    <?php echo htmlspecialchars($cat['name']); ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="header-actions">

            <!-- SEARCH -->
            <div class="search-wrapper">
                <input type="text" id="searchInput" placeholder="Search products…">
                <div id="searchResults" class="search-dropdown"></div>
            </div>
            <div id="searchAutocomplete"></div>
            <!-- ACCOUNT -->
            <a href="/asm-crockery/login.php" class="icon-btn" title="Account">
                <svg class="icon" fill="none" viewBox="0 0 24 24">
                    <circle cx="12" cy="8" r="4"></circle>
                    <path d="M4 20c2-4 14-4 16 0"></path>
                </svg>
            </a>
        
            <!-- CART -->
            <a href="/asm-crockery/cart.php" class="icon-btn" title="Cart">
                <svg class="icon" fill="none" viewBox="0 0 24 24">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l3 13h12l2-8H6"></path>
                </svg>
                <span id="cartCount">
                <?php
                echo isset($_SESSION['cart'])
                  ? array_sum(array_column($_SESSION['cart'],'qty'))
                  : 0;
                ?>
                </span>
            </a>
        </div>

    </div>
</header>
<style>
/* minimal styles for suggestions */
#searchAutocomplete {
  position: absolute;
  z-index: 2000;
  background: white;
  border: 1px solid #ddd;
  width: 100%;
  box-shadow: 0 6px 18px rgba(0,0,0,0.08);
  max-height: 420px;
  overflow: auto;
  display: none;
}
.search-suggestion { padding: 8px 10px; cursor: pointer; display:flex; gap:10px; align-items:center; }
.search-suggestion:hover, .search-suggestion.active { background:#f6f6f6; }
.search-suggestion img { width:48px; height:48px; object-fit:cover; border-radius:6px; }
.search-suggestion .meta { flex:1; }
.search-suggestion .type { font-size:11px; color:#666; }
.icon {
    width: 20px;
    height: 20px;
    stroke: currentColor;
}
.icon-btn {
    display: flex;
    align-items: center;
    color: #333;
}

.icon-btn:hover {
    color: #0d6efd;
}
</style>

<!-- put directly after the search form so positioning works -->
<script>
function updateCartCount(count) {
    const el = document.getElementById('cartCount');
    if (el) el.textContent = count;
}
</script>

<script>
(function(){
  const input = document.getElementById('searchInput');
  const box = document.getElementById('searchAutocomplete');
  let timer = null;
  let suggestions = [];
  let selected = -1;

  function render(items) {
    suggestions = items;
    selected = -1;
    if (!items || items.length === 0) {
      box.style.display = 'none'; box.innerHTML = ''; return;
    }
    box.style.display = 'block';
    box.innerHTML = items.map((it, idx) => {
      if (it.type === 'product') {
        const price = it.sale_price ? '₹' + Number(it.sale_price).toFixed(2) : '';
        const img = it.image ? '/assets/uploads/' + it.image : '/assets/images/default.png';
        return `<div class="search-suggestion" data-idx="${idx}" data-type="product" data-id="${it.id}">
                  <img src="${img}" alt="">
                  <div class="meta">
                    <div>${escapeHtml(it.label)}</div>
                    <div class="type">${price} · Product</div>
                  </div>
                </div>`;
      }
      if (it.type === 'category') {
        return `<div class="search-suggestion" data-idx="${idx}" data-type="category" data-id="${it.id}">
                  <div class="meta"><div>${escapeHtml(it.label)}</div><div class="type">Category</div></div>
                </div>`;
      }
      // synonym
      return `<div class="search-suggestion" data-idx="${idx}" data-type="synonym">
                <div class="meta"><div>${escapeHtml(it.label)}</div><div class="type">Suggestion</div></div>
              </div>`;
    }).join('');

    // attach click
    box.querySelectorAll('.search-suggestion').forEach(el => {
      el.addEventListener('click', function () {
        const idx = this.dataset.idx;
        const it = suggestions[idx];
        if (!it) return;
        if (it.type === 'product') {
          window.location.href = '/product.php?id=' + it.id;
        } else if (it.type === 'category') {
          window.location.href = '/category.php?id=' + it.id;
        } else {
          // treat synonyms / suggestion as search term
          window.location.href = '/search.php?q=' + encodeURIComponent(it.label);
        }
      });
    });
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];});
  }

  function fetchSuggestions(q) {
    if (!q || q.length < 2) {
      render([]);
      return;
    }
    fetch('/api/search/suggest.php?q=' + encodeURIComponent(q))
      .then(r => r.json())
      .then(data => {
        if (data && data.success) render(data.items || []);
      })
      .catch(()=> render([]));
  }

  input.addEventListener('input', function() {
    const v = this.value.trim();
    clearTimeout(timer);
    timer = setTimeout(() => fetchSuggestions(v), 220); // debounce 220ms
  });

  // keyboard navigation
  input.addEventListener('keydown', function(e) {
    const nodes = box.querySelectorAll('.search-suggestion');
    if (!nodes.length) return;
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      selected = Math.min(selected + 1, nodes.length - 1);
      updateActive(nodes);
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      selected = Math.max(selected - 1, 0);
      updateActive(nodes);
    } else if (e.key === 'Enter') {
      if (selected >= 0 && selected < nodes.length) {
        nodes[selected].click();
        e.preventDefault();
      } else {
        // normal submit - go to search page
        window.location.href = '/search.php?q=' + encodeURIComponent(this.value.trim());
      }
    } else if (e.key === 'Escape') {
      box.style.display = 'none';
    }
  });

  function updateActive(nodes) {
    nodes.forEach(n => n.classList.remove('active'));
    if (selected >= 0 && selected < nodes.length) nodes[selected].classList.add('active');
  }

  // click outside to close
  document.addEventListener('click', function(e){
    if (!box.contains(e.target) && e.target !== input) {
      box.style.display = 'none';
    }
  });

})();
</script>
