<?php
// database/query_stok_v2.php
// API Stok V2 — desain dari nol, tidak tergantung query_stok.php lama

require_once __DIR__ . '/db_barang.php';

// ============================================================
// HELPER: BASE UNIT
// ============================================================

if (!function_exists('getSatuanBaseBarang')) {
    function getSatuanBaseBarang(PDO $pdo, int $barangId): ?string {
        $stmt = $pdo->prepare("
            SELECT satuan FROM barang_kemasan 
            WHERE barang_id = ? 
            ORDER BY id ASC LIMIT 1
        ");
        $stmt->execute([$barangId]);
        $satuan = $stmt->fetchColumn();
        return $satuan !== false ? (string) $satuan : null;
    }
}

if (!function_exists('getKemasanPertamaBarang')) {
    function getKemasanPertamaBarang(PDO $pdo, int $barangId): ?array {
        $stmt = $pdo->prepare("
            SELECT * FROM barang_kemasan 
            WHERE barang_id = ? 
            ORDER BY id ASC LIMIT 1
        ");
        $stmt->execute([$barangId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}

// ============================================================
// HELPER: PASTIKAN BARIS barang_stok ADA
// ============================================================

if (!function_exists('ensureBarangStok')) {
    function ensureBarangStok(PDO $pdo, int $barangId): void {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO barang_stok (barang_id, stok_base) VALUES (?, 0)
        ");
        $stmt->execute([$barangId]);
    }
}

// ============================================================
// BACA STOK
// ============================================================

if (!function_exists('getStokBase')) {
    function getStokBase(PDO $pdo, int $barangId): int {
        $stmt = $pdo->prepare("SELECT stok_base FROM barang_stok WHERE barang_id = ?");
        $stmt->execute([$barangId]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (int) $val : 0;
    }
}

if (!function_exists('getStokTampilanKemasan')) {
    function getStokTampilanKemasan(PDO $pdo, int $kemasanId): array {
        $stmt = $pdo->prepare("
            SELECT bk.barang_id, COALESCE(bk.isi,1) AS isi, bk.satuan
            FROM barang_kemasan bk WHERE bk.id = ?
        ");
        $stmt->execute([$kemasanId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return ['full' => 0, 'sisa' => 0, 'isi' => 1, 'satuan' => ''];

        $isi = max(1, (int) $row['isi']);
        $stokBase = getStokBase($pdo, (int) $row['barang_id']);
        return [
            'full'   => intdiv($stokBase, $isi),
            'sisa'   => $stokBase % $isi,
            'isi'    => $isi,
            'satuan' => (string) $row['satuan'],
        ];
    }
}

if (!function_exists('getStokSemuaKemasanBarang')) {
    function getStokSemuaKemasanBarang(PDO $pdo, int $barangId): array {
        $stokBase = getStokBase($pdo, $barangId);
        $stmt = $pdo->prepare("
            SELECT id AS kemasan_id, nama_kemasan, satuan, COALESCE(isi,1) AS isi
            FROM barang_kemasan WHERE barang_id = ? ORDER BY id ASC
        ");
        $stmt->execute([$barangId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $r) {
            $isi = max(1, (int) $r['isi']);
            $result[] = [
                'kemasan_id'    => (int) $r['kemasan_id'],
                'nama_kemasan'  => $r['nama_kemasan'],
                'satuan'        => $r['satuan'],
                'isi'           => $isi,
                'full'          => intdiv($stokBase, $isi),
                'sisa'          => $stokBase % $isi,
                'stok_base'     => $stokBase,
            ];
        }
        return $result;
    }
}

// ============================================================
// CATAT MUTASI STOK (inti API)
// ============================================================

if (!function_exists('catatMutasi')) {
    /**
     * @param bool $isSatuanBase Jika TRUE, qtyInput dianggap sudah dalam satuan terkecil (tidak dikali isi).
     */
    function catatMutasi(
        PDO $pdo,
        int $kemasanId,
        string $jenisMutasi,
        int $qtyInput,
        ?string $refType = null,
        ?int $refId = null,
        string $keterangan = '',
        bool $isSatuanBase = false // <--- PARAMETER BARU
    ): array {
        $fail = fn(string $msg) => ['ok' => false, 'error' => $msg, 'stok_sebelum' => 0, 'stok_sesudah' => 0, 'qty_base' => 0];

        if ($kemasanId <= 0) return $fail('Kemasan ID tidak valid.');
        if ($qtyInput <= 0) return $fail('Qty harus > 0.');

        // Ambil info kemasan + barang
        $stmtK = $pdo->prepare("
            SELECT bk.id, bk.barang_id, bk.nama_kemasan, bk.satuan,
                   COALESCE(bk.isi,1) AS isi
            FROM barang_kemasan bk WHERE bk.id = ?
        ");
        $stmtK->execute([$kemasanId]);
        $kem = $stmtK->fetch(PDO::FETCH_ASSOC);
        if (!$kem) return $fail('Kemasan tidak ditemukan.');

        $barangId = (int) $kem['barang_id'];
        $isi      = max(1, (int) $kem['isi']);
        $satuan   = (string) $kem['satuan'];
        
        // Tentukan qty base
        if ($isSatuanBase) {
            $qtyBase = $qtyInput; // Jika sudah eceran, jangan dikali
        } else {
            $qtyBase = $qtyInput * $isi;
        }

        // Pastikan baris stok ada
        ensureBarangStok($pdo, $barangId);

        try {
            $isTransaksiSendiri = !$pdo->inTransaction();
            if ($isTransaksiSendiri) {
                $pdo->beginTransaction();
            }

            // Lock baris stok
            $stmtS = $pdo->prepare("SELECT stok_base FROM barang_stok WHERE barang_id = ? FOR UPDATE");
            $stmtS->execute([$barangId]);
            $stokSebelum = (int) $stmtS->fetchColumn();

            // Hitung arah
            $isMasuk = (strpos($jenisMutasi, 'MASUK') === 0);
            if ($jenisMutasi === 'OPNAME_SET') {
                $qtyBase = $qtyInput;
                $stokSesudah = $qtyInput;
            } elseif ($isMasuk) {
                $stokSesudah = $stokSebelum + $qtyBase;
            } else {
                $stokSesudah = $stokSebelum - $qtyBase;
                if ($stokSesudah < 0) {
                    if ($isTransaksiSendiri) $pdo->rollBack();
                    return $fail("Stok tidak cukup. Stok saat ini: {$stokSebelum} {$satuan}, butuh: {$qtyBase} {$satuan}.");
                }
            }

            // Update barang_stok
            $stmtU = $pdo->prepare("UPDATE barang_stok SET stok_base = ? WHERE barang_id = ?");
            $stmtU->execute([$stokSesudah, $barangId]);

            // Insert mutasi
            $stmtM = $pdo->prepare("
                INSERT INTO stok_mutasi 
                (barang_id, barang_kemasan_id, jenis_mutasi, qty_base, qty_kemasan,
                 isi_saat_itu, satuan_saat_itu, stok_sebelum, stok_sesudah,
                 ref_type, ref_id, keterangan)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtM->execute([
                $barangId, $kemasanId, $jenisMutasi,
                $qtyBase, $qtyInput, $isi, $satuan,
                $stokSebelum, $stokSesudah,
                $refType, $refId, $keterangan !== '' ? $keterangan : null
            ]);

            if ($isTransaksiSendiri) {
                $pdo->commit();
            }

            return [
                'ok' => true,
                'error' => null,
                'stok_sebelum' => $stokSebelum,
                'stok_sesudah' => $stokSesudah,
                'qty_base' => $qtyBase,
            ];
        } catch (PDOException $e) {
            if ($isTransaksiSendiri && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("catatMutasi error: " . $e->getMessage());
            return $fail('DB error: ' . $e->getMessage());
        }
    }
}

// ============================================================
// REKALKULASI STOK (safety net)
// ============================================================

if (!function_exists('recalculateStok')) {
    function recalculateStok(PDO $pdo, int $barangId): int {
        $stmt = $pdo->prepare("
            SELECT 
                SUM(CASE WHEN jenis_mutasi LIKE 'MASUK%' THEN qty_base ELSE 0 END) AS total_in,
                SUM(CASE WHEN jenis_mutasi LIKE 'KELUAR%' THEN qty_base ELSE 0 END) AS total_out
            FROM stok_mutasi WHERE barang_id = ?
        ");
        $stmt->execute([$barangId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalIn  = (int) ($row['total_in'] ?? 0);
        $totalOut = (int) ($row['total_out'] ?? 0);

        $stmtO = $pdo->prepare("
            SELECT stok_sesudah FROM stok_mutasi 
            WHERE barang_id = ? AND jenis_mutasi = 'OPNAME_SET'
            ORDER BY id DESC LIMIT 1
        ");
        $stmtO->execute([$barangId]);
        $lastOpname = $stmtO->fetchColumn();

        $stokBaru = ($lastOpname !== false)
            ? (int) $lastOpname
            : ($totalIn - $totalOut);

        ensureBarangStok($pdo, $barangId);
        $stmtU = $pdo->prepare("UPDATE barang_stok SET stok_base = ? WHERE barang_id = ?");
        $stmtU->execute([$stokBaru, $barangId]);

        return $stokBaru;
    }
}

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

        // Query Hitung Total Baris (untuk paginasi)
        $countSql = "SELECT COUNT(DISTINCT b.id) 
                     FROM barang_kemasan bk
                     JOIN barang b ON bk.barang_id = b.id
                     LEFT JOIN barang_barcode bb ON bk.id = bb.barang_kemasan_id
                     $whereSql";
        $stmtCount = $pdo->prepare($countSql);
        $stmtCount->execute($params);
        $totalItems = (int) $stmtCount->fetchColumn();

        // Query Data dengan LIMIT
        $sql = "SELECT DISTINCT
                    bk.id AS kemasan_id,
                    b.id AS barang_id,
                    b.nama_barang,
                    bk.nama_kemasan,
                    bk.satuan,
                    COALESCE(bk.isi, 1) AS isi,
                    COALESCE(bs.stok_base, 0) AS stok_base
                FROM barang_kemasan bk
                JOIN barang b ON bk.barang_id = b.id
                LEFT JOIN barang_stok bs ON b.id = bs.barang_id
                LEFT JOIN barang_barcode bb ON bk.id = bb.barang_kemasan_id
                $whereSql
                ORDER BY b.nama_barang ASC
                LIMIT $limit OFFSET $offset";
        
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
