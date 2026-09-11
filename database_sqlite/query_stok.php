<?php
// database/query_stok.php
require_once __DIR__ . '/db_barang.php';

/**
 * Catat Mutasi Stok & Update Saldo Akhir di barang_kemasan
 * Parameter $opsiSatuan: 'GROSIR' (menggunakan perkalian nilai 'isi') atau 'ECER' (langsung pcs)
 */
function catatMutasiStok(PDO $pdo, int $kemasanId, string $jenisMutasi, int $qtyInput, string $opsiSatuan = 'ECER', string $keterangan = ''): bool {
    try {
        // 1. Ambil data stok awal dan nilai perkalian 'isi'
        $stmtBk = $pdo->prepare("SELECT COALESCE(stok, 0) AS stok, COALESCE(isi, 1) AS isi, nama_kemasan, satuan FROM barang_kemasan WHERE id = ?");
        $stmtBk->execute([$kemasanId]);
        $rowBk = $stmtBk->fetch(PDO::FETCH_ASSOC);

        if (!$rowBk) return false;

        $stokSebelum = intval($rowBk['stok']);
        $isi         = max(1, intval($rowBk['isi']));
        $namaKemasan = $rowBk['nama_kemasan'];
        $satuanEcer  = $rowBk['satuan'];

        // 2. Hitung jumlah aktual dalam PCS berdasarkan opsi satuan yang dipilih
        if (strtoupper($opsiSatuan) === 'GROSIR' || strtoupper($opsiSatuan) === 'DUS') {
            $qtyPcs = $qtyInput * $isi;
            $ketTambahan = " (Input: {$qtyInput} {$namaKemasan} @ {$isi} {$satuanEcer})";
        } else {
            $qtyPcs = $qtyInput;
            $ketTambahan = "";
        }

        $keteranganAkhir = trim($keterangan . $ketTambahan);

        // 3. Hitung stok akhir sesudah mutasi
        if ($jenisMutasi === 'MASUK') {
            $stokSesudah = $stokSebelum + $qtyPcs;
        } else if ($jenisMutasi === 'KELUAR' || $jenisMutasi === 'PENJUALAN') {
            $stokSesudah = max(0, $stokSebelum - $qtyPcs);
        } else {
            $stokSesudah = $qtyPcs; // OPNAME langsung menimpa ke nilai PCS
        }

        // 4. Simpan Log Mutasi
        $sqlMutasi = "INSERT INTO stok_mutasi (barang_kemasan_id, jenis_mutasi, qty, stok_sebelum, stok_sesudah, keterangan) 
                      VALUES (?, ?, ?, ?, ?, ?)";
        $stmtM = $pdo->prepare($sqlMutasi);
        $stmtM->execute([$kemasanId, $jenisMutasi, $qtyPcs, $stokSebelum, $stokSesudah, $keteranganAkhir]);

        // 5. Update Saldo Stok Utama dalam PCS
        $stmtUpdate = $pdo->prepare("UPDATE barang_kemasan SET stok = ? WHERE id = ?");
        return $stmtUpdate->execute([$stokSesudah, $kemasanId]);

    } catch (PDOException $e) {
        error_log("Error Mutasi Stok: " . $e->getMessage());
        return false;
    }
}

/**
 * Fitur Restok / Tambah Stok Masuk dengan Konversi
 */
function tambahStokMasuk(PDO $pdo, int $kemasanId, int $qtyMasuk, string $opsiSatuan = 'ECER', string $keterangan = 'Restok Supplier'): bool {
    if ($qtyMasuk <= 0) return false;
    return catatMutasiStok($pdo, $kemasanId, 'MASUK', $qtyMasuk, $opsiSatuan, $keterangan);
}

/**
 * Fitur Stock Opname
 */
function simpanStockOpname(PDO $pdo, int $kemasanId, int $stokFisikPcs, string $keterangan = 'Stock Opname'): bool {
    $stmtStok = $pdo->prepare("SELECT COALESCE(stok, 0) FROM barang_kemasan WHERE id = ?");
    $stmtStok->execute([$kemasanId]);
    $stokSistem = intval($stmtStok->fetchColumn() ?: 0);

    $selisih = $stokFisikPcs - $stokSistem;

    $sqlOpname = "INSERT INTO stok_opname (barang_kemasan_id, stok_sistem, stok_fisik, selisih, keterangan) VALUES (?, ?, ?, ?, ?)";
    $stmtO = $pdo->prepare($sqlOpname);
    $stmtO->execute([$kemasanId, $stokSistem, $stokFisikPcs, $selisih, $keterangan]);

    return catatMutasiStok($pdo, $kemasanId, 'OPNAME', $stokFisikPcs, 'ECER', $keterangan . " (Selisih: {$selisih} Pcs)");
}