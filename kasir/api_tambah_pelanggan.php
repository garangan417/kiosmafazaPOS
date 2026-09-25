<?php
// kasir/api_tambah_pelanggan.php
require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_pelanggan.php';

header('Content-Type: application/json');

$nama   = mb_strtoupper(trim($_POST['nama'] ?? ''));
$no_hp  = trim($_POST['no_hp'] ?? '');
$alamat = trim($_POST['alamat'] ?? '');

// Validasi
if (empty($nama)) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Nama pelanggan wajib diisi!'
    ]);
    exit;
}

try {
    // Cek duplikat nama (opsional, biar tidak dobel)
    $cek = $pdoPelanggan->prepare("SELECT id FROM pelanggan WHERE nama = ? LIMIT 1");
    $cek->execute([$nama]);

    if ($cek->fetch()) {
        echo json_encode([
            'status'  => 'error',
            'message' => "Pelanggan dengan nama \"$nama\" sudah terdaftar!"
        ]);
        exit;
    }

    // Insert pelanggan baru
    $stmt = $pdoPelanggan->prepare(
        "INSERT INTO pelanggan (nama, no_hp, alamat) VALUES (?, ?, ?)"
    );
    $stmt->execute([$nama, $no_hp, $alamat]);

    $newId = $pdoPelanggan->lastInsertId();

    echo json_encode([
        'status'  => 'success',
        'message' => 'Pelanggan berhasil ditambahkan',
        'data'    => [
            'id'     => (int) $newId,
            'nama'   => $nama,
            'no_hp'  => $no_hp,
            'alamat' => $alamat
        ]
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Gagal menyimpan: ' . $e->getMessage()
    ]);
}