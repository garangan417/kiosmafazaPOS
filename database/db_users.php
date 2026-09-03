<?php
// database/db_users.php
require_once __DIR__ . '/../config.php';

$dbUsersPath = __DIR__ . '/users.sqlite';

try {
    $pdoUsers = new PDO("sqlite:" . $dbUsersPath);
    $pdoUsers->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Auto-generate tabel users jika belum ada
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        nama_lengkap TEXT NOT NULL,
        role TEXT CHECK(role IN ('admin', 'kasir')) DEFAULT 'kasir',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";
    $pdoUsers->exec($sql);

    // Auto-generate user admin default jika tabel masih kosong
    $stmtCheck = $pdoUsers->query("SELECT COUNT(*) FROM users");
    if ($stmtCheck->fetchColumn() == 0) {
        $defaultUser = 'admin';
        // Password default: admin123
        $defaultPass = password_hash('admin123', PASSWORD_BCRYPT);
        $defaultName = 'Administrator';
        $defaultRole = 'admin';

        $stmtInsert = $pdoUsers->prepare("INSERT INTO users (username, password, nama_lengkap, role) VALUES (?, ?, ?, ?)");
        $stmtInsert->execute([$defaultUser, $defaultPass, $defaultName, $defaultRole]);
    }

} catch (PDOException $e) {
    die("Koneksi Database Users Gagal: " . $e->getMessage());
}