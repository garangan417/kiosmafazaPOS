<?php
// kasir/api_utang_aktif.php
// GET: list pelanggan dengan utang aktif (sisa > 0)
// Response sesuai yang diharapkan helper.js:
// { status, total_count, grand_format, data: [{ id, nama, no_hp, sisa_utang, sisa_format }] }

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_pelanggan.php';

$q = trim($_GET['q'] ?? '');

try {
    // === 1. Ambil pelanggan (filter search nama / no_hp) ===
    if ($q !== '') {
        $stmtP = $pdoPelanggan->prepare("
            SELECT id, nama, no_hp
            FROM pelanggan
            WHERE nama LIKE ? OR no_hp LIKE ?
            ORDER BY nama ASC
        ");
        $searchTerm = '%' . $q . '%';
        $stmtP->execute([$searchTerm, $searchTerm]);
    } else {
        $stmtP = $pdoPelanggan->query("SELECT id, nama, no_hp FROM pelanggan ORDER BY nama ASC");
    }
    $pelangganList = $stmtP->fetchAll(PDO::FETCH_ASSOC);

    // === 2. Ambil semua riwayat utang (efisien, tanpa N+1) ===
    $stmtU = $pdoPelanggan->query("
        SELECT pelanggan_id, tipe, nominal
        FROM utang
        ORDER BY pelanggan_id ASC, created_at ASC, id ASC
    ");
    $utangRaw = $stmtU->fetchAll(PDO::FETCH_ASSOC);

    $utangByPelanggan = [];
    foreach ($utangRaw as $u) {
        $utangByPelanggan[$u['pelanggan_id']][] = $u;
    }

    // === 3. Hitung sisa utang per pelanggan ===
    $data  = [];
    $grand = 0;

    foreach ($pelangganList as $p) {
        $riwayat = $utangByPelanggan[$p['id']] ?? [];

        $totalUtangSesi = 0;
        $totalBayarSesi = 0;
        foreach ($riwayat as $r) {
            $nominal = floatval($r['nominal']);
            if ($r['tipe'] === 'utang') {
                $totalUtangSesi += $nominal;
            } else {
                $totalBayarSesi += $nominal;
            }
            if ($totalUtangSesi > 0 && $totalBayarSesi >= $totalUtangSesi) {
                $totalUtangSesi = 0;
                $totalBayarSesi = 0;
            }
        }
        $sisa = max(0, $totalUtangSesi - $totalBayarSesi);

        if ($sisa > 0) {
            $data[] = [
                'id'          => (int)$p['id'],
                'nama'        => $p['nama'],
                'no_hp'       => $p['no_hp'] ?? '',
                'sisa_utang'  => $sisa,
                'sisa_format' => 'Rp ' . number_format($sisa, 0, ',', '.')
            ];
            $grand += $sisa;
        }
    }

    // === 4. Urutkan dari utang terbesar ===
    usort($data, fn($a, $b) => $b['sisa_utang'] <=> $a['sisa_utang']);

    echo json_encode([
        'status'       => 'success',
        'total_count'  => count($data),
        'grand_total'  => $grand,
        'grand_format' => 'Rp ' . number_format($grand, 0, ',', '.'),
        'data'         => $data
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Gagal memuat data: ' . $e->getMessage()
    ]);
}