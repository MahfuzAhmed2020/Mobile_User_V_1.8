<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

include '../config/db.php';
include '../config/session.php';
// require_login(); // enable later

$data = json_decode(file_get_contents("php://input"), true);

$address_id = $data['address_id'] ?? '';

if (!$address_id) {
    echo json_encode(["success"=>false,"message"=>"Address is required"]);
    exit;
}

$user_id = $_SESSION['user_id'] ?? 1; // TEMP for testing

/* =========================
   FETCH CART
========================= */
$stmt = $conn->prepare("
    SELECT c.product_id, p.price, c.quantity
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id = ?
");
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$cart_items) {
    echo json_encode(["success"=>false,"message"=>"Cart is empty"]);
    exit;
}

/* =========================
   CALCULATE TOTAL
========================= */
$total = 0;
foreach ($cart_items as $item) {
    $total += $item['price'] * $item['quantity'];
}

/* =========================
   CREATE ORDER
========================= */
$stmt = $conn->prepare("
    INSERT INTO orders (user_id, address_id, total)
    VALUES (?,?,?)
");
$stmt->execute([$user_id, $address_id, $total]);
$order_id = $conn->lastInsertId();

/* =========================
   ORDER ITEMS
========================= */
$stmtItem = $conn->prepare("
    INSERT INTO order_items (order_id, product_id, price, quantity)
    VALUES (?,?,?,?)
");

foreach ($cart_items as $item) {
    $stmtItem->execute([
        $order_id,
        $item['product_id'],
        $item['price'],
        $item['quantity']
    ]);
}

/* =========================
   TRACKING
========================= */
$tracking_number = strtoupper(substr(md5(uniqid()), 0, 10));

$stmt = $conn->prepare("
    INSERT INTO order_tracking (order_id, tracking_number)
    VALUES (?,?)
");
$stmt->execute([$order_id, $tracking_number]);

/* =========================
   CLEAR CART
========================= */
$stmt = $conn->prepare("DELETE FROM cart WHERE user_id=?");
$stmt->execute([$user_id]);

/* =========================
   SEND CHECKOUT EMAIL (ADDED)
========================= */
$stmt = $conn->prepare("SELECT email, first_name FROM users WHERE id=?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && !empty($user['email'])) {

    $order_link = "http://localhost/Mobile_User/public/orders.php?tracking=" . $tracking_number;

    $subject = "Your Order Confirmation & Tracking";

    $message = "Hi {$user['first_name']},

Your order has been placed successfully 🎉

Tracking Number: {$tracking_number}
Order Total: \${$total}

View your order:
{$order_link}

Thank you for shopping with us.
Mobile User Team";

    $headers = "From: Mobile User <no-reply@mobileuser.test>";

    $mail_sent = mail($user['email'], $subject, $message, $headers);

    if (!$mail_sent) {
        error_log("Checkout email FAILED for order ID: $order_id");
    }
}

/* =========================
   RESPONSE
========================= */
echo json_encode([
    "success" => true,
    "tracking_number" => $tracking_number
]);
exit;
