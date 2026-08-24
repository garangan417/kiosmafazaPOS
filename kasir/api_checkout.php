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

    $noFaktur     = $input['no_faktur'] ?? ('INV/' . date('Ymd') . '/' . rand(1000, 9999));
    $tglTransaksi = !empty($input['tanggal']) ? $input['tanggal'] : date('Y-m-d');

    $itemsFisik = [];
    $itemsJasa  = [];
    $totalFisik = 0;

    // Pemisahan item keranjang: Jasa/PPOB vs Barang Fisik
    foreach ($input['items'] as $item) {
        $isJasa = !empty($item['is_jasa']);

        if ($isJasa) {
            $itemsJasa[] = [
                'kategori'   => $item['kategori'] ?? 'PPOB / Jasa',
                'keterangan' => $item['keterangan'] ?? $item['nama_barang'] ?? '',
                'harga_beli' => floatval($item['harga_beli'] ?? 0),
                'harga_jual' => floatval($item['harga_jual'] ?? 0),
                'qty'        => intval($item['qty'] ?? 1),
                'subtotal'   => floatval($item['subtotal'] ?? 0)
            ];
        } else {
            $sub = floatval($item['subtotal'] ?? 0);
            $totalFisik += $sub;

            $itemsFisik[] = [
                'kemasan_id'   => intval($item['kemasan_id']),
                'nama_barang'  => $item['nama_barang'] ?? '',
                'nama_kemasan' => $item['nama_kemasan'] ?? '',
                'qty'          => intval($item['qty']),
                'satuan'       => $item['satuan'] ?? 'pcs',
                'harga_beli'   => floatval($item['harga_beli'] ?? 0),
                'harga_jual'   => floatval($item['harga_jual'] ?? 0),
                'jenis_harga'  => $item['jenis_harga'] ?? 'ECER',
                'subtotal'     => $sub
            ];
        }
    }

    // =========================================================================
    // 1. PROSES PENYIMPANAN JASA (Disesuaikan persis dengan Form Transaksi)
    // =========================================================================
    if (!empty($itemsJasa)) {
        if (!isset($pdoKios)) {
            $pathKios = BASE_PATH . 'database/kios.sqlite';
            $pdoKios  = new PDO("sqlite:" . $pathKios);
            $pdoKios->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }

        // Ambil saldo akhir terakhir untuk menghitung running saldo
        $stmtSaldo = $pdoKios->query("SELECT saldo_akhir FROM transaksi ORDER BY id DESC LIMIT 1");
        $lastRow   = $stmtSaldo->fetch(PDO::FETCH_ASSOC);
        $lastSaldo = $lastRow ? floatval($lastRow['saldo_akhir']) : 0;

        $runningSaldo = $lastSaldo;

        $stmtTx = $pdoKios->prepare("
            INSERT INTO transaksi (tanggal, tipe, kategori, nominal, saldo_akhir, keterangan)
            VALUES (:tanggal, 'masuk', :kategori, :nominal, :saldo_akhir, :keterangan)
        ");

        foreach ($itemsJasa as $jasa) {
            $runningSaldo += $jasa['subtotal'];

            $stmtTx->execute([
                ':tanggal'     => $tglTransaksi,
                ':kategori'    => $jasa['kategori'],
                ':nominal'     => $jasa['subtotal'],
                ':saldo_akhir' => $runningSaldo,
                ':keterangan'  => trim($jasa['keterangan'])
            ]);
        }
    }

    // =========================================================================
    // 2. PROSES PENYIMPANAN BARANG FISIK (ke barang.sqlite)
    // =========================================================================
    if (!empty($itemsFisik)) {
        $headerFisik = [
            'no_faktur'    => $noFaktur,
            'pelanggan_id' => null,
            'total_kotor'  => $totalFisik,
            'diskon'       => floatval($input['diskon'] ?? 0),
            'total_bersih' => $totalFisik - floatval($input['diskon'] ?? 0),
            'bayar'        => floatval($input['bayar'] ?? 0),
            'kembalian'    => floatval($input['kembalian'] ?? 0),
            'metode_bayar' => $input['metode_bayar'] ?? 'TUNAI',
            'catatan'      => $input['catatan'] ?? ''
        ];

        simpanPenjualan($pdoBarang, $headerFisik, $itemsFisik);
    }

    echo json_encode([
        'status'    => true,
        'message'   => 'Transaksi berhasil diproses',
        'no_faktur' => $noFaktur
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => false, 'message' => 'Error Server: ' . $e->getMessage()]);
}