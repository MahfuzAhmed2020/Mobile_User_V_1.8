<?php
header('Content-Type: application/json');
include '../config/db.php';

if (isset($_GET['PHPSESSID'])) {
    session_id($_GET['PHPSESSID']);
}

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true);

function get_cart_and_total($conn, $user_id) {
    $stmt = $conn->prepare(
        "SELECT c.product_id, p.name, p.price, c.quantity
         FROM cart c
         JOIN products p ON c.product_id = p.id
         WHERE c.user_id = ?"
    );
    $stmt->execute([$user_id]);
    $cart = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $total = 0;
    foreach ($cart as $item) {
        $total += $item['price'] * $item['quantity'];
    }

    return ['cart' => $cart, 'total' => number_format($total, 2)];
}

/* GET CART */
if ($method === 'GET') {
    $result = get_cart_and_total($conn, $user_id);
    echo json_encode(['success' => true, 'data' => $result['cart'], 'total' => $result['total']]);
    exit;
}

/* ADD TO CART */
if ($method === 'POST' && isset($data['product_id'])) {
    $pid = $data['product_id'];

    $stmt = $conn->prepare("SELECT quantity FROM cart WHERE user_id=? AND product_id=?");
    $stmt->execute([$user_id, $pid]);

    if ($stmt->rowCount() > 0) {
        // Increment by 1
        $stmt = $conn->prepare("UPDATE cart SET quantity = quantity + 1 WHERE user_id=? AND product_id=?");
        $stmt->execute([$user_id, $pid]);
    } else {
        $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?,?,1)");
        $stmt->execute([$user_id, $pid]);
    }

    $result = get_cart_and_total($conn, $user_id);
    echo json_encode(['success' => true, 'message' => 'Product added to cart', 'data' => $result['cart'], 'total' => $result['total']]);
    exit;
}

/* UPDATE QUANTITY */
if ($method === 'PATCH') {
    $pid = $data['product_id'];
    $change = $data['change'] ?? 0;

    // Get current quantity
    $stmt = $conn->prepare("SELECT quantity FROM cart WHERE user_id=? AND product_id=?");
    $stmt->execute([$user_id, $pid]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC)['quantity'] ?? 0;

    $newQty = $current + $change;
    if ($newQty <= 0) {
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id=? AND product_id=?");
        $stmt->execute([$user_id, $pid]);
    } else {
        $stmt = $conn->prepare("UPDATE cart SET quantity=? WHERE user_id=? AND product_id=?");
        $stmt->execute([$newQty, $user_id, $pid]);
    }

    $result = get_cart_and_total($conn, $user_id);
    echo json_encode(['success' => true, 'message' => 'Cart updated', 'data' => $result['cart'], 'total' => $result['total']]);
    exit;
}

/* DELETE ITEM */
if ($method === 'DELETE') {
    $pid = $data['product_id'];
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id=? AND product_id=?");
    $stmt->execute([$user_id, $pid]);

    $result = get_cart_and_total($conn, $user_id);
    echo json_encode(['success' => true, 'message' => 'Item removed', 'data' => $result['cart'], 'total' => $result['total']]);
    exit;
}
