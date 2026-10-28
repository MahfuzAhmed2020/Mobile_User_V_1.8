<?php
// Requires a local PHP server on 8086 with the same DB_NAME and MAIL_ENABLED=0.
require dirname(__DIR__) . '/config/database_settings.php';
if (PHP_SAPI !== 'cli' || !preg_match('/^mobile_user_smoke_[a-f0-9]+$/D', $dbname)) {
    throw new RuntimeException('Use a uniquely named mobile_user_smoke_ test database.');
}
$db = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$created = false;
$checks = 0;
function check($condition, $message) {
    global $checks;
    if (!$condition) { throw new RuntimeException($message); }
    $checks++;
}
function request($path, $method, $data, &$cookie) {
    $headers = "Content-Type: application/json\r\n";
    if ($cookie) { $headers .= "Cookie: $cookie\r\n"; }
    $context = stream_context_create(['http'=>[
        'method'=>$method, 'header'=>$headers, 'ignore_errors'=>true,
        'follow_location'=>0, 'timeout'=>10,
        'content'=>$data === null ? '' : json_encode($data),
    ]]);
    $body = file_get_contents('http://127.0.0.1:8086' . $path, false, $context);
    $status = 0;
    foreach ($http_response_header as $header) {
        if (preg_match('~^HTTP/\S+ (\d+)~', $header, $match)) { $status = (int) $match[1]; }
        if (preg_match('/^Set-Cookie: (PHPSESSID=[^;]+)/i', $header, $match)) { $cookie = $match[1]; }
    }
    return [$status, json_decode($body, true), $body, $http_response_header];
}
try {
    // CREATE without IF NOT EXISTS ensures existing databases cannot be touched.
    $db->exec("CREATE DATABASE `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true;
    $db->exec("USE `$dbname`");
    foreach (explode(';', file_get_contents(dirname(__DIR__) . '/database/schema.sql')) as $sql) {
        if (trim($sql) !== '') { $db->exec($sql); }
    }
    $insert = $db->prepare('INSERT INTO users (first_name,last_name,email,password) VALUES (?,?,?,?)');
    foreach (['one','two'] as $name) {
        $insert->execute(['Smoke', $name, "$name@example.invalid", password_hash('SmokeTest1!', PASSWORD_DEFAULT)]);
    }
    $db->exec("INSERT INTO products (name,price) VALUES ('Smoke product',12.34)");
    $db->exec("INSERT INTO addresses (user_id,address_line,city,state,zip,country) VALUES (1,'Test only','Test','TS','00000','Test'),(2,'Other account','Test','TS','00000','Test')");
    $db->exec("INSERT INTO cards (user_id,card_number) VALUES (1,'4111111111111111'),(2,'5555555555554444')");
    $db->exec("INSERT INTO addresses_whitelist (user_id,address_line,city,state,zip,country) VALUES (2,'Other account','Test','TS','00000','Test')");
    $cookie = '';
    $sharedProduct = request('/public/product.php?id=1','GET',null,$cookie);
    check($sharedProduct[0] === 200 && str_contains($sharedProduct[2],'Smoke product'), 'Shared product not accessible without login');
    check(str_contains($sharedProduct[2],'Sign in to add to cart') && !str_contains($sharedProduct[2],'id="detailAdd"'), 'Shared product guest action incorrect');
    check(str_contains($sharedProduct[2],'property="og:title"') && str_contains($sharedProduct[2],'rel="canonical"'), 'Share metadata missing');
    check(request('/public/product.php?id=999999','GET',null,$cookie)[0] === 404, 'Missing shared product did not return 404');
    check(request('/public/product.php?id=bad','GET',null,$cookie)[0] === 404, 'Invalid shared product ID accepted');
    check(str_contains(request('/public/swagger.php','GET',null,$cookie)[2], 'swagger-ui-bundle.js'), 'Swagger UI page unavailable');
    $spec = request('/public/openapi.json','GET',null,$cookie)[1];
    check(($spec['openapi'] ?? '') === '3.0.3', 'OpenAPI specification unavailable');
    $apiFiles = glob(dirname(__DIR__) . '/api/*.php');
    foreach ($apiFiles as $apiFile) {
        check(isset($spec['paths']['/' . basename($apiFile)]), 'API missing from documentation: ' . basename($apiFile));
    }
    check(request('/public/index.php','GET',null,$cookie)[0] === 200, 'Registration page failed');
    check(request('/api/checkout_api.php','POST',[],$cookie)[0] === 401, 'Anonymous checkout accepted');
    check(request('/api/addresses_api.php','GET',null,$cookie)[0] === 401, 'Anonymous addresses exposed');
    check(request('/api/cards_api.php','GET',null,$cookie)[0] === 401, 'Anonymous cards exposed');
    $sessionBefore = $cookie;
    $result = request('/api/login_api.php','POST',['email'=>'one@example.invalid','password'=>'SmokeTest1!'],$cookie);
    check($result[1]['success'] ?? false, 'Login failed');
    check($cookie !== $sessionBefore, 'Session ID was not regenerated');
    check(str_contains(request('/public/product.php?id=1','GET',null,$cookie)[2],'id="detailAdd"'), 'Signed-in shared product missing add-to-cart');
    foreach (['profile.php','cart.php','checkout.php','orders.php','update_profile.php'] as $page) {
        $view = request('/public/' . $page,'GET',null,$cookie);
        check($view[0] === 200 && str_contains($view[2], 'assets/store.css'), 'Shared page layout missing: ' . $page);
    }
    check(request('/api/update_profile_api.php','POST',['first_name'=>'Smoke','last_name'=>'User','email'=>'not-an-email'],$cookie)[0] === 422, 'Invalid profile email accepted');
    check(request('/api/update_profile_api.php','POST',['first_name'=>'Smoke','last_name'=>'User','email'=>'two@example.invalid'],$cookie)[0] === 409, 'Duplicate profile email accepted');
    check(request('/api/update_profile_api.php','POST',['first_name'=>'Smoke','last_name'=>'User','email'=>'one@example.invalid'],$cookie)[1]['success'] === true, 'Profile details update failed');
    $redirect = request('/public/login.php?redirect=' . rawurlencode('/public/orders.php?tracking=DEMO'),'GET',null,$cookie);
    check(in_array('Location: /public/orders.php?tracking=DEMO',$redirect[3],true), 'Order link lost after login');
    $redirect = request('/public/login.php?redirect=' . rawurlencode('https://example.invalid/'),'GET',null,$cookie);
    check(in_array('Location: profile.php',$redirect[3],true), 'External login redirect allowed');
    $result = request('/api/addresses_api.php','GET',null,$cookie);
    check(count($result[1]['data']) === 1 && $result[1]['data'][0]['id'] === 1, 'Address ownership failed');
    $result = request('/api/cards_api.php','GET',null,$cookie);
    check(count($result[1]['data']) === 1, 'Card ownership failed');
    check(request('/api/cart_api.php','POST',['product_id'=>1],$cookie)[1]['success'], 'Add to cart failed');
    $cart = request('/api/cart_api.php','PATCH',['product_id'=>1,'quantity'=>2],$cookie)[1];
    check($cart['total'] === '24.68' && $cart['data'][0]['subtotal'] === '24.68', 'Absolute quantity or total failed');
    check($cart['item_count'] === 2, 'Cart item count failed');
    $cart = request('/api/cart_api.php','PATCH',['product_id'=>1,'change'=>-1],$cookie)[1];
    check($cart['total'] === '12.34', 'Relative quantity update failed');
    check(request('/api/cart_api.php','PATCH',['product_id'=>1,'quantity'=>1.5],$cookie)[0] === 422, 'Fractional quantity accepted');
    check(request('/api/checkout_api.php','POST',['address_id'=>2,'card_number'=>'4111111111111111'],$cookie)[0] === 422, 'Other user address accepted');
    check(request('/api/checkout_api.php','POST',['address_id'=>1,'card_number'=>'4111111111111111','expected_total'=>'0.01'],$cookie)[0] === 409, 'Changed cart total accepted');
    check((int) $db->query('SELECT COUNT(*) FROM orders')->fetchColumn() === 0, 'Stale checkout created an order');
    $result = request('/api/checkout_api.php','POST',['address_id'=>1,'card_number'=>'4111111111111111'],$cookie);
    check($result[1]['success'] ?? false, 'Checkout failed: ' . $result[2]);
    check((string) $db->query('SELECT total FROM orders WHERE id=1')->fetchColumn() === '12.34', 'Wrong order total');
    check((int) $db->query('SELECT COUNT(*) FROM cart')->fetchColumn() === 0, 'Cart not cleared');
    check((string) $db->query('SELECT product_name FROM order_items WHERE order_id=1')->fetchColumn() === 'Smoke product', 'Order snapshot missing');
    $db->exec('UPDATE products SET price=199.99 WHERE id=1');
    check(count(request('/api/products_api.php','GET',null,$cookie)[1]['data']) === 1, 'Product above $100 hidden');
    $result = request('/api/orders_api.php','GET',null,$cookie);
    check((string) $result[1]['data'][0]['items'][0]['price'] === '12.34', 'Order price changed with catalog');
    $db->exec('INSERT INTO cart (user_id,product_id,quantity) VALUES (1,1,1)');
    $db->exec("CREATE TRIGGER smoke_tracking_failure BEFORE INSERT ON order_tracking FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Intentional smoke failure'");
    check(request('/api/checkout_api.php','POST',['address_id'=>1,'card_number'=>'4111111111111111'],$cookie)[0] === 500, 'Checkout failure was not handled');
    check((int) $db->query('SELECT COUNT(*) FROM orders')->fetchColumn() === 1, 'Failed checkout left a partial order');
    check((int) $db->query('SELECT COUNT(*) FROM cart')->fetchColumn() === 1, 'Failed checkout cleared cart');
    $db->exec('DROP TRIGGER smoke_tracking_failure');
    check(request('/api/orders_api.php','PATCH',['order_id'=>1,'whitelist_address_id'=>1],$cookie)[0] === 422, 'Other user delivery address accepted');
    $db->exec("INSERT INTO addresses_whitelist (user_id,address_line,city,state,zip,country) VALUES (1,'Own alternate','Test','TS','00000','Test')");
    $updated = request('/api/orders_api.php','PATCH',['order_id'=>1,'whitelist_address_id'=>2],$cookie);
    check($updated[0] === 200 && ($updated[1]['success'] ?? false), 'Owned address update failed');
    check(str_contains($updated[1]['delivery_address'], 'Own alternate'), 'Updated address missing from response');
    check((int) $db->query('SELECT whitelist_address_id FROM orders WHERE id=1')->fetchColumn() === 2, 'Address change not persisted in database');
    check(str_contains(request('/api/orders_api.php','GET',null,$cookie)[1]['data'][0]['delivery_address'], 'Own alternate'), 'Saved address lost on reload');
    check($updated[1]['changed'] === true && $updated[1]['email_sent'] === false, 'Saved change failed when email disabled');
    check(request('/api/orders_api.php','PATCH',['order_id'=>1,'whitelist_address_id'=>2],$cookie)[1]['changed'] === false, 'Repeated address save reported a new change');
    $db->exec("UPDATE order_tracking SET status='Shipped' WHERE order_id=1");
    check(request('/api/orders_api.php','PATCH',['order_id'=>1,'whitelist_address_id'=>2],$cookie)[0] === 409, 'Dispatched order address changed');
    check(request('/api/orders_api.php','GET',null,$cookie)[1]['data'][0]['can_update_address'] === false, 'Dispatched order still editable');
    $db->exec("UPDATE order_tracking SET status='Processing' WHERE order_id=1");
    check(request('/api/orders_api.php','PATCH',['order_id'=>999999,'whitelist_address_id'=>2],$cookie)[0] === 404, 'Missing order accepted');
    $otherCookie = '';
    request('/api/login_api.php','POST',['email'=>'two@example.invalid','password'=>'SmokeTest1!'],$otherCookie);
    check(request('/api/orders_api.php','DELETE',['order_id'=>1],$otherCookie)[0] === 404, 'Other user order cancelled');
    check($db->query('SELECT deleted_at FROM order_tracking WHERE order_id=1')->fetchColumn() === null, 'Other user tracking changed');
    check(request('/api/track_order_api.php','POST',['tracking_number'=>$result[1]['data'][0]['tracking_number']],$otherCookie)[1]['success'] === false, 'Other user tracking exposed');
    check(request('/config/db.php','GET',null,$cookie)[0] === 404, 'Configuration exposed');
    check(request('/public/../config/db.php','GET',null,$cookie)[0] === 404, 'Traversal allowed');
    require dirname(__DIR__) . '/database/seed_demo.php';
    check((int) $db->query('SELECT COUNT(*) FROM products')->fetchColumn() === 1, 'Demo seed replaced existing catalog');
    check((int) $db->query('SELECT COUNT(*) FROM cards')->fetchColumn() === 10, 'Demo cards missing');
    check((int) $db->query('SELECT COUNT(*) FROM addresses')->fetchColumn() === 12, 'Sample addresses missing');
    require dirname(__DIR__) . '/database/seed_demo.php';
    check((int) $db->query('SELECT COUNT(*) FROM cards')->fetchColumn() === 10, 'Demo cards duplicated');
    check((int) $db->query('SELECT COUNT(*) FROM addresses')->fetchColumn() === 12, 'Sample addresses duplicated');
    $result = request('/api/register_api.php','POST',[
        'first_name'=>'New', 'last_name'=>'Account', 'email'=>'new@example.invalid',
        'password'=>'SmokeTest1!', 'confirm_password'=>'SmokeTest1!',
    ],$otherCookie);
    check($result[1]['success'] ?? false, 'New account registration failed');
    $count = $db->prepare('SELECT COUNT(*) FROM cards WHERE user_id=?');
    $count->execute([$result[1]['user_id']]);
    check((int) $count->fetchColumn() === 5, 'New account test cards missing');
    $count = $db->prepare('SELECT COUNT(*) FROM addresses WHERE user_id=?');
    $count->execute([$result[1]['user_id']]);
    check((int) $count->fetchColumn() === 5, 'New account sample addresses missing');
    $newCookie = '';
    request('/api/login_api.php','POST',['email'=>'new@example.invalid','password'=>'SmokeTest1!'],$newCookie);
    $passwordUpdate = ['first_name'=>'New','last_name'=>'Account','email'=>'new@example.invalid','old_password'=>'WrongPass1!','new_password'=>'ChangedPass1!','confirm_password'=>'ChangedPass1!'];
    check(request('/api/update_profile_api.php','POST',$passwordUpdate,$newCookie)[0] === 422, 'Incorrect current password accepted');
    $passwordUpdate['old_password'] = 'SmokeTest1!';
    $passwordUpdate['confirm_password'] = 'DifferentPass1!';
    check(request('/api/update_profile_api.php','POST',$passwordUpdate,$newCookie)[0] === 422, 'Mismatched password confirmation accepted');
    $passwordUpdate['confirm_password'] = 'ChangedPass1!';
    check(request('/api/update_profile_api.php','POST',$passwordUpdate,$newCookie)[1]['success'] === true, 'Password update failed');
    check(request('/api/login_api.php','POST',['email'=>'new@example.invalid','password'=>'ChangedPass1!'],$newCookie)[1]['success'] === true, 'Changed password login failed');
    check(request('/api/logout_api.php','GET',null,$newCookie)[0] === 405, 'GET request signed out');
    check(request('/api/logout_api.php','POST',null,$newCookie)[1]['success'] === true, 'Sign-out failed');
    check(request('/api/profile_api.php','GET',null,$newCookie)[0] === 401, 'Signed-out session still authenticated');
    check(request('/api/logout_api.php','POST',null,$newCookie)[1]['success'] === true, 'Repeated sign-out failed');
    echo "$checks smoke checks passed.\n";
} finally {
    if ($created) { $db->exec("DROP DATABASE `$dbname`"); }
}
