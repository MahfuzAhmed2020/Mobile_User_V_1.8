<?php
require_once dirname(__DIR__) . '/config/mailer.php';
function sendTrackingEmail(PDO $conn, int $order_id): bool {
    $stmt = $conn->prepare('SELECT o.id, o.total, u.email, u.first_name, t.tracking_number, t.status,
        a.address_line, a.city, a.state, a.zip, a.country
        FROM orders o JOIN users u ON u.id=o.user_id
        JOIN order_tracking t ON t.order_id=o.id
        JOIN addresses a ON a.id=o.address_id
        WHERE o.id=? AND o.deleted_at IS NULL AND t.deleted_at IS NULL');
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) { return false; }
    $stmt = $conn->prepare('SELECT product_name, price, quantity FROM order_items WHERE order_id=?');
    $stmt->execute([$order_id]);
    $body = "Hi {$order['first_name']},\n\nYour order #{$order['id']} has been placed.\n\nItems:\n";
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $subtotal = number_format((float) $item['price'] * $item['quantity'], 2, '.', '');
        $body .= $item['product_name'] . ' x ' . $item['quantity'] . ' = $' . $subtotal . "\n";
    }
    $body .= "\nOrder Total: $" . $order['total']
        . "\nTracking Number: " . $order['tracking_number']
        . "\nStatus: " . $order['status']
        . "\nDelivery Address: " . implode(', ', array_intersect_key($order, array_flip(['address_line','city','state','zip','country'])))
        . "\n\nView your order: " . app_url('public/orders.php') . '?tracking=' . urlencode($order['tracking_number'])
        . "\n\nMobile User Team";
    return send_app_mail($order['email'], 'Your Order Confirmation & Tracking', $body);
}