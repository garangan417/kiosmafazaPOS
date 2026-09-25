<?php
// kasir/api_utang_aktif.php
require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_pelanggan.php';

header('Content-Type: application/json');

if (!function_exists('formatRupiah')) {
    function formatRupiah($nominal) {
        return 'Rp ' . number_format((float)$nominal, 0, ',', '.');
    }
}

$q = trim($_GET['q'] ?? '');

try {
    // Ambil semua pelanggan + total utang per pelanggan
    $sql = "
        SELECT 
            p.id, 
            p.nama, 
            p.no_hp,
            COALESCE(SUM(CASE WHEN u.tipe = 'utang' THEN u.nominal ELSE 0 END), 0) AS total_utang,
            COALESCE(SUM(CASE WHEN u.tipe = 'bayar' THEN u.nominal ELSE 0 END), 0) AS total_bayar
        FROM pelanggan p
        LEFT JOIN utang u ON u.pelanggan_id = p.id
    ";

    if (!empty($q)) {
        $sql .= " WHERE p.nama LIKE ? OR p.no_hp LIKE ? ";
    }

    $sql .= " GROUP BY p.id, p.nama, p.no_hp ";
    $sql .= " ORDER BY p.nama ASC ";

    $stmt = $pdoPelanggan->prepare($sql);

    if (!empty($q)) {
        $stmt->execute(["%$q%", "%$q%"]);
    } else {
        $stmt->execute();
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Filter yang benar-benar punya sisa utang > 0
    // pakai helper hitungSisaUtangPelanggan() biar konsisten dengan sesi
    $data = [];
    $grandTotal = 0;

    foreach ($rows as $r) {
        $sisa = hitungSisaUtangPelanggan($pdoPelanggan, $r['id']);

        if ($sisa > 0) {
            $data[] = [
                'id'         => (int) $r['id'],
                'nama'       => $r['nama'],
                'no_hp'      => $r['no_hp'] ?? '',
                'sisa_utang' => (float) $sisa,
                'sisa_format'=> formatRupiah($sisa)
            ];
            $grandTotal += $sisa;
        }
    }

    // Sort: sisa utang terbesar dulu
    usort($data, function($a, $b) {
        return $b['sisa_utang'] <=> $a['sisa_utang'];
    });

    echo json_encode([
        'status'       => 'success',
        'data'         => $data,
        'total_count'  => count($data),
        'grand_total'  => (float) $grandTotal,
        'grand_format' => formatRupiah($grandTotal)
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Gagal ambil data: ' . $e->getMessage()
    ]);
}