<!-- utang/_area_utang.php -->
<div id="area-utang">
  <?php if (!empty($successMsg)): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm mb-3" role="alert">
      <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($successMsg); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if (!empty($errorMsg)): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-3" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($errorMsg); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <div class="card shadow-sm border-0 p-3">
    <h5 class="card-title mb-3 fw-bold">Riwayat Utang / Pembayaran (50 Terbaru)</h5>
    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>Tanggal</th>
            <th>Nama Pelanggan</th>
            <th>Keterangan</th>
            <th class="text-end">Nominal</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($riwayatUtang)): ?>
            <tr>
              <td colspan="4" class="text-center text-muted py-3">Belum ada riwayat utang/pembayaran.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($riwayatUtang as $row): ?>
              <tr>
                <td class="small"><?= date('d/m/Y H:i', strtotime($row['created_at'])); ?></td>
                <td class="fw-bold"><?= htmlspecialchars($row['nama']); ?></td>
                <td class="small">
                  <span class="badge bg-<?= $row['tipe'] === 'utang' ? 'danger' : 'success'; ?> me-1">
                    <?= strtoupper($row['tipe']); ?>
                  </span>
                  <?= htmlspecialchars($row['keterangan'] ?: '-'); ?>
                </td>
                <td class="text-end fw-bold text-<?= $row['tipe'] === 'utang' ? 'danger' : 'success'; ?>">
                  <?= $row['tipe'] === 'utang' ? '+' : '-'; ?> <?= formatRupiah($row['nominal']); ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>