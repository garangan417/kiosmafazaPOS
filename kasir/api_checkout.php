<?php
// kasir/api_checkout.php
require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';
require_once BASE_PATH . 'database/db_pelanggan.php';
require_once BASE_PATH . 'database/db.php'; // Dompet Keuangan (kios.sqlite)

header('Content-Type: application/json');

$rawInput = file_get_contents('php://input');
$data = json_decode($rawInput, true);

if (!$data || empty($data['items'])) {
    echo json_encode(['status' => false, 'message' => 'Data transaksi kosong atau tidak valid.']);
    exit;
}

$createdAt    = $data['created_at'] ?? date('Y-m-d H:i:s');
$totalKotor   = floatval($data['total_kotor'] ?? 0);
$diskon       = floatval($data['diskon'] ?? 0);
$totalBersih  = floatval($data['total_bersih'] ?? 0);
$bayar        = floatval($data['bayar'] ?? 0);
$kembalian    = floatval($data['kembalian'] ?? 0);
$metodeBayar  = strtoupper(trim($data['metode_bayar'] ?? 'TUNAI'));
$pelangganId  = intval($data['pelanggan_id'] ?? 0);
$catatan      = trim($data['catatan'] ?? '');
$items        = $data['items'];

$noFaktur = 'TRX-' . date('Ymd-His');
$namaPelanggan = '';

