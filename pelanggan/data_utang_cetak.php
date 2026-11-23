<?php
// pelanggan/data_utang_cetak.php
require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_pelanggan.php';

header('Content-Type: application/json; charset=utf-8');

$pelanggan_id = intval($_GET['id'] ?? 0);
if ($pelanggan_id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'ID Pelanggan tidak valid.']);
    exit;
}

$stmtP = $pdoPelanggan->prepare("SELECT * FROM pelanggan WHERE id = ?");
$stmtP->execute([$pelanggan_id]);
$pelanggan = $stmtP->fetch(PDO::FETCH_ASSOC);

if (!$pelanggan) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Pelanggan tidak ditemukan.']);
    exit;
}

$stmtU = $pdoPelanggan->prepare("SELECT * FROM utang WHERE pelanggan_id = ? ORDER BY created_at ASC, id ASC");
$stmtU->execute([$pelanggan_id]);
$riwayatAsc = $stmtU->fetchAll(PDO::FETCH_ASSOC);

$totalUtangSesi = 0;
$totalBayarSesi = 0;
$sesiAktif = [];

foreach ($riwayatAsc as $r) {
    $nominal = floatval($r['nominal']);
    if ($r['tipe'] === 'utang') {
        $totalUtangSesi += $nominal;
    } else {
        $totalBayarSesi += $nominal;
    }
    $sesiAktif[] = $r;

    if ($totalUtangSesi > 0 && $totalBayarSesi >= $totalUtangSesi) {
        $totalUtangSesi = 0;
        $totalBayarSesi = 0;
        $sesiAktif = [];
    }
}

$sisaUtang = max(0, $totalUtangSesi - $totalBayarSesi);

$riwayatBelanja = [];
$daftarCicilan = [];

foreach ($sesiAktif as $item) {
    if ($item['tipe'] === 'utang') {
        $itemsBongkar = [];
        if (!empty($item['items_json'])) {
            $decoded = json_decode($item['items_json'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $it) {
                    $itemsBongkar[] = [
                        'nama_barang' => $it['nama_barang'] ?? 'Item',
                        'qty' => floatval($it['qty'] ?? 1),
                        'satuan' => $it['satuan'] ?? 'PCS',
                        'harga_jual' => floatval($it['harga_jual'] ?? 0),
                        'subtotal' => floatval($it['subtotal'] ?? (floatval($it['qty'] ?? 1) * floatval($it['harga_jual'] ?? 0))),
                    ];
                }
            }
        } else {
            $itemsBongkar[] = [
                'nama_barang' => $item['keterangan'] ?: 'Catatan Utang Manual',
                'qty' => 1,
                'satuan' => 'trx',
                'harga_jual' => floatval($item['nominal']),
                'subtotal' => floatval($item['nominal']),
            ];
        }

        $riwayatBelanja[] = [
            'tanggal' => date('d/m/Y H:i', strtotime($item['created_at'])),
            'keterangan' => $item['keterangan'] ?? '',
            'items' => $itemsBongkar,
            'total' => floatval($item['nominal']),
        ];
    } else {
        $daftarCicilan[] = [
            'tanggal' => date('d/m/Y H:i', strtotime($item['created_at'])),
            'keterangan' => $item['keterangan'] ?: 'Pembayaran',
            'nominal' => floatval($item['nominal']),
        ];
    }
}

echo json_encode([
    'ok' => true,
    'toko_nama' => 'KIOS MAFAZA',
    'toko_alamat' => 'Jl. Jend Sudirman ,langgar selatan',
    'toko_telp' => '0852 0530 7492',
    'pelanggan' => [
        'nama' => $pelanggan['nama'],
        'no_hp' => $pelanggan['no_hp'] ?: '-',
    ],
    'tgl_cetak' => date('d/m/Y H:i'),
    'riwayat_belanja' => $riwayatBelanja,
    'daftar_cicilan' => $daftarCicilan,
    'total_utang_sesi' => $totalUtangSesi,
    'total_bayar_sesi' => $totalBayarSesi,
    'sisa_utang' => $sisaUtang,
]);
