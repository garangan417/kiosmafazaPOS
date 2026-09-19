<?php
// database/query_harga.php

require_once __DIR__ . '/db_barang.php';

if (!function_exists('saveOrUpdateHarga')) {
    function saveOrUpdateHarga(PDO $pdo, int $kemasanId, float $hargaBeli, float $hargaEcer, float $hargaGrosir = 0.0, int $minGrosir = 1): bool {
        $stmtIsi = $pdo->prepare("SELECT COALESCE(isi, 1) FROM barang_kemasan WHERE id = ?");
        $stmtIsi->execute([$kemasanId]);
        $isi = floatval($stmtIsi->fetchColumn() ?: 1);
        
        if ($isi <= 0) {
            $isi = 1;
        }

        $hargaBeliPcs = $hargaBeli / $isi;

        $sql = "INSERT INTO harga_barang (
                    barang_kemasan_id, 
                    harga_beli, 
                    harga_beli_pcs, 
                    harga_jual_ecer, 
                    harga_jual_grosir, 
                    min_qty_grosir, 
                    updated_at
                )
                VALUES (?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    harga_beli = VALUES(harga_beli),
                    harga_beli_pcs = VALUES(harga_beli_pcs),
                    harga_jual_ecer = VALUES(harga_jual_ecer),
                    harga_jual_grosir = VALUES(harga_jual_grosir),
                    min_qty_grosir = VALUES(min_qty_grosir),
                    updated_at = NOW()";

        $stmt = $pdo->prepare($sql);
        return $stmt->execute([
            $kemasanId, 
            $hargaBeli, 
            $hargaBeliPcs, 
            $hargaEcer, 
            $hargaGrosir, 
            $minGrosir
        ]);
    }
}

if (!function_exists('getDaftarHargaLengkap')) {
    /**
     * Ambil daftar harga barang lengkap dengan opsi filter pencarian, kategori, dan status unset harga.
     */
    function getDaftarHargaLengkap(PDO $pdo, string $search = '', $kategoriId = '', bool $unsetOnly = false): array {
        $params = [];
        $whereConditions = [];

        // Filter Pencarian Teks
        if (!empty($search)) {
            $whereConditions[] = "(b.nama_barang LIKE ? 
                                   OR bk.nama_kemasan LIKE ? 
                                   OR k.nama_kategori LIKE ? 
                                   OR bk.id IN (SELECT barang_kemasan_id FROM barang_barcode WHERE barcode LIKE ?))";
            $searchTerm = '%' . $search . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }

        // Filter ID Kategori
        if (!empty($kategoriId) && $kategoriId !== 'semua' && $kategoriId !== '0') {
            $whereConditions[] = "b.kategori_id = ?";
            $params[] = intval($kategoriId);
        }

        // Filter Khusus Barang Belum Set Harga
        if ($unsetOnly) {
            $whereConditions[] = "(h.harga_jual_ecer IS NULL OR h.harga_jual_ecer = 0)";
        }

        $whereSql = "";
        if (!empty($whereConditions)) {
            $whereSql = " WHERE " . implode(" AND ", $whereConditions);
        }

        $sql = "SELECT 
                    bk.id AS kemasan_id,
                    b.id AS barang_id,
                    b.kategori_id,
                    b.nama_barang,
                    k.nama_kategori,
                    bk.nama_kemasan,
                    bk.satuan,
                    COALESCE(bk.isi, 1) AS isi,
                    COALESCE(h.harga_beli, 0) AS harga_beli,
                    COALESCE(h.harga_beli_pcs, 0) AS harga_beli_pcs,
                    COALESCE(h.harga_jual_ecer, 0) AS harga_jual_ecer,
                    COALESCE(h.harga_jual_grosir, 0) AS harga_jual_grosir,
                    COALESCE(h.min_qty_grosir, 1) AS min_qty_grosir,
                    h.updated_at
                FROM barang_kemasan bk
                JOIN barang b ON bk.barang_id = b.id
                JOIN kategori k ON b.kategori_id = k.id
                LEFT JOIN harga_barang h ON bk.id = h.barang_kemasan_id
                {$whereSql}
                ORDER BY b.nama_barang ASC, bk.isi ASC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('getJumlahBarangBelumSetHarga')) {
    /**
     * Hitung total barang yang harganya belum di-set (Harga Jual = 0 / NULL)
     */
    function getJumlahBarangBelumSetHarga(PDO $pdo): int {
        $sql = "SELECT COUNT(*) 
                FROM barang_kemasan bk
                LEFT JOIN harga_barang h ON bk.id = h.barang_kemasan_id
                WHERE h.harga_jual_ecer IS NULL OR h.harga_jual_ecer = 0";
                
        return (int) $pdo->query($sql)->fetchColumn();
    }
}