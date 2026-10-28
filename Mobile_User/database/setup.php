<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/config/database_settings.php';
if (!preg_match('/^[A-Za-z0-9_]+$/D', $dbname)) {
    throw new RuntimeException('Invalid database name.');
}
$db = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);
// Existing tables are never replaced. Schema changes need a separate migration.
$db->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$db->exec("USE `$dbname`");
foreach (explode(';', file_get_contents(__DIR__ . '/schema.sql')) as $statement) {
    if (trim($statement) !== '') { $db->exec($statement); }
}
echo "Application database ready: $dbname\n";
