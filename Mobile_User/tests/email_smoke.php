<?php
// Run against the isolated server on 8086, with MAIL_ENABLED=1 and MailHog running.
require dirname(__DIR__) . '/config/database_settings.php';
if (PHP_SAPI !== 'cli' || !preg_match('/^mobile_user_smoke_[a-f0-9]+$/D', $dbname)) {
    throw new RuntimeException('A uniquely named test database is required.');
}
$db = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$created = false;
$checks = 0;
function verify_mail_test($ok, $message) {
    global $checks;
    if (!$ok) { throw new RuntimeException($message); }
    $checks++;
}
function mail_test_request($path, $data, &$cookie, $form = false, $method = 'POST') {
    $headers = 'Content-Type: ' . ($form ? 'application/x-www-form-urlencoded' : 'application/json') . "\r\n";
    if ($cookie) { $headers .= "Cookie: $cookie\r\n"; }
    $context = stream_context_create(['http'=>[
        'method'=>$method, 'header'=>$headers, 'ignore_errors'=>true, 'follow_location'=>0, 'timeout'=>15,
        'content'=>$method === 'GET' ? '' : ($form ? http_build_query($data) : json_encode($data)),
    ]]);
    $body = file_get_contents('http://127.0.0.1:8086' . $path, false, $context);
    foreach ($http_response_header as $header) {
        if (preg_match('/^Set-Cookie: (PHPSESSID=[^;]+)/i', $header, $match)) { $cookie = $match[1]; }
    }
    return json_decode($body, true) ?? $body;
}
function captured_test_mail($email): array {
    $result = json_decode(file_get_contents('http://127.0.0.1:8025/api/v2/search?kind=to&query=' . urlencode($email)), true);
    $messages = [];
    foreach ($result['items'] ?? [] as $item) {
        $subject = implode(' ', $item['Content']['Headers']['Subject']);
        $body = $item['Content']['Body'];
        if (strtolower($item['Content']['Headers']['Content-Transfer-Encoding'][0] ?? '') === 'quoted-printable') {
            $body = quoted_printable_decode($body);
        }
        $messages[$subject] = $body;
    }
    return $messages;
}
try {
    $db->exec("CREATE DATABASE `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true;
    $db->exec("USE `$dbname`");
    foreach (explode(';', file_get_contents(dirname(__DIR__) . '/database/schema.sql')) as $sql) {
        if (trim($sql) !== '') { $db->exec($sql); }
    }
    $db->exec("INSERT INTO products (name,price) VALUES ('Mail test earbuds',49.99)");
    $suffix = substr($dbname, -12);
    $email = "api-$suffix@example.invalid";
    $webEmail = "web-$suffix@example.invalid";
    $payload = ['first_name'=>'Email', 'last_name'=>'Test', 'email'=>$email,
        'password'=>'EmailTest1!', 'confirm_password'=>'EmailTest1!'];
    $cookie = '';
    $result = mail_test_request('/api/register_api.php',$payload,$cookie);
    verify_mail_test(($result['success'] ?? false) && ($result['email_sent'] ?? false), 'API registration email failed');
    $webCookie = '';
    $payload['email'] = $webEmail;
    $payload['agree_terms'] = '1';
    $result = mail_test_request('/public/index.php',$payload,$webCookie,true);
    verify_mail_test(is_string($result) && str_contains($result,'Registration successful!'), 'Web registration failed');
    $result = mail_test_request('/api/login_api.php',['email'=>$email,'password'=>'EmailTest1!'],$cookie);
    verify_mail_test($result['success'] ?? false, 'Login failed');
    $addresses = mail_test_request('/api/addresses_api.php',[],$cookie,false,'GET');
    verify_mail_test(count($addresses['data']) === 5, 'Sample addresses unavailable');
    mail_test_request('/api/cart_api.php',['product_id'=>1],$cookie);
    $cart = mail_test_request('/api/cart_api.php',['product_id'=>1,'quantity'=>3],$cookie,false,'PATCH');
    verify_mail_test($cart['total'] === '149.97', 'Cart total incorrect');
    $order = mail_test_request('/api/checkout_api.php',['address_id'=>$addresses['data'][0]['id'],'card_number'=>'4111111111111111'],$cookie);
    verify_mail_test(($order['success'] ?? false) && ($order['email_sent'] ?? false), 'Order tracking email failed');
    $result = mail_test_request('/api/forgot_password_api.php',['email'=>$email],$cookie);
    verify_mail_test($result['success'] ?? false, 'Password reset email failed');
    $messages = captured_test_mail($email);
    verify_mail_test(isset($messages['Welcome to Mobile User']), 'Welcome message not captured');
    $receipt = $messages['Your Order Confirmation & Tracking'] ?? '';
    verify_mail_test(str_contains($receipt,$order['tracking_number']), 'Receipt tracking number missing');
    verify_mail_test(str_contains($receipt,'149.97') && str_contains($receipt,'Mail test earbuds x 3'), 'Receipt calculation missing');
    verify_mail_test(str_contains($receipt,'123 Main Street'), 'Receipt address missing');
    $base = rtrim(getenv('APP_URL') ?: 'http://127.0.0.1:8086','/');
    verify_mail_test(str_contains($receipt,$base . '/public/orders.php?tracking='), 'Public tracking link incorrect');
    $stmt = $db->prepare('SELECT reset_token FROM users WHERE email=?');
    $stmt->execute([$email]);
    $token = $stmt->fetchColumn();
    verify_mail_test(str_contains($messages['Password Reset Request'] ?? '',$base . '/public/reset_password.php?token=' . $token), 'Public reset link incorrect');
    $page = mail_test_request('/public/reset_password.php?token=' . urlencode($token),[],$cookie,false,'GET');
    verify_mail_test(is_string($page) && str_contains($page,'id="resetForm"'), 'Reset page does not open');
    $result = mail_test_request('/api/reset_password_api.php',['token'=>str_repeat('a',64),'password'=>'UpdatedTest1!','confirm_password'=>'UpdatedTest1!'],$cookie);
    verify_mail_test(($result['success'] ?? true) === false, 'Invalid reset token accepted');
    $result = mail_test_request('/api/reset_password_api.php',['token'=>$token,'password'=>'short','confirm_password'=>'short'],$cookie);
    verify_mail_test(isset($result['errors']), 'Weak reset password accepted');
    $result = mail_test_request('/api/reset_password_api.php',['token'=>$token,'password'=>'UpdatedTest1!','confirm_password'=>'DifferentTest1!'],$cookie);
    verify_mail_test(isset($result['errors']), 'Mismatched reset passwords accepted');
    $expires = $db->query('SELECT reset_expires FROM users WHERE reset_token=' . $db->quote($token))->fetchColumn();
    verify_mail_test(strtotime($expires) > time() && strtotime($expires) <= time()+3600, 'Reset expiry is not one hour');
    $expire = $db->prepare('UPDATE users SET reset_expires=? WHERE reset_token=?');
    $expire->execute(['2000-01-01 00:00:00',$token]);
    $result = mail_test_request('/api/reset_password_api.php',['token'=>$token,'password'=>'UpdatedTest1!','confirm_password'=>'UpdatedTest1!'],$cookie);
    verify_mail_test(($result['success'] ?? true) === false && str_contains($result['message'] ?? '', 'expired'), 'Expired reset token accepted');
    $expire->execute([$expires,$token]);
    $result = mail_test_request('/api/reset_password_api.php',['token'=>$token,'password'=>'UpdatedTest1!','confirm_password'=>'UpdatedTest1!'],$cookie);
    verify_mail_test($result['success'] ?? false, 'Password reset failed');
    $result = mail_test_request('/api/reset_password_api.php',['token'=>$token,'password'=>'UpdatedTest1!','confirm_password'=>'UpdatedTest1!'],$cookie);
    verify_mail_test(($result['success'] ?? true) === false, 'Reset token reusable');
    $result = mail_test_request('/api/login_api.php',['email'=>$email,'password'=>'EmailTest1!'],$cookie);
    verify_mail_test(($result['success'] ?? true) === false, 'Old password still accepted after reset');
    $result = mail_test_request('/api/login_api.php',['email'=>$email,'password'=>'UpdatedTest1!'],$cookie);
    verify_mail_test($result['success'] ?? false, 'New password login failed');
    verify_mail_test(isset(captured_test_mail($webEmail)['Welcome to Mobile User']), 'Web registration email not captured');
    $orders = mail_test_request('/api/orders_api.php',[],$cookie,false,'GET');
    $newAddressId = $orders['data'][0]['addresses_set_b'][1]['id'];
    $changed = mail_test_request('/api/orders_api.php',['order_id'=>$order['order_id'],'whitelist_address_id'=>$newAddressId],$cookie,false,'PATCH');
    verify_mail_test(($changed['changed'] ?? false) && ($changed['email_sent'] ?? false), 'Address change email failed');
    $saved = $db->prepare('SELECT whitelist_address_id FROM orders WHERE id=?');
    $saved->execute([$order['order_id']]);
    verify_mail_test((int) $saved->fetchColumn() === (int) $newAddressId, 'Address change not saved to database');
    $addressMessage = captured_test_mail($email)['Delivery address updated for order #' . $order['order_id']] ?? '';
    verify_mail_test(str_contains($addressMessage,'123 Main Street') && str_contains($addressMessage,'456 Oak Avenue'), 'Old/new address missing from notification');
    verify_mail_test(str_contains($addressMessage,$order['tracking_number']), 'Address notification tracking missing');
    $searchUrl = 'http://127.0.0.1:8025/api/v2/search?kind=to&query=' . urlencode($email);
    $before = json_decode(file_get_contents($searchUrl),true)['total'];
    $unchanged = mail_test_request('/api/orders_api.php',['order_id'=>$order['order_id'],'whitelist_address_id'=>$newAddressId],$cookie,false,'PATCH');
    verify_mail_test(($unchanged['changed'] ?? true) === false, 'Repeated save was treated as a change');
    verify_mail_test(json_decode(file_get_contents($searchUrl),true)['total'] === $before, 'Repeated save sent a duplicate notification');
    $result = mail_test_request('/api/orders_api.php',['order_id'=>$order['order_id']],$cookie,false,'DELETE');
    verify_mail_test($result['success'] ?? false, 'Order cancellation failed');
    verify_mail_test(isset(captured_test_mail($email)['Your Order #' . $order['order_id'] . ' has been Cancelled']), 'Cancellation email missing');
    echo "$checks email checks passed. Captured test emails remain in MailHog.\n";
} finally {
    if ($created) { $db->exec("DROP DATABASE `$dbname`"); }
}
