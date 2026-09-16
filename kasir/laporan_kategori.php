<?php
// kasir/laporan_kategori.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';

// Filter Tanggal & Input Parameter
$tglMulai   = $_GET['tgl_mulai'] ?? date('Y-m-d');
$tglSelesai = $_GET['tgl_selesai'] ?? date('Y-m-t');
$kategoriId = isset($_GET['kategori_id']) ? intval($_GET['kategori_id']) : 0;
$keyword    = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

// Cek Request HTMX
$isHtmx = !empty($_SERVER['HTTP_HX_REQUEST']);

// Ambil list kategori untuk dropdown filter (Hanya saat Akses Penuh)
$listKategori = [];
if (!$isHtmx) {
    try {
        $stmtKat = $pdoBarang->query("SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori ASC");
        $listKategori = $stmtKat->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $listKategori = [];
    }
}

try {
    // 1. Query Detail Barang Terjual Per Kategori
    $sqlDetail = "SELECT 
                    k.id AS kategori_id,
                    k.nama_kategori,
                    b.nama_barang,
                    pd.nama_kemasan,
                    pd.satuan,
                    SUM(pd.qty) AS total_qty,
                    COALESCE(SUM(pd.subtotal), 0) AS total_omzet,
                    COALESCE(SUM(pd.qty * COALESCE(pd.harga_beli, 0)), 0) AS total_modal,
                    COALESCE(SUM(pd.subtotal - (pd.qty * COALESCE(pd.harga_beli, 0))), 0) AS total_keuntungan
                FROM penjualan_detail pd
                JOIN penjualan p ON pd.penjualan_id = p.id
                JOIN barang_kemasan bk ON pd.barang_kemasan_id = bk.id
                JOIN barang b ON bk.barang_id = b.id
                JOIN kategori k ON b.kategori_id = k.id
                LEFT JOIN barang_barcode bb ON bk.id = bb.barang_kemasan_id
                WHERE DATE(p.tanggal) BETWEEN ? AND ?";

    $params = [$tglMulai, $tglSelesai];

    if ($kategoriId > 0) {
        $sqlDetail .= " AND k.id = ?";
        $params[] = $kategoriId;
    }

    if (!empty($keyword)) {
        $sqlDetail .= " AND (b.nama_barang LIKE ? OR bb.barcode LIKE ?)";
        $params[] = "%" . $keyword . "%";
        $params[] = "%" . $keyword . "%";
    }

    $sqlDetail .= " GROUP BY k.id, k.nama_kategori, b.id, b.nama_barang, pd.nama_kemasan, pd.satuan
                   ORDER BY k.nama_kategori ASC, total_omzet DESC";

    $stmt = $pdoBarang->prepare($sqlDetail);
    $stmt->execute($params);
    $rawDetail = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Grouping Data Berdasarkan Kategori & Hitung Ringkasan
    $rekapKategori = [];
    $grandOmzet = 0;
    $grandModal = 0;
    $grandLaba  = 0;
    $grandQty   = 0;

    foreach ($rawDetail as $row) {
        $katId = $row['kategori_id'];

        if (!isset($rekapKategori[$katId])) {
            $rekapKategori[$katId] = [
                'nama_kategori'    => $row['nama_kategori'],
                'total_qty'        => 0,
                'total_omzet'      => 0,
                'total_modal'      => 0,
                'total_keuntungan' => 0,
                'items'            => []
            ];
        }

        $rekapKategori[$katId]['total_qty']        += $row['total_qty'];
        $rekapKategori[$katId]['total_omzet']      += $row['total_omzet'];
        $rekapKategori[$katId]['total_modal']      += $row['total_modal'];
        $rekapKategori[$katId]['total_keuntungan'] += $row['total_keuntungan'];

        $rekapKategori[$katId]['items'][] = $row;

        // Accumulate Grand Total
        $grandOmzet += $row['total_omzet'];
        $grandModal += $row['total_modal'];
        $grandLaba  += $row['total_keuntungan'];
        $grandQty   += $row['total_qty'];
    }

} catch (PDOException $e) {
    echo '<div class="alert alert-danger shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i>Error Database: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

// Function Helper Render Konten
function renderKontenLaporan($rekapKategori, $grandOmzet, $grandModal, $grandLaba, $grandQty, $tglMulai, $tglSelesai) {
    ?>
    <!-- CARD SUMMARY STATISTIK KATEGORI -->
    <div class="row g-3 mb-4">
      <div class="col-md-3">
        <div class="card border-0 shadow-sm bg-primary text-white">
          <div class="card-body p-3">
            <small class="text-uppercase fw-semibold opacity-75">Total Omzet</small>
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

    <!-- RINCIAN PENJUALAN PER KATEGORI & DETAIL ITEM -->
    <div class="card shadow-sm border-0">
      <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <h6 class="mb-0 fw-bold"><i class="bi bi-tags-fill me-2 text-primary"></i>Rincian Penjualan Per Kategori & Detail Item</h6>
        <span class="badge bg-light text-dark border">
          Periode: <?= date('d/m/Y', strtotime($tglMulai)); ?> s/d <?= date('d/m/Y', strtotime($tglSelesai)); ?>
        </span>
      </div>
      <div class="card-body p-0">
        <?php if (empty($rekapKategori)): ?>
          <div class="text-center text-muted py-5">
            <i class="bi bi-inbox display-5 d-block mb-2 text-muted"></i>
            Tidak ada data penjualan kategori pada rentang tanggal / pencarian ini.
          </div>
        <?php else: ?>
          <div class="accordion accordion-flush" id="accordionKategori">
            <?php $index = 0; ?>
            <?php foreach ($rekapKategori as $katId => $kat): ?>
              <?php 
                $index++;
                $persenOmzet = ($grandOmzet > 0) ? ($kat['total_omzet'] / $grandOmzet) * 100 : 0;
              ?>
              <div class="accordion-item border-bottom">
                <h2 class="accordion-header" id="heading-<?= $katId; ?>">
                  <button class="accordion-button <?= $index > 1 ? 'collapsed' : ''; ?> bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $katId; ?>" aria-expanded="<?= $index === 1 ? 'true' : 'false'; ?>" aria-controls="collapse-<?= $katId; ?>">
                    <div class="w-100 d-flex flex-wrap justify-content-between align-items-center me-3">
                      <div>
                        <i class="bi bi-folder2-open me-2 text-primary fw-bold"></i>
                        <strong class="fs-6 text-dark"><?= htmlspecialchars($kat['nama_kategori']); ?></strong>
                        <span class="badge bg-secondary ms-2"><?= count($kat['items']); ?> Item</span>
                      </div>
                      <div class="d-flex align-items-center gap-3 mt-2 mt-md-0 font-monospace fs-6">
                        <small class="text-muted fw-normal">Qty: <strong><?= number_format($kat['total_qty']); ?></strong></small>
                        <small class="text-muted fw-normal">Omzet: <strong class="text-dark">Rp <?= number_format($kat['total_omzet'], 0, ',', '.'); ?></strong></small>
                        <small class="text-muted fw-normal">Laba: <strong class="<?= $kat['total_keuntungan'] >= 0 ? 'text-success' : 'text-danger'; ?>">Rp <?= number_format($kat['total_keuntungan'], 0, ',', '.'); ?></strong></small>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace small"><?= number_format($persenOmzet, 1); ?>% Omzet</span>
                      </div>
                    </div>
                  </button>
                </h2>
                <div id="collapse-<?= $katId; ?>" class="accordion-collapse collapse <?= $index === 1 ? 'show' : ''; ?>" aria-labelledby="heading-<?= $katId; ?>" data-bs-parent="#accordionKategori">
                  <div class="accordion-body p-0">
                    <div class="table-responsive">
                      <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light text-muted small">
                          <tr>
                            <th class="ps-4">Nama Barang</th>
                            <th>Kemasan / Satuan</th>
                            <th class="text-center">Total Terjual</th>
                            <th class="text-end">Total Omzet</th>
                            <th class="text-end">Total Modal</th>
                            <th class="text-end pe-4">Estimasi Laba</th>
                          </tr>
                        </thead>
                        <tbody>
                          <?php foreach ($kat['items'] as $item): ?>
                            <tr>
                              <td class="ps-4 fw-semibold text-dark">
                                <i class="bi bi-box-seam me-2 text-secondary"></i><?= htmlspecialchars($item['nama_barang']); ?>
                              </td>
                              <td>
                                <span class="badge bg-light text-dark border">
                                  <?= htmlspecialchars($item['nama_kemasan']); ?> (<?= htmlspecialchars($item['satuan']); ?>)
                                </span>
                              </td>
                              <td class="text-center font-monospace fw-bold"><?= number_format($item['total_qty']); ?></td>
                              <td class="text-end font-monospace">Rp <?= number_format($item['total_omzet'], 0, ',', '.'); ?></td>
                              <td class="text-end font-monospace text-muted">Rp <?= number_format($item['total_modal'], 0, ',', '.'); ?></td>
                              <td class="text-end pe-4 font-monospace fw-bold <?= $item['total_keuntungan'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                                Rp <?= number_format($item['total_keuntungan'], 0, ',', '.'); ?>
                              </td>
                            </tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php
}

// Jika request berasal dari HTMX, kirim HTML parsial saja
if ($isHtmx) {
    renderKontenLaporan($rekapKategori, $grandOmzet, $grandModal, $grandLaba, $grandQty, $tglMulai, $tglSelesai);
    exit;
}

// Request Biasa Browser: Tampilkan Lengkap dengan Header & Footer
require_once BASE_PATH . 'partials/header.php';
?>

<div class="container-fluid my-4 px-4">

  <!-- HEADER & FILTER -->
  <div class="row align-items-center mb-4">
    <div class="col-md-4">
      <h4 class="fw-bold mb-1"><i class="bi bi-grid-3x3-gap-fill text-primary me-2"></i>Laporan Per Kategori</h4>
      <p class="text-muted small mb-0">Analisis kontribusi omzet, modal, dan keuntungan bersih per kategori & barang.</p>
    </div>
    <div class="col-md-8">
      <form id="filterFormKategori"
            hx-get="laporan_kategori.php"
            hx-target="#laporan-kategori-container"
            hx-trigger="keyup changed delay:500ms from:input[name='keyword'], change, submit"
            hx-indicator="#loading-spinner"
            class="row g-2 justify-content-md-end align-items-center">

        <!-- Input Pencarian Nama Barang / Barcode -->
        <div class="col-auto">
          <div class="input-group input-group-sm">
            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
            <input type="text" 
                   name="keyword" 
                   class="form-control form-control-sm" 
                   placeholder="Cari Barang / Barcode..." 
                   value="<?= htmlspecialchars($keyword); ?>"
                   autocomplete="off">
          </div>
        </div>

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
    <div class="text-muted small mt-2">Sedang menghitung rekapitulasi kategori & item...</div>
  </div>

  <!-- KONTAINER UTAMA HASIL DATA -->
  <div id="laporan-kategori-container">
    <?php renderKontenLaporan($rekapKategori, $grandOmzet, $grandModal, $grandLaba, $grandQty, $tglMulai, $tglSelesai); ?>
  </div>

</div>

<?php require_once BASE_PATH . 'partials/footer.php'; ?>