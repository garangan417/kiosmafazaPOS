<?php
// pelanggan/cetak_utang.php
require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_pelanggan.php';

$pelanggan_id = intval($_GET['id'] ?? 0);
if ($pelanggan_id <= 0) { die("ID Pelanggan tidak valid."); }

// 1. Ambil Data Pelanggan
$stmtP = $pdoPelanggan->prepare("SELECT * FROM pelanggan WHERE id = ?");
$stmtP->execute([$pelanggan_id]);
$pelanggan = $stmtP->fetch(PDO::FETCH_ASSOC);
if (!$pelanggan) { die("Pelanggan tidak ditemukan."); }

// 2. Ambil Riwayat
$stmtU = $pdoPelanggan->prepare("SELECT * FROM utang WHERE pelanggan_id = ? ORDER BY created_at ASC, id ASC");
$stmtU->execute([$pelanggan_id]);
$riwayatAsc = $stmtU->fetchAll(PDO::FETCH_ASSOC);

$totalUtangSesi = 0;
$totalBayarSesi = 0;
$sesiAktif = []; // Menyimpan data sesi yang belum lunas

foreach ($riwayatAsc as $r) {
    $nominal = floatval($r['nominal']);
    
    if ($r['tipe'] === 'utang') { 
        $totalUtangSesi += $nominal; 
    } else { 
        $totalBayarSesi += $nominal; 
    }

    $sesiAktif[] = $r;

    // Jika pembayaran sudah menutupi utang, sesi di-reset (dianggap lunas)
    if ($totalUtangSesi > 0 && $totalBayarSesi >= $totalUtangSesi) {
        $totalUtangSesi = 0;
        $totalBayarSesi = 0;
        $sesiAktif = []; // Kosongkan, karena ini sesi lama yang sudah lunas
    }
}

$sisaUtang = max(0, $totalUtangSesi - $totalBayarSesi);

// 3. Kelompokkan Transaksi dari Sesi Aktif
$riwayatBelanja = []; // Array untuk menampung transaksi utang beserta tanggalnya
$daftarCicilan = [];

