<?php
// database/query_pos.php

/**
 * Cek Pengaturan Fitur Stok & Opsi Stok Minus
 */
function getPengaturanStok(PDO $pdo): array {
    try {
        $stmt = $pdo->prepare("SELECT kunci, nilai FROM pengaturan WHERE kunci IN ('fitur_stok', 'izinkan_stok_minus')");
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        return [
            'fitur_stok'         => ($rows['fitur_stok'] ?? '1') === '1',
            'izinkan_stok_minus' => ($rows['izinkan_stok_minus'] ?? '0') === '1'
        ];
    } catch (PDOException $e) {
        return [
            'fitur_stok'         => true,
            'izinkan_stok_minus' => false
        ];
    }
}

/**
 * Cek apakah fitur pelacakan stok sedang aktif
 */
function isFiturStokAktif(PDO $pdo): bool {
    $cfg = getPengaturanStok($pdo);
    return $cfg['fitur_stok'];
}

/**
 * Update Status Fitur Stok (Aktif / Nonaktif) - Kompatibel SQLite
 */
function setFiturStok(PDO $pdo, bool $status): bool {
    $val = $status ? '1' : '0';
    $sql = "INSERT INTO pengaturan (kunci, nilai) VALUES ('fitur_stok', ?) 
            ON CONFLICT(kunci) DO UPDATE SET nilai = excluded.nilai";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$val]);
}

/**
 * Update Status Izinkan Stok Minus (1 = Boleh Minus, 0 = Ditolak) - Kompatibel SQLite
 */
function setIzinkanStokMinus(PDO $pdo, bool $status): bool {
    $val = $status ? '1' : '0';
    $sql = "INSERT INTO pengaturan (kunci, nilai) VALUES ('izinkan_stok_minus', ?) 
            ON CONFLICT(kunci) DO UPDATE SET nilai = excluded.nilai";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$val]);
}

/**
 * Ambil Barang Cepat / Favorit untuk Kasir (Modal dihitung per PCS)
 */
