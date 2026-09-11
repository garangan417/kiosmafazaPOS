<?php
// database2/db_transaksi.php

require_once __DIR__ . '/../config.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Error: Variabel \$pdo dari config.php tidak ditemukan.");
}

try {
    // Tabel Kas / Pembukuan Transaksi
    $sql = "CREATE TABLE IF NOT EXISTS transaksi (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tanggal DATETIME NOT NULL,
        tipe ENUM('masuk', 'keluar') NOT NULL,
        kategori VARCHAR(100) NOT NULL,
        nominal DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
        saldo_akhir DECIMAL(15, 2) DEFAULT NULL,
        keterangan TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql);

    // Alias untuk kompatibilitas jika ada kode lama panggil $pdoTransaksi
    $pdoTransaksi = $pdo;

} catch (PDOException $e) {
    die("Koneksi / Pembuatan Tabel Transaksi MariaDB Gagal: " . $e->getMessage());
}