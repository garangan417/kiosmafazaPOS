<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';

// Panggil koneksi database POS (kalau butuh) dan database Automasi
require_once BASE_PATH . 'database/db.php'; 
require_once BASE_PATH . 'database/db_automasi.php'; // <--- KONEKSI BARU KITA

// ============================================================
// KONFIGURASI PAGINASI & FILTER
// ============================================================
$limit = 20; 
$isHtmx = isset($_SERVER['HTTP_HX_REQUEST']);

$tgl_awal  = $_GET['tgl_awal'] ?? date('Y-m-d');
$tgl_akhir = $_GET['tgl_akhir'] ?? date('Y-m-d');

// ============================================================
// FUNGSI UNTUK AMBIL DATA
// ============================================================
function getDataLaporan($pdo_automasi, $tgl_awal, $tgl_akhir, $limit) {
    $page = max(1, intval($_GET['page'] ?? 1));
    $offset = ($page - 1) * $limit;

    // 1. DATA PPOB (KROM)
    // ✨ PAKAI DATE() BIAR CUMA NYOCokin TANGGALNYA AJA ✨
    $stmtCountPpob = $pdo_automasi->prepare("SELECT COUNT(*) FROM transaksi_ppob WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?");
    $stmtCountPpob->execute([$tgl_awal, $tgl_akhir]);
    $totalPpob = (int) $stmtCountPpob->fetchColumn();

    $stmtPpob = $pdo_automasi->prepare("
        SELECT pengirim, nominal, tanggal_transaksi, created_at 
        FROM transaksi_ppob 
        WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmtPpob->bindValue(1, $tgl_awal);
    $stmtPpob->bindValue(2, $tgl_akhir);
    $stmtPpob->bindValue(3, $limit, PDO::PARAM_INT);
    $stmtPpob->bindValue(4, $offset, PDO::PARAM_INT);
    $stmtPpob->execute();
    $dataPpob = $stmtPpob->fetchAll(PDO::FETCH_ASSOC);

    // 2. DATA PENJUALAN (GOJEK)
    $stmtCountGojek = $pdo_automasi->prepare("SELECT COUNT(*) FROM penjualan_gojek WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?");
    $stmtCountGojek->execute([$tgl_awal, $tgl_akhir]);
    $totalGojek = (int) $stmtCountGojek->fetchColumn();

    $stmtGojek = $pdo_automasi->prepare("
        SELECT merchant, total_pendapatan, total_transaksi, tanggal_laporan, created_at 
        FROM penjualan_gojek 
        WHERE DATE(created_at) >= ? AND DATE(created_at) <= ?
        ORDER BY created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmtGojek->bindValue(1, $tgl_awal);
    $stmtGojek->bindValue(2, $tgl_akhir);
    $stmtGojek->bindValue(3, $limit, PDO::PARAM_INT);
    $stmtGojek->bindValue(4, $offset, PDO::PARAM_INT);
    $stmtGojek->execute();
    $dataGojek = $stmtGojek->fetchAll(PDO::FETCH_ASSOC);

    return [
        'ppob' => ['data' => $dataPpob, 'total' => $totalPpob],
        'gojek' => ['data' => $dataGojek, 'total' => $totalGojek],
        'pagination' => [
            'current_page' => $page,
            'total_pages' => max(1, ceil(max($totalPpob, $totalGojek) / $limit))
        ]
    ];
}

// ============================================================
// HANDLE HTMX GET (FILTER / PAGINASI)
// ============================================================
if ($isHtmx && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Gunakan $pdo_automasi
    $laporan = getDataLaporan($pdo_automasi, $tgl_awal, $tgl_akhir, $limit);
    renderLaporan($laporan, $tgl_awal, $tgl_akhir);
    exit;
}

// ============================================================
// GET BIASA: RENDER FULL PAGE
// ============================================================
$laporan = getDataLaporan($pdo_automasi, $tgl_awal, $tgl_akhir, $limit);

require_once BASE_PATH . 'partials/header.php';
?>

<main class="container my-4 flex-grow-1">

  <div class="row mb-4">
    <div class="col-12">
      <h4 class="fw-bold mb-0">Laporan Automasi STB</h4>
      <p class="text-muted small">Data ditarik otomatis dari email (Krom & Gojek).</p>
    </div>
  </div>

  <!-- Form Filter Tanggal (HTMX) -->
  <div class="card shadow-sm border-0 p-3 mb-4">
    <form hx-get="<?= BASE_URL; ?>keuangan/automasi.php" 
          hx-target="#area-laporan" 
          hx-swap="outerHTML"
          class="row align-items-end g-3">
      
      <div class="col-md-4">
        <label class="form-label small fw-semibold">Dari Tanggal</label>
        <input type="date" name="tgl_awal" class="form-control" value="<?= htmlspecialchars($tgl_awal); ?>" required>
      </div>
      
      <div class="col-md-4">
        <label class="form-label small fw-semibold">Sampai Tanggal</label>
        <input type="date" name="tgl_akhir" class="form-control" value="<?= htmlspecialchars($tgl_akhir); ?>" required>
      </div>

      <div class="col-md-4">
        <button type="submit" class="btn btn-dark w-100 d-flex align-items-center justify-content-center gap-2">
          <span>Filter Laporan</span>
          <div class="spinner-border spinner-border-sm htmx-indicator" role="status"></div>
        </button>
      </div>
    </form>
  </div>

  <!-- Area Hasil Laporan -->
  <div class="col-12">
    <?php renderLaporan($laporan, $tgl_awal, $tgl_akhir); ?>
  </div>

</main>

<?php require_once BASE_PATH . 'partials/footer.php'; ?>

<?php
// FUNGSI RENDER (Sama persis seperti sebelumnya)
function renderLaporan($laporan, $tgl_awal, $tgl_akhir) {
?>
  <div id="area-laporan">
    <div class="row mb-4 g-3">
      <div class="col-md-6">
        <div class="card border-0 shadow-sm bg-primary text-white p-3 h-100">
          <div class="small text-uppercase font-weight-bold opacity-75">Total Transaksi PPOB (Krom)</div>
          <div class="h3 mb-0 fw-bold"><?= number_format($laporan['ppob']['total']); ?> Transaksi</div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card border-0 shadow-sm bg-success text-white p-3 h-100">
          <div class="small text-uppercase font-weight-bold opacity-75">Total Laporan Penjualan (Gojek)</div>
          <div class="h3 mb-0 fw-bold"><?= number_format($laporan['gojek']['total']); ?> Laporan</div>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <!-- TABEL PPOB -->
      <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
            <h6 class="fw-bold mb-0">📥 Transaksi PPOB (Krom)</h6>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-hover align-middle table-sm">
                <thead class="table-light">
                  <tr>
                    <th>Waktu</th>
                    <th>Pengirim</th>
                    <th class="text-end">Nominal</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($laporan['ppob']['data'])): ?>
                    <tr><td colspan="3" class="text-center text-muted py-3">Belum ada data.</td></tr>
                  <?php else: ?>
                    <?php foreach ($laporan['ppob']['data'] as $row): ?>
                      <tr>
                        <td class="small text-muted"><?= date('d/m H:i', strtotime($row['created_at'])); ?></td>
                        <td class="small"><?= htmlspecialchars($row['pengirim']); ?></td>
                        <td class="text-end fw-bold text-success"><?= htmlspecialchars($row['nominal']); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- TABEL GOJEK -->
      <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
          <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
            <h6 class="fw-bold mb-0">🛒 Laporan Penjualan (Gojek)</h6>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-hover align-middle table-sm">
                <thead class="table-light">
                  <tr>
                    <th>Tanggal</th>
                    <th>Merchant</th>
                    <th class="text-end">Pendapatan</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($laporan['gojek']['data'])): ?>
                    <tr><td colspan="3" class="text-center text-muted py-3">Belum ada data.</td></tr>
                  <?php else: ?>
                    <?php foreach ($laporan['gojek']['data'] as $row): ?>
                      <tr>
                        <td class="small text-muted"><?= htmlspecialchars($row['tanggal_laporan']); ?></td>
                        <td class="small"><?= htmlspecialchars($row['merchant']); ?></td>
                        <td class="text-end fw-bold text-success"><?= htmlspecialchars($row['total_pendapatan']); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php
}
?>