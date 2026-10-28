<?php
require_once __DIR__ . '/../config/session.php';
require_login();
require_once __DIR__ . '/../config/layout.php';
page_header('My orders', 'orders', ['assets/orders.js']);
?>
<div class="container">
<header class="page-header"><div><p class="eyebrow">YOUR PURCHASES</p><h1>My orders</h1><p class="muted">Track your purchases and manage delivery details.</p></div><a class="button secondary" href="profile.php">Continue shopping →</a></header>
<div id="notice" class="notice" role="status" aria-live="polite" hidden></div>
<div id="orders" aria-live="polite"><div class="skeleton"></div></div>
<p id="all-orders" hidden><a href="orders.php">View all orders →</a></p>
</div>
<?php page_footer(); ?>