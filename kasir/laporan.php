<?php
// kasir/laporan.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';

// Filter Tanggal (Default: Hari Ini)
$tglMulai   = $_GET['tgl_mulai'] ?? date('Y-m-d');
$tglSelesai = $_GET['tgl_selesai'] ?? date('Y-m-d');

try {
    // 1. Total Summary (Omzet, Modal, & Laba)
    $sqlSum = "SELECT 
                    COUNT(p.id) AS total_transaksi,
                    COALESCE(SUM(p.total_bersih), 0) AS total_omzet,
                    COALESCE(SUM(det.total_modal_transaksi), 0) AS total_modal,
                    COALESCE(SUM(p.total_bersih - det.total_modal_transaksi), 0) AS total_keuntungan
               FROM penjualan p
               LEFT JOIN (
                   SELECT 
                       penjualan_id,
                       SUM(COALESCE(harga_beli, 0) * COALESCE(qty, 0)) AS total_modal_transaksi
                   FROM penjualan_detail
                   GROUP BY penjualan_id
               ) det ON p.id = det.penjualan_id
               WHERE DATE(p.tanggal) BETWEEN ? AND ?";

    $stmtSum = $pdoBarang->prepare($sqlSum);
    $stmtSum->execute([$tglMulai, $tglSelesai]);
    $summary = $stmtSum->fetch(PDO::FETCH_ASSOC);

    // 2. Daftar Transaksi Penjualan + Keuntungan per Transaksi
    $sqlList = "SELECT 
                    p.*,
                    (SELECT COUNT(id) FROM penjualan_detail WHERE penjualan_id = p.id) AS item_count,
                    COALESCE(p.total_bersih - (
                        SELECT SUM(COALESCE(harga_beli, 0) * COALESCE(qty, 0)) 
                        FROM penjualan_detail 
                        WHERE penjualan_id = p.id
                    ), 0) AS untung_per_transaksi
                FROM penjualan p
                WHERE DATE(p.tanggal) BETWEEN ? AND ?
                ORDER BY p.id DESC";
    $stmtList = $pdoBarang->prepare($sqlList);
    $stmtList->execute([$tglMulai, $tglSelesai]);
    $transaksiList = $stmtList->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Error Database: " . $e->getMessage());
}

require_once BASE_PATH . 'partials/header.php';
?>

