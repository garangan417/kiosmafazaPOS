<?php
// config.php
$host = '127.0.0.1';
$db   = 'radius'; // Sesuaikan dengan nama database FreeRADIUS kamu
$user = 'radius';  // Sesuaikan dengan user database kamu
$pass = '1234';  // Sesuaikan dengan password database kamu

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    die("Database Connection Error: " . $e->getMessage());
}