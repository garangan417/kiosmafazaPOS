<?php
// kasir/api_retur_item.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method tidak diizinkan!']);
    exit;
}

$inputData = json_decode(file_get_contents('php://input'), true);

$penjualanId       = intval($inputData['penjualan_id'] ?? 0);
$penjualanDetailId = intval($inputData['penjualan_detail_id'] ?? 0);
$qtyRetur          = intval($inputData['qty_retur'] ?? 0);
$alasan            = trim($inputData['alasan'] ?? 'Barang Cacat/Rusak');

if ($penjualanId <= 0 || $penjualanDetailId <= 0 || $qtyRetur <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Data input tidak valid!']);
    exit;
}

try {
    $pdoBarang->beginTransaction();

    // 1. Ambil detail item dari penjualan_detail
    $stmtItem = $pdoBarang->prepare("SELECT * FROM penjualan_detail WHERE id = ? AND penjualan_id = ?");
    $stmtItem->execute([$penjualanDetailId, $penjualanId]);
    $item = $stmtItem->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception("Detail item tidak ditemukan!");
    }

    // 2. Cek total Qty yang sudah diretur sebelumnya untuk item ini
    $stmtCekRetur = $pdoBarang->prepare("SELECT COALESCE(SUM(qty_retur), 0) FROM retur_penjualan_detail WHERE penjualan_detail_id = ?");
    $stmtCekRetur->execute([$penjualanDetailId]);
    $alreadyReturnedQty = intval($stmtCekRetur->fetchColumn());

    $maxCanReturn = intval($item['qty']) - $alreadyReturnedQty;

    if ($qtyRetur > $maxCanReturn) {
        throw new Exception("Qty retur ($qtyRetur) melebihi batas yang dapat diretur ($maxCanReturn)!");
    }

    $hargaJual  = floatval($item['harga_jual']);
    $hargaBeli  = floatval($item['harga_beli'] ?? 0);
    $subtotal   = $hargaJual * $qtyRetur;
    $noRetur    = 'RTR-' . date('YmdHis') . '-' . rand(100, 999);

    // 3. Insert ke Header (retur_penjualan)
    $sqlHeader = "INSERT INTO retur_penjualan (no_retur, penjualan_id, total_retur, alasan) VALUES (?, ?, ?, ?)";
    $stmtHeader = $pdoBarang->prepare($sqlHeader);
    $stmtHeader->execute([$noRetur, $penjualanId, $subtotal, $alasan]);
    $returHeaderId = $pdoBarang->lastInsertId();

    // 4. Insert ke Detail (retur_penjualan_detail)
    $sqlDetail = "INSERT INTO retur_penjualan_detail 
                  (retur_penjualan_id, penjualan_detail_id, barang_kemasan_id, nama_barang, nama_kemasan, qty_retur, harga_jual, harga_beli, subtotal)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmtDetail = $pdoBarang->prepare($sqlDetail);
    $stmtDetail->execute([
        $returHeaderId,
        $penjualanDetailId,
        $item['barang_kemasan_id'],
        $item['nama_barang'],
        $item['nama_kemasan'],
        $qtyRetur,
        $hargaJual,
        $hargaBeli,
        $subtotal
    ]);

    // 5. Kembalikan Stok Barang & Catat Mutasi
    if (!empty($item['barang_kemasan_id'])) {
        $kemasanId = $item['barang_kemasan_id'];

        $stmtStok = $pdoBarang->prepare("SELECT COALESCE(stok, 0) FROM barang_kemasan WHERE id = ?");
        $stmtStok->execute([$kemasanId]);
        $stokSebelum = intval($stmtStok->fetchColumn());
        $stokSesudah = $stokSebelum + $qtyRetur;

        $stmtUpdStok = $pdoBarang->prepare("UPDATE barang_kemasan SET stok = ? WHERE id = ?");
        $stmtUpdStok->execute([$stokSesudah, $kemasanId]);

        try {
            $stmtMutasi = $pdoBarang->prepare("INSERT INTO stok_mutasi (barang_kemasan_id, jenis_mutasi, qty, stok_sebelum, stok_sesudah, keterangan) VALUES (?, 'RETUR_MASUK', ?, ?, ?, ?)");
            $stmtMutasi->execute([$kemasanId, $qtyRetur, $stokSebelum, $stokSesudah, 'Retur Penjualan: ' . $noRetur]);
        } catch (Exception $e) {
            // Biarkan jika tabel mutasi opsional
        }
    }

    $pdoBarang->commit();

    echo json_encode([
        'status'  => 'success',
        'message' => 'Retur barang berhasil diproses dan stok telah diperbarui!'
    ]);

} catch (Exception $e) {
    if ($pdoBarang->inTransaction()) {
        $pdoBarang->rollBack();
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}