<div class="container-fluid my-4 px-4">
  
  <!-- HEADER & FILTER -->
  <div class="row align-items-center mb-4">
    <div class="col-md-6">
      <h4 class="fw-bold mb-1"><i class="bi bi-journal-text text-primary me-2"></i>Laporan Penjualan & Keuntungan</h4>
      <p class="text-muted small mb-0">Rekap omzet, total modal, dan estimasi keuntungan bersih (laba).</p>
    </div>
    <div class="col-md-6">
      <form method="GET" class="row g-2 justify-content-md-end">
        <div class="col-auto">
          <input type="date" name="tgl_mulai" class="form-control form-control-sm" value="<?= htmlspecialchars($tglMulai); ?>">
        </div>
        <div class="col-auto align-self-center">s/d</div>
        <div class="col-auto">
          <input type="date" name="tgl_selesai" class="form-control form-control-sm" value="<?= htmlspecialchars($tglSelesai); ?>">
        </div>
        <div class="col-auto">
          <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-filter me-1"></i> Filter</button>
          <a href="index.php" class="btn btn-sm btn-outline-secondary me-1"><i class="bi bi-arrow-left me-1"></i> Ke Kasir</a>
        </div>
      </form>
    </div>
  </div>

  <!-- CARD SUMMARY STATISTIK DENGAN KEUNTUNGAN -->
  <div class="row g-3 mb-4">
    
    <!-- CARD 1: OMZET -->
    <div class="col-md-3">
      <div class="card border-0 shadow-sm bg-primary text-white">
        <div class="card-body p-3">
          <small class="text-uppercase fw-semibold opacity-75">Total Omzet (Kotor)</small>
          <h3 class="fw-bold mb-0 font-monospace">Rp <?= number_format($summary['total_omzet'], 0, ',', '.'); ?></h3>
        </div>
      </div>
    </div>

    <!-- CARD 2: TOTAL MODAL -->
    <div class="col-md-3">
      <div class="card border-0 shadow-sm bg-secondary text-white">
        <div class="card-body p-3">
          <small class="text-uppercase fw-semibold opacity-75">Total Modal (HPP)</small>
          <h3 class="fw-bold mb-0 font-monospace">Rp <?= number_format($summary['total_modal'], 0, ',', '.'); ?></h3>
        </div>
      </div>
    </div>

    <!-- CARD 3: KEUNTUNGAN BERSIH (LABA) -->
    <div class="col-md-3">
      <div class="card border-0 shadow-sm bg-success text-white">
        <div class="card-body p-3">
          <small class="text-uppercase fw-semibold opacity-75"><i class="bi bi-cash-stack me-1"></i>Keuntungan (Laba)</small>
          <h3 class="fw-bold mb-0 font-monospace <?= $summary['total_keuntungan'] >= 0 ? 'text-warning' : 'text-danger'; ?>">
            Rp <?= number_format($summary['total_keuntungan'], 0, ',', '.'); ?>
          </h3>
        </div>
      </div>
    </div>

    <!-- CARD 4: TRANSAKSI -->
    <div class="col-md-3">
      <div class="card border-0 shadow-sm bg-dark text-white">
        <div class="card-body p-3">
          <small class="text-uppercase fw-semibold opacity-75">Total Struk</small>
          <h3 class="fw-bold mb-0 font-monospace"><?= number_format($summary['total_transaksi']); ?> <span class="fs-6 fw-normal">Transaksi</span></h3>
        </div>
      </div>
    </div>

  </div>

  <!-- TABEL RIWAYAT TRANSAKSI -->
  <div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3">
      <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Riwayat Transaksi Penjualan</h6>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Waktu & Faktur</th>
            <th class="text-center">Jumlah Item</th>
            <th class="text-end">Total Belanja</th>
            <th class="text-end text-success fw-bold">Est. Untung</th>
            <th class="text-end">Uang Bayar</th>
            <th class="text-end">Kembalian</th>
            <th class="text-center">Metode</th>
            <th class="text-center">#</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($transaksiList)): ?>
            <tr>
              <td colspan="8" class="text-center text-muted py-5">
                <i class="bi bi-receipt display-5 d-block mb-2 text-muted"></i>
                Tidak ada transaksi penjualan pada rentang tanggal ini.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($transaksiList as $row): ?>
              <tr>
                <td>
                  <strong class="text-dark d-block"><?= htmlspecialchars($row['no_faktur']); ?></strong>
                  <small class="text-muted"><i class="bi bi-calendar-event me-1"></i><?= date('d/m/Y H:i', strtotime($row['tanggal'])); ?></small>
                </td>
                <td class="text-center">
                  <span class="badge bg-light text-dark border"><?= $row['item_count']; ?> Jenis Item</span>
                </td>
                <td class="text-end font-monospace fw-bold text-dark">
                  Rp <?= number_format($row['total_bersih'], 0, ',', '.'); ?>
                </td>
                <td class="text-end font-monospace fw-bold <?= $row['untung_per_transaksi'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                  <?= $row['untung_per_transaksi'] >= 0 ? '+' : ''; ?>Rp <?= number_format($row['untung_per_transaksi'], 0, ',', '.'); ?>
                </td>
                <td class="text-end font-monospace">
                  Rp <?= number_format($row['bayar'], 0, ',', '.'); ?>
                </td>
                <td class="text-end font-monospace text-muted">
                  Rp <?= number_format($row['kembalian'], 0, ',', '.'); ?>
                </td>
                <td class="text-center">
                  <span class="badge bg-info text-dark"><?= htmlspecialchars($row['metode_bayar']); ?></span>
                </td>
                <td class="text-center">
                  <button type="button" class="btn btn-sm btn-outline-primary" onclick="lihatDetailStruk(<?= $row['id']; ?>)">
                    <i class="bi bi-eye-fill me-1"></i> Detail
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- MODAL DETAIL STRUK TRANSAKSI -->
<div class="modal fade" id="modalDetailStruk" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white py-2">
        <h6 class="modal-title fw-bold" id="detail_faktur_title">Detail Transaksi</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-3" id="detail_struk_body">
        <div class="text-center py-4"><span class="spinner-border text-primary"></span> Loading detail...</div>
      </div>
      <div class="modal-footer py-2">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<script>
