<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// utang/index.php
require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';
require_once BASE_PATH . 'database/db_pelanggan.php';
require_once BASE_PATH . 'database/db.php';

if (!function_exists('formatRupiah')) {
    function formatRupiah($nominal) {
        return 'Rp ' . number_format((float)$nominal, 0, ',', '.');
    }
}

$errorMsg = '';
$successMsg = '';
$isHtmx = isset($_SERVER['HTTP_HX_REQUEST']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pelangganId  = intval($_POST['pelanggan_id'] ?? 0);
    $tipe         = trim($_POST['tipe'] ?? 'bayar');
    $rawNominal   = $_POST['nominal'] ?? '0';
    $nominal      = floatval(preg_replace('/[^0-9]/', '', $rawNominal));
    $keterangan   = trim($_POST['keterangan'] ?? '');
    $createdAt    = !empty($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d H:i:s');

    if ($pelangganId <= 0 || $nominal <= 0) {
        $errorMsg = "Pelanggan dan Nominal harus diisi dengan benar!";
    } else {
        try {
            $stmtPel = $pdoPelanggan->prepare("SELECT nama FROM pelanggan WHERE id = ?");
            $stmtPel->execute([$pelangganId]);
            $pel = $stmtPel->fetch(PDO::FETCH_ASSOC);
            $namaPelanggan = $pel ? $pel['nama'] : 'Pelanggan';

            // ============================================================
            // TIPE: UTANG BARU
            // ============================================================
            if ($tipe === 'utang') {
                $ketUtang = $keterangan ?: "Catatan Utang Manual";
                $stmtUtang = $pdoPelanggan->prepare("
                INSERT INTO utang (pelanggan_id, tipe, nominal, keterangan, created_at)
                VALUES (?, 'utang', ?, ?, ?)
                ");
                $stmtUtang->execute([$pelangganId, $nominal, $ketUtang, $createdAt]);
                $successMsg = "Utang baru sebesar " . formatRupiah($nominal) . " berhasil ditambahkan!";
            }

            // ============================================================
            // TIPE: BAYAR / CICIL
            // ============================================================
            elseif ($tipe === 'bayar') {
                // Cek sisa utang SEBELUM bayar
                $sisaSebelumBayar = hitungSisaUtangPelanggan($pdoPelanggan, $pelangganId);

                if ($sisaSebelumBayar <= 0) {
                    $errorMsg = "Pelanggan ini tidak memiliki utang aktif!";
                } elseif ($nominal > $sisaSebelumBayar) {
                    $errorMsg = "Nominal bayar (" . formatRupiah($nominal) . ") melebihi sisa utang (" . formatRupiah($sisaSebelumBayar) . ")!";
                } else {
                    // Simpan pembayaran
                    $ketPelunasan = "Pembayaran Utang - a.n {$namaPelanggan}";
                    if (!empty($keterangan)) {
                        $ketPelunasan .= " | Ket: " . $keterangan;
                    }

                    $stmtUtang = $pdoPelanggan->prepare("
                    INSERT INTO utang (pelanggan_id, tipe, nominal, keterangan, created_at)
                    VALUES (?, 'bayar', ?, ?, ?)
                    ");
                    $stmtUtang->execute([$pelangganId, $nominal, $ketPelunasan, $createdAt]);

                    // Hitung sisa utang setelah bayar
                    $sisaUtang = hitungSisaUtangPelanggan($pdoPelanggan, $pelangganId);

                    // ============================================================
                    // JIKA LUNAS -> PINDAHKAN ITEM KE PENJUALAN / DOMPET
                    // ============================================================
                    if ($sisaUtang <= 0) {
                        // Tracking reset
                        $stmtAll = $pdoPelanggan->prepare("
                        SELECT id, tipe, nominal
                        FROM utang
                        WHERE pelanggan_id = ?
                        ORDER BY id ASC
                        ");
                        $stmtAll->execute([$pelangganId]);
                        $allUtang = $stmtAll->fetchAll(PDO::FETCH_ASSOC);

                        $resetIds = [0];
                        $tempUtang = 0;
                        $tempBayar = 0;

                        foreach ($allUtang as $rowU) {
                            if ($rowU['tipe'] === 'utang') {
                                $tempUtang += floatval($rowU['nominal']);
                            } else {
                                $tempBayar += floatval($rowU['nominal']);
                            }

                            if ($tempUtang > 0 && $tempBayar >= $tempUtang) {
                                $resetIds[] = $rowU['id'];
                                $tempUtang = 0;
                                $tempBayar = 0;
                            }
                        }

                        $lastResetId = end($resetIds);
                        $prevResetId = count($resetIds) >= 2
                        ? $resetIds[count($resetIds) - 2]
                        : 0;

                        // Ambil item utang dalam range sesi ini
                        $stmtItems = $pdoPelanggan->prepare("
                        SELECT id, nominal, keterangan, items_json, created_at
                        FROM utang
                        WHERE pelanggan_id = ?
                        AND tipe = 'utang'
                        AND id > ? AND id <= ?
                        ORDER BY id ASC
                        ");
                        $stmtItems->execute([$pelangganId, $prevResetId, $lastResetId]);
                        $rowsUtang = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

                        if (!empty($rowsUtang)) {
                            // FIX: Hanya 1 kali beginTransaction (pdoBarang === pdo)
                            $pdoBarang->beginTransaction();

                            try {
                                foreach ($rowsUtang as $row) {
                                    $itemsList = !empty($row['items_json'])
                                    ? json_decode($row['items_json'], true)
                                    : [];

                                    $noFakturLunas = 'PAY-' . date('Ymd-His') . '-' . rand(100, 999);
                                    $tanggalTrx = $createdAt;

                                    if (!empty($itemsList) && is_array($itemsList)) {
                                        $itemsBarangFisik = [];
                                        $subtotalFisik = 0;
                                        $subtotalPpob = 0;
                                        $rincianPpobText = [];

                                        foreach ($itemsList as $it) {
                                            $isJasa = !empty($it['is_jasa']) && (
                                                $it['is_jasa'] === true ||
                                                $it['is_jasa'] == 1 ||
                                                $it['is_jasa'] === 'true'
                                            );

                                            $subtotal = floatval($it['subtotal'] ?? ($it['harga_jual'] * $it['qty']));
                                            $namaBarang = $it['nama_barang'] ?? 'Item Utang';
                                            $qty = floatval($it['qty'] ?? 1);
                                            $satuan = $it['satuan'] ?? 'PCS';

                                            if ($isJasa) {
                                                $subtotalPpob += $subtotal;
                                                $rincianPpobText[] = "{$namaBarang} ({$qty} {$satuan})";
                                            } else {
                                                $subtotalFisik += $subtotal;
                                                $itemsBarangFisik[] = $it;
                                            }
                                        }

                                        // 1. Simpan barang fisik ke penjualan
                                        if (!empty($itemsBarangFisik)) {
                                            $stmtPenjualan = $pdoBarang->prepare("
                                            INSERT INTO penjualan (no_faktur, pelanggan_id, total_kotor, diskon, total_bersih, bayar, kembalian, metode_bayar, catatan, tanggal)
                                            VALUES (?, ?, ?, 0, ?, ?, 0, 'TUNAI', ?, ?)
                                            ");
                                            $stmtPenjualan->execute([
                                                $noFakturLunas,
                                                $pelangganId,
                                                $subtotalFisik,
                                                $subtotalFisik,
                                                $subtotalFisik,
                                                "Pelunasan Utang: {$namaPelanggan}",
                                                $tanggalTrx
                                            ]);
                                            $penjualanId = $pdoBarang->lastInsertId();

                                            $stmtDetail = $pdoBarang->prepare("
                                            INSERT INTO penjualan_detail (penjualan_id, barang_kemasan_id, nama_barang, nama_kemasan, qty, satuan, harga_beli, harga_jual, jenis_harga, subtotal)
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                            ");

                                            foreach ($itemsBarangFisik as $it) {
                                                $stmtDetail->execute([
                                                    $penjualanId,
                                                    intval($it['kemasan_id'] ?? 0) > 0 ? intval($it['kemasan_id']) : NULL,
                                                                     $it['nama_barang'] ?? 'Item Utang',
                                                                     $it['nama_kemasan'] ?? '',
                                                                     floatval($it['qty'] ?? 1),
                                                                     $it['satuan'] ?? 'PCS',
                                                                     floatval($it['harga_beli'] ?? 0),
                                                                     floatval($it['harga_jual'] ?? 0),
                                                                     $it['jenis_harga'] ?? 'ECER',
                                                                     floatval($it['subtotal'] ?? ($it['harga_jual'] * $it['qty']))
                                                ]);
                                            }
                                        }

                                        // 2. Simpan PPOB/Jasa ke dompet keuangan
                                        if ($subtotalPpob > 0) {
                                            $stmtLast = $pdo->query("SELECT saldo_akhir FROM transaksi ORDER BY id DESC LIMIT 1");
                                            $lastRow  = $stmtLast->fetch(PDO::FETCH_ASSOC);
                                            $saldoTerakhir = $lastRow ? floatval($lastRow['saldo_akhir']) : 0;

                                            $saldoAkhirBaru = $saldoTerakhir + $subtotalPpob;
                                            $tglKas = date('Y-m-d', strtotime($createdAt));
                                            $ketPpobText = !empty($rincianPpobText) ? " (" . implode(', ', $rincianPpobText) . ")" : "";
                                            $ketKas = "Pelunasan Utang PPOB/Jasa a.n {$namaPelanggan}" . $ketPpobText;

                                            $stmtKeuangan = $pdo->prepare("
                                            INSERT INTO transaksi (tanggal, tipe, kategori, nominal, saldo_akhir, keterangan)
                                            VALUES (?, 'masuk', 'PPOB & Jasa', ?, ?, ?)
                                            ");
                                            $stmtKeuangan->execute([
                                                $tglKas,
                                                $subtotalPpob,
                                                $saldoAkhirBaru,
                                                $ketKas
                                            ]);
                                        }

                                    } else {
                                        // Utang manual / tanpa JSON
                                        $nominalUtang = floatval($row['nominal']);
                                        if ($nominalUtang > 0) {
                                            $stmtPenjualan = $pdoBarang->prepare("
                                            INSERT INTO penjualan (no_faktur, pelanggan_id, total_kotor, diskon, total_bersih, bayar, kembalian, metode_bayar, catatan, tanggal)
                                            VALUES (?, ?, ?, 0, ?, ?, 0, 'TUNAI', ?, ?)
                                            ");
                                            $stmtPenjualan->execute([
                                                $noFakturLunas,
                                                $pelangganId,
                                                $nominalUtang,
                                                $nominalUtang,
                                                $nominalUtang,
                                                "Pelunasan Utang: {$namaPelanggan}",
                                                $tanggalTrx
                                            ]);
                                            $penjualanId = $pdoBarang->lastInsertId();

                                            $stmtDetail = $pdoBarang->prepare("
                                            INSERT INTO penjualan_detail (penjualan_id, barang_kemasan_id, nama_barang, nama_kemasan, qty, satuan, harga_beli, harga_jual, jenis_harga, subtotal)
                                            VALUES (?, NULL, ?, 'NON_STOK', 1, 'TRANSAKSI', 0, ?, 'ECER', ?)
                                            ");
                                            $stmtDetail->execute([
                                                $penjualanId,
                                                "Pelunasan Utang - {$row['keterangan']}",
                                                $nominalUtang,
                                                $nominalUtang
                                            ]);
                                        }
                                    }
                                }

                                $pdoBarang->commit();

                                $successMsg = "Pembayaran " . formatRupiah($nominal) . " berhasil. Utang <strong>LUNAS</strong>! Barang resmi masuk ke laporan penjualan.";

                            } catch (Exception $e) {
                                if ($pdoBarang->inTransaction()) {
                                    $pdoBarang->rollBack();
                                }
                                throw $e;
                            }
                        } else {
                            $successMsg = "Pembayaran " . formatRupiah($nominal) . " berhasil. Utang <strong>LUNAS</strong>!";
                        }
                    } else {
                        $successMsg = "Cicilan " . formatRupiah($nominal) . " berhasil. Sisa utang: " . formatRupiah($sisaUtang) . ". (Belum lunas).";
                    }
                }
            }
        } catch (PDOException $e) {
            if (isset($pdoBarang) && $pdoBarang->inTransaction()) {
                $pdoBarang->rollBack();
            }
            $errorMsg = "Gagal memproses transaksi: " . $e->getMessage();
        }
    }

    // ============================================================
    // FIX: Simpan pesan ke session sebelum redirect
    // ============================================================
    if (!$isHtmx) {
        if (!empty($successMsg)) {
            $_SESSION['toast_success'] = $successMsg;
        }
        if (!empty($errorMsg)) {
            $_SESSION['toast_error'] = $errorMsg;
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? BASE_URL . 'pelanggan/';
        header("Location: " . $referer);
        exit;
    }
}

// ============================================================
// AMBIL RIWAYAT UNTUK DITAMPILKAN
// ============================================================
$riwayatUtang = [];
try {
    $sql = "SELECT u.*, p.nama
    FROM utang u
    JOIN pelanggan p ON u.pelanggan_id = p.id
    ORDER BY u.created_at DESC, u.id DESC
    LIMIT 50";
    $stmt = $pdoPelanggan->query($sql);
    $riwayatUtang = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMsg = "Gagal mengambil riwayat utang: " . $e->getMessage();
}

if ($isHtmx) {
    include __DIR__ . '/_area_utang.php';
    exit;
}

require_once BASE_PATH . 'partials/header.php';
?>

<main class="container my-4 flex-grow-1">
<div class="row">
<div class="col-12">
<?php include __DIR__ . '/_area_utang.php'; ?>
</div>
</div>
</main>

<?php require_once BASE_PATH . 'partials/footer.php'; ?>
