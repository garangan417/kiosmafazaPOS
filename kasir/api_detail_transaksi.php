<?php
// kasir/api_detail_transaksi.php
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ID Transaksi tidak valid']);
    exit;
}

try {
    // Header
    $stmtH = $pdoBarang->prepare("SELECT * FROM penjualan WHERE id = ?");
    $stmtH->execute([$id]);
    $header = $stmtH->fetch(PDO::FETCH_ASSOC);

    if (!$header) {
        echo json_encode(['status' => 'error', 'message' => 'Transaksi tidak ditemukan']);
        exit;
    }

    // Details
    $stmtD = $pdoBarang->prepare("SELECT * FROM penjualan_detail WHERE penjualan_id = ?");
    $stmtD->execute([$id]);
    $details = $stmtD->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'header' => $header,
        'details' => $details
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}