try {
    $pdoBarang->beginTransaction();
    $pdo->beginTransaction();

    $totalNominalPpob  = 0;
    $totalBarangFisik  = 0;
    $itemsBarangFisik  = [];
    $itemsPpob         = [];
    $rincianItemText   = [];
    $rincianPpobText   = [];

    foreach ($items as $item) {
        $isJasa = !empty($item['is_jasa']) && (
            $item['is_jasa'] === true || 
            $item['is_jasa'] == 1 || 
            $item['is_jasa'] === 'true'
        );

        $hargaJual   = floatval($item['harga_jual'] ?? 0);
        $qty         = floatval($item['qty'] ?? 1);
        $subtotal    = floatval($item['subtotal'] ?? ($hargaJual * $qty));
        $namaBarang  = $item['nama_barang'] ?? 'Item';
        $satuan      = $item['satuan'] ?? 'PCS';

        if ($isJasa) {
            $totalNominalPpob += $subtotal;
            $itemsPpob[] = $item;
            $rincianPpobText[] = "{$namaBarang} ({$qty} {$satuan})";
        } else {
            $totalBarangFisik += $subtotal;
            $itemsBarangFisik[] = $item;
        }

        $rincianItemText[] = "{$namaBarang} ({$qty} {$satuan})";
    }

    // A. SIMPAN KE TABEL PENJUALAN (HANYA UNTUK TRANSAKSI TUNAI / NON-UTANG)
    // Jika UTANG, jangan masuk ke tabel penjualan dulu agar omzet tidak tembus!
    if ($metodeBayar !== 'UTANG' && count($itemsBarangFisik) > 0) {
        $totalBersihBarang = $totalBarangFisik - $diskon;
        if ($totalBersihBarang < 0) $totalBersihBarang = 0;

        $stmtPenjualan = $pdoBarang->prepare("
            INSERT INTO penjualan (no_faktur, pelanggan_id, total_kotor, diskon, total_bersih, bayar, kembalian, metode_bayar, catatan, tanggal)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtPenjualan->execute([
            $noFaktur, 
            $pelangganId > 0 ? $pelangganId : NULL, 
            $totalBarangFisik, 
            $diskon, 
            $totalBersihBarang, 
            $bayar, 
            $kembalian, 
            $metodeBayar, 
            $catatan, 
            $createdAt
        ]);
        $penjualanId = $pdoBarang->lastInsertId();

        $stmtDetail = $pdoBarang->prepare("
            INSERT INTO penjualan_detail (penjualan_id, barang_kemasan_id, nama_barang, nama_kemasan, qty, satuan, harga_beli, harga_jual, jenis_harga, subtotal)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($itemsBarangFisik as $itemFisik) {
            $kemasanId   = intval($itemFisik['kemasan_id'] ?? 0);
            $namaBarang  = $itemFisik['nama_barang'] ?? 'Item';
            $namaKemasan = $itemFisik['nama_kemasan'] ?? '';
            $satuan      = $itemFisik['satuan'] ?? 'PCS';
            $hargaBeli   = floatval($itemFisik['harga_beli'] ?? 0);
            $hargaJual   = floatval($itemFisik['harga_jual'] ?? 0);
            $qty         = floatval($itemFisik['qty'] ?? 1);
            $subtotal    = floatval($itemFisik['subtotal'] ?? ($hargaJual * $qty));
            $jenisHarga  = $itemFisik['jenis_harga'] ?? 'ECER';

            $stmtDetail->execute([
                $penjualanId,
                $kemasanId > 0 ? $kemasanId : NULL,
                $namaBarang,
                $namaKemasan,
                $qty,
                $satuan,
                $hargaBeli,
                $hargaJual,
                $jenisHarga,
                $subtotal
            ]);
        }
    }

    // B. POTONG STOK FISIK (TETAP BERJALAN WALAUPUN UTANG)
    $stmtUpdateStok = $pdoBarang->prepare("UPDATE barang_kemasan SET stok = stok - ? WHERE id = ?");
    foreach ($items as $item) {
        $isJasa = !empty($item['is_jasa']) && (
            $item['is_jasa'] === true || 
            $item['is_jasa'] == 1 || 
            $item['is_jasa'] === 'true'
        );
        $isStokAktif = !isset($item['stok_aktif']) || $item['stok_aktif'] == true;
        $kemasanId   = intval($item['kemasan_id'] ?? 0);
        $qty         = floatval($item['qty'] ?? 1);

        if (!$isJasa && $kemasanId > 0 && $isStokAktif) {
            $stmtUpdateStok->execute([$qty, $kemasanId]);
        }
    }

    // C. LOGIKA PPOB TUNAI -> MASUK KAS KEUANGAN
    if ($totalNominalPpob > 0 && $metodeBayar !== 'UTANG') {
        $stmtLast = $pdo->query("SELECT saldo_akhir FROM transaksi ORDER BY id DESC LIMIT 1");
        $lastRow  = $stmtLast->fetch(PDO::FETCH_ASSOC);
        $saldoTerakhir = $lastRow ? floatval($lastRow['saldo_akhir']) : 0;

        $saldoAkhirBaru = $saldoTerakhir + $totalNominalPpob;
        $tglKas = date('Y-m-d', strtotime($createdAt));
        $ketPpobText = !empty($rincianPpobText) ? " (" . implode(', ', $rincianPpobText) . ")" : "";
        $ketKas = "Hasil Penjualan PPOB/Jasa [#{$noFaktur}]{$ketPpobText}";

        $stmtKeuangan = $pdo->prepare("
            INSERT INTO transaksi (tanggal, tipe, kategori, nominal, saldo_akhir, keterangan)
            VALUES (?, 'masuk', 'PPOB & Jasa', ?, ?, ?)
        ");
        $stmtKeuangan->execute([
            $tglKas, 
            $totalNominalPpob, 
            $saldoAkhirBaru, 
            $ketKas
        ]);
    }

    // D. RECORD UTANG DI DB PELANGGAN
    if ($metodeBayar === 'UTANG' && $pelangganId > 0) {
        $stmtPel = $pdoPelanggan->prepare("SELECT nama FROM pelanggan WHERE id = ?");
        $stmtPel->execute([$pelangganId]);
        $pel = $stmtPel->fetch(PDO::FETCH_ASSOC);
        if ($pel) {
            $namaPelanggan = $pel['nama'];
        }

        $nominalUtang = $totalBersih - $bayar;

        if ($nominalUtang > 0) {
            $infoKategoriUtang = "";
            if ($totalNominalPpob > 0 && count($itemsBarangFisik) > 0) {
                $infoKategoriUtang = " (Barang + PPOB)";
            } elseif ($totalNominalPpob > 0) {
                $infoKategoriUtang = " (PPOB/Jasa)";
            }

            $ketUtang = "Bon Kasir{$infoKategoriUtang} [#{$noFaktur}] - Items: " . implode(', ', $rincianItemText);
            if (!empty($catatan)) {
                $ketUtang .= " | Ket: " . $catatan;
            }

            $itemsJson = json_encode($items);

            $stmtUtang = $pdoPelanggan->prepare("
                INSERT INTO utang (pelanggan_id, tipe, nominal, keterangan, items_json, created_at)
                VALUES (?, 'utang', ?, ?, ?, ?)
            ");
            $stmtUtang->execute([
                $pelangganId,
                $nominalUtang,
                $ketUtang,
                $itemsJson,
                $createdAt
            ]);
        }
    }

    $pdoBarang->commit();
    $pdo->commit();

    echo json_encode([
        'status' => true,
        'message' => $metodeBayar === 'UTANG' ? 'Utang berhasil dicatat (stok dipotong).' : 'Transaksi berhasil disimpan.',
        'no_faktur' => $noFaktur,
        'nama_pelanggan' => $namaPelanggan
    ]);

} catch (PDOException $e) {
    if ($pdoBarang->inTransaction()) {
        $pdoBarang->rollBack();
    }
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode([
        'status' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}