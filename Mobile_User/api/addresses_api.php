<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_login();
require __DIR__ . '/../config/db.php';
$stmt = $conn->prepare('SELECT id, address_line, city, state, zip, country FROM addresses WHERE user_id = ?');
$stmt->execute([$_SESSION['user_id']]);
echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);