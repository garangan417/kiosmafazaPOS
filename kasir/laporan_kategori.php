<?php
// kasir/laporan_kategori.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';

// Filter Tanggal (Default Awal: Hari ini, Default Akhir: Akhir bulan ini)
$tglMulai   = $_GET['tgl_mulai'] ?? date('Y-m-d');
$tglSelesai = $_GET['tgl_selesai'] ?? date('Y-m-t');
$kategoriId = isset($_GET['kategori_id']) ? intval($_GET['kategori_id']) : 0;

// Cek apakah request berasal dari HTMX
$isHtmx = !empty($_SERVER['HTTP_HX_REQUEST']);

// Ambil list kategori untuk dropdown filter (hanya diproses saat load halaman penuh)
$listKategori = [];
if (!$isHtmx) {
    try {
        $stmtKat = $pdoBarang->query("SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori ASC");
        $listKategori = $stmtKat->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $listKategori = [];
    }
}

// Query Rekapitulasi Data Per Kategori
try {
    $sql = "SELECT 
                k.id AS kategori_id,
                k.nama_kategori,
                COUNT(DISTINCT pd.penjualan_id) AS total_transaksi,
                SUM(pd.qty) AS total_qty_terjual,
                COALESCE(SUM(pd.subtotal), 0) AS total_omzet,
                COALESCE(SUM(pd.qty * COALESCE(pd.harga_beli, 0)), 0) AS total_modal,
                COALESCE(SUM(pd.subtotal - (pd.qty * COALESCE(pd.harga_beli, 0))), 0) AS total_keuntungan
            FROM penjualan_detail pd
            JOIN penjualan p ON pd.penjualan_id = p.id
            JOIN barang_kemasan bk ON pd.barang_kemasan_id = bk.id
            JOIN barang b ON bk.barang_id = b.id
            JOIN kategori k ON b.kategori_id = k.id
            WHERE DATE(p.tanggal) BETWEEN ? AND ?";

    $params = [$tglMulai, $tglSelesai];

    if ($kategoriId > 0) {
        $sql .= " AND k.id = ?";
        $params[] = $kategoriId;
    }

    $sql .= " GROUP BY k.id, k.nama_kategori ORDER BY total_omzet DESC";

    $stmt = $pdoBarang->prepare($sql);
    $stmt->execute($params);
    $rekapKategori = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Hitung Grand Total Keseluruhan
    $grandOmzet = 0;
    $grandModal = 0;
    $grandLaba  = 0;
    $grandQty   = 0;

    foreach ($rekapKategori as $row) {
        $grandOmzet += $row['total_omzet'];
        $grandModal += $row['total_modal'];
        $grandLaba  += $row['total_keuntungan'];
        $grandQty   += $row['total_qty_terjual'];
    }

} catch (PDOException $e) {
    echo '<div class="alert alert-danger shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i>Error Database: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

// --------------------------------------------------------------------------
// JIKA REQUEST DARI HTMX: Hanya cetak bagian konten/data
// --------------------------------------------------------------------------
if ($isHtmx): ?>

  <!-- CARD SUMMARY STATISTIK KATEGORI -->
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card border-0 shadow-sm bg-primary text-white">
        <div class="card-body p-3">
          <small class="text-uppercase fw-semibold opacity-75">Total Omzet Kategori</small>
          <h3 class="fw-bold mb-0 font-monospace">Rp <?= number_format($grandOmzet, 0, ',', '.'); ?></h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm bg-secondary text-white">
        <div class="card-body p-3">
          <small class="text-uppercase fw-semibold opacity-75">Total Modal (HPP)</small>
          <h3 class="fw-bold mb-0 font-monospace">Rp <?= number_format($grandModal, 0, ',', '.'); ?></h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm bg-success text-white">
        <div class="card-body p-3">
          <small class="text-uppercase fw-semibold opacity-75"><i class="bi bi-cash-stack me-1"></i>Keuntungan (Laba)</small>
          <h3 class="fw-bold mb-0 font-monospace <?= $grandLaba >= 0 ? 'text-warning' : 'text-danger'; ?>">
            Rp <?= number_format($grandLaba, 0, ',', '.'); ?>
          </h3>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm bg-dark text-white">
        <div class="card-body p-3">
          <small class="text-uppercase fw-semibold opacity-75">Total Produk Terjual</small>
          <h3 class="fw-bold mb-0 font-monospace"><?= number_format($grandQty, 0, ',', '.'); ?> <span class="fs-6 fw-normal">Qty</span></h3>
        </div>
      </div>
    </div>
  </div>

  <!-- TABEL REKAPITULASI PENJUALAN PER KATEGORI -->
  <div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
      <h6 class="mb-0 fw-bold"><i class="bi bi-tags-fill me-2 text-primary"></i>Rincian Penjualan Per Kategori Produk</h6>
      <span class="badge bg-light text-dark border">
        Periode: <?= date('d/m/Y', strtotime($tglMulai)); ?> s/d <?= date('d/m/Y', strtotime($tglSelesai)); ?>
      </span>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Nama Kategori</th>
            <th class="text-center">Jml Transaksi</th>
            <th class="text-center">Total Qty Terjual</th>
            <th class="text-end">Total Omzet</th>
            <th class="text-end">Total Modal (HPP)</th>
            <th class="text-end text-success fw-bold">Estimasi Laba</th>
            <th class="text-center">Kontribusi Omzet</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rekapKategori)): ?>
            <tr>
              <td colspan="7" class="text-center text-muted py-5">
                <i class="bi bi-inbox display-5 d-block mb-2 text-muted"></i>
                Tidak ada data penjualan kategori pada rentang tanggal ini.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($rekapKategori as $row): ?>
              <?php $persenOmzet = ($grandOmzet > 0) ? ($row['total_omzet'] / $grandOmzet) * 100 : 0; ?>
              <tr>
                <td>
                  <strong class="text-dark d-block">
                    <i class="bi bi-folder2-open me-2 text-primary"></i><?= htmlspecialchars($row['nama_kategori']); ?>
                  </strong>
                </td>
                <td class="text-center">
                  <span class="badge bg-light text-dark border"><?= number_format($row['total_transaksi']); ?> Struk</span>
                </td>
                <td class="text-center fw-semibold"><?= number_format($row['total_qty_terjual']); ?></td>
                <td class="text-end font-monospace fw-bold text-dark">Rp <?= number_format($row['total_omzet'], 0, ',', '.'); ?></td>
                <td class="text-end font-monospace text-muted">Rp <?= number_format($row['total_modal'], 0, ',', '.'); ?></td>
                <td class="text-end font-monospace fw-bold <?= $row['total_keuntungan'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                  Rp <?= number_format($row['total_keuntungan'], 0, ',', '.'); ?>
                </td>
                <td class="text-center" style="min-width: 160px;">
                  <div class="d-flex align-items-center">
                    <div class="progress flex-grow-1 me-2" style="height: 6px;">
                      <div class="progress-bar bg-primary" role="progressbar" style="width: <?= number_format($persenOmzet, 1); ?>%"></div>
                    </div>
                    <small class="font-monospace text-muted small"><?= number_format($persenOmzet, 1); ?>%</small>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

