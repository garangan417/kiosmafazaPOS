<?php
// database2/db_users.php

require_once __DIR__ . '/../config.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Error: Variabel \$pdo dari config.php tidak ditemukan.");
}

try {
    // Tabel Users
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        nama_lengkap VARCHAR(150) NOT NULL,
        role ENUM('admin', 'kasir') NOT NULL DEFAULT 'kasir',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql);

    // Auto-generate user admin default jika tabel masih kosong
    $stmtCheck = $pdo->query("SELECT COUNT(*) FROM users");
    if ($stmtCheck->fetchColumn() == 0) {
        $defaultUser = 'admin';
        // Password default: admin123
        $defaultPass = password_hash('admin123', PASSWORD_BCRYPT);
        $defaultName = 'Administrator';
        $defaultRole = 'admin';

        $stmtInsert = $pdo->prepare("INSERT INTO users (username, password, nama_lengkap, role) VALUES (?, ?, ?, ?)");
        $stmtInsert->execute([$defaultUser, $defaultPass, $defaultName, $defaultRole]);
    }

    // Alias untuk kompatibilitas jika ada kode lama panggil $pdoUsers
    $pdoUsers = $pdo;

} catch (PDOException $e) {
    die("Koneksi Database Users MariaDB Gagal: " . $e->getMessage());
}