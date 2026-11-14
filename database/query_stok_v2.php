<?php
// database/query_stok_v2.php
// API Stok V2 — desain dari nol, tidak tergantung query_stok.php lama

require_once __DIR__ . '/db_barang.php';

// ============================================================
// HELPER: BASE UNIT & STOK
// ============================================================
if (!function_exists('getSatuanBaseBarang')) {
    function getSatuanBaseBarang(PDO $pdo, int $barangId): ?string {
        $stmt = $pdo->prepare("SELECT satuan FROM barang_kemasan WHERE barang_id = ? ORDER BY id ASC LIMIT 1");
        $stmt->execute([$barangId]);
        return $stmt->fetchColumn() ?: null;
    }
}

if (!function_exists('ensureBarangStok')) {
    function ensureBarangStok(PDO $pdo, int $barangId): void {
        $stmt = $pdo->prepare("INSERT IGNORE INTO barang_stok (barang_id, stok_base) VALUES (?, 0)");
        $stmt->execute([$barangId]);
    }
}

if (!function_exists('getStokBase')) {
    function getStokBase(PDO $pdo, int $barangId): int {
        $stmt = $pdo->prepare("SELECT stok_base FROM barang_stok WHERE barang_id = ?");
        $stmt->execute([$barangId]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (int) $val : 0;
    }
}

// ============================================================
// FUNGSI TRANSFER INTERNAL (Mencegah Infinite Loop)
// ============================================================
if (!function_exists('transferStokInternal')) {
    function transferStokInternal(PDO $pdo, int $kemasanSumberId, int $kemasanTujuanId, int $qtySumber, int $isiKonversi): bool {
        try {
            $stmtS = $pdo->prepare("SELECT barang_id, satuan FROM barang_kemasan WHERE id = ?");
            $stmtS->execute([$kemasanSumberId]);
            $sumber = $stmtS->fetch(PDO::FETCH_ASSOC);

            $stmtT = $pdo->prepare("SELECT barang_id, satuan FROM barang_kemasan WHERE id = ?");
            $stmtT->execute([$kemasanTujuanId]);
            $tujuan = $stmtT->fetch(PDO::FETCH_ASSOC);

            if (!$sumber || !$tujuan) return false;

            $stokSumber = getStokBase($pdo, $sumber['barang_id']);
            if ($stokSumber < $qtySumber) return false;

            // Potong Sumber
            $pdo->prepare("UPDATE barang_stok SET stok_base = stok_base - ? WHERE barang_id = ?")->execute([$qtySumber, $sumber['barang_id']]);
            $pdo->prepare("INSERT INTO stok_mutasi (barang_id, barang_kemasan_id, jenis_mutasi, qty_base, qty_kemasan, isi_saat_itu, satuan_saat_itu, stok_sebelum, stok_sesudah, ref_type, ref_id, keterangan) VALUES (?, ?, 'KELUAR_TRANSFER', ?, ?, 1, ?, ?, ?, 'TRANSFER', NULL, 'Auto-Transfer ke Etalase')")
                ->execute([$sumber['barang_id'], $kemasanSumberId, $qtySumber, $qtySumber, $sumber['satuan'], $stokSumber, $stokSumber - $qtySumber]);

            // Tambah Tujuan
            $qtyTambah = $qtySumber * $isiKonversi;
            $stokTujuan = getStokBase($pdo, $tujuan['barang_id']);
            $pdo->prepare("UPDATE barang_stok SET stok_base = stok_base + ? WHERE barang_id = ?")->execute([$qtyTambah, $tujuan['barang_id']]);
            $pdo->prepare("INSERT INTO stok_mutasi (barang_id, barang_kemasan_id, jenis_mutasi, qty_base, qty_kemasan, isi_saat_itu, satuan_saat_itu, stok_sebelum, stok_sesudah, ref_type, ref_id, keterangan) VALUES (?, ?, 'MASUK_TRANSFER', ?, ?, 1, ?, ?, ?, 'TRANSFER', NULL, 'Auto-Transfer dari Gudang')")
                ->execute([$tujuan['barang_id'], $kemasanTujuanId, $qtyTambah, $qtyTambah, $tujuan['satuan'], $stokTujuan, $stokTujuan + $qtyTambah]);

            return true;
        } catch (PDOException $e) {
            error_log("Transfer Internal Error: " . $e->getMessage());
            return false;
        }
    }
}

// ============================================================
// FUNGSI TRANSFER MANUAL (Dari Tombol)
// ============================================================
if (!function_exists('transferStokManual')) {
    function transferStokManual(PDO $pdo, int $kemasanSumberId, int $kemasanTujuanId, int $qtySumber, int $isiKonversi): array {
        if ($kemasanSumberId <= 0 || $kemasanTujuanId <= 0 || $qtySumber <= 0) {
            return ['ok' => false, 'error' => 'Data transfer tidak valid.'];
        }

        try {
            $pdo->beginTransaction();
            $ok = transferStokInternal($pdo, $kemasanSumberId, $kemasanTujuanId, $qtySumber, $isiKonversi);
            if ($ok) {
                $pdo->commit();
                return ['ok' => true, 'error' => null];
            } else {
                $pdo->rollBack();
                return ['ok' => false, 'error' => 'Stok sumber tidak mencukupi atau data tidak ditemukan.'];
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

// ============================================================
// CATAT MUTASI STOK (INTI API + OTOMATISASI)
// ============================================================
if (!function_exists('catatMutasi')) {
    function catatMutasi(
        PDO $pdo,
        int $kemasanId,
        string $jenisMutasi,
        int $qtyInput,
        ?string $refType = null,
        ?int $refId = null,
        string $keterangan = '',
        bool $isSatuanBase = false
    ): array {
        $fail = fn(string $msg) => ['ok' => false, 'error' => $msg, 'stok_sebelum' => 0, 'stok_sesudah' => 0, 'qty_base' => 0];

        if ($kemasanId <= 0) return $fail('Kemasan ID tidak valid.');
        if ($jenisMutasi !== 'OPNAME_SET' && $qtyInput <= 0) return $fail('Qty harus > 0.');

        $stmtK = $pdo->prepare("SELECT bk.id, bk.barang_id, bk.nama_kemasan, bk.satuan, COALESCE(bk.isi,1) AS isi FROM barang_kemasan bk WHERE bk.id = ?");
        $stmtK->execute([$kemasanId]);
        $kem = $stmtK->fetch(PDO::FETCH_ASSOC);
        if (!$kem) return $fail('Kemasan tidak ditemukan.');

        $barangId = (int) $kem['barang_id'];
        $isi      = max(1, (int) $kem['isi']);
        $satuan   = (string) $kem['satuan'];
        $qtyBase  = $isSatuanBase ? $qtyInput : ($qtyInput * $isi);

        ensureBarangStok($pdo, $barangId);

        try {
            $isTransaksiSendiri = !$pdo->inTransaction();
            if ($isTransaksiSendiri) $pdo->beginTransaction();

            $stmtS = $pdo->prepare("SELECT stok_base FROM barang_stok WHERE barang_id = ? FOR UPDATE");
            $stmtS->execute([$barangId]);
            $stokSebelum = (int) $stmtS->fetchColumn();

            $isMasuk = (strpos($jenisMutasi, 'MASUK') === 0);
            if ($jenisMutasi === 'OPNAME_SET') {
                $qtyBase = $qtyInput;
                $stokSesudah = $qtyInput;
            } elseif ($isMasuk) {
                $stokSesudah = $stokSebelum + $qtyBase;
            } else {
                // === OTOMATISASI JEMBATAN (JIKA STOK KURANG) ===
                if ($stokSebelum < $qtyBase) {
                    $stmtJ = $pdo->prepare("SELECT * FROM jembatan_stok WHERE kemasan_tujuan_id = ? AND is_aktif_otomatis = 1");
                    $stmtJ->execute([$kemasanId]);
                    $jembatan = $stmtJ->fetch(PDO::FETCH_ASSOC);

                    if ($jembatan) {
                        $ok = transferStokInternal(
                            $pdo, 
                            $jembatan['kemasan_sumber_id'], 
                            $kemasanId, 
                            1, 
                            $jembatan['isi_konversi']
                        );

                        if ($ok) {
                            $stmtS->execute([$barangId]);
                            $stokSebelum = (int) $stmtS->fetchColumn();
                        }
                    }
                }
                // === END OTOMATISASI ===

                $stokSesudah = $stokSebelum - $qtyBase;
                
                if ($stokSesudah < 0) {
                    if ($isTransaksiSendiri) $pdo->rollBack();
                    return $fail("Stok tidak cukup. Stok saat ini: {$stokSebelum} {$satuan}, butuh: {$qtyBase} {$satuan}.");
                }
            }

            // Update barang_stok
            $pdo->prepare("UPDATE barang_stok SET stok_base = ? WHERE barang_id = ?")->execute([$stokSesudah, $barangId]);

            // Insert mutasi
            $stmtM = $pdo->prepare("INSERT INTO stok_mutasi (barang_id, barang_kemasan_id, jenis_mutasi, qty_base, qty_kemasan, isi_saat_itu, satuan_saat_itu, stok_sebelum, stok_sesudah, ref_type, ref_id, keterangan) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmtM->execute([$barangId, $kemasanId, $jenisMutasi, $qtyBase, $qtyInput, $isi, $satuan, $stokSebelum, $stokSesudah, $refType, $refId, $keterangan !== '' ? $keterangan : null]);

            if ($isTransaksiSendiri) $pdo->commit();

            return ['ok' => true, 'error' => null, 'stok_sebelum' => $stokSebelum, 'stok_sesudah' => $stokSesudah, 'qty_base' => $qtyBase];
        } catch (PDOException $e) {
            if ($isTransaksiSendiri && $pdo->inTransaction()) $pdo->rollBack();
            return $fail('DB error: ' . $e->getMessage());
        }
    }
}

// ============================================================
// PEMBACAAN LIST STOK (Paginasi)
// ============================================================
if (!function_exists('getDaftarStokV2')) {
    function getDaftarStokV2(PDO $pdo, string $search = '', int $page = 1, int $limit = 15): array {
        $offset = ($page - 1) * $limit;
        
        $whereSql = "";
        $params = [];
        if ($search !== '') {
            $whereSql = " WHERE b.nama_barang LIKE ? OR bk.nama_kemasan LIKE ? OR bb.barcode LIKE ?";
            $term = '%' . $search . '%';
            $params = [$term, $term, $term];
        }

        $countSql = "SELECT COUNT(DISTINCT b.id) FROM barang_kemasan bk JOIN barang b ON bk.barang_id = b.id LEFT JOIN barang_barcode bb ON bk.id = bb.barang_kemasan_id $whereSql";
        $stmtCount = $pdo->prepare($countSql);
        $stmtCount->execute($params);
        $totalItems = (int) $stmtCount->fetchColumn();

        $sql = "SELECT DISTINCT bk.id AS kemasan_id, b.id AS barang_id, b.nama_barang, bk.nama_kemasan, bk.satuan, COALESCE(bk.isi, 1) AS isi, COALESCE(bs.stok_base, 0) AS stok_base FROM barang_kemasan bk JOIN barang b ON bk.barang_id = b.id LEFT JOIN barang_stok bs ON b.id = bs.barang_id LEFT JOIN barang_barcode bb ON bk.id = bb.barang_kemasan_id $whereSql ORDER BY b.nama_barang ASC LIMIT $limit OFFSET $offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'items' => $items,
            'total_items' => $totalItems,
            'total_pages' => ceil($totalItems / $limit),
            'current_page' => $page,
            'limit' => $limit
        ];
    }
}
