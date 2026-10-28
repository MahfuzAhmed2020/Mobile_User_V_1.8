<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/session.php';
require_login();
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/app.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['success'=>false, 'message'=>'Use POST.']); exit;
}
$data = json_decode(file_get_contents('php://input'), true);
$address_id = filter_var($data['address_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
$card_number = (string) ($data['card_number'] ?? '');
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare('SELECT id FROM addresses WHERE id=? AND user_id=?');
$stmt->execute([$address_id ?: 0, $user_id]);
$address = $stmt->fetchColumn();
$stmt = $conn->prepare('SELECT id FROM cards WHERE card_number=? AND user_id=?');
$stmt->execute([$card_number, $user_id]);
if (!$address || !$stmt->fetchColumn()) {
    http_response_code(422);
    echo json_encode(['success'=>false, 'message'=>'Select an address and card belonging to your account.']); exit;
}
try {
    $conn->beginTransaction();
    $stmt = $conn->prepare('SELECT c.product_id, p.name, p.price, c.quantity FROM cart c JOIN products p ON p.id=c.product_id WHERE c.user_id=? FOR UPDATE');
    $stmt->execute([$user_id]);
    $cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$cart_items) {
        $conn->rollBack(); echo json_encode(['success'=>false, 'message'=>'Cart is empty']); exit;
    }
    $totalCents = 0;
    foreach ($cart_items as $item) {
        if ($item['quantity'] < 1) { throw new RuntimeException('Invalid cart quantity'); }
        $totalCents += (int) round((float) $item['price'] * 100) * $item['quantity'];
    }
    $total = number_format($totalCents / 100, 2, '.', '');
    if (isset($data['expected_total']) && (!is_numeric($data['expected_total']) || number_format((float) $data['expected_total'], 2, '.', '') !== $total)) {
        $conn->rollBack();
        http_response_code(409);
        echo json_encode(['success'=>false, 'message'=>'Your cart has changed. Review the updated total before placing your order.']);
        exit;
    }
    $stmt = $conn->prepare('INSERT INTO orders (user_id, address_id, total) VALUES (?,?,?)');
    $stmt->execute([$user_id, $address_id, $total]);
    $order_id = $conn->lastInsertId();
    $stmt = $conn->prepare('INSERT INTO order_items (order_id, product_id, product_name, price, quantity) VALUES (?,?,?,?,?)');
    foreach ($cart_items as $item) { $stmt->execute([$order_id, $item['product_id'], $item['name'], $item['price'], $item['quantity']]); }
    $tracking_number = strtoupper(bin2hex(random_bytes(16)));
    $stmt = $conn->prepare('INSERT INTO order_tracking (order_id, tracking_number) VALUES (?,?)');
    $stmt->execute([$order_id, $tracking_number]);
    $stmt = $conn->prepare('DELETE FROM cart WHERE user_id=?');
    $stmt->execute([$user_id]);
    $conn->commit();
} catch (Throwable $e) {
    if ($conn->inTransaction()) { $conn->rollBack(); }
    error_log('Checkout failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>'Unable to place order. Your cart has been kept.']); exit;
}
require_once __DIR__ . '/../utils/send_tracking_email.php';
$mail_sent = sendTrackingEmail($conn, (int) $order_id);
echo json_encode([
    'success' => true,
    'message' => 'Order placed successfully',
    'order_id' => $order_id,
    'tracking_number' => $tracking_number,
    'email_sent' => $mail_sent,
]);
exit;
