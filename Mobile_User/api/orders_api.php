<?php
header('Content-Type: application/json');
include '../config/db.php';
require_once __DIR__ . '/../config/mailer.php';
include '../config/session.php';
require_login();

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents("php://input"), true);

// ---------------- GET ORDERS ----------------
if($_SERVER['REQUEST_METHOD']==='GET'){

    $stmt = $conn->prepare("
        SELECT o.id, o.total, o.created_at, t.tracking_number, t.status, o.whitelist_address_id,
               a.address_line AS a_line, a.city AS a_city, a.state AS a_state, a.zip AS a_zip, a.country AS a_country,
               w.address_line AS b_line, w.city AS b_city, w.state AS b_state, w.zip AS b_zip, w.country AS b_country
        FROM orders o
        LEFT JOIN order_tracking t ON t.order_id=o.id AND t.deleted_at IS NULL
        LEFT JOIN addresses a ON a.id=o.address_id
        LEFT JOIN addresses_whitelist w ON w.id=o.whitelist_address_id
        WHERE o.user_id=? AND o.deleted_at IS NULL
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch whitelist addresses
    $stmtB = $conn->prepare("SELECT * FROM addresses_whitelist WHERE user_id=?");
    $stmtB->execute([$user_id]);
    $setB = $stmtB->fetchAll(PDO::FETCH_ASSOC);

    foreach($orders as &$o){
        // Delivery address logic
        if($o['b_line']){
            $o['delivery_address'] = "{$o['b_line']}, {$o['b_city']}, {$o['b_state']} {$o['b_zip']}, {$o['b_country']}";
        } else {
            $o['delivery_address'] = "{$o['a_line']}, {$o['a_city']}, {$o['a_state']} {$o['a_zip']}, {$o['a_country']}";
        }

        // Order items
        $stmtItems = $conn->prepare("
            SELECT oi.product_name AS name, oi.quantity, oi.price
            FROM order_items oi
            JOIN products p ON p.id=oi.product_id
            WHERE oi.order_id=?
        ");
        $stmtItems->execute([$o['id']]);
        $o['items'] = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

        $o['addresses_set_b'] = $setB;
        $o['can_update_address'] = strcasecmp($o['status'] ?? '', 'Processing') === 0;
    }

    echo json_encode(["success"=>true,"data"=>$orders]);
    exit;
}

// ---------------- PATCH: APPLY SET B ----------------
if($_SERVER['REQUEST_METHOD']==='PATCH'){
    $orderId = filter_var($data['order_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    $addressId = filter_var($data['whitelist_address_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if (!$orderId || !$addressId) {
        http_response_code(422);
        echo json_encode(['success'=>false, 'message'=>'Select an order and a saved delivery address.']);
        exit;
    }
    try {
        $conn->beginTransaction();
        $check = $conn->prepare("SELECT o.id, o.whitelist_address_id, t.status, t.tracking_number, u.email, u.first_name,
            CONCAT_WS(', ', COALESCE(w.address_line,a.address_line), COALESCE(w.city,a.city), COALESCE(w.state,a.state), COALESCE(w.zip,a.zip), COALESCE(w.country,a.country)) AS previous_address
            FROM orders o
            JOIN users u ON u.id=o.user_id
            LEFT JOIN addresses a ON a.id=o.address_id
            LEFT JOIN addresses_whitelist w ON w.id=o.whitelist_address_id
            LEFT JOIN order_tracking t ON t.order_id=o.id AND t.deleted_at IS NULL
            WHERE o.id=? AND o.user_id=? AND o.deleted_at IS NULL FOR UPDATE");
        $check->execute([$orderId, $user_id]);
        $order = $check->fetch(PDO::FETCH_ASSOC);
        if (!$order) {
            $conn->rollBack();
            http_response_code(404);
            echo json_encode(['success'=>false, 'message'=>'Order not found.']);
            exit;
        }
        if (strcasecmp($order['status'] ?? '', 'Processing') !== 0) {
            $conn->rollBack();
            http_response_code(409);
            echo json_encode(['success'=>false, 'message'=>'The delivery address can only be changed while the order is processing.']);
            exit;
        }
        $check = $conn->prepare('SELECT address_line, city, state, zip, country FROM addresses_whitelist WHERE id=? AND user_id=?');
        $check->execute([$addressId, $user_id]);
        $address = $check->fetch(PDO::FETCH_ASSOC);
        if (!$address) {
            $conn->rollBack();
            http_response_code(422);
            echo json_encode(['success'=>false, 'message'=>'Select a saved delivery address belonging to your account.']);
            exit;
        }
        $newAddress = implode(', ', $address);
        if ((int) $order['whitelist_address_id'] === $addressId) {
            $conn->commit();
            echo json_encode(['success'=>true, 'message'=>'The delivery address is already up to date.', 'changed'=>false, 'email_sent'=>false, 'delivery_address'=>$newAddress, 'whitelist_address_id'=>$addressId]);
            exit;
        }
        $stmt = $conn->prepare('UPDATE orders SET whitelist_address_id=? WHERE id=? AND user_id=? AND deleted_at IS NULL');
        $stmt->execute([$addressId, $orderId, $user_id]);
        $conn->commit();
        $emailSent = send_address_change_email($order['email'], $order['first_name'], $orderId, $order['previous_address'], $newAddress, $order['tracking_number'] ?? '');
        echo json_encode(['success'=>true, 'message'=>'Delivery address saved.', 'changed'=>true, 'email_sent'=>$emailSent, 'delivery_address'=>$newAddress, 'whitelist_address_id'=>$addressId]);
    } catch (Throwable $e) {
        if ($conn->inTransaction()) { $conn->rollBack(); }
        error_log('Delivery address update failed: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success'=>false, 'message'=>'Unable to update the delivery address. Please try again.']);
    }
    exit;
}

// ---------------- DELETE: CANCEL ORDER ----------------
if($_SERVER['REQUEST_METHOD'] === 'DELETE'){
    $data = json_decode(file_get_contents("php://input"), true);
    $order_id = $data['order_id'] ?? '';

    if(!$order_id){
        echo json_encode(["success"=>false,"message"=>"Order ID is required"]);
        exit;
    }

    $check = $conn->prepare('SELECT id FROM orders WHERE id=? AND user_id=? AND deleted_at IS NULL');
    $check->execute([$order_id, $user_id]);
    if (!$check->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['success'=>false, 'message'=>'Order not found']);
        exit;
    }

    $now = date('Y-m-d H:i:s');

    // Cancel order tracking
    $stmt = $conn->prepare("UPDATE order_tracking t JOIN orders o ON o.id=t.order_id SET t.deleted_at=? WHERE t.order_id=? AND o.user_id=?");
    $stmt->execute([$now, $order_id, $user_id]);

    // Cancel order
    $stmt = $conn->prepare("UPDATE orders SET deleted_at=? WHERE id=? AND user_id=?");
    $stmt->execute([$now, $order_id, $user_id]);

    // ---------------- SEND CANCELLATION EMAIL ----------------
    $stmt = $conn->prepare("SELECT email, first_name FROM users WHERE id=?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if($user && !empty($user['email'])){
        $subject = "Your Order #$order_id has been Cancelled";
        $message = "Hi {$user['first_name']},\n\n".
                   "Your order #$order_id has been successfully cancelled.\n".
                   "If you have questions, please contact support.\n\n".
                   "Mobile User Team";

        $headers = "From: Mobile User <no-reply@mobileuser.test>";

        // MailHog configuration
        ini_set('SMTP','localhost');
        ini_set('smtp_port',1025);
        ini_set('sendmail_from','no-reply@mobileuser.test');

        if(!send_app_mail($user['email'],$subject,$message)){
            error_log("Cancellation email FAILED for order $order_id");
        } else {
            error_log("Cancellation email sent to {$user['email']}");
        }
    }

    echo json_encode(["success"=>true,"message"=>"Order cancelled"]);
    exit;
}

?>
