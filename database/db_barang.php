<?php
// database/db_barang.php

require_once __DIR__ . '/../config.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Error: Variabel \$pdo dari config.php tidak ditemukan.");
}

try {
    // ============================================================
    // 1. TABEL MASTER (BARANG, KATEGORI, KEMASAN, HARGA)
    // ============================================================

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
            stok INT NOT NULL DEFAULT 0, -- Dibiarkan untuk kompatibilitas, tapi sistem baru pakai barang_stok
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

    // ============================================================
    // 2. TABEL TRANSAKSI PENJUALAN (KASIR)
    // ============================================================

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

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS retur_penjualan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            no_retur VARCHAR(50) NOT NULL UNIQUE,
            penjualan_id INT NOT NULL,
            total_retur DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            alasan TEXT NULL,
            tanggal DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (penjualan_id) REFERENCES penjualan(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS retur_penjualan_detail (
            id INT AUTO_INCREMENT PRIMARY KEY,
            retur_penjualan_id INT NOT NULL,
            penjualan_detail_id INT NOT NULL,
            barang_kemasan_id INT DEFAULT NULL,
            nama_barang VARCHAR(255) NOT NULL,
            nama_kemasan VARCHAR(100) NOT NULL,
            qty_retur INT NOT NULL DEFAULT 1,
            harga_jual DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            harga_beli DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            subtotal DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            FOREIGN KEY (retur_penjualan_id) REFERENCES retur_penjualan(id) ON DELETE CASCADE,
            FOREIGN KEY (penjualan_detail_id) REFERENCES penjualan_detail(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // ============================================================
    // 3. TABEL STOK V2 (SIDECAR & MUTASI)
    // ============================================================

    // Stok per barang (level barang, bukan kemasan)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS barang_stok (
            barang_id INT PRIMARY KEY,
            stok_base INT NOT NULL DEFAULT 0,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (barang_id) REFERENCES barang(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Riwayat mutasi stok V2 (append-only)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS stok_mutasi (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            barang_id INT NOT NULL,
            barang_kemasan_id INT NOT NULL,
            jenis_mutasi VARCHAR(30) NOT NULL,
            arah ENUM('IN','OUT') GENERATED ALWAYS AS (
                CASE WHEN jenis_mutasi LIKE 'MASUK%' THEN 'IN' ELSE 'OUT' END
            ) STORED,
            qty_base INT NOT NULL,
            qty_kemasan INT NULL,
            isi_saat_itu INT NULL,
            satuan_saat_itu VARCHAR(20) NULL,
            stok_sebelum INT NOT NULL,
            stok_sesudah INT NOT NULL,
            ref_type VARCHAR(30) NULL,
            ref_id BIGINT NULL,
            keterangan TEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (barang_id) REFERENCES barang(id) ON DELETE RESTRICT,
            FOREIGN KEY (barang_kemasan_id) REFERENCES barang_kemasan(id) ON DELETE RESTRICT,
            INDEX idx_mutasi_barang (barang_id, created_at),
            INDEX idx_mutasi_ref (ref_type, ref_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // ============================================================
    // 4. TABEL KULAKAN (RENCANA BELANJA)
    // ============================================================

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kulakan (
            id INT AUTO_INCREMENT PRIMARY KEY,
            no_kulakan VARCHAR(50) NOT NULL UNIQUE,
            nama_kulakan VARCHAR(150) NOT NULL,
            supplier VARCHAR(100) DEFAULT NULL,
            total_modal DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            catatan TEXT NULL,
            status ENUM('BELUM_DATANG', 'SELESAI', 'BATAL') NOT NULL DEFAULT 'BELUM_DATANG',
            realisasi_at DATETIME NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS kulakan_detail (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kulakan_id INT NOT NULL,
            barang_kemasan_id INT NOT NULL,
            nama_barang VARCHAR(255) NOT NULL,
            nama_kemasan VARCHAR(100) NOT NULL,
            satuan VARCHAR(20) NOT NULL DEFAULT 'PCS',
            isi INT NOT NULL DEFAULT 1,
            qty INT NOT NULL DEFAULT 1,
            qty_realisasi INT NOT NULL DEFAULT 0,
            harga_beli DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            harga_beli_aktual DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            subtotal_modal DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
            FOREIGN KEY (kulakan_id) REFERENCES kulakan(id) ON DELETE CASCADE,
            FOREIGN KEY (barang_kemasan_id) REFERENCES barang_kemasan(id) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // ============================================================
    // 5. TABEL PENDUKUNG & PENGATURAN
    // ============================================================

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

    // Insert pengaturan default
    $pdo->exec("
        INSERT IGNORE INTO pengaturan (kunci, nilai) VALUES 
        ('fitur_stok', '1'),
        ('izinkan_stok_minus', '0'),
        ('fitur_stok_v2', '1'),
        ('fitur_kulakan', '1')
    ");

    // ============================================================
    // 6. BACKFILL: Pastikan semua barang punya baris di barang_stok
    // ============================================================
    $pdo->exec("
        INSERT IGNORE INTO barang_stok (barang_id, stok_base)
        SELECT id, 0 FROM barang
    ");
    
        // ============================================================
    // 6. TABEL JEMBATAN STOK (HUBUNGAN ANTAR KEMASAN)
    // ============================================================
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS jembatan_stok (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kemasan_sumber_id INT NOT NULL,
            kemasan_tujuan_id INT NOT NULL,
            isi_konversi INT NOT NULL DEFAULT 12,
            is_aktif_otomatis TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (kemasan_sumber_id) REFERENCES barang_kemasan(id) ON DELETE CASCADE,
            FOREIGN KEY (kemasan_tujuan_id) REFERENCES barang_kemasan(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $pdoBarang = $pdo;

} catch (PDOException $e) {
    die("Inisialisasi Tabel MariaDB Gagal: " . $e->getMessage());
}
