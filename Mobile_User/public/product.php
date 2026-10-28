<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/layout.php';
require_once __DIR__ . '/../config/app.php';
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
$product = false;
if ($id) {
    require __DIR__ . '/../config/db.php';
    $query = $conn->prepare('SELECT id,name,description,price FROM products WHERE id=?');
    $query->execute([$id]);
    $product = $query->fetch(PDO::FETCH_ASSOC);
}
if (!$product) {
    http_response_code(404);
    page_header('Product not found', 'product');
    echo '<div class="container"><section class="empty"><h1>Product not found</h1><p>This product link is unavailable. Explore the shop to find something else.</p><a class="button" href="profile.php">Visit the shop</a></section></div>';
    page_footer();
    exit;
}
$escape = fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$url = app_url('public/product.php') . '?id=' . $product['id'];
$description = $product['description'] ?: 'An everyday essential from Mobile User.';
page_header($product['name'], 'product', ['assets/commerce.js','assets/product.js'], [
    'canonical'=>$url, 'description'=>$description, 'og:title'=>$product['name'],
    'og:description'=>$description . ' — $' . number_format((float)$product['price'],2) . ' USD',
    'og:url'=>$url, 'og:type'=>'product',
]);
?>
<div class="container">
<a class="continue-link" style="margin:0 0 24px" href="profile.php">← Explore the collection</a>
<article class="product-detail panel" id="productDetails" data-id="<?= (int)$product['id'] ?>" data-name="<?= $escape($product['name']) ?>" data-url="<?= $escape($url) ?>">
    <div id="detailArt" class="detail-art" aria-hidden="true"></div>
    <div class="detail-copy"><p class="eyebrow">THE EVERYDAY COLLECTION</p><h1><?= $escape($product['name']) ?></h1><p class="detail-price">$<?= number_format((float)$product['price'],2) ?> <span>USD</span></p><p class="muted"><?= $escape($description) ?></p>
    <div class="detail-actions">
    <?php if (is_logged_in()): ?><button class="button" type="button" id="detailAdd">Add to cart +</button><a class="button secondary" href="cart.php">View cart</a>
    <?php else: ?><a class="button" href="login.php?redirect=<?= urlencode(parse_url($url,PHP_URL_PATH) . '?id=' . $product['id']) ?>">Sign in to add to cart</a><?php endif; ?>
    <button class="button secondary" type="button" id="detailShare">↗ Share product</button>
    </div><p class="field-help">Share this product link with friends. They can view the details without an account.</p></div>
</article></div>
<?php page_footer(); ?>