foreach ($sesiAktif as $item) {
    if ($item['tipe'] === 'utang') {
        $itemsBongkar = [];
        
        // Bongkar items_json untuk mendapatkan detail barang
        if (!empty($item['items_json'])) {
            $items = json_decode($item['items_json'], true);
            if (is_array($items)) {
                foreach ($items as $it) {
                    $itemsBongkar[] = $it;
                }
            }
        } else {
            // Jika utang manual (tanpa JSON)
            $itemsBongkar[] = [
                'nama_barang' => $item['keterangan'] ?: 'Catatan Utang Manual',
                'qty' => 1,
                'satuan' => 'trx',
                'harga_jual' => $item['nominal'],
                'subtotal' => $item['nominal']
            ];
        }

        $riwayatBelanja[] = [
            'tanggal' => $item['created_at'],
            'no_faktur' => $item['keterangan'], // Bisa berisi keterangan bon
            'items' => $itemsBongkar,
            'total' => $item['nominal']
        ];
    } else {
        $daftarCicilan[] = $item;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nota Bon - <?= htmlspecialchars($pelanggan['nama']) ?></title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; font-size: 13px; color: #000; background: #f4f4f4; padding: 20px; }
        .struk-container { background: #fff; max-width: 400px; margin: 0 auto; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .fw-bold { font-weight: bold; }
        .border-top-dashed { border-top: 1px dashed #000; margin: 10px 0; }
        .border-bottom-dashed { border-bottom: 1px dashed #000; margin: 10px 0; }
        .border-bottom-dotted { border-bottom: 1px dotted #ccc; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table td { padding: 3px 0; vertical-align: top; }
        .bg-gray { background: #eee; padding: 5px; }
        .tanggal-transaksi { font-size: 11px; color: #555; background: #f9f9f9; padding: 4px; margin-bottom: 4px; border-radius: 3px; }
        
        @media print {
            body { background: #fff; padding: 0; }
            .struk-container { box-shadow: none; max-width: 100%; margin: 0; padding: 0; }
            .no-print { display: none !important; }
            @page { margin: 10mm; }
        }
    </style>
</head>
<body>

<div class="struk-container">
    <!-- Header Toko -->
    <div class="text-center mb-3">
        <h3 class="fw-bold" style="margin:0; font-size: 18px;">KIOS MAFAZA</h3>
        <div style="font-size: 11px;">Jl. Jend Sudirman ,langgar selatan</div>
        <div style="font-size: 11px;">Telp/WA: 0852 0530 7492</div>
    </div>
    
    <div class="border-top-dashed"></div>

    <!-- Info Pelanggan -->
    <table>
        <tr>
            <td class="fw-bold">Nama</td>
            <td>: <?= htmlspecialchars($pelanggan['nama']) ?></td>
        </tr>
        <tr>
            <td class="fw-bold">No. HP</td>
            <td>: <?= htmlspecialchars($pelanggan['no_hp'] ?: '-') ?></td>
        </tr>
        <tr>
            <td class="fw-bold">Tgl Cetak</td>
            <td>: <?= date('d/m/Y H:i') ?></td>
        </tr>
    </table>

    <div class="border-top-dashed"></div>

    <!-- RINCIAN BARANG YANG DIAMBIL -->
    <div class="bg-gray mb-2 fw-bold">RINCIAN PENGAMBILAN BARANG</div>
    
    <?php if(empty($riwayatBelanja)): ?>
        <div class="text-center py-2">Tidak ada rincian barang.</div>
    <?php else: ?>
        <?php foreach ($riwayatBelanja as $belanja): ?>
            <!-- Judul per Transaksi (Tanggal & No Faktur) -->
            <div class="tanggal-transaksi">
                <span class="fw-bold"><i class="bi bi-calendar-event"></i> <?= date('d/m/Y H:i', strtotime($belanja['tanggal'])) ?></span>
            </div>
            
            <table>
                <?php foreach ($belanja['items'] as $it): 
                    $nama = $it['nama_barang'] ?? 'Item';
                    $qty = floatval($it['qty'] ?? 1);
                    $satuan = $it['satuan'] ?? 'PCS';
                    $harga = floatval($it['harga_jual'] ?? 0);
                    $subtotal = floatval($it['subtotal'] ?? ($harga * $qty));
                ?>
                <tr>
                    <td colspan="2" class="fw-bold"><?= htmlspecialchars($nama) ?></td>
                </tr>
                <tr>
                    <td style="font-size: 11px; color: #555;">
                        <?= $qty ?> <?= htmlspecialchars($satuan) ?> x <?= number_format($harga, 0, ',', '.') ?>
                    </td>
                    <td class="text-right fw-bold">
                        <?= number_format($subtotal, 0, ',', '.') ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
            <div class="border-bottom-dotted"></div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="border-bottom-dashed"></div>

    <!-- RINGKASAN KEUANGAN -->
    <table>
        <tr>
            <td class="fw-bold">Total Belanja (Sesi Ini)</td>
            <td class="text-right fw-bold">Rp <?= number_format($totalUtangSesi, 0, ',', '.') ?></td>
        </tr>
        <?php if ($totalBayarSesi > 0): ?>
        <tr>
            <td>Total Dibayar / Dicicil</td>
            <td class="text-right">- Rp <?= number_format($totalBayarSesi, 0, ',', '.') ?></td>
        </tr>
        <?php endif; ?>
        <tr style="font-size: 16px;">
            <td class="fw-bold" style="padding-top: 10px;">SISA TAGIHAN</td>
            <td class="text-right fw-bold" style="padding-top: 10px;">Rp <?= number_format($sisaUtang, 0, ',', '.') ?></td>
        </tr>
    </table>

    <div class="border-bottom-dashed"></div>

    <!-- RIWAYAT CICILAN (JIKA ADA) -->
    <?php if (!empty($daftarCicilan)): ?>
    <div class="bg-gray mb-2 fw-bold">RIWAYAT PEMBAYARAN</div>
    <table>
        <?php foreach ($daftarCicilan as $c): ?>
        <tr>
            <td>
                <span style="font-size: 11px;"><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></span><br>
                <span style="font-size: 11px;"><?= htmlspecialchars($c['keterangan'] ?: 'Pembayaran') ?></span>
            </td>
            <td class="text-right fw-bold">- <?= number_format($c['nominal'], 0, ',', '.') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <div class="border-bottom-dashed"></div>
    <?php endif; ?>

    <div class="text-center mt-4" style="font-size: 11px;">
        <p>Barang yang sudah dibeli tidak dapat ditukar/dikembalikan.</p>
        <p class="fw-bold">-- Terima Kasih --</p>
    </div>
</div>

<!-- Tombol Aksi (Disembunyikan saat di-print) -->
<div class="text-center mt-4 no-print">
    <button onclick="window.print()" style="padding: 10px 20px; font-size: 16px; background: #0d6efd; color: #fff; border: none; border-radius: 5px; cursor: pointer;">
        🖨️ Cetak / Simpan PDF
    </button>
    <button onclick="window.close()" style="padding: 10px 20px; font-size: 16px; background: #6c757d; color: #fff; border: none; border-radius: 5px; cursor: pointer;">
        Tutup
    </button>
</div>

</body>
</html>