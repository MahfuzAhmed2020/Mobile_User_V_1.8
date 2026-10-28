<?php
require_once __DIR__ . '/../config/session.php';
require_login();
require_once __DIR__ . '/../config/layout.php';
page_header('Checkout', 'checkout', ['assets/commerce.js']);
?>
<div class="container">
<div id="checkoutContent">
<header class="page-header"><div><p class="eyebrow">ONE LAST LOOK</p><h1>Checkout</h1><p class="muted">Review your details and place your order.</p></div><a class="continue-link" href="cart.php">← Return to cart</a></header>
<nav class="steps" aria-label="Checkout progress"><span class="done"><b>✓</b> Cart</span><i aria-hidden="true"></i><span class="active" aria-current="step"><b>2</b> Checkout</span><i aria-hidden="true"></i><span><b>3</b> Confirmation</span></nav>
<div id="pageNotice" class="notice" role="status" hidden></div>
<div id="checkoutEmpty" hidden></div>
<form id="checkoutForm" class="split-layout">
<div class="stack">
<section class="panel"><h2><span class="section-number">1</span>Delivery address</h2><p class="section-intro">Choose where you'd like your order delivered.</p><div class="field"><label for="address">Saved delivery address</label><select id="address" name="address_id" required disabled><option value="">Loading saved addresses…</option></select></div><div id="addressPreview" class="address-preview" hidden><strong>DELIVER TO</strong><span></span></div></section>
<section class="panel"><h2><span class="section-number">2</span>Payment method</h2><p class="section-intro">Select a saved card for this order.</p><div class="field"><label for="card">Payment card</label><select id="card" name="card_number" required disabled><option value="">Loading saved cards…</option></select></div><div id="cardPreview" class="card-preview" hidden><span class="card-symbol" aria-hidden="true"></span><div><strong id="cardEnding" class="small-text"></strong><div class="muted small-text">Sample payment card</div></div></div><p class="field-help">This store uses test cards. No real payment will be charged.</p></section>
<section class="panel"><h2><span class="section-number">3</span>Review your items</h2><ul id="reviewItems" class="review-list"><li class="muted">Loading your order…</li></ul><a class="continue-link" href="cart.php">Edit your cart →</a></section>
</div>
<aside class="panel summary-panel"><h2>Order summary</h2><div class="summary-row"><span>Items (<span id="cartCount">0</span>)</span><span id="cartSubtotal">$0.00</span></div><div class="summary-row"><span>Delivery</span><span>Included</span></div><div class="summary-row summary-total"><span>Total</span><span id="checkoutTotal">$0.00</span></div><button id="Checkout" class="button full" type="submit" disabled>Place order</button><p class="summary-note">Your confirmation and tracking number will be available after checkout.</p><p id="checkoutHint" class="field-help">Choose a delivery address and payment card to continue.</p></aside>
</form></div>
<section id="orderSuccess" class="panel success-panel" aria-live="polite" hidden></section>
</div>
<?php page_footer(); ?>