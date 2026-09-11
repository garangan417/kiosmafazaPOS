<?php
// database2/db_pelanggan.php

require_once __DIR__ . '/../config.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Error: Variabel \$pdo dari config.php tidak ditemukan.");
}

try {
    // 1. Buat tabel pelanggan jika belum ada
    $pdo->exec("CREATE TABLE IF NOT EXISTS pelanggan (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nama VARCHAR(150) NOT NULL,
        no_hp VARCHAR(20),
        alamat TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 2. Buat tabel utang jika belum ada
    $pdo->exec("CREATE TABLE IF NOT EXISTS utang (
        id INT AUTO_INCREMENT PRIMARY KEY,
        pelanggan_id INT NOT NULL,
        tipe ENUM('utang', 'bayar') NOT NULL,
        nominal DECIMAL(15, 2) NOT NULL DEFAULT 0,
        keterangan TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (pelanggan_id) REFERENCES pelanggan(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // 3. Auto-Migration: Cek dan tambahkan kolom items_json jika belum ada
    $checkColumn = $pdo->query("SHOW COLUMNS FROM utang LIKE 'items_json'");
    if ($checkColumn && $checkColumn->rowCount() === 0) {
        $pdo->exec("ALTER TABLE utang ADD COLUMN items_json TEXT NULL AFTER keterangan");
    }

    $pdoPelanggan = $pdo;

} catch (PDOException $e) {
    die("Koneksi Database Pelanggan MariaDB Gagal: " . $e->getMessage());
}

function hitungSisaUtangPelanggan($pdoPelanggan, $pelanggan_id) {
    $stmt = $pdoPelanggan->prepare("SELECT tipe, nominal FROM utang WHERE pelanggan_id = ? ORDER BY created_at ASC, id ASC");
    $stmt->execute([$pelanggan_id]);
    $riwayat = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalUtangSesi = 0;
    $totalBayarSesi = 0;

    foreach ($riwayat as $r) {
        $nominal = floatval($r['nominal']);
        
        if ($r['tipe'] === 'utang') {
            $totalUtangSesi += $nominal;
        } else {
            $totalBayarSesi += $nominal;
        }

        if ($totalUtangSesi > 0 && $totalBayarSesi >= $totalUtangSesi) {
            $totalUtangSesi = 0;
            $totalBayarSesi = 0;
        }
    }

    return max(0, $totalUtangSesi - $totalBayarSesi);
}