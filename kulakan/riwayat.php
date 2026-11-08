<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

// kulakan/riwayat.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';
require_once BASE_PATH . 'database/query_kulakan.php';

// ============================================================
// AMBIL DATA LANGSUNG DARI DATABASE
// ============================================================
$stmt = $pdoBarang->query("
    SELECT k.*, 
           (SELECT COUNT(*) FROM kulakan_detail WHERE kulakan_id = k.id) AS total_item 
    FROM kulakan k 
    ORDER BY k.created_at DESC
");
$daftar = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once BASE_PATH . 'partials/header.php';
?>

<main class="container-fluid my-4 px-4 flex-grow-1">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Daftar Rencana Belanja</h4>
    <a href="index.php" class="btn btn-dark btn-sm">
      <i class="bi bi-plus-lg me-1"></i> Buat Rencana Baru
    </a>
  </div>

  <div class="card shadow-sm border-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>No. Nota</th>
            <th>Nama / Supplier</th>
            <th class="text-center">Item</th>
            <th class="text-end">Total Estimasi</th>
            <th class="text-center">Status</th>
            <th>Tanggal</th>
            <th class="text-center" style="width: 200px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($daftar)): ?>
            <tr>
              <td colspan="7" class="text-center text-muted py-5">
                <i class="bi bi-inbox display-6 d-block mb-2 text-secondary"></i>
                Belum ada daftar belanja.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($daftar as $k): ?>
              <tr>
                <td><code class="small"><?= htmlspecialchars($k['no_kulakan']); ?></code></td>
                <td>
                  <strong class="d-block small"><?= htmlspecialchars($k['nama_kulakan']); ?></strong>
                  <?php if (!empty($k['supplier'])): ?>
                    <span class="text-muted extra-small"><?= htmlspecialchars($k['supplier']); ?></span>
                  <?php endif; ?>
                </td>
                <td class="text-center"><span class="badge bg-secondary"><?= (int) $k['total_item']; ?></span></td>
                <td class="text-end font-monospace fw-bold">
                  Rp <?= number_format((float) $k['total_modal'], 0, ',', '.'); ?>
                </td>
                <td class="text-center">
                  <?php if ($k['status'] === 'BELUM_DATANG'): ?>
                    <span class="badge bg-warning text-dark">BELUM DATANG</span>
                  <?php elseif ($k['status'] === 'SELESAI'): ?>
                    <span class="badge bg-success">SELESAI</span>
                  <?php else: ?>
                    <span class="badge bg-danger">BATAL</span>
                  <?php endif; ?>
                </td>
                <td class="small text-muted">
                  <?= date('d M Y H:i', strtotime($k['created_at'])); ?>
                </td>
                <td class="text-center">
                  <a href="detail.php?id=<?= (int) $k['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Lihat Detail">
                    <i class="bi bi-eye"></i>
                  </a>
                  <?php if ($k['status'] === 'BELUM_DATANG'): ?>
                    <a href="input_hasil.php?id=<?= (int) $k['id']; ?>" class="btn btn-sm btn-primary fw-bold">
                      <i class="bi bi-box-arrow-in-down me-1"></i> Input Hasil
                    </a>
                  <?php endif; ?>
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
