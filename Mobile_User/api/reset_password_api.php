<?php
header('Content-Type: application/json');
include '../config/db.php';

/* =========================
   READ INPUT
========================= */
$data = json_decode(file_get_contents("php://input"), true);

$token            = trim($data['token'] ?? '');
$password         = $data['password'] ?? '';
$confirm_password = $data['confirm_password'] ?? '';

if (!$token || !$password || !$confirm_password) {
    echo json_encode([
        "success" => false,
        "message" => "Token and passwords are required"
    ]);
    exit;
}

/* =========================
   PASSWORD VALIDATION
========================= */
$errors = [];

if (strlen($password) < 8) {
    $errors[] = "Password must be at least 8 characters";
}
if (!preg_match('/[A-Z]/', $password)) {
    $errors[] = "Password must contain at least one uppercase letter";
}
if (!preg_match('/[0-9]/', $password)) {
    $errors[] = "Password must contain at least one number";
}
if (!preg_match('/[!@#$%^&*]/', $password)) {
    $errors[] = "Password must contain at least one special character";
}
if ($password !== $confirm_password) {
    $errors[] = "Passwords do not match";
}

if ($errors) {
    echo json_encode([
        "success" => false,
        "errors" => $errors
    ]);
    exit;
}

/* =========================
   VALIDATE TOKEN
========================= */
$stmt = $conn->prepare("
    SELECT id, reset_expires
    FROM users
    WHERE reset_token = ?
");
$stmt->execute([$token]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode([
        "success" => false,
        "message" => "Invalid reset token"
    ]);
    exit;
}

if (strtotime($user['reset_expires']) < time()) {
    echo json_encode([
        "success" => false,
        "message" => "Reset token has expired"
    ]);
    exit;
}

/* =========================
   UPDATE PASSWORD
========================= */
$hashed = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("
    UPDATE users
    SET password = ?, reset_token = NULL, reset_expires = NULL
    WHERE id = ?
");
$stmt->execute([$hashed, $user['id']]);

/* =========================
   RESPONSE
========================= */
echo json_encode([
    "success" => true,
    "message" => "Password reset successful. You may now log in."
]);
