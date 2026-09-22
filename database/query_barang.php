<?php
// database2/query_barang.php

require_once __DIR__ . '/db_barang.php';

/**
 * Cari barang berdasarkan barcode yang di-scan
 */
function cariBarangByBarcode($pdo, $barcode) {
    $sql = "SELECT 
                b.id AS barang_id,
                b.nama_barang,
                k.nama_kategori,
                bk.id AS kemasan_id,
                bk.nama_kemasan,
                bk.satuan,
                bk.isi,
                bb.barcode
            FROM barang_barcode bb
            JOIN barang_kemasan bk ON bb.barang_kemasan_id = bk.id
            JOIN barang b ON bk.barang_id = b.id
            JOIN kategori k ON b.kategori_id = k.id
            WHERE bb.barcode = ?";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([trim($barcode)]);
    return $stmt->fetch();
}

/**
 * Ambil semua data master barang beserta varian kemasan & list barcodenya
 */
function getDaftarBarangLengkap($pdo) {
    $sql = "SELECT 
                b.id AS barang_id,
                b.kategori_id,
                b.nama_barang,
                k.nama_kategori,
                bk.id AS kemasan_id,
                bk.nama_kemasan,
                bk.satuan,
                bk.isi,
                (
                    SELECT GROUP_CONCAT(barcode SEPARATOR ', ') 
                    FROM barang_barcode 
                    WHERE barang_kemasan_id = bk.id
                ) AS list_barcode
            FROM barang b
            JOIN kategori k ON b.kategori_id = k.id
            JOIN barang_kemasan bk ON b.id = bk.barang_id
            ORDER BY b.nama_barang ASC, bk.id ASC";
            
    return $pdo->query($sql)->fetchAll();
}

/**
 * Cek apakah barcode sudah pernah terdaftar di database
 */
function isBarcodeExists($pdo, $barcode) {
    if (empty(trim($barcode))) return false;
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM barang_barcode WHERE barcode = ?");
    $stmt->execute([trim($barcode)]);
    return $stmt->fetchColumn() > 0;
}

if (!function_exists('getDaftarBarangPaginated')) {
    /**
     * Ambil daftar barang lengkap DENGAN PAGINASI SQL.
     * Hanya `$perPage` baris yang diambil dari database per halaman.
     *
     * @param PDO    $pdo
     * @param string $search      Pencarian teks (nama barang / kemasan / barcode)
     * @param int    $kategoriId  Filter kategori (0 = semua)
     * @param int    $page        Halaman saat ini (1-based)
     * @param int    $perPage     Baris per halaman
     * @return array Format sama dengan paginateSql()
     */
    function getDaftarBarangPaginated(PDO $pdo, string $search = '', int $kategoriId = 0, int $page = 1, int $perPage = 15): array {
        require_once __DIR__ . '/pagination_helper.php';

        $params = [];
        $whereConditions = [];

        // Filter pencarian teks (nama barang / kemasan / kategori / barcode)
        if (!empty($search)) {
            $whereConditions[] = "(b.nama_barang LIKE ? 
                                   OR bk.nama_kemasan LIKE ? 
                                   OR k.nama_kategori LIKE ? 
                                   OR bk.id IN (SELECT barang_kemasan_id FROM barang_barcode WHERE barcode LIKE ?))";
            $searchTerm = '%' . $search . '%';
            $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        }

        // Filter kategori
        if ($kategoriId > 0) {
            $whereConditions[] = "b.kategori_id = ?";
            $params[] = $kategoriId;
        }

        $whereSql = !empty($whereConditions) ? " WHERE " . implode(" AND ", $whereConditions) : "";

        // Query SELECT (tanpa LIMIT)
        $selectSql = "SELECT 
                        b.id AS barang_id,
                        b.kategori_id,
                        b.nama_barang,
                        k.nama_kategori,
                        bk.id AS kemasan_id,
                        bk.nama_kemasan,
                        bk.satuan,
                        bk.isi,
                        (
                            SELECT GROUP_CONCAT(barcode SEPARATOR ', ') 
                            FROM barang_barcode 
                            WHERE barang_kemasan_id = bk.id
                        ) AS list_barcode
                      FROM barang b
                      JOIN kategori k ON b.kategori_id = k.id
                      JOIN barang_kemasan bk ON b.id = bk.barang_id
                      {$whereSql}
                      ORDER BY b.nama_barang ASC, bk.id ASC";

        // Query COUNT (WHERE sama)
        $countSql = "SELECT COUNT(*) 
                     FROM barang b
                     JOIN kategori k ON b.kategori_id = k.id
                     JOIN barang_kemasan bk ON b.id = bk.barang_id
                     {$whereSql}";

        return paginateSql($pdo, $selectSql, $countSql, $params, $page, $perPage);
    }
}