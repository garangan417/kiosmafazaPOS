<?php
// kasir/api_bayar_utang.php
require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_pelanggan.php';

header('Content-Type: application/json');

$pelanggan_id = intval($_POST['pelanggan_id'] ?? 0);
$nominal_raw  = $_POST['nominal'] ?? '0';
$keterangan   = trim($_POST['keterangan'] ?? '');
$tanggal      = trim($_POST['tanggal'] ?? '');

// Bersihkan titik pemisah ribuan
$nominal = floatval(preg_replace('/[^0-9]/', '', $nominal_raw));

// Validasi
if ($pelanggan_id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Pelanggan tidak valid!']);
    exit;
}

if ($nominal <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Nominal harus lebih dari 0!']);
    exit;
}

// Cek pelanggan exists
$stmt = $pdoPelanggan->prepare("SELECT id, nama FROM pelanggan WHERE id = ?");
$stmt->execute([$pelanggan_id]);
$pelanggan = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pelanggan) {
    echo json_encode(['status' => 'error', 'message' => 'Pelanggan tidak ditemukan!']);
    exit;
}

// Cek sisa utang saat ini
$sisaSekarang = hitungSisaUtangPelanggan($pdoPelanggan, $pelanggan_id);

if ($sisaSekarang <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Pelanggan ini tidak punya utang aktif.']);
    exit;
}

// Validasi: tidak boleh bayar lebih dari sisa utang
if ($nominal > $sisaSekarang) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Nominal bayar melebihi sisa utang (Rp ' . number_format($sisaSekarang, 0, ',', '.') . ')!'
    ]);
    exit;
}

// Set tanggal default jika kosong
if (empty($tanggal)) {
    $tanggal = date('Y-m-d H:i:s');
}

try {
    // Insert pembayaran
    $stmt = $pdoPelanggan->prepare("
        INSERT INTO utang (pelanggan_id, tipe, nominal, keterangan, created_at)
        VALUES (?, 'bayar', ?, ?, ?)
    ");
    $stmt->execute([$pelanggan_id, $nominal, $keterangan, $tanggal]);

    // Hitung sisa utang setelah bayar
    $sisaBaru = hitungSisaUtangPelanggan($pdoPelanggan, $pelanggan_id);

    $lunas = $sisaBaru <= 0;

    echo json_encode([
        'status'      => 'success',
        'message'     => $lunas
            ? '✅ Pembayaran berhasil! Utang ' . $pelanggan['nama'] . ' sudah LUNAS.'
            : '✅ Pembayaran berhasil dicatat. Sisa utang: Rp ' . number_format($sisaBaru, 0, ',', '.'),
        'nama'        => $pelanggan['nama'],
        'sisa_baru'   => (float) $sisaBaru,
        'sisa_format' => 'Rp ' . number_format($sisaBaru, 0, ',', '.'),
        'lunas'       => $lunas
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Gagal menyimpan: ' . $e->getMessage()
    ]);
}