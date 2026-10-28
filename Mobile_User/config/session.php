<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
    ]);
    session_start();
}
if (!function_exists('is_logged_in')) {
    function is_logged_in() { return isset($_SESSION['user_id']); }
    function require_login() {
        if (is_logged_in()) { return; }
        if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        } else {
            header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
        }
        exit;
    }
}