<?php 
exit; 
endif; 

// --------------------------------------------------------------------------
// JIKA REQUEST AKSES REGULER BROWSER: Render Halaman Penuh + Header/Footer
// --------------------------------------------------------------------------
require_once BASE_PATH . 'partials/header.php';
?>

<div class="container-fluid my-4 px-4">

  <!-- HEADER & FILTER -->
  <div class="row align-items-center mb-4">
    <div class="col-md-5">
      <h4 class="fw-bold mb-1"><i class="bi bi-grid-3x3-gap-fill text-primary me-2"></i>Laporan Per Kategori</h4>
      <p class="text-muted small mb-0">Analisis kontribusi omzet, modal, dan keuntungan bersih berdasarkan kategori barang.</p>
    </div>
    <div class="col-md-7">
      <form id="filterFormKategori"
            hx-get="laporan_kategori.php"
            hx-target="#laporan-kategori-container"
            hx-trigger="change, submit"
            hx-indicator="#loading-spinner"
            class="row g-2 justify-content-md-end align-items-center">

        <div class="col-auto">
          <select name="kategori_id" class="form-select form-select-sm">
            <option value="0">-- Semua Kategori --</option>
            <?php foreach ($listKategori as $kat): ?>
              <option value="<?= $kat['id']; ?>" <?= $kategoriId == $kat['id'] ? 'selected' : ''; ?>>
                <?= htmlspecialchars($kat['nama_kategori']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-auto">
          <input type="date" name="tgl_mulai" class="form-control form-control-sm" value="<?= htmlspecialchars($tglMulai); ?>">
        </div>
        <div class="col-auto align-self-center small text-muted">s/d</div>
        <div class="col-auto">
          <input type="date" name="tgl_selesai" class="form-control form-control-sm" value="<?= htmlspecialchars($tglSelesai); ?>">
        </div>

        <div class="col-auto">
          <button type="submit" class="btn btn-sm btn-primary">
            <i class="bi bi-filter me-1"></i> Filter
          </button>
          <a href="laporan.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Laporan Umum
          </a>
        </div>
      </form>
    </div>
  </div>

  <!-- SPINNER INDICATOR -->
  <div id="loading-spinner" class="htmx-indicator text-center py-5">
    <div class="spinner-border text-primary" role="status">
      <span class="visually-hidden">Memuat data...</span>
    </div>
    <div class="text-muted small mt-2">Sedang menghitung rekapitulasi kategori...</div>
  </div>

  <!-- KONTAINER UTAMA HASIL DATA -->
  <div id="laporan-kategori-container">
    <div class="row g-3 mb-4">
      <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-primary text-white">
          <div class="card-body p-3">
            <small class="text-uppercase fw-semibold opacity-75">Total Omzet Kategori</small>
            <h3 class="fw-bold mb-0 font-monospace">Rp <?= number_format($grandOmzet, 0, ',', '.'); ?></h3>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-secondary text-white">
          <div class="card-body p-3">
            <small class="text-uppercase fw-semibold opacity-75">Total Modal (HPP)</small>
            <h3 class="fw-bold mb-0 font-monospace">Rp <?= number_format($grandModal, 0, ',', '.'); ?></h3>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-success text-white">
          <div class="card-body p-3">
            <small class="text-uppercase fw-semibold opacity-75"><i class="bi bi-cash-stack me-1"></i>Keuntungan (Laba)</small>
            <h3 class="fw-bold mb-0 font-monospace <?= $grandLaba >= 0 ? 'text-warning' : 'text-danger'; ?>">
              Rp <?= number_format($grandLaba, 0, ',', '.'); ?>
            </h3>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-dark text-white">
          <div class="card-body p-3">
            <small class="text-uppercase fw-semibold opacity-75">Total Produk Terjual</small>
            <h3 class="fw-bold mb-0 font-monospace"><?= number_format($grandQty, 0, ',', '.'); ?> <span class="fs-6 fw-normal">Qty</span></h3>
          </div>
        </div>
      </div>
    </div>

    <div class="card shadow-sm border-0">
      <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold"><i class="bi bi-tags-fill me-2 text-primary"></i>Rincian Penjualan Per Kategori Produk</h6>
        <span class="badge bg-light text-dark border">
          Periode: <?= date('d/m/Y', strtotime($tglMulai)); ?> s/d <?= date('d/m/Y', strtotime($tglSelesai)); ?>
        </span>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Nama Kategori</th>
              <th class="text-center">Jml Transaksi</th>
              <th class="text-center">Total Qty Terjual</th>
              <th class="text-end">Total Omzet</th>
              <th class="text-end">Total Modal (HPP)</th>
              <th class="text-end text-success fw-bold">Estimasi Laba</th>
              <th class="text-center">Kontribusi Omzet</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rekapKategori)): ?>
              <tr>
                <td colspan="7" class="text-center text-muted py-5">
                  <i class="bi bi-inbox display-5 d-block mb-2 text-muted"></i>
                  Tidak ada data penjualan kategori pada rentang tanggal ini.
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($rekapKategori as $row): ?>
                <?php $persenOmzet = ($grandOmzet > 0) ? ($row['total_omzet'] / $grandOmzet) * 100 : 0; ?>
                <tr>
                  <td>
                    <strong class="text-dark d-block">
                      <i class="bi bi-folder2-open me-2 text-primary"></i><?= htmlspecialchars($row['nama_kategori']); ?>
                    </strong>
                  </td>
                  <td class="text-center">
                    <span class="badge bg-light text-dark border"><?= number_format($row['total_transaksi']); ?> Struk</span>
                  </td>
                  <td class="text-center fw-semibold"><?= number_format($row['total_qty_terjual']); ?></td>
                  <td class="text-end font-monospace fw-bold text-dark">Rp <?= number_format($row['total_omzet'], 0, ',', '.'); ?></td>
                  <td class="text-end font-monospace text-muted">Rp <?= number_format($row['total_modal'], 0, ',', '.'); ?></td>
                  <td class="text-end font-monospace fw-bold <?= $row['total_keuntungan'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                    Rp <?= number_format($row['total_keuntungan'], 0, ',', '.'); ?>
                  </td>
                  <td class="text-center" style="min-width: 160px;">
                    <div class="d-flex align-items-center">
                      <div class="progress flex-grow-1 me-2" style="height: 6px;">
                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?= number_format($persenOmzet, 1); ?>%"></div>
                      </div>
                      <small class="font-monospace text-muted small"><?= number_format($persenOmzet, 1); ?>%</small>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<?php require_once BASE_PATH . 'partials/footer.php'; ?>