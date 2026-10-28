<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/layout.php';
if (is_logged_in()) { header('Location: profile.php'); exit; }
page_header('Signed out');
?>
<div class="container"><section class="panel signed-out"><div class="empty-icon" aria-hidden="true">✓</div><p class="eyebrow">UNTIL NEXT TIME</p><h1>You're signed out.</h1><p>Your cart and orders are saved in your account. Sign in whenever you're ready to pick up where you left off.</p><a class="button" href="login.php">Sign in again</a><p style="margin:20px 0 0"><a class="small-text" href="index.php">New here? Create an account</a></p></section></div>
<?php page_footer(); ?>