function getBarangFavorit(PDO $pdo): array {
    $sql = "SELECT 
                bk.id AS kemasan_id,
                b.nama_barang,
                bk.nama_kemasan,
                bk.satuan,
                COALESCE(bk.isi, 1) AS isi,
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
                COALESCE(bk.stok, 0) AS stok
            FROM barang_kemasan bk
            JOIN barang b ON bk.barang_id = b.id
            LEFT JOIN harga_barang h ON bk.id = h.barang_kemasan_id
            WHERE bk.is_favorite = 1
            ORDER BY b.nama_barang ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Hitung harga satuan & jenis harga (ECER / GROSIR) berdasarkan Tiering Qty
 */
function getHargaTiering(PDO $pdo, int $kemasanId, int $qty): array {
    $stmt = $pdo->prepare("
        SELECT 
            harga_jual_ecer, 
            harga_jual_grosir, 
            min_qty_grosir 
        FROM harga_barang 
        WHERE barang_kemasan_id = ?
    ");
    $stmt->execute([$kemasanId]);
    $harga = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$harga) {
        return [
            'harga_satuan' => 0.0,
            'jenis_harga'  => 'ECER',
            'subtotal'     => 0.0
        ];
    }

    $hargaEcer   = floatval($harga['harga_jual_ecer']);
    $hargaGrosir = floatval($harga['harga_jual_grosir']);
    $minGrosir   = intval($harga['min_qty_grosir']);

    return hitungHargaItem($hargaEcer, $hargaGrosir, $minGrosir, $qty);
}

/**
 * Helper Hitung Harga Tiering berdasarkan nilai harga ecer, grosir, dan min qty
 */
function hitungHargaItem(float $hargaEcer, float $hargaGrosir, int $minGrosir, int $qty): array {
    if ($hargaGrosir > 0 && $minGrosir > 0 && $qty >= $minGrosir) {
        return [
            'harga_satuan' => $hargaGrosir,
            'jenis_harga'  => 'GROSIR',
            'subtotal'     => $hargaGrosir * $qty
        ];
    }

    return [
        'harga_satuan' => $hargaEcer,
        'jenis_harga'  => 'ECER',
        'subtotal'     => $hargaEcer * $qty
    ];
}

/**
 * Simpan Penjualan + Validasi Stok, Potong Stok & Catat Log Mutasi Otomatis
 */
function simpanPenjualan(PDO $pdo, array $header, array $items): array {
    $cfgStok    = getPengaturanStok($pdo);
    $stokAktif  = $cfgStok['fitur_stok'];
    $bolehMinus = $cfgStok['izinkan_stok_minus'];

    try {
        $pdo->beginTransaction();

        // -------------------------------------------------------------
        // STEP 1: Cek & Validasi Stok Sebelum Transaksi Disimpan
        // -------------------------------------------------------------
        if ($stokAktif && !$bolehMinus) {
            $stmtCekStok = $pdo->prepare("
                SELECT b.nama_barang, bk.nama_kemasan, COALESCE(bk.stok, 0) AS stok 
                FROM barang_kemasan bk 
                JOIN barang b ON bk.barang_id = b.id 
                WHERE bk.id = ?
            ");

            foreach ($items as $item) {
                $kemasanId = intval($item['kemasan_id'] ?? 0);
                $qtyJual   = intval($item['qty'] ?? 0);

                if ($kemasanId > 0) {
                    $stmtCekStok->execute([$kemasanId]);
                    $dataStok = $stmtCekStok->fetch(PDO::FETCH_ASSOC);

                    if ($dataStok) {
                        $stokSaatIni = intval($dataStok['stok']);
                        if ($stokSaatIni < $qtyJual) {
                            $pdo->rollBack();
                            return [
                                'status'  => false, 
                                'message' => "Stok '{$dataStok['nama_barang']} ({$dataStok['nama_kemasan']})' tidak mencukupi! (Sisa stok: {$stokSaatIni}, Dibeli: {$qtyJual})"
                            ];
                        }
                    }
                }
            }
        }

        // -------------------------------------------------------------
        // STEP 2: Simpan Header Penjualan
        // -------------------------------------------------------------
        $sqlH = "INSERT INTO penjualan (no_faktur, pelanggan_id, total_kotor, diskon, total_bersih, bayar, kembalian, metode_bayar, catatan)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmtH = $pdo->prepare($sqlH);
        $stmtH->execute([
            $header['no_faktur'],
            $header['pelanggan_id'] ?? NULL,
            $header['total_kotor'],
            $header['diskon'] ?? 0,
            $header['total_bersih'],
            $header['bayar'],
            $header['kembalian'],
            $header['metode_bayar'] ?? 'TUNAI',
            $header['catatan'] ?? ''
        ]);

        $penjualanId = $pdo->lastInsertId();

        // Query Ambil Harga Beli/Modal eceran dari harga_barang
        $stmtGetModal = $pdo->prepare("
            SELECT 
                COALESCE(
                    CASE 
                        WHEN h.harga_beli_pcs > 0 THEN h.harga_beli_pcs
                        WHEN h.harga_beli > 0 THEN h.harga_beli / COALESCE(NULLIF(bk.isi, 0), 1)
                        ELSE 0
                    END, 0
                ) 
            FROM barang_kemasan bk
            LEFT JOIN harga_barang h ON bk.id = h.barang_kemasan_id
            WHERE bk.id = ?
        ");

        $sqlD = "INSERT INTO penjualan_detail (penjualan_id, barang_kemasan_id, nama_barang, nama_kemasan, qty, satuan, harga_beli, harga_jual, jenis_harga, subtotal)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmtD = $pdo->prepare($sqlD);

        // Prepare Statement Mutasi & Update Stok
        $stmtCurStok = $pdo->prepare("SELECT COALESCE(stok, 0) FROM barang_kemasan WHERE id = ?");
        $stmtMutasi  = $pdo->prepare("INSERT INTO stok_mutasi (barang_kemasan_id, jenis_mutasi, qty, stok_sebelum, stok_sesudah, keterangan) VALUES (?, 'PENJUALAN', ?, ?, ?, ?)");
        $stmtUpdStok = $pdo->prepare("UPDATE barang_kemasan SET stok = ? WHERE id = ?");

        // -------------------------------------------------------------
        // STEP 3: Simpan Detail & Potong Stok
        // -------------------------------------------------------------
        foreach ($items as $item) {
            $kemasanId = intval($item['kemasan_id'] ?? 0);
            $qtyJual   = intval($item['qty'] ?? 0);
            $modal     = floatval($item['harga_beli'] ?? 0);

            // Selalu validasi modal agar menggunakan modal per PCS dari DB jika modal tidak valid
            if ($kemasanId > 0) {
                $stmtGetModal->execute([$kemasanId]);
                $resModal = $stmtGetModal->fetchColumn();
                if ($resModal > 0) {
                    $modal = floatval($resModal);
                }
            }

            // Hitung Ulang / Pastikan Harga & Jenis Harga Sesuai Tiering Qty
            $hargaTiering   = getHargaTiering($pdo, $kemasanId, $qtyJual);
            $hargaJualFinal = ($hargaTiering['harga_satuan'] > 0) ? $hargaTiering['harga_satuan'] : floatval($item['harga_jual'] ?? 0);
            $jenisHarga     = $hargaTiering['harga_satuan'] > 0 ? $hargaTiering['jenis_harga'] : ($item['jenis_harga'] ?? 'ECER');
            $subtotalFinal  = $hargaJualFinal * $qtyJual;

            $stmtD->execute([
                $penjualanId,
                $kemasanId,
                $item['nama_barang'] ?? '',
                $item['nama_kemasan'] ?? '',
                $qtyJual,
                $item['satuan'] ?? 'PCS',
                $modal,
                $hargaJualFinal,
                $jenisHarga,
                $subtotalFinal
            ]);

            // Jika Fitur Stok Aktif, Hitung Mutasi dan Update Stok di DB
            if ($stokAktif && $kemasanId > 0) {
                // 1. Ambil stok awal sebelum transaksi
                $stmtCurStok->execute([$kemasanId]);
                $stokSebelum = intval($stmtCurStok->fetchColumn() ?: 0);
                
                $stokSesudah = $stokSebelum - $qtyJual;

                // Jika stok minus tidak diizinkan, jaga nilai tidak kurang dari 0
                if (!$bolehMinus && $stokSesudah < 0) {
                    $stokSesudah = 0;
                }

                // 2. Catat Log Mutasi Stok
                $ketMutasi = 'Penjualan No. Faktur: ' . $header['no_faktur'];
                $stmtMutasi->execute([$kemasanId, $qtyJual, $stokSebelum, $stokSesudah, $ketMutasi]);

                // 3. Update Saldo Stok Utama
                $stmtUpdStok->execute([$stokSesudah, $kemasanId]);
            }
        }

        $pdo->commit();
        return [
            'status'       => true, 
            'penjualan_id' => $penjualanId, 
            'no_faktur'    => $header['no_faktur'],
            'message'      => 'Transaksi berhasil tersimpan!'
        ];

    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['status' => false, 'message' => 'Gagal transaksi: ' . $e->getMessage()];
    }
}

/**
 * Hitung Rekapitulasi Laporan Penjualan & Keuntungan Real
 */
function getRekapPenjualan(PDO $pdo, string $tglAwal, string $tglAkhir): array {
    $sql = "
        SELECT 
            COUNT(p.id) AS total_faktur,
            COALESCE(SUM(p.total_bersih), 0) AS total_omzet,
            COALESCE(SUM(
                (
                    SELECT SUM(d.qty * d.harga_beli)
                    FROM penjualan_detail d
                    WHERE d.penjualan_id = p.id
                )
            ), 0) AS total_modal
        FROM penjualan p
        WHERE DATE(p.tanggal) BETWEEN :tglAwal AND :tglAkhir
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['tglAwal' => $tglAwal, 'tglAkhir' => $tglAkhir]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $omzet = floatval($row['total_omzet']);
    $modal = floatval($row['total_modal']);
    $laba  = $omzet - $modal;

    return [
        'total_faktur' => intval($row['total_faktur']),
        'total_omzet'  => $omzet,
        'total_modal'  => $modal,
        'laba'         => $laba
    ];
}