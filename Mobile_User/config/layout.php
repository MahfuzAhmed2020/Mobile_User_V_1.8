<?php
function page_header(string $title, string $active = '', array $scripts = [], array $metadata = []): void {
    $name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? ''));
    $initials = strtoupper(substr($_SESSION['first_name'] ?? 'M', 0, 1) . substr($_SESSION['last_name'] ?? 'U', 0, 1));
    $escape = fn($value) => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $escape($title) ?> | Mobile User</title>
<?php foreach ($metadata as $key=>$value): ?>
<?php if ($key === 'canonical'): ?><link rel="canonical" href="<?= $escape($value) ?>">
<?php elseif (in_array($key, ['description','og:title','og:description','og:url','og:type'], true)): ?><meta <?= str_starts_with($key, 'og:') ? 'property' : 'name' ?>="<?= $escape($key) ?>" content="<?= $escape($value) ?>">
<?php endif; ?>
<?php endforeach; ?>
<link rel="stylesheet" href="assets/store.css">
<?php if ($active === 'orders'): ?><link rel="stylesheet" href="assets/orders.css"><?php endif; ?>
<script src="assets/app.js" defer></script>
<?php foreach ($scripts as $script): ?><script src="<?= $escape($script) ?>" defer></script><?php endforeach; ?>
</head>
<body data-page="<?= $escape($active) ?>">
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
<div class="header-inner">
    <a class="brand" href="profile.php"><span class="brand-mark" aria-hidden="true">M</span><span>Mobile User<span class="brand-caption">EVERYDAY ESSENTIALS</span></span></a>
    <?php if (isset($_SESSION['user_id'])): ?>
    <nav class="main-nav" aria-label="Main navigation">
    <?php foreach (['shop'=>['profile.php','Shop'], 'cart'=>['cart.php','Cart'], 'orders'=>['orders.php','My orders'], 'account'=>['update_profile.php','My account']] as $key=>$item): ?>
        <a href="<?= $item[0] ?>" <?= $active === $key ? 'aria-current="page"' : '' ?> <?= $key === 'orders' ? 'id="My_Orders"' : ($key === 'account' ? 'id="Update_Profile"' : '') ?>><?= $item[1] ?><?php if ($key === 'cart'): ?> <span class="cart-badge" data-cart-count aria-label="Items in cart">0</span><?php endif; ?></a>
    <?php endforeach; ?>
    </nav>
    <div class="account-nav"><span class="avatar" data-user-initials aria-hidden="true"><?= $escape($initials) ?></span><span class="account-name" data-user-name><?= $escape($name) ?></span><button type="button" class="text-button" data-signout>Sign out</button></div>
    <?php else: ?><a class="button secondary small" href="login.php">Sign in</a><?php endif; ?>
</div>
</header>
<div id="globalNotice" class="toast" role="status" aria-live="polite" hidden></div>
<main id="main" class="site-main">
<?php
}
function page_footer(): void {
?>
</main>
<dialog id="shareDialog" class="confirmation" aria-labelledby="shareTitle">
    <h2 id="shareTitle">Share this product</h2><p>Send this link to someone you'd like to share it with.</p>
    <label class="field-label" for="shareLink">Product link</label><input id="shareLink" type="text" readonly>
    <p id="shareFeedback" class="field-help" role="status" aria-live="polite"></p>
    <div class="dialog-actions"><button type="button" class="button secondary" id="closeShare">Done</button><button type="button" class="button" id="copyShare">Copy link</button></div>
</dialog>
<footer class="site-footer"><div class="container footer-inner"><span>© <?= date('Y') ?> Mobile User</span><span>Thoughtful essentials for everyday life.</span></div></footer>
<dialog id="confirmDialog" class="confirmation" aria-labelledby="confirmTitle" aria-describedby="confirmDescription">
    <div class="dialog-icon" aria-hidden="true">↗</div>
    <h2 id="confirmTitle">Sign out of your account?</h2>
    <p id="confirmDescription">Your cart and orders will be saved. You can sign in again whenever you're ready.</p>
    <div class="dialog-actions"><button type="button" class="button secondary" data-dialog-cancel>Stay signed in</button><button type="button" class="button" data-dialog-confirm>Sign out</button></div>
</dialog>
</body>
</html>
<?php
}
