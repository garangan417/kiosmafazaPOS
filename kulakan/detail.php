<?php
// kulakan/detail.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';
require_once BASE_PATH . 'database/query_kulakan.php';

$id = intval($_GET['id'] ?? 0);
$kulakan = $id > 0 ? getKulakanById($pdoBarang, $id) : null;

if (!$kulakan) {
    $_SESSION['toast_error'] = "Kulakan tidak ditemukan.";
    header("Location: riwayat.php");
    exit;
}

$items = getKulakanDetailItems($pdoBarang, $id);

require_once BASE_PATH . 'partials/header.php';
?>

<main class="container my-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><i class="bi bi-receipt me-2"></i>Detail Kulakan</h4>
    <a href="riwayat.php" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i> Kembali
    </a>
  </div>

  <div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <div class="small text-muted">No. Kulakan</div>
          <code><?= htmlspecialchars($kulakan['no_kulakan']); ?></code>
          <div class="small text-muted mt-2">Nama</div>
          <strong><?= htmlspecialchars($kulakan['nama_kulakan']); ?></strong>
          <?php if (!empty($kulakan['supplier'])): ?>
            <div class="small text-muted mt-2">Supplier</div>
            <span><?= htmlspecialchars($kulakan['supplier']); ?></span>
          <?php endif; ?>
        </div>
        <div class="col-md-6 text-md-end">
          <div class="small text-muted">Status</div>
          <span class="badge bg-<?= $kulakan['status'] === 'SELESAI' ? 'success' : ($kulakan['status'] === 'DRAFT' ? 'warning text-dark' : 'danger'); ?> fs-6">
            <?= htmlspecialchars($kulakan['status']); ?>
          </span>
          <div class="small text-muted mt-2">Total Modal</div>
          <div class="fw-bold fs-4 text-primary font-monospace">
            Rp <?= number_format((float) $kulakan['total_modal'], 0, ',', '.'); ?>
          </div>
          <div class="small text-muted">Dibuat: <?= date('d M Y H:i', strtotime($kulakan['created_at'])); ?></div>
          <?php if (!empty($kulakan['realisasi_at'])): ?>
            <div class="small text-success">Direalisasi: <?= date('d M Y H:i', strtotime($kulakan['realisasi_at'])); ?></div>
          <?php endif; ?>
        </div>
        <?php if (!empty($kulakan['catatan'])): ?>
          <div class="col-12">
            <div class="small text-muted">Catatan</div>
            <div class="p-2 bg-light rounded border small"><?= nl2br(htmlspecialchars($kulakan['catatan'])); ?></div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3">
      <h6 class="mb-0 fw-bold">Daftar Item (<?= count($items); ?>)</h6>
    </div>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Barang</th>
            <th class="text-center">Qty</th>
            <th class="text-center">Realisasi</th>
            <th class="text-end">Modal/Kemasan</th>
            <th class="text-end">Subtotal</th>
            <th class="text-end">Estimasi Profit</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $it): ?>
            <tr>
              <td>
                <strong class="d-block small"><?= htmlspecialchars($it['nama_barang']); ?></strong>
                <span class="badge bg-info-subtle text-info border border-info extra-small"><?= htmlspecialchars($it['nama_kemasan']); ?></span>
                <span class="text-muted extra-small"><?= (int) $it['isi']; ?> <?= htmlspecialchars($it['satuan']); ?></span>
              </td>
              <td class="text-center"><?= (int) $it['qty']; ?></td>
              <td class="text-center">
                <?php if ((int) $it['qty_realisasi'] > 0): ?>
                  <span class="badge bg-success"><?= (int) $it['qty_realisasi']; ?></span>
                <?php else: ?>
                  <span class="text-muted small">-</span>
                <?php endif; ?>
              </td>
              <td class="text-end font-monospace small">Rp <?= number_format((float) $it['harga_beli'], 0, ',', '.'); ?></td>
              <td class="text-end font-monospace fw-bold">Rp <?= number_format((float) $it['subtotal_modal'], 0, ',', '.'); ?></td>
              <td class="text-end font-monospace small <?= $it['estimasi_profit'] >= 0 ? 'text-success' : 'text-danger'; ?>">
                <?= $it['estimasi_profit'] >= 0 ? '+' : '-'; ?>Rp <?= number_format(abs((float) $it['estimasi_profit']), 0, ',', '.'); ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</main>

<?php require_once BASE_PATH . 'partials/footer.php'; ?>
