<?php
// kasir/api_search.php
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';

$q = trim($_GET['q'] ?? '');

if (empty($q)) {
    echo json_encode(['status' => 'empty', 'data' => []]);
    exit;
}

try {
    // Cari berdasarkan barcode (exact) atau nama barang/kemasan (like)
    // PERBAIKAN: Hitung harga_beli per PCS / unit kecil (dibagi isi kemasan)
    $sql = "SELECT 
                bk.id AS kemasan_id,
                b.nama_barang,
                bk.nama_kemasan,
                bk.satuan,
                bk.isi,
                COALESCE(
                    CASE 
                        WHEN h.harga_beli_pcs > 0 THEN h.harga_beli_pcs
                        WHEN h.harga_beli > 0 THEN h.harga_beli / COALESCE(NULLIF(bk.isi, 0), 1)
                        ELSE 0
                    END, 0
                ) AS harga_beli,
                COALESCE(h.harga_jual_ecer, 0) AS harga_ecer,
                COALESCE(h.harga_jual_grosir, 0) AS harga_grosir,
                COALESCE(h.min_qty_grosir, 1) AS min_qty_grosir,
                (SELECT barcode FROM barang_barcode WHERE barang_kemasan_id = bk.id LIMIT 1) AS barcode
            FROM barang_kemasan bk
            JOIN barang b ON bk.barang_id = b.id
            LEFT JOIN harga_barang h ON bk.id = h.barang_kemasan_id
            WHERE bk.id IN (SELECT barang_kemasan_id FROM barang_barcode WHERE barcode = ?)
               OR b.nama_barang LIKE ?
               OR bk.nama_kemasan LIKE ?
            ORDER BY b.nama_barang ASC, bk.isi ASC
            LIMIT 15";

    $searchTerm = '%' . $q . '%';
    $stmt = $pdoBarang->prepare($sql);
    $stmt->execute([$q, $searchTerm, $searchTerm]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cek apakah pencarian ini hasil dari Exact Match Barcode
    $isBarcodeMatch = false;
    if (count($results) === 1 && $results[0]['barcode'] === $q) {
        $isBarcodeMatch = true;
    }

    echo json_encode([
        'status' => 'success',
        'is_barcode' => $isBarcodeMatch,
        'data' => $results
    ]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}