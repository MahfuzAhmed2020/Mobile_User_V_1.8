<?php
require __DIR__ . '/database_settings.php';
try {
    $conn = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(503);
    if (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/')) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Database unavailable. Please try again later.']);
    } else {
        echo 'Database unavailable. Please contact the application administrator.';
    }
    exit;
}