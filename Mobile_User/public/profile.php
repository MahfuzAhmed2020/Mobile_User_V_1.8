<?php
require_once __DIR__ . '/../config/session.php';
require_login();
require_once __DIR__ . '/../config/layout.php';
page_header('Shop', 'shop', ['assets/commerce.js']);
?>
<div class="container">
<header class="page-header"><div><p class="eyebrow">THE EVERYDAY COLLECTION</p><h1>Small essentials. Better days.</h1><p class="muted">Discover useful finds for your everyday routine.</p></div><a class="button secondary" href="cart.php">View your cart <span aria-hidden="true">→</span></a></header>
<div id="pageNotice" class="notice" role="status" hidden></div>
<div class="toolbar"><div class="search-field"><span aria-hidden="true">⌕</span><label class="visually-hidden" for="productSearch">Search products</label><input type="search" id="productSearch" placeholder="Search the collection" autocomplete="off"></div><div class="sort-field"><label for="productSort">Sort by</label><select id="productSort"><option value="featured">Featured</option><option value="price-asc">Price: low to high</option><option value="price-desc">Price: high to low</option><option value="name">Name: A–Z</option></select></div></div>
<p class="catalog-count results-status" id="productCount" role="status">Loading the collection…</p>
<section id="products" class="product-grid" aria-label="Products"><div class="skeleton"></div><div class="skeleton"></div><div class="skeleton"></div></section>
<p class="shop-footnote">Make it yours. Add your favorites to the cart, then review your delivery details at checkout.</p>
</div>
<?php page_footer(); ?>