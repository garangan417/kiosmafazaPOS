<?php
// Path ke file database SQLite
$dbPath = __DIR__ . '/kios.sqlite';

try {
    // Membuka koneksi (dan otomatis membuat file jika belum ada)
    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Auto-generate tabel transaksi (dengan kolom tambahan saldo_akhir)
    $sql = "CREATE TABLE IF NOT EXISTS transaksi (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        tanggal TEXT NOT NULL,
        tipe TEXT CHECK(tipe IN ('masuk', 'keluar')) NOT NULL,
        kategori TEXT NOT NULL,
        nominal REAL NOT NULL,
        saldo_akhir REAL DEFAULT NULL,
        keterangan TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )";

    $pdo->exec($sql);

} catch (PDOException $e) {
    die("Koneksi / Pembuatan Database Gagal: " . $e->getMessage());
}