<?php
require_once __DIR__ . '/config.php';
require_once BASE_PATH . 'database/db_pelanggan.php';

// Cek dan gunakan database transaksi kas jika ada, fallback ke $pdoPelanggan
if (file_exists(BASE_PATH . 'database/db.php')) {
    require_once BASE_PATH . 'database/db.php';
} else {
    $pdo = $pdoPelanggan;
}

// Pastikan fungsi formatRupiah terdefinisi
if (!function_exists('formatRupiah')) {
    function formatRupiah($nominal) {
        return 'Rp ' . number_format((float)$nominal, 0, ',', '.');
    }
}

// 1. Ambil Sisa Saldo Kas Terakhir (Gunakan try-catch agar tidak error 500 jika tabel transaksi belum ada)
$saldoKas = 0;
try {
    $stmtSaldo = $pdo->query("SELECT saldo_akhir FROM transaksi ORDER BY id DESC LIMIT 1");
    if ($stmtSaldo) {
        $rowSaldo = $stmtSaldo->fetch(PDO::FETCH_ASSOC);
        $saldoKas = $rowSaldo ? floatval($rowSaldo['saldo_akhir']) : 0;
    }
} catch (Exception $e) {
    $saldoKas = 0; // Jika tabel transaksi belum disetup
}

// 2. Ambil Semua Pelanggan & Hitung Utang per Pelanggan
$daftarUtang = [];
$totalPiutangKios = 0;

try {
    $stmtP = $pdoPelanggan->query("SELECT id, nama, no_hp FROM pelanggan ORDER BY nama ASC");
    $pelangganList = $stmtP->fetchAll(PDO::FETCH_ASSOC);

    foreach ($pelangganList as $p) {
        // Ambil riwayat utang pelanggan ini
        $stmtU = $pdoPelanggan->prepare("SELECT tipe, nominal FROM utang WHERE pelanggan_id = ? ORDER BY created_at ASC, id ASC");
        $stmtU->execute([$p['id']]);
        $riwayat = $stmtU->fetchAll(PDO::FETCH_ASSOC);

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

        $sisaUtang = max(0, $totalUtangSesi - $totalBayarSesi);

        // Hanya masukkan yang masih berutang (> 0)
        if ($sisaUtang > 0) {
            $p['total_utang'] = $sisaUtang;
            $daftarUtang[] = $p;
            $totalPiutangKios += $sisaUtang;
        }
    }

    // Urutkan dari utang terbesar ke terkecil
    usort($daftarUtang, function($a, $b) {
        return $b['total_utang'] <=> $a['total_utang'];
    });

} catch (Exception $e) {
    // Tangani error database jika ada masalah query
}

require_once BASE_PATH . 'partials/header.php';
?>

<main class="container my-4 flex-grow-1">

  <!-- Header Dashboard -->
  <div class="d-flex align-items-center justify-content-between mb-4">
    <div>
      <h3 class="fw-bold mb-0">Dashboard Kios</h3>
      <p class="text-muted small mb-0">Ringkasan kas dan rekap piutang pelanggan</p>
    </div>
    <a href="<?= BASE_URL; ?>pelanggan/" class="btn btn-dark d-flex align-items-center gap-2">
      <i class="bi bi-people-fill"></i>
      <span>Kelola Pelanggan</span>
    </a>
  </div>

  <!-- Cards Stat Top Summary -->
  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <div class="card border-0 shadow-sm bg-primary text-white p-3">
        <div class="small text-uppercase font-weight-bold opacity-75">Saldo Pulsa</div>
        <div class="h2 mb-0 fw-bold"><?= formatRupiah($saldoKas); ?></div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="card border-0 shadow-sm bg-danger text-white p-3">
        <div class="small text-uppercase font-weight-bold opacity-75">Total Piutang Belum Terbayar</div>
        <div class="h2 mb-0 fw-bold"><?= formatRupiah($totalPiutangKios); ?></div>
      </div>
    </div>
  </div>

<!-- Daftar Pelanggan Berutang -->
  <div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
      <h5 class="card-title mb-0 fw-bold text-danger">
        <i class="bi bi-exclamation-circle-fill me-2"></i>Daftar Pelanggan Berutang
      </h5>
      <span class="badge bg-danger rounded-pill"><?= count($daftarUtang); ?> Pelanggan</span>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <!-- URUTAN DITUKAR: Nama -> Sisa Utang -> No. HP -> Aksi -->
            <th>Nama Pelanggan</th>
            <th class="text-start">Sisa Utang</th>
            <th>No. HP</th>
            <th class="text-center" style="width: 100px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($daftarUtang)): ?>
            <tr>
              <td colspan="4" class="text-center text-muted py-4">
                <i class="bi bi-check-circle text-success fs-3 d-block mb-1"></i>
                Tidak ada pelanggan yang memiliki utang saat ini.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($daftarUtang as $row): ?>
              <tr role="button" 
                  onclick="window.location.href='<?= BASE_URL; ?>pelanggan/'" 
                  style="cursor: pointer;">
                
                <!-- 1. Nama Pelanggan -->
                <td>
                  <span class="fw-bold text-dark d-block"><?= htmlspecialchars($row['nama']); ?></span>
                </td>

                <!-- 2. Sisa Utang (Pindah ke Posisi Ke-2) -->
                <td class="fw-bold text-danger fs-6">
                  <?= formatRupiah($row['total_utang']); ?>
                </td>

                <!-- 3. No. HP (Pindah ke Posisi Ke-3) -->
                <td class="text-muted small">
                  <?= htmlspecialchars($row['no_hp'] ?: '-'); ?>
                </td>

                <!-- 4. Aksi -->
                <td class="text-center" onclick="event.stopPropagation();">
                  <a href="<?= BASE_URL; ?>pelanggan/" 
                     class="btn btn-sm btn-outline-secondary">
                    Detail <i class="bi bi-chevron-right ms-1"></i>
                  </a>
                </td>

              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>

<?php require_once BASE_PATH . 'partials/footer.php'; ?>