<?php
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
                b.nama_barang,
                k.nama_kategori,
                bk.id AS kemasan_id,
                bk.nama_kemasan,
                bk.satuan,
                bk.isi,
                (
                    SELECT GROUP_CONCAT(barcode, ', ') 
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