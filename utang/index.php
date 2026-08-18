<?php
// utang/index.php
require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_pelanggan.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pelanggan_id = intval($_POST['pelanggan_id'] ?? 0);
    $tipe         = trim($_POST['tipe'] ?? 'utang');
    $keterangan   = trim($_POST['keterangan'] ?? '');
    $tanggal      = $_POST['tanggal'] ?? date('Y-m-d H:i:s');
    
    $nominal_raw  = $_POST['nominal'] ?? '0';
    $nominal      = floatval(preg_replace('/[^0-9]/', '', $nominal_raw));

    if ($pelanggan_id > 0 && $nominal > 0) {
        $stmt = $pdoPelanggan->prepare("
            INSERT INTO utang (pelanggan_id, tipe, nominal, keterangan, created_at) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$pelanggan_id, $tipe, $nominal, $keterangan, $tanggal]);
    }

    // Redirect penuh kembali ke halaman pelanggan (Otomatis reload & hapus modal/backdrop)
    header("Location: " . BASE_URL . "pelanggan/index.php");
    exit;
}

// Jika diakses GET biasa, arahkan juga ke daftar pelanggan
header("Location: " . BASE_URL . "pelanggan/index.php");
exit;