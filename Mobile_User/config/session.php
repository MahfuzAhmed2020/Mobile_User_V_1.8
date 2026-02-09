<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* Prevent redeclaration */
if (!function_exists('is_logged_in')) {

    function is_logged_in() {
        return isset($_SESSION['user_id']);
    }

function require_login() {
    if (!isset($_SESSION['user_id'])) {
        $current = $_SERVER['REQUEST_URI']; // get current URL
        header("Location: /Mobile_User/public/login.php?redirect=" . urlencode($current));
        exit;
    }
}

}
