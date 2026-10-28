<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false,'message'=>'Use POST to sign out.']);
    exit;
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'=>time()-42000, 'path'=>$params['path'], 'domain'=>$params['domain'],
        'secure'=>$params['secure'], 'httponly'=>$params['httponly'], 'samesite'=>$params['samesite'] ?: 'Lax',
    ]);
}
session_destroy();
echo json_encode(['success'=>true,'message'=>'You have been signed out.']);