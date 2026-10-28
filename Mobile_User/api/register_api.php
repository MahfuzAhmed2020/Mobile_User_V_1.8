<?php
header('Content-Type: application/json');
include '../config/db.php';

$data = json_decode(file_get_contents("php://input"), true);

$first_name = trim($data['first_name'] ?? '');
$last_name  = trim($data['last_name'] ?? '');
$email      = trim($data['email'] ?? '');
$password   = $data['password'] ?? '';
$confirm_password = $data['confirm_password'] ?? '';

$errors = [];
if ($first_name === '' || $last_name === '') $errors[] = 'First and last name are required';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address';

/* ---------------- PASSWORD VALIDATION ---------------- */
if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters";
if (!preg_match('/[A-Z]/', $password)) $errors[] = "Password must contain a capital letter";
if (!preg_match('/[0-9]/', $password)) $errors[] = "Password must contain a number";
if (!preg_match('/[!$%?*@#]/', $password)) $errors[] = "Password must contain a special character";
if ($password !== $confirm_password) $errors[] = "Passwords do not match";

if ($errors) {
    echo json_encode(['success'=>false,'errors'=>$errors]);
    exit;
}

/* ---------------- CHECK EMAIL ---------------- */
$stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
$stmt->execute([$email]);
if ($stmt->rowCount() > 0) {
    echo json_encode(['success'=>false,'message'=>'Email already exists']);
    exit;
}

/* ---------------- INSERT USER ---------------- */
$hashed = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare(
    "INSERT INTO users (first_name,last_name,email,password) VALUES (?,?,?,?)"
);
$stmt->execute([$first_name,$last_name,$email,$hashed]);

$user_id = $conn->lastInsertId();
require_once __DIR__ . '/../config/demo_cards.php';
add_demo_cards($conn, (int) $user_id);
require_once __DIR__ . '/../config/demo_addresses.php';
add_demo_addresses($conn, (int) $user_id);

require_once __DIR__ . '/../config/mailer.php';
$mail_sent = send_welcome_email($email, $first_name);

/* ---------------- RESPONSE ---------------- */
echo json_encode([
    'success'=>true,
    'message'=>'Registration successful',
    'user_id'=>$user_id,
    'email_sent'=>$mail_sent
]);
