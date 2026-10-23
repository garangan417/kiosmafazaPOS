<?php
// kasir/api_bayar_utang.php
// POST: simpan pembayaran / cicilan utang
// Body (FormData): pelanggan_id, nominal, keterangan (opsional), tanggal (opsional)
// Response: { status: 'success'|'error', message }

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_pelanggan.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$pelangganId = intval($_POST['pelanggan_id'] ?? 0);
$nominal     = floatval(str_replace('.', '', $_POST['nominal'] ?? '0'));
$keterangan  = trim($_POST['keterangan'] ?? '');
$tanggal     = trim($_POST['tanggal'] ?? '');

// === Validasi ===
if ($pelangganId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Pelanggan tidak valid']);
    exit;
}
if ($nominal <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Nominal harus lebih dari 0']);
    exit;
}

try {
    // === 1. Hitung sisa utang saat ini ===
    $stmt = $pdoPelanggan->prepare("
        SELECT tipe, nominal FROM utang
        WHERE pelanggan_id = ?
        ORDER BY created_at ASC, id ASC
    ");
    $stmt->execute([$pelangganId]);
    $riwayat = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $totalUtangSesi = 0;
    $totalBayarSesi = 0;
    foreach ($riwayat as $r) {
        $n = floatval($r['nominal']);
        if ($r['tipe'] === 'utang') $totalUtangSesi += $n;
        else $totalBayarSesi += $n;
        if ($totalUtangSesi > 0 && $totalBayarSesi >= $totalUtangSesi) {
            $totalUtangSesi = 0;
            $totalBayarSesi = 0;
        }
    }
    $sisaUtang = max(0, $totalUtangSesi - $totalBayarSesi);

    if ($sisaUtang <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Pelanggan ini tidak punya utang aktif']);
        exit;
    }
    if ($nominal > $sisaUtang) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Nominal melebihi sisa utang (Rp ' . number_format($sisaUtang, 0, ',', '.') . ')'
        ]);
        exit;
    }

    // === 2. Insert pembayaran ===
    // Jika tanggal diisi, pakai itu. Kalau kosong, biarkan DEFAULT CURRENT_TIMESTAMP.
    if ($tanggal !== '') {
        $sql = "INSERT INTO utang (pelanggan_id, tipe, nominal, keterangan, created_at)
                VALUES (?, 'bayar', ?, ?, ?)";
        $stmtIns = $pdoPelanggan->prepare($sql);
        $stmtIns->execute([$pelangganId, $nominal, $keterangan, $tanggal]);
    } else {
        $sql = "INSERT INTO utang (pelanggan_id, tipe, nominal, keterangan)
                VALUES (?, 'bayar', ?, ?)";
        $stmtIns = $pdoPelanggan->prepare($sql);
        $stmtIns->execute([$pelangganId, $nominal, $keterangan]);
    }

    $sisaBaru = $sisaUtang - $nominal;

    echo json_encode([
        'status'     => 'success',
        'message'    => 'Pembayaran berhasil disimpan! Sisa utang: Rp ' . number_format($sisaBaru, 0, ',', '.'),
        'sisa_baru'  => $sisaBaru
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Gagal menyimpan pembayaran: ' . $e->getMessage()
    ]);
}