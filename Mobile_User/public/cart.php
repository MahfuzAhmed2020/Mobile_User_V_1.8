<?php
require_once __DIR__ . '/../config/session.php';
require_login();
require_once __DIR__ . '/../config/layout.php';
page_header('Your cart', 'cart', ['assets/commerce.js']);
?>
<div class="container">
<header class="page-header"><div><p class="eyebrow">YOUR SHOPPING BAG</p><h1>Your cart</h1><p class="muted">A few good things, ready for your everyday.</p></div><a class="continue-link" href="profile.php">Continue shopping →</a></header>
<nav class="steps" aria-label="Checkout progress"><span class="active" aria-current="step"><b>1</b> Cart</span><i aria-hidden="true"></i><span><b>2</b> Checkout</span><i aria-hidden="true"></i><span><b>3</b> Confirmation</span></nav>
<div id="pageNotice" class="notice" role="status" hidden></div>
<div id="cartEmpty" hidden></div>
<div id="cartLayout" class="split-layout">
<section class="panel cart-list" aria-label="Cart items"><div class="cart-heading">Items in your cart</div><div id="cart"><div class="skeleton"></div></div></section>
<aside class="panel summary-panel" aria-labelledby="summaryTitle"><h2 id="summaryTitle">Order summary</h2><div class="summary-row"><span>Items (<span id="cartCount">0</span>)</span><span id="cartSubtotal">$0.00</span></div><div class="summary-row"><span>Delivery</span><span>Included</span></div><div class="summary-row summary-total"><span>Total</span><span id="cartTotal">$0.00</span></div><a id="checkoutLink" class="button full" href="checkout.php">Continue to checkout →</a><p class="summary-note">Review your address and payment method in the next step. All prices are in USD.</p></aside>
</div></div>
<?php page_footer(); ?>