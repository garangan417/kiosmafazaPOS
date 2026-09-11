<?php
// database/db_pelanggan.php
require_once __DIR__ . '/../config.php';

try {
    $dbPath = BASE_PATH . 'database/pelanggan.sqlite';
    $pdoPelanggan = new PDO("sqlite:" . $dbPath);
    $pdoPelanggan->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Aktifkan dukungan Foreign Key di SQLite
    $pdoPelanggan->exec("PRAGMA foreign_keys = ON;");

    // 1. Otomatis buat tabel pelanggan jika belum ada
    $pdoPelanggan->exec("CREATE TABLE IF NOT EXISTS pelanggan (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nama TEXT NOT NULL,
        no_hp TEXT,
        alamat TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Otomatis buat tabel utang jika belum ada (langsung sertakan items_json)
    $pdoPelanggan->exec("CREATE TABLE IF NOT EXISTS utang (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        pelanggan_id INTEGER NOT NULL,
        tipe TEXT NOT NULL CHECK(tipe IN ('utang', 'bayar')),
        nominal REAL NOT NULL DEFAULT 0,
        keterangan TEXT,
        items_json TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (pelanggan_id) REFERENCES pelanggan(id) ON DELETE CASCADE
    )");

    // 3. Auto-Migration Safe Guard: Cek & Tambah kolom items_json otomatis jika database lama sudah ada
    $cols = $pdoPelanggan->query("PRAGMA table_info(utang)")->fetchAll(PDO::FETCH_ASSOC);
    $hasItemsJson = false;
    foreach ($cols as $col) {
        if ($col['name'] === 'items_json') {
            $hasItemsJson = true;
            break;
        }
    }
    if (!$hasItemsJson) {
        $pdoPelanggan->exec("ALTER TABLE utang ADD COLUMN items_json TEXT");
    }

} catch (PDOException $e) {
    die("Koneksi Database Pelanggan Gagal: " . $e->getMessage());
}

// ===================================================
// HELPER PERHITUNGAN SESI UTANG & SISA UTANG
// ===================================================
if (!function_exists('hitungSisaUtangPelanggan')) {
    function hitungSisaUtangPelanggan($pdoPelanggan, $pelanggan_id) {
        // Ambil riwayat utang dari TERLAMA ke TERBARU
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

            // Jika lunas / lebih, reset sesi untuk transaksi berikutnya
            if ($totalUtangSesi > 0 && $totalBayarSesi >= $totalUtangSesi) {
                $totalUtangSesi = 0;
                $totalBayarSesi = 0;
            }
        }

        return max(0, $totalUtangSesi - $totalBayarSesi);
    }
}