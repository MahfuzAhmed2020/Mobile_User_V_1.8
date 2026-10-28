<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_login();
require __DIR__ . '/../config/db.php';
function profile_error(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(['success'=>false, 'message'=>$message]);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { profile_error(405, 'Use POST to update your profile.'); }
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) { profile_error(422, 'Provide your profile details.'); }
foreach (['first_name','last_name','email'] as $field) {
    if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '' || strlen(trim($data[$field])) > 255) {
        profile_error(422, 'Enter a valid first name, last name, and email address.');
    }
}
$first = trim($data['first_name']); $last = trim($data['last_name']); $email = trim($data['email']);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { profile_error(422, 'Enter a valid email address.'); }
$old_password = $data['old_password'] ?? '';
$new_password = $data['new_password'] ?? '';
$confirmation = $data['confirm_password'] ?? '';
if (!is_string($old_password) || !is_string($new_password) || !is_string($confirmation)) { profile_error(422, 'Enter valid password values.'); }
$user_id = (int) $_SESSION['user_id'];
try {
    $stmt = $conn->prepare('SELECT password FROM users WHERE id=?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) { profile_error(401, 'Please sign in again.'); }
    $check = $conn->prepare('SELECT id FROM users WHERE email=? AND id<>?');
    $check->execute([$email, $user_id]);
    if ($check->fetchColumn()) { profile_error(409, 'This email address is already used by another account.'); }
    if ($old_password !== '' || $new_password !== '' || $confirmation !== '') {
        if (!$old_password || !$new_password || !$confirmation) { profile_error(422, 'Enter your current password and confirm your new password.'); }
        if (!password_verify($old_password, $user['password'])) { profile_error(422, 'Your current password is incorrect.'); }
        if ($new_password !== $confirmation) { profile_error(422, 'Your new passwords do not match.'); }
        if (strlen($new_password) < 8 || !preg_match('/[A-Z]/',$new_password) || !preg_match('/[0-9]/',$new_password) || !preg_match('/[^A-Za-z0-9]/',$new_password)) {
            profile_error(422, 'Use at least 8 characters, an uppercase letter, a number, and a special character.');
        }
        $stmt = $conn->prepare('UPDATE users SET first_name=?,last_name=?,email=?,password=? WHERE id=?');
        $stmt->execute([$first,$last,$email,password_hash($new_password,PASSWORD_DEFAULT),$user_id]);
        session_regenerate_id(true);
    } else {
        $stmt = $conn->prepare('UPDATE users SET first_name=?,last_name=?,email=? WHERE id=?');
        $stmt->execute([$first,$last,$email,$user_id]);
    }
    $_SESSION['first_name']=$first; $_SESSION['last_name']=$last; $_SESSION['email']=$email;
    echo json_encode(['success'=>true, 'message'=>'Your account details have been updated.']);
} catch (PDOException $e) {
    if ($e->getCode() === '23000') { profile_error(409, 'This email address is already used by another account.'); }
    error_log('Profile update failed: '.$e->getMessage());
    profile_error(500, 'Unable to save your changes. Please try again.');
}