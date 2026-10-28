<?php
require_once __DIR__ . '/../config/session.php';
require_login();
require_once __DIR__ . '/../config/layout.php';
page_header('My account', 'account', ['assets/account.js']);
?>
<div class="container">
<header class="page-header"><div><p class="eyebrow">YOUR ACCOUNT</p><h1>Account settings</h1><p class="muted">Keep your personal information up to date.</p></div></header>
<div id="pageNotice" class="notice" role="status" hidden></div>
<div class="account-layout">
<aside class="panel account-sidebar"><span class="avatar" data-user-initials aria-hidden="true"></span><h2 data-user-name></h2><p class="muted" id="accountEmail">Loading your account…</p><a class="selected" href="#personalDetails">Personal details</a><a href="#accountSecurity">Password & security</a><a href="orders.php">My orders →</a></aside>
<div class="stack">
<section class="panel" id="personalDetails">
<div class="settings-heading"><div><h2>Personal details</h2><p class="section-intro">Review your information. Choose Edit personal details when you want to make a change.</p></div><button class="button secondary" type="button" id="editProfile" aria-expanded="false" aria-controls="profileForm" disabled>Edit personal details</button></div>
<dl class="account-details" id="profileSummary"><div><dt>First name</dt><dd id="summaryFirstName">Loading…</dd></div><div><dt>Last name</dt><dd id="summaryLastName">Loading…</dd></div><div class="detail-wide"><dt>Email address</dt><dd id="summaryEmail">Loading…</dd></div></dl>
<div id="profileFeedback" class="form-feedback" role="status" aria-live="polite" hidden></div>
<form id="profileForm" hidden><fieldset id="profileFields" disabled style="border:0;padding:0;margin:0">
<div class="field-grid"><div class="field"><label for="firstName">First name</label><input type="text" id="firstName" name="first_name" maxlength="255" autocomplete="given-name" required></div><div class="field"><label for="lastName">Last name</label><input type="text" id="lastName" name="last_name" maxlength="255" autocomplete="family-name" required></div></div>
<div class="field"><label for="email">Email address</label><input type="email" id="email" name="email" maxlength="255" autocomplete="email" required><p class="field-help">Order confirmations and password-reset links are sent to this address.</p></div>

<div class="form-actions"><button class="button secondary" type="button" id="resetProfile">Cancel</button><button class="button" type="submit">Save changes</button></div>
</fieldset></form></section>
<section class="panel" id="accountSecurity"><div class="settings-heading"><div><h2>Password & security</h2><p class="section-intro">Manage your sign-in password separately from your personal details.</p></div><button class="button secondary" type="button" id="editPassword" aria-expanded="false" aria-controls="passwordForm" disabled>Change password</button></div><div id="passwordSummary" class="security-summary"><span class="security-mark" aria-hidden="true">✓</span><div><strong>Password protected</strong><p>You'll need your current password to set a new one.</p></div></div>
<div id="passwordFeedback" class="form-feedback" role="status" aria-live="polite" hidden></div>
<form id="passwordForm" hidden><fieldset id="passwordFields" disabled style="border:0;padding:0;margin:0">
<div class="field"><label for="oldPassword">Current password</label><input type="password" id="oldPassword" name="old_password" autocomplete="current-password" required></div>
<div class="field-grid"><div class="field"><label for="newPassword">New password</label><input type="password" id="newPassword" name="new_password" minlength="8" autocomplete="new-password" required aria-describedby="passwordHelp"></div><div class="field"><label for="confirmPassword">Confirm new password</label><input type="password" id="confirmPassword" name="confirm_password" minlength="8" autocomplete="new-password" required></div></div>
<p class="field-help" id="passwordHelp">Use at least 8 characters, including an uppercase letter, a number, and a special character.</p><div class="form-actions"><button class="button secondary" type="button" id="cancelPassword">Cancel</button><button class="button" type="submit">Update password</button></div>
</fieldset></form></section>
</div></div></div>
<?php page_footer(); ?>