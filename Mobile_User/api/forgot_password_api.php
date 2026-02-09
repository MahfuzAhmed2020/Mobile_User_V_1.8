<?php
header('Content-Type: application/json');
include '../config/db.php';

/* =========================
   READ INPUT
========================= */
$data = json_decode(file_get_contents("php://input"), true);
$email = trim($data['email'] ?? '');

if (!$email) {
    echo json_encode([
        "success" => false,
        "message" => "Email is required"
    ]);
    exit;
}

/* =========================
   CHECK USER
========================= */
$stmt = $conn->prepare("SELECT id, first_name FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo json_encode([
        "success" => false,
        "message" => "Email not found"
    ]);
    exit;
}

/* =========================
   GENERATE TOKEN
========================= */
$token   = bin2hex(random_bytes(32));
$expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

/* =========================
   SAVE TOKEN
========================= */
$stmt = $conn->prepare("
    UPDATE users
    SET reset_token = ?, reset_expires = ?
    WHERE id = ?
");
$stmt->execute([$token, $expires, $user['id']]);

/* =========================
   BUILD RESET LINK
========================= */
$reset_link = "http://localhost/Mobile_User/public/reset_password.php?token=" . urlencode($token);

/* =========================
   EMAIL CONTENT
========================= */
$subject = "Password Reset Request";

$message = "Hi {$user['first_name']},

You requested to reset your password.

Click the link below to reset it:
$reset_link

This link will expire in 1 hour.

If you did not request this, please ignore this email.

— Mobile User Team";

/* =========================
   EMAIL HEADERS (IMPORTANT)
========================= */
$headers  = "From: Mobile User <no-reply@mobileuser.test>\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

/* =========================
   SEND EMAIL
========================= */
$sent = mail($email, $subject, $message, $headers);

if (!$sent) {
    echo json_encode([
        "success" => false,
        "message" => "Failed to send email. Check MailHog / sendmail config."
    ]);
    exit;
}

/* =========================
   RESPONSE
========================= */
echo json_encode([
    "success" => true,
    "message" => "Password reset email sent successfully"
]);
