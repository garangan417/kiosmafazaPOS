<?php
// database2/query_pos.php

require_once __DIR__ . '/db_barang.php';

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

function isFiturStokAktif(PDO $pdo): bool {
    $cfg = getPengaturanStok($pdo);
    return $cfg['fitur_stok'];
}

function setFiturStok(PDO $pdo, bool $status): bool {
    $val = $status ? '1' : '0';
    $sql = "INSERT INTO pengaturan (kunci, nilai) VALUES ('fitur_stok', ?) 
            ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$val]);
}

function setIzinkanStokMinus(PDO $pdo, bool $status): bool {
    $val = $status ? '1' : '0';
    $sql = "INSERT INTO pengaturan (kunci, nilai) VALUES ('izinkan_stok_minus', ?) 
            ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$val]);
}

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

function simpanPenjualan(PDO $pdo, array $header, array $items): array {
    $cfgStok    = getPengaturanStok($pdo);
    $stokAktif  = $cfgStok['fitur_stok'];
    $bolehMinus = $cfgStok['izinkan_stok_minus'];

    // Cek apakah transaksi sudah dibuka di luar fungsi ini (di controller)
    $alreadyInTransaction = $pdo->inTransaction();

    try {
        // Mulai transaksi HANYA jika belum ada transaksi aktif
        if (!$alreadyInTransaction) {
            $pdo->beginTransaction();
        }

        // 1. Cek Stok (Jika Fitur Stok Aktif & Tidak Boleh Minus)
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
                            if (!$alreadyInTransaction && $pdo->inTransaction()) {
                                $pdo->rollBack();
                            }
                            return [
                                'status'  => false,
                                'message' => "Stok '{$dataStok['nama_barang']} ({$dataStok['nama_kemasan']})' tidak mencukupi! (Sisa stok: {$stokSaatIni}, Dibeli: {$qtyJual})"
                            ];
                        }
                    }
                }
            }
        }

        // 2. Insert Header Penjualan
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

        // 3. Prepare Query Detail & Stok
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

        $stmtCurStok = $pdo->prepare("SELECT COALESCE(stok, 0) FROM barang_kemasan WHERE id = ?");
        $stmtMutasi  = $pdo->prepare("INSERT INTO stok_mutasi (barang_kemasan_id, jenis_mutasi, qty, stok_sebelum, stok_sesudah, keterangan) VALUES (?, 'PENJUALAN', ?, ?, ?, ?)");
        $stmtUpdStok = $pdo->prepare("UPDATE barang_kemasan SET stok = ? WHERE id = ?");

        // 4. Loop Items Penjualan
        foreach ($items as $item) {
            $kemasanId = intval($item['kemasan_id'] ?? 0);
            $modal     = floatval($item['harga_beli'] ?? 0);

            if ($kemasanId > 0) {
                $stmtGetModal->execute([$kemasanId]);
                $resModal = $stmtGetModal->fetchColumn();
                if ($resModal > 0) {
                    $modal = floatval($resModal);
                }
            }

            $stmtD->execute([
                $penjualanId,
                $kemasanId,
                $item['nama_barang'] ?? '',
                $item['nama_kemasan'] ?? '',
                intval($item['qty']),
                $item['satuan'] ?? 'PCS',
                $modal,
                floatval($item['harga_jual']),
                $item['jenis_harga'] ?? 'ECER',
                floatval($item['subtotal'])
            ]);

       function simpanReturPenjualan(PDO $pdo, array $header, array $items): array {
    $cfgStok   = getPengaturanStok($pdo);
    $stokAktif = $cfgStok['fitur_stok'];

    $alreadyInTransaction = $pdo->inTransaction();

    try {
        if (!$alreadyInTransaction) {
            $pdo->beginTransaction();
        }

        // 1. Insert Header Retur
        $sqlH = "INSERT INTO retur_penjualan (no_retur, penjualan_id, total_retur, alasan)
                 VALUES (?, ?, ?, ?)";
        $stmtH = $pdo->prepare($sqlH);
        $stmtH->execute([
            $header['no_retur'],
            $header['penjualan_id'],
            $header['total_retur'],
            $header['alasan'] ?? ''
        ]);

        $returId = $pdo->lastInsertId();

        // 2. Prepare Query Detail & Stok
        $sqlD = "INSERT INTO retur_penjualan_detail (retur_penjualan_id, barang_kemasan_id, nama_barang, nama_kemasan, qty, harga_jual, subtotal)
                 VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmtD = $pdo->prepare($sqlD);

        $stmtCurStok = $pdo->prepare("SELECT COALESCE(stok, 0) FROM barang_kemasan WHERE id = ?");
        $stmtMutasi  = $pdo->prepare("INSERT INTO stok_mutasi (barang_kemasan_id, jenis_mutasi, qty, stok_sebelum, stok_sesudah, keterangan) VALUES (?, 'RETUR_MASUK', ?, ?, ?, ?)");
        $stmtUpdStok = $pdo->prepare("UPDATE barang_kemasan SET stok = ? WHERE id = ?");

        // 3. Loop Detail Items
        foreach ($items as $item) {
            $kemasanId = intval($item['barang_kemasan_id'] ?? 0);
            $qtyRetur  = intval($item['qty']);

            $stmtD->execute([
                $returId,
                $kemasanId,
                $item['nama_barang'] ?? '',
                $item['nama_kemasan'] ?? '',
                $qtyRetur,
                floatval($item['harga_jual']),
                floatval($item['subtotal'])
            ]);

            // Kembalikan Stok ke Inventori
            if ($stokAktif && $kemasanId > 0) {
                $stmtCurStok->execute([$kemasanId]);
                $stokSebelum = intval($stmtCurStok->fetchColumn() ?: 0);
                $stokSesudah = $stokSebelum + $qtyRetur;

                $ketMutasi = 'Retur Penjualan No: ' . $header['no_retur'];
                $stmtMutasi->execute([$kemasanId, $qtyRetur, $stokSebelum, $stokSesudah, $ketMutasi]);

                $stmtUpdStok->execute([$stokSesudah, $kemasanId]);
            }
        }

        if (!$alreadyInTransaction && $pdo->inTransaction()) {
            $pdo->commit();
        }

        return [
            'status'   => true,
            'retur_id' => $returId,
            'message'  => 'Retur penjualan berhasil diproses dan stok telah diperbarui!'
        ];

    } catch (PDOException $e) {
        if (!$alreadyInTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['status' => false, 'message' => 'Gagal memproses retur: ' . $e->getMessage()];
    }
}
    

            // Update Stok
            if ($stokAktif && $kemasanId > 0) {
                $stmtCurStok->execute([$kemasanId]);
                $stokSebelum = intval($stmtCurStok->fetchColumn() ?: 0);

                $qtyJual = intval($item['qty']);
                $stokSesudah = $stokSebelum - $qtyJual;

                if (!$bolehMinus && $stokSesudah < 0) {
                    $stokSesudah = 0;
                }

                $ketMutasi = 'Penjualan No. Faktur: ' . $header['no_faktur'];
                $stmtMutasi->execute([$kemasanId, $qtyJual, $stokSebelum, $stokSesudah, $ketMutasi]);

                $stmtUpdStok->execute([$stokSesudah, $kemasanId]);
            }
        }

        // Commit HANYA jika transaksi dimulai di dalam fungsi ini
        if (!$alreadyInTransaction && $pdo->inTransaction()) {
            $pdo->commit();
        }

        return [
            'status'       => true,
            'penjualan_id' => $penjualanId,
            'no_faktur'    => $header['no_faktur'],
            'message'      => 'Transaksi berhasil tersimpan!'
        ];

    } catch (PDOException $e) {
        // Rollback HANYA jika transaksi dimulai di dalam fungsi ini
        if (!$alreadyInTransaction && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        return ['status' => false, 'message' => 'Gagal transaksi: ' . $e->getMessage()];
    }
}

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