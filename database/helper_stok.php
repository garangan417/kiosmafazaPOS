<?php
// database/helper_stok.php

/**
 * Fungsi Konversi: Menghitung total satuan terkecil (PCS) dari input qty
 */
function hitungQtyPcs(PDO $pdo, int $kemasanId, float $qtyInput): int {
    $stmt = $pdo->prepare("SELECT isi FROM barang_kemasan WHERE id = ?");
    $stmt->execute([$kemasanId]);
    $isi = max(1, (int) $stmt->fetchColumn()); 
    return (int) ($qtyInput * $isi);
}

/**
 * Fungsi Tampilan: Mengubah PCS ke "X Kemasan + Y PCS"
 */
function formatStokTampil(int $stokPcs, int $isi, string $namaKemasan, string $satuan): string {
    $stokPcs = max(0, $stokPcs);
    $isi     = max(1, $isi);
    
    if ($isi === 1) {
        return number_format($stokPcs, 0, ',', '.') . " " . $satuan;
    }
    
    $jumlahKemasan = intdiv($stokPcs, $isi); 
    $sisaPcs       = $stokPcs % $isi;        

    $teks = [];
    if ($jumlahKemasan > 0) $teks[] = number_format($jumlahKemasan, 0, ',', '.') . " " . $namaKemasan;
    if ($sisaPcs > 0)       $teks[] = number_format($sisaPcs, 0, ',', '.') . " " . $satuan;
    
    if (empty($teks)) return "0 " . $satuan;
    return implode(" + ", $teks);
}
