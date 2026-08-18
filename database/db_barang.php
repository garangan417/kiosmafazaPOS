<?php
// database/db_barang.php
require_once __DIR__ . '/../config.php';

try {
    $dbPath = BASE_PATH . 'database/barang.sqlite';
    $pdoBarang = new PDO("sqlite:" . $dbPath);
    $pdoBarang->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdoBarang->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Aktifkan Foreign Key Constraint SQLite
    $pdoBarang->exec("PRAGMA foreign_keys = ON;");

    // 1. TABEL KATEGORI
    $pdoBarang->exec("
        CREATE TABLE IF NOT EXISTS kategori (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nama_kategori VARCHAR(100) NOT NULL UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 2. TABEL BARANG (Master)
    $pdoBarang->exec("
        CREATE TABLE IF NOT EXISTS barang (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            kategori_id INTEGER NOT NULL,
            nama_barang VARCHAR(150) NOT NULL,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (kategori_id) REFERENCES kategori(id) ON DELETE RESTRICT
        );
    ");

    // 3. TABEL BARANG KEMASAN (Rujukan Nilai 'isi')
    $pdoBarang->exec("
        CREATE TABLE IF NOT EXISTS barang_kemasan (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            barang_id INTEGER NOT NULL,
            nama_kemasan VARCHAR(50) NOT NULL,
            satuan VARCHAR(20) NOT NULL DEFAULT 'PCS',
            isi INTEGER NOT NULL DEFAULT 1,
            stok INTEGER NOT NULL DEFAULT 0,
            is_favorite INTEGER NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (barang_id) REFERENCES barang(id) ON DELETE CASCADE
        );
    ");

    // 4. TABEL BARANG BARCODE
    $pdoBarang->exec("
        CREATE TABLE IF NOT EXISTS barang_barcode (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            barang_kemasan_id INTEGER NOT NULL,
            barcode VARCHAR(50) NOT NULL UNIQUE,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (barang_kemasan_id) REFERENCES barang_kemasan(id) ON DELETE CASCADE
        );
    ");

    // 5. TABEL HARGA BARANG (Simpan Harga Induk + Hasil Hitung Ecer)
    $pdoBarang->exec("
        CREATE TABLE IF NOT EXISTS harga_barang (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            barang_kemasan_id INTEGER NOT NULL UNIQUE,
            harga_beli REAL NOT NULL DEFAULT 0.00,
            harga_beli_pcs REAL NOT NULL DEFAULT 0.00,
            harga_jual_ecer REAL NOT NULL DEFAULT 0.00,
            harga_jual_grosir REAL NOT NULL DEFAULT 0.00,
            min_qty_grosir INTEGER NOT NULL DEFAULT 1,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (barang_kemasan_id) REFERENCES barang_kemasan(id) ON DELETE CASCADE
        );
    ");

    // 6. TABEL MUTASI STOK
    $pdoBarang->exec("
        CREATE TABLE IF NOT EXISTS stok_mutasi (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            barang_kemasan_id INTEGER NOT NULL,
            jenis_mutasi VARCHAR(20) NOT NULL,
            qty INTEGER NOT NULL,
            stok_sebelum INTEGER NOT NULL,
            stok_sesudah INTEGER NOT NULL,
            keterangan TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (barang_kemasan_id) REFERENCES barang_kemasan(id) ON DELETE CASCADE
        );
    ");

    // 7. TABEL STOK OPNAME
    $pdoBarang->exec("
        CREATE TABLE IF NOT EXISTS stok_opname (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            barang_kemasan_id INTEGER NOT NULL,
            stok_sistem INTEGER NOT NULL,
            stok_fisik INTEGER NOT NULL,
            selisih INTEGER NOT NULL,
            keterangan TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (barang_kemasan_id) REFERENCES barang_kemasan(id) ON DELETE CASCADE
        );
    ");

    // 8. TABEL PENGATURAN (Fitur Stok & Stok Minus)
    $pdoBarang->exec("
        CREATE TABLE IF NOT EXISTS pengaturan (
            kunci VARCHAR(50) PRIMARY KEY,
            nilai VARCHAR(255) NOT NULL
        );
    ");

    // Inisialisasi Default Nilai Pengaturan jika belum ada
    $pdoBarang->exec("
        INSERT OR IGNORE INTO pengaturan (kunci, nilai) VALUES 
        ('fitur_stok', '1'),
        ('izinkan_stok_minus', '0');
    ");

    // 9. TABEL PENJUALAN (Header Faktur / Struk)
    $pdoBarang->exec("
        CREATE TABLE IF NOT EXISTS penjualan (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            no_faktur VARCHAR(50) NOT NULL UNIQUE,
            pelanggan_id INTEGER DEFAULT NULL,
            total_kotor REAL NOT NULL DEFAULT 0.00,
            diskon REAL NOT NULL DEFAULT 0.00,
            total_bersih REAL NOT NULL DEFAULT 0.00,
            bayar REAL NOT NULL DEFAULT 0.00,
            kembalian REAL NOT NULL DEFAULT 0.00,
            metode_bayar VARCHAR(20) NOT NULL DEFAULT 'TUNAI',
            catatan TEXT,
            tanggal DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // 10. TABEL PENJUALAN DETAIL (Item Per Transaksi)
    $pdoBarang->exec("
        CREATE TABLE IF NOT EXISTS penjualan_detail (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            penjualan_id INTEGER NOT NULL,
            barang_kemasan_id INTEGER DEFAULT NULL,
            nama_barang VARCHAR(255) NOT NULL,
            nama_kemasan VARCHAR(100) NOT NULL,
            qty INTEGER NOT NULL DEFAULT 1,
            satuan VARCHAR(20) DEFAULT 'PCS',
            harga_beli REAL NOT NULL DEFAULT 0.00,
            harga_jual REAL NOT NULL DEFAULT 0.00,
            jenis_harga VARCHAR(20) DEFAULT 'ECER',
            subtotal REAL NOT NULL DEFAULT 0.00,
            FOREIGN KEY (penjualan_id) REFERENCES penjualan(id) ON DELETE CASCADE
        );
    ");

} catch (PDOException $e) {
    die("Koneksi / Inisialisasi Database SQLite Gagal: " . $e->getMessage());
}