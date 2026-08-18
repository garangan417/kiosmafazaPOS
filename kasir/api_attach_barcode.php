<?php
// kasir/api_attach_barcode.php
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';

$data = json_decode(file_get_contents('php_input'), true);

$kemasanId = intval($data['barang_kemasan_id'] ?? 0);
$barcode   = trim($data['barcode'] ?? '');

if ($kemasanId <= 0 || empty($barcode)) {
    echo json_encode(['status' => 'error', 'message' => 'Data barcode atau kemasan tidak valid.']);
    exit;
}

try {
    // Cek apakah barcode ini sudah terdaftar
    $stmtCek = $pdoBarang->prepare("SELECT COUNT(*) FROM barang_barcode WHERE barcode = ?");
    $stmtCek->execute([$barcode]);
    if ($stmtCek->fetchColumn() > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Barcode sudah terdaftar pada produk lain.']);
        exit;
    }

    // Insert barcode baru
    $stmtIns = $pdoBarang->prepare("INSERT INTO barang_barcode (barang_kemasan_id, barcode) VALUES (?, ?)");
    $stmtIns->execute([$kemasanId, $barcode]);

    echo json_encode(['status' => 'success', 'message' => 'Barcode berhasil dihubungkan!']);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}