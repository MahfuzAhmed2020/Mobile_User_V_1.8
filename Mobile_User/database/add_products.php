<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/config/db.php';
$conn->beginTransaction();
try {
    $insert = $conn->prepare('INSERT INTO products (name,description,price) SELECT ?,?,? WHERE NOT EXISTS (SELECT 1 FROM products WHERE name=?)');
    $added = 0;
    foreach (require __DIR__ . '/additional_products.php' as $product) {
        $insert->execute([...$product, $product[0]]);
        $added += $insert->rowCount();
    }
    $conn->commit();
    echo "Added $added products. Shop catalog total: " . $conn->query('SELECT COUNT(*) FROM products')->fetchColumn() . ".\n";
} catch (Throwable $e) {
    $conn->rollBack();
    throw $e;
}
