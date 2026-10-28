<?php
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'Mobile_User_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASSWORD');
if ($pass === false) { $pass = 'root'; }
