<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/config/demo_cards.php';
require_once dirname(__DIR__) . '/config/demo_addresses.php';
$conn->beginTransaction();
try {
    if ((int) $conn->query('SELECT COUNT(*) FROM products')->fetchColumn() === 0) {
        $insert = $conn->prepare('INSERT INTO products (name, description, price) VALUES (?,?,?)');
        foreach ([
            ['Wireless Earbuds', 'Wireless earbuds for everyday listening.', '49.99'],
            ['Bluetooth Speaker', 'Portable wireless speaker.', '79.99'],
            ['Smart Phone Case', 'Protective case for your phone.', '19.99'],
            ['Charging Cable Set', 'Charging cables for everyday use.', '12.99'],
            ['Fitness Tracker Band', 'Fitness tracker for daily activity.', '89.99'],
        ] as $product) { $insert->execute($product); }
        foreach (require __DIR__ . '/additional_products.php' as $product) {
            $insert->execute($product);
        }
    }
    foreach ($conn->query('SELECT id FROM users')->fetchAll(PDO::FETCH_COLUMN) as $id) {
        add_demo_cards($conn, (int) $id);
        add_demo_addresses($conn, (int) $id);
    }
    $conn->commit();
    echo "Demo products, test cards, and sample addresses are ready.\n";
} catch (Throwable $e) {
    $conn->rollBack();
    throw $e;
}
