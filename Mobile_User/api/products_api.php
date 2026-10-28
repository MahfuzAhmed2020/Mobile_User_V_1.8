<?php
header('Content-Type: application/json');
include '../config/db.php';
include '../config/session.php';

require_login();

$stmt = $conn->prepare("SELECT id, name, description, price FROM products ORDER BY id");
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'data' => $products]);
?>