function lihatDetailStruk(id) {
  const modal = new bootstrap.Modal(document.getElementById('modalDetailStruk'));
  const body = document.getElementById('detail_struk_body');
  
  body.innerHTML = '<div class="text-center py-4"><span class="spinner-border text-primary"></span> Loading detail...</div>';
  modal.show();

  fetch('api_detail_transaksi.php?id=' + id)
    .then(res => res.json())
    .then(res => {
      if (res.status === 'success') {
        document.getElementById('detail_faktur_title').innerText = 'Faktur: ' + res.header.no_faktur;
        
        let itemsHtml = '';
        let totalModalStruk = 0;

        res.details.forEach(d => {
          let modalItem = (parseFloat(d.harga_beli) || 0) * parseInt(d.qty);
          totalModalStruk += modalItem;

          itemsHtml += `
            <tr>
              <td>
                <div class="fw-bold text-dark">${d.nama_barang}</div>
                <small class="text-muted">${d.nama_kemasan} @ Rp ${Math.round(d.harga_jual).toLocaleString('id-ID')} (Modal: Rp ${Math.round(d.harga_beli).toLocaleString('id-ID')})</small>
              </td>
              <td class="text-center fw-bold">${d.qty} ${d.satuan}</td>
              <td class="text-end font-monospace fw-bold">Rp ${Math.round(d.subtotal).toLocaleString('id-ID')}</td>
            </tr>`;
        });

        let totalUntungStruk = parseFloat(res.header.total_bersih) - totalModalStruk;

        body.innerHTML = `
          <div class="text-center mb-3 border-bottom pb-2">
            <h5 class="fw-bold mb-0">KIOS MAFAZA</h5>
            <small class="text-muted d-block">${res.header.tanggal}</small>
          </div>
          <table class="table table-sm align-middle mb-3">
            <thead>
              <tr class="table-light">
                <th>Item</th>
                <th class="text-center">Qty</th>
                <th class="text-end">Subtotal</th>
              </tr>
            </thead>
            <tbody>${itemsHtml}</tbody>
          </table>
          <div class="p-2 bg-light rounded border font-monospace fs-6">
            <div class="d-flex justify-content-between mb-1">
              <span>Total Omzet:</span>
              <strong class="text-dark">Rp ${Math.round(res.header.total_bersih).toLocaleString('id-ID')}</strong>
            </div>
            <div class="d-flex justify-content-between mb-1">
              <span>Total Modal (HPP):</span>
              <strong class="text-secondary">Rp ${Math.round(totalModalStruk).toLocaleString('id-ID')}</strong>
            </div>
            <div class="d-flex justify-content-between mb-1 ${totalUntungStruk >= 0 ? 'text-success' : 'text-danger'} fw-bold">
              <span>Keuntungan (Laba):</span>
              <span>${totalUntungStruk >= 0 ? '+' : ''}Rp ${Math.round(totalUntungStruk).toLocaleString('id-ID')}</span>
            </div>
            <hr class="my-1">
            <div class="d-flex justify-content-between mb-1">
              <span>Bayar:</span>
              <span>Rp ${Math.round(res.header.bayar).toLocaleString('id-ID')}</span>
            </div>
            <div class="d-flex justify-content-between text-primary">
              <span>Kembalian:</span>
              <span>Rp ${Math.round(res.header.kembalian).toLocaleString('id-ID')}</span>
            </div>
          </div>`;
      } else {
        body.innerHTML = '<div class="alert alert-danger">' + res.message + '</div>';
      }
    });
}
</script>

<?php require_once BASE_PATH . 'partials/footer.php'; ?>