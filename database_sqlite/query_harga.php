<?php
// database/query_harga.php
require_once __DIR__ . '/db_barang.php';

/**
 * Simpan atau Update Harga Barang di SQLite.
 * Otomatis mengambil rujukan 'isi' dari barang_kemasan 
 * dan menghitung harga_beli_pcs (modal ecer) = harga_beli / isi.
 */
function saveOrUpdateHarga(PDO $pdo, int $kemasanId, float $hargaBeli, float $hargaEcer, float $hargaGrosir = 0.0, int $minGrosir = 1): bool {
    // 1. Ambil nilai rujukan 'isi' dari tabel barang_kemasan
    $stmtIsi = $pdo->prepare("SELECT COALESCE(isi, 1) FROM barang_kemasan WHERE id = ?");
    $stmtIsi->execute([$kemasanId]);
    $isi = floatval($stmtIsi->fetchColumn() ?: 1);
    
    // Keamanan jika nilai isi 0 atau negatif
    if ($isi <= 0) {
        $isi = 1;
    }

    // 2. Hitung Modal Ecer (harga_beli_pcs) secara otomatis
    $hargaBeliPcs = $hargaBeli / $isi;

    // 3. Simpan atau Update data ke tabel harga_barang (UPSERT)
    $sql = "INSERT INTO harga_barang (
                barang_kemasan_id, 
                harga_beli, 
                harga_beli_pcs, 
                harga_jual_ecer, 
                harga_jual_grosir, 
                min_qty_grosir, 
                updated_at
            )
            VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
            ON CONFLICT(barang_kemasan_id) DO UPDATE SET
                harga_beli = excluded.harga_beli,
                harga_beli_pcs = excluded.harga_beli_pcs,
                harga_jual_ecer = excluded.harga_jual_ecer,
                harga_jual_grosir = excluded.harga_jual_grosir,
                min_qty_grosir = excluded.min_qty_grosir,
                updated_at = CURRENT_TIMESTAMP";

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

/**
 * Ambil daftar harga lengkap beserta estimasi margin keuntungan (%)
 */
function getDaftarHargaLengkap(PDO $pdo, string $search = ''): array {
    $params = [];
    $whereSql = "";

    if (!empty($search)) {
        $whereSql = " WHERE b.nama_barang LIKE ? 
                       OR bk.nama_kemasan LIKE ? 
                       OR k.nama_kategori LIKE ? 
                       OR bk.id IN (SELECT barang_kemasan_id FROM barang_barcode WHERE barcode LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params = [$searchTerm, $searchTerm, $searchTerm, $searchTerm];
    }

    $sql = "SELECT 
                bk.id AS kemasan_id,
                b.id AS barang_id,
                b.kategori_id AS kategori_id, -- TAMBAHAN: Dibutuhkan untuk filter dropdown kategori di UI
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