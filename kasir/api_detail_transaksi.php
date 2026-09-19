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
    // 1. Header Penjualan
    $stmtH = $pdoBarang->prepare("SELECT * FROM penjualan WHERE id = ?");
    $stmtH->execute([$id]);
    $header = $stmtH->fetch(PDO::FETCH_ASSOC);

    if (!$header) {
        echo json_encode(['status' => 'error', 'message' => 'Transaksi tidak ditemukan']);
        exit;
    }

    // 2. Details Penjualan + Subquery Total Qty Retur per Item
    $sqlD = "SELECT 
                d.*,
                COALESCE((
                    SELECT SUM(rd.qty_retur) 
                    FROM retur_penjualan_detail rd 
                    WHERE rd.penjualan_detail_id = d.id
                ), 0) AS qty_retur
             FROM penjualan_detail d
             WHERE d.penjualan_id = ?";
             
    $stmtD = $pdoBarang->prepare($sqlD);
    $stmtD->execute([$id]);
    $details = $stmtD->fetchAll(PDO::FETCH_ASSOC);

    // 3. Response JSON (Sesuai struktur asli)
    echo json_encode([
        'status'  => 'success',
        'header'  => $header,
        'details' => $details
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}