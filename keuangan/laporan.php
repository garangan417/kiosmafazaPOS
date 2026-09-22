<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set zona waktu ke Indonesia
date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db.php';

// Cek apakah request dipanggil via HTMX (Ajax)
$isHtmx = isset($_SERVER['HTTP_HX_REQUEST']);

// Tangkap Parameter Filter (Default: Hari Ini)
$today    = date('Y-m-d');
$tglAwal  = $_GET['tgl_awal'] ?? $today;
$tglAkhir = $_GET['tgl_akhir'] ?? date('Y-m-t', strtotime($today));
$tipe     = $_GET['tipe'] ?? 'semua';
$kategori = trim($_GET['kategori'] ?? '');
$search   = trim($_GET['q'] ?? '');
$page     = max(1, intval($_GET['page'] ?? 1));

// Ambil Daftar Kategori Unik dari Database
$stmtKat = $pdo->query("SELECT DISTINCT kategori FROM transaksi WHERE kategori != '' ORDER BY kategori ASC");
$listKategori = $stmtKat->fetchAll(PDO::FETCH_COLUMN);

// Jika HTMX Request, Hanya Render bagian Area Laporan
if ($isHtmx) {
    renderMainLaporan($pdo, $tglAwal, $tglAkhir, $tipe, $kategori, $search, $page);
    exit;
}

require_once BASE_PATH . 'partials/header.php';
?>

<main class="container my-4 flex-grow-1">
  <!-- HEADER LAPORAN -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h4 class="fw-bold mb-0 text-dark"><i class="bi bi-file-earmark-bar-graph text-success me-2"></i>Laporan Keuangan & PPOB</h4>
      <small class="text-muted">Rekapitulasi mutasi kas, transaksi PPOB, dan operasional toko.</small>
    </div>
    <a href="<?= BASE_URL; ?>keuangan/" class="btn btn-outline-secondary btn-sm fw-bold">
      <i class="bi bi-arrow-left me-1"></i> Kembali ke Form Input
    </a>
  </div>

  <!-- PANEL FILTER LAPORAN -->
  <div class="card shadow-sm border-0 p-3 mb-4">
    <form hx-get="<?= BASE_URL; ?>keuangan/laporan.php" 
          hx-target="#area-laporan-content" 
          hx-swap="outerHTML" 
          hx-trigger="change, submit, keyup delay:400ms from:#inputSearch"
          class="row g-2 align-items-end">

      <!-- Preset Tanggal Cepat -->
      <div class="col-12 mb-2">
        <div class="btn-group btn-group-sm" role="group">
          <button type="button" class="btn btn-outline-secondary" onclick="setPresetDate('today')">Hari Ini</button>
          <button type="button" class="btn btn-outline-secondary" onclick="setPresetDate('yesterday')">Kemarin</button>
          <button type="button" class="btn btn-outline-secondary" onclick="setPresetDate('this_month')">Bulan Ini</button>
        </div>
      </div>

      <div class="col-md-2 col-6">
        <label class="form-label small fw-bold mb-1">Dari Tanggal</label>
        <input type="date" id="tglAwal" name="tgl_awal" class="form-control form-control-sm" value="<?= htmlspecialchars($tglAwal); ?>">
      </div>

      <div class="col-md-2 col-6">
        <label class="form-label small fw-bold mb-1">Sampai Tanggal</label>
        <input type="date" id="tglAkhir" name="tgl_akhir" class="form-control form-control-sm" value="<?= htmlspecialchars($tglAkhir); ?>">
      </div>

      <div class="col-md-2 col-6">
        <label class="form-label small fw-bold mb-1">Jenis Transaksi</label>
        <select name="tipe" class="form-select form-select-sm">
          <option value="semua" <?= $tipe === 'semua' ? 'selected' : ''; ?>>Semua Tipe</option>
          <option value="masuk" <?= $tipe === 'masuk' ? 'selected' : ''; ?>>Uang Masuk (+)</option>
          <option value="keluar" <?= $tipe === 'keluar' ? 'selected' : ''; ?>>Uang Keluar (-)</option>
        </select>
      </div>

      <div class="col-md-3 col-6">
        <label class="form-label small fw-bold mb-1">Kategori Layanan</label>
        <select name="kategori" class="form-select form-select-sm">
          <option value="">Semua Kategori</option>
          <?php foreach ($listKategori as $kat): ?>
            <option value="<?= htmlspecialchars($kat); ?>" <?= $kategori === $kat ? 'selected' : ''; ?>>
              <?= htmlspecialchars($kat); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-3 col-12">
        <label class="form-label small fw-bold mb-1">Cari Keterangan</label>
        <div class="input-group input-group-sm">
          <input type="text" id="inputSearch" name="q" class="form-control" placeholder="Ketik nomor/nama..." value="<?= htmlspecialchars($search); ?>">
          <button type="submit" class="btn btn-dark fw-bold"><i class="bi bi-search"></i></button>
        </div>
      </div>
    </form>
  </div>

  <!-- AREA KONTEN LAPORAN -->
  <?php renderMainLaporan($pdo, $tglAwal, $tglAkhir, $tipe, $kategori, $search, $page); ?>

