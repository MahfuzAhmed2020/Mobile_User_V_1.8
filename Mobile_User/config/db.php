<?php
// $host = 'localhost';
// $dbname = 'Mobile_User_db';
// $user = 'root';
// $pass = '';

$host = 'db';
$dbname = 'Mobile_User_db';
$user = 'appuser';
$pass = 'appPass123';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}



