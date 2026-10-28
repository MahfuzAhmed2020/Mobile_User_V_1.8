<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_login();
require __DIR__ . '/../config/db.php';
$user_id = (int) $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true) ?: [];
function cart_response(PDO $conn, int $userId, string $message = ''): void {
    $stmt = $conn->prepare('SELECT c.product_id, p.name, p.price, c.quantity FROM cart c JOIN products p ON p.id=c.product_id WHERE c.user_id=? ORDER BY c.id');
    $stmt->execute([$userId]);
    $cart = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $totalCents = 0;
    $count = 0;
    foreach ($cart as &$item) {
        $cents = (int) round((float) $item['price'] * 100) * (int) $item['quantity'];
        $item['subtotal'] = number_format($cents / 100, 2, '.', '');
        $totalCents += $cents;
        $count += (int) $item['quantity'];
    }
    unset($item);
    echo json_encode(['success'=>true, 'message'=>$message, 'data'=>$cart,
        'total'=>number_format($totalCents / 100, 2, '.', ''), 'item_count'=>$count]);
}
function cart_error(int $status, string $message): void {
    http_response_code($status);
    echo json_encode(['success'=>false, 'message'=>$message]);
    exit;
}
if ($method === 'GET') { cart_response($conn, $user_id); exit; }
if (!in_array($method, ['POST','PATCH','DELETE'], true)) { cart_error(405, 'Method not allowed'); }
$pid = filter_var($data['product_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
if (!$pid) { cart_error(422, 'A valid product is required'); }
if ($method === 'DELETE') {
    $stmt = $conn->prepare('DELETE FROM cart WHERE user_id=? AND product_id=?');
    $stmt->execute([$user_id, $pid]);
    cart_response($conn, $user_id, 'Item removed'); exit;
}
if ($method === 'POST') {
    $check = $conn->prepare('SELECT id FROM products WHERE id=?');
    $check->execute([$pid]);
    if (!$check->fetchColumn()) { cart_error(404, 'Product not found'); }
    $stmt = $conn->prepare('INSERT INTO cart (user_id,product_id,quantity) VALUES (?,?,1) ON DUPLICATE KEY UPDATE quantity=LEAST(quantity+1,999)');
    $stmt->execute([$user_id, $pid]);
    cart_response($conn, $user_id, 'Product added to cart'); exit;
}
$absolute = array_key_exists('quantity', $data);
$value = filter_var($absolute ? $data['quantity'] : ($data['change'] ?? null), FILTER_VALIDATE_INT);
if ($value === false || $value === null || ($absolute && $value < 0) || abs($value) > 999) {
    cart_error(422, 'Quantity must be a whole number between 0 and 999');
}
try {
    $conn->beginTransaction();
    $stmt = $conn->prepare('SELECT quantity FROM cart WHERE user_id=? AND product_id=? FOR UPDATE');
    $stmt->execute([$user_id, $pid]);
    $current = $stmt->fetchColumn();
    if ($current === false) {
        $conn->rollBack(); cart_error(404, 'Cart item not found');
    }
    $quantity = $absolute ? $value : max(0, (int) $current + $value);
    if ($quantity > 999) {
        $conn->rollBack(); cart_error(422, 'Maximum quantity is 999');
    }
    if ($quantity === 0) {
        $stmt = $conn->prepare('DELETE FROM cart WHERE user_id=? AND product_id=?');
        $stmt->execute([$user_id, $pid]);
    } else {
        $stmt = $conn->prepare('UPDATE cart SET quantity=? WHERE user_id=? AND product_id=?');
        $stmt->execute([$quantity, $user_id, $pid]);
    }
    $conn->commit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) { $conn->rollBack(); }
    error_log('Cart update failed: ' . $e->getMessage());
    cart_error(500, 'Unable to update cart');
}
cart_response($conn, $user_id, 'Cart updated');