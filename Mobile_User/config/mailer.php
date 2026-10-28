<?php
use PHPMailer\PHPMailer\PHPMailer;

require_once dirname(__DIR__) . '/vendor/phpmailer/src/Exception.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/src/SMTP.php';
require_once dirname(__DIR__) . '/vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/app.php';

function send_app_mail(string $to, string $subject, string $body): bool {
    if (getenv('MAIL_ENABLED') === '0') { return false; }
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = getenv('MAIL_HOST') ?: '127.0.0.1';
        $mail->Port = (int) (getenv('MAIL_PORT') ?: '1025');
        $mail->SMTPAuth = false;
        $mail->SMTPAutoTLS = false;
        $mail->SMTPSecure = '';
        $mail->Timeout = 5;
        $mail->CharSet = 'UTF-8';
        $mail->setFrom('no-reply@mobileuser.test', 'Mobile User');
        $mail->addAddress($to);
        $mail->Subject = $subject;
        $mail->Body = $body;
        return $mail->send();
    } catch (Throwable $e) {
        error_log('Email delivery failed: ' . $e->getMessage());
        return false;
    }
}

function send_welcome_email(string $email, string $firstName): bool {
    return send_app_mail($email, 'Welcome to Mobile User',
        "Hi $firstName,\n\nYour account has been created successfully.\n\n"
        . 'Log in: ' . app_url('public/login.php')
        . "\n\nYou can now shop, place orders, and track them from My Orders.\n\nMobile User Team");
}

function send_address_change_email(string $email, string $firstName, int $orderId, string $previousAddress, string $newAddress, string $tracking): bool {
    return send_app_mail($email, "Delivery address updated for order #$orderId",
        "Hi $firstName,\n\nThe delivery address for your order #$orderId has been updated.\n\n"
        . "Previous delivery address:\n$previousAddress\n\nNew delivery address:\n$newAddress\n\n"
        . "Tracking number: $tracking\n\nView your order: "
        . app_url('public/orders.php') . '?tracking=' . urlencode($tracking)
        . "\n\nIf you did not request this change, please contact support.\n\nMobile User Team");
}
