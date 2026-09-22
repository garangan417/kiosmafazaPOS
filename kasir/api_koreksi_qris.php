<?php
// kasir/api_koreksi_qris.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method tidak diizinkan.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$tanggal    = $input['tanggal'] ?? date('Y-m-d');
$nominal    = filter_var($input['nominal'] ?? null, FILTER_VALIDATE_FLOAT);
$keterangan = trim($input['keterangan'] ?? 'Penyesuaian Saldo QRIS');

if ($nominal === false || $nominal <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Nominal tidak valid!']);
    exit;
}

try {
    $sql = "INSERT INTO qris_penyesuaian (tanggal, nominal, keterangan) VALUES (?, ?, ?)";
    $stmt = $pdoBarang->prepare($sql);
    $stmt->execute([$tanggal, $nominal, $keterangan]);

    echo json_encode([
        'status'  => 'success',
        'message' => 'Saldo penyesuaian QRIS berhasil ditambahkan!'
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database Error: ' . $e->getMessage()]);
}