</main>

<script>
// Helper JavaScript lokal (Tanpa ISO String UTC)
function setPresetDate(type) {
  const tglAwal = document.getElementById('tglAwal');
  const tglAkhir = document.getElementById('tglAkhir');

  const formatDate = (date) => {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
  };

  const today = new Date();

  if (type === 'today') {
    tglAwal.value = formatDate(today);
    tglAkhir.value = formatDate(today);
  } else if (type === 'yesterday') {
    const yesterday = new Date(today);
    yesterday.setDate(today.getDate() - 1);
    tglAwal.value = formatDate(yesterday);
    tglAkhir.value = formatDate(yesterday);
  } else if (type === 'this_month') {
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
    tglAwal.value = formatDate(firstDay);
    tglAkhir.value = formatDate(today);
  }

  tglAwal.dispatchEvent(new Event('change', { bubbles: true }));
}
</script>

<?php require_once BASE_PATH . 'partials/footer.php'; ?>

<?php
/**
 * FUNGSI UTAMA UNTUK SUMMARY & TABEL LAPORAN
 */
function renderMainLaporan($pdo, $tglAwal, $tglAkhir, $tipe, $kategori, $search, $page = 1) {
    // ============================================================
    // KONFIGURASI PAGINASI
    // ============================================================
    $limit  = 50;
    $offset = ($page - 1) * $limit;

    // 1. Ambil Saldo Kas Terakhir
    $stmtCurrentSaldo = $pdo->query("SELECT saldo_akhir FROM transaksi ORDER BY id DESC LIMIT 1");
    $currentSaldoRow  = $stmtCurrentSaldo->fetch(PDO::FETCH_ASSOC);
    $saldoTerakhir    = $currentSaldoRow ? floatval($currentSaldoRow['saldo_akhir']) : 0;

    // 2. Query Summary dengan SUBSTR/DATE untuk menangani format 'YYYY-MM-DD' maupun 'YYYY-MM-DD HH:MM:SS'
    $sqlSummaryWhere = ["SUBSTR(tanggal, 1, 10) BETWEEN ? AND ?"];
    $paramSummary    = [$tglAwal, $tglAkhir];

    if (!empty($kategori)) {
        $sqlSummaryWhere[] = "kategori = ?";
        $paramSummary[]   = $kategori;
    }

    if (!empty($search)) {
        $sqlSummaryWhere[] = "(kategori LIKE ? OR keterangan LIKE ?)";
        $paramSummary[]   = "%$search%";
        $paramSummary[]   = "%$search%";
    }

    $whereSummaryStr = implode(" AND ", $sqlSummaryWhere);
    $sqlSummary = "SELECT 
                    SUM(CASE WHEN tipe = 'masuk' THEN nominal ELSE 0 END) as total_masuk,
                    SUM(CASE WHEN tipe = 'keluar' THEN nominal ELSE 0 END) as total_keluar,
                    COUNT(id) as total_trx
                   FROM transaksi WHERE {$whereSummaryStr}";
    $stmtSummary = $pdo->prepare($sqlSummary);
    $stmtSummary->execute($paramSummary);
    $summary = $stmtSummary->fetch(PDO::FETCH_ASSOC);

    $totalMasuk  = floatval($summary['total_masuk'] ?? 0);
    $totalKeluar = floatval($summary['total_keluar'] ?? 0);
    $totalTrx    = intval($summary['total_trx'] ?? 0);
    $selisih     = $totalMasuk - $totalKeluar;

    // ============================================================
    // 3. QUERY DETAIL DENGAN PAGINASI
    // ============================================================
    $sqlWhere = $sqlSummaryWhere;
    $params   = $paramSummary;

    if ($tipe !== 'semua') {
        $sqlWhere[] = "tipe = ?";
        $params[]   = $tipe;
    }

    $whereStr = implode(" AND ", $sqlWhere);

    // Hitung total untuk paginasi (dengan filter yang sama, termasuk $tipe)
    $sqlCount = "SELECT COUNT(*) FROM transaksi WHERE {$whereStr}";
    $stmtCount = $pdo->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalRows = (int) $stmtCount->fetchColumn();
    $totalPages = max(1, (int) ceil($totalRows / $limit));

    // Clamp page
    if ($page > $totalPages) {
        $page   = $totalPages;
        $offset = ($page - 1) * $limit;
    }

    // Susun array paginasi
    $pagination = [
        'total_items'  => $totalRows,
        'total_pages'  => $totalPages,
        'current_page' => $page,
        'per_page'     => $limit,
        'from'         => $totalRows > 0 ? $offset + 1 : 0,
        'to'           => min($offset + $limit, $totalRows),
    ];

    // Query detail dengan LIMIT + OFFSET
    $sqlDetail = "SELECT * FROM transaksi WHERE {$whereStr} ORDER BY tanggal DESC, id DESC LIMIT ? OFFSET ?";
    $stmtDetail = $pdo->prepare($sqlDetail);

    // Bind parameter filter
    $bindIndex = 1;
    foreach ($params as $p) {
        $stmtDetail->bindValue($bindIndex++, $p);
    }
    // Bind LIMIT & OFFSET
    $stmtDetail->bindValue($bindIndex++, $limit, PDO::PARAM_INT);
    $stmtDetail->bindValue($bindIndex++, $offset, PDO::PARAM_INT);

    $stmtDetail->execute();
    $transaksi = $stmtDetail->fetchAll(PDO::FETCH_ASSOC);
?>
  <div id="area-laporan-content">
    
    <!-- CARD RINGKASAN REKAPITULASI LAPORAN -->
    <div class="row g-2 mb-3">
      <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm bg-success text-white p-3">
          <small class="text-uppercase fw-bold opacity-75">Total Uang Masuk</small>
          <div class="h4 mb-0 fw-bold font-monospace">+ <?= formatRupiah($totalMasuk); ?></div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm bg-danger text-white p-3">
          <small class="text-uppercase fw-bold opacity-75">Total Uang Keluar</small>
          <div class="h4 mb-0 fw-bold font-monospace">- <?= formatRupiah($totalKeluar); ?></div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm bg-<?= $selisih >= 0 ? 'info' : 'warning'; ?> text-dark p-3">
          <small class="text-uppercase fw-bold opacity-75">Arus Kas Bersih (Net)</small>
          <div class="h4 mb-0 fw-bold font-monospace"><?= formatRupiah($selisih); ?></div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="card border-0 shadow-sm bg-primary text-white p-3">
          <small class="text-uppercase fw-bold opacity-75">Saldo Kas Saat Ini</small>
          <div class="h4 mb-0 fw-bold font-monospace"><?= formatRupiah($saldoTerakhir); ?></div>
        </div>
      </div>
    </div>

    <!-- TABEL DATA LAPORAN -->
    <div class="card shadow-sm border-0 p-3">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0 text-dark">
          <i class="bi bi-list-check me-1"></i> Rincian Transaksi
        </h6>
        <span class="badge bg-secondary font-monospace"><?= $totalTrx; ?> Transaksi Ditemukan</span>
      </div>

      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width: 50px;">#</th>
              <th>Tanggal</th>
              <th>Kategori</th>
              <th>Keterangan</th>
              <th class="text-end">Nominal</th>
              <th class="text-end">Saldo Akhir</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($transaksi)): ?>
              <tr>
                <td colspan="6" class="text-center text-muted py-4">
                  <i class="bi bi-inbox fs-2 d-block mb-1"></i>
                  Tidak ada data transaksi yang sesuai dengan filter.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($transaksi as $idx => $row): ?>
                <tr>
                  <td class="text-muted small"><?= $offset + $idx + 1; ?></td>
                  <td class="small fw-semibold text-dark" style="white-space: nowrap;">
                    <?= htmlspecialchars($row['tanggal']); ?>
                  </td>
                  <td>
                    <span class="badge bg-<?= $row['tipe'] === 'masuk' ? 'success' : 'danger'; ?> me-1">
                      <?= strtoupper($row['tipe']); ?>
                    </span>
                    <strong class="text-secondary"><?= htmlspecialchars($row['kategori']); ?></strong>
                  </td>
                  <td class="small text-muted">
                    <?= htmlspecialchars($row['keterangan'] ?: '-'); ?>
                  </td>
                  <td class="text-end fw-bold font-monospace text-<?= $row['tipe'] === 'masuk' ? 'success' : 'danger'; ?>" style="white-space: nowrap;">
                    <?= $row['tipe'] === 'masuk' ? '+' : '-'; ?> <?= formatRupiah($row['nominal']); ?>
                  </td>
                  <td class="text-end fw-semibold font-monospace text-dark" style="white-space: nowrap;">
                    <?= formatRupiah($row['saldo_akhir']); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- PAGINASI SQL (HTMX-compatible) -->
      <?php if ($pagination['total_pages'] > 1): ?>
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-3 gap-2">
          <small class="text-muted">
            Menampilkan <strong><?= $pagination['from']; ?></strong>-<strong><?= $pagination['to']; ?></strong> 
            dari <strong><?= $pagination['total_items']; ?></strong> transaksi
          </small>

          <nav>
            <ul class="pagination pagination-sm mb-0">
              <?php
              $currentPage = $pagination['current_page'];
              $totalPages  = $pagination['total_pages'];

              // Helper untuk bangun URL paginasi (dengan filter tetap)
              $buildUrl = function($p) use ($tglAwal, $tglAkhir, $tipe, $kategori, $search) {
                  $params = [
                      'tgl_awal'  => $tglAwal,
                      'tgl_akhir' => $tglAkhir,
                      'tipe'      => $tipe,
                      'page'      => $p,
                  ];
                  if (!empty($kategori)) $params['kategori'] = $kategori;
                  if (!empty($search))   $params['q'] = $search;
                  return BASE_URL . 'keuangan/laporan.php?' . http_build_query($params);
              };
              ?>

              <!-- Tombol Prev -->
              <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" 
                   href="<?= $buildUrl($currentPage - 1); ?>"
                   hx-get="<?= $buildUrl($currentPage - 1); ?>"
                   hx-target="#area-laporan-content"
                   hx-swap="outerHTML"
                   hx-push-url="true">Previous</a>
              </li>

              <!-- Nomor Halaman (Windowing) -->
              <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i == 1 || $i == $totalPages || abs($i - $currentPage) <= 1): ?>
                  <li class="page-item <?= ($i === $currentPage) ? 'active' : ''; ?>">
                    <a class="page-link" 
                       href="<?= $buildUrl($i); ?>"
                       hx-get="<?= $buildUrl($i); ?>"
                       hx-target="#area-laporan-content"
                       hx-swap="outerHTML"
                       hx-push-url="true"><?= $i; ?></a>
                  </li>
                <?php elseif ($i == 2 || $i == $totalPages - 1): ?>
                  <li class="page-item disabled"><span class="page-link">…</span></li>
                <?php endif; ?>
              <?php endfor; ?>

              <!-- Tombol Next -->
              <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : ''; ?>">
                <a class="page-link" 
                   href="<?= $buildUrl($currentPage + 1); ?>"
                   hx-get="<?= $buildUrl($currentPage + 1); ?>"
                   hx-target="#area-laporan-content"
                   hx-swap="outerHTML"
                   hx-push-url="true">Next</a>
              </li>
            </ul>
          </nav>
        </div>
      <?php endif; ?>

    </div>

  </div>
<?php
}
?>