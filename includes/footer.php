<footer class="site-footer">
    <div class="container footer-inner">

        <div class="footer-brand">
            <strong>ASM Crockery</strong>
            <p>Premium crockery & kitchenware for home and hospitality.</p>
        </div>

        <div class="footer-links">
            <h6>Categories</h6>
            <?php foreach ($menuCategories as $cat): ?>
                <a href="/asm-crockery/category/<?php echo $cat['slug']; ?>">
                    <?php echo htmlspecialchars($cat['name']); ?>
                </a>
            <?php endforeach; ?>
        </div>

    </div>

    <div class="footer-bottom">
        © <?php echo date('Y'); ?> ASM Crockery
    </div>
</footer>

<script src="/assets/js/main.js"></script>
<script>
const input = document.getElementById('searchInput');
const box = document.getElementById('searchResults');

let timer = null;

input.addEventListener('input', () => {
    clearTimeout(timer);
    const q = input.value.trim();
    if (q.length < 2) {
        box.innerHTML = '';
        box.style.display = 'none';
        return;
    }

    timer = setTimeout(() => {
        fetch(`/asm-crockery/api/search-suggest.php?q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(data => {
            box.innerHTML = '';
            data.forEach(p => {
                box.innerHTML += `
                    <a href="/asm-crockery/product/${p.slug}" class="search-item">
                        <img src="/asm-crockery/assets/uploads/${p.img}">
                        <div>
                            <div>${p.title}</div>
                            <small>₹${p.price}</small>
                        </div>
                    </a>
                `;
            });

            box.innerHTML += `
                <a href="/asm-crockery/search.php?q=${encodeURIComponent(q)}"
                   class="search-all">
                   View all results →
                </a>
            `;

            box.style.display = 'block';
        });
    }, 300);
});
</script>
</body>
</html>