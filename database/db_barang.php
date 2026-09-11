<?php
// database2/db_barang.php

require_once __DIR__ . '/../config.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Error: Variabel \$pdo dari config.php tidak ditemukan.");
}

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kategori (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nama_kategori VARCHAR(100) NOT NULL UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS barang (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kategori_id INT NOT NULL,
            nama_barang VARCHAR(150) NOT NULL,
            is_active INT NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (kategori_id) REFERENCES kategori(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS barang_kemasan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            barang_id INT NOT NULL,
            nama_kemasan VARCHAR(50) NOT NULL,
            satuan VARCHAR(20) NOT NULL DEFAULT 'PCS',
            isi INT NOT NULL DEFAULT 1,
            stok INT NOT NULL DEFAULT 0,
            is_favorite INT NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (barang_id) REFERENCES barang(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS barang_barcode (
            id INT AUTO_INCREMENT PRIMARY KEY,
            barang_kemasan_id INT NOT NULL,
            barcode VARCHAR(50) NOT NULL UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (barang_kemasan_id) REFERENCES barang_kemasan(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS harga_barang (
            id INT AUTO_INCREMENT PRIMARY KEY,
            barang_kemasan_id INT NOT NULL UNIQUE,
            harga_beli DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            harga_beli_pcs DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            harga_jual_ecer DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            harga_jual_grosir DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            min_qty_grosir INT NOT NULL DEFAULT 1,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (barang_kemasan_id) REFERENCES barang_kemasan(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS stok_mutasi (
            id INT AUTO_INCREMENT PRIMARY KEY,
            barang_kemasan_id INT NOT NULL,
            jenis_mutasi VARCHAR(20) NOT NULL,
            qty INT NOT NULL,
            stok_sebelum INT NOT NULL,
            stok_sesudah INT NOT NULL,
            keterangan TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (barang_kemasan_id) REFERENCES barang_kemasan(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS stok_opname (
            id INT AUTO_INCREMENT PRIMARY KEY,
            barang_kemasan_id INT NOT NULL,
            stok_sistem INT NOT NULL,
            stok_fisik INT NOT NULL,
            selisih INT NOT NULL,
            keterangan TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (barang_kemasan_id) REFERENCES barang_kemasan(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pengaturan (
            kunci VARCHAR(50) PRIMARY KEY,
            nilai VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        INSERT IGNORE INTO pengaturan (kunci, nilai) VALUES 
        ('fitur_stok', '1'),
        ('izinkan_stok_minus', '0');
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS penjualan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            no_faktur VARCHAR(50) NOT NULL UNIQUE,
            pelanggan_id INT DEFAULT NULL,
            total_kotor DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            diskon DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            total_bersih DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            bayar DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            kembalian DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            metode_bayar VARCHAR(20) NOT NULL DEFAULT 'TUNAI',
            catatan TEXT,
            tanggal DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS penjualan_detail (
            id INT AUTO_INCREMENT PRIMARY KEY,
            penjualan_id INT NOT NULL,
            barang_kemasan_id INT DEFAULT NULL,
            nama_barang VARCHAR(255) NOT NULL,
            nama_kemasan VARCHAR(100) NOT NULL,
            qty INT NOT NULL DEFAULT 1,
            satuan VARCHAR(20) DEFAULT 'PCS',
            harga_beli DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            harga_jual DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            jenis_harga VARCHAR(20) DEFAULT 'ECER',
            subtotal DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            FOREIGN KEY (penjualan_id) REFERENCES penjualan(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdoBarang = $pdo;

} catch (PDOException $e) {
    die("Inisialisasi Tabel MariaDB Gagal: " . $e->getMessage());
}