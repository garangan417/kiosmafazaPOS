<?php
// kasir/api_checkout.php
header('Content-Type: application/json');

// Matikan error HTML agar respon JSON tidak rusak
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    require_once __DIR__ . '/../config.php';
    require_once BASE_PATH . 'database/db_barang.php';
    require_once BASE_PATH . 'database/query_pos.php';

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['status' => false, 'message' => 'Method tidak diizinkan']);
        exit;
    }

    $raw = file_get_contents('php://input');
    $input = json_decode($raw, true);

    if (!$input || empty($input['items'])) {
        echo json_encode(['status' => false, 'message' => 'Keranjang belanja kosong!']);
        exit;
    }

    $header = [
        'no_faktur'    => $input['no_faktur'] ?? ('INV/' . date('Ymd') . '/' . rand(1000, 9999)),
        'pelanggan_id' => null,
        'total_kotor'  => floatval($input['total_kotor'] ?? 0),
        'diskon'       => floatval($input['diskon'] ?? 0),
        'total_bersih' => floatval($input['total_bersih'] ?? 0),
        'bayar'        => floatval($input['bayar'] ?? 0),
        'kembalian'    => floatval($input['kembalian'] ?? 0),
        'metode_bayar' => $input['metode_bayar'] ?? 'TUNAI',
        'catatan'      => $input['catatan'] ?? ''
    ];

    $items = [];
    foreach ($input['items'] as $item) {
        $items[] = [
            'kemasan_id'   => intval($item['kemasan_id']),
            'nama_barang'  => $item['nama_barang'] ?? '',
            'nama_kemasan' => $item['nama_kemasan'] ?? '',
            'qty'          => intval($item['qty']),
            'satuan'       => $item['satuan'] ?? 'pcs',
            'harga_beli'   => floatval($item['harga_beli'] ?? 0),
            'harga_jual'   => floatval($item['harga_jual'] ?? 0),
            'jenis_harga'  => $item['jenis_harga'] ?? 'ECER',
            'subtotal'     => floatval($item['subtotal'] ?? 0)
        ];
    }

    // Simpan Transaksi via Helper
    $result = simpanPenjualan($pdoBarang, $header, $items);
    echo json_encode($result);

} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => 'Error Server: ' . $e->getMessage()]);
}