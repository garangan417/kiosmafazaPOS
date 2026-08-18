<?php
// kasir/pengaturan.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';
require_once BASE_PATH . 'database/query_pos.php';

$pesan = '';

// Proses Simpan Pengaturan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fiturStok = isset($_POST['fitur_stok']) ? true : false;
    if (setFiturStok($pdoBarang, $fiturStok)) {
        $pesan = '<div class="alert alert-success">Pengaturan berhasil diperbarui!</div>';
    } else {
        $pesan = '<div class="alert alert-danger">Gagal memperbarui pengaturan.</div>';
    }
}

$statusStok = isFiturStokAktif($pdoBarang);

require_once BASE_PATH . 'partials/header.php';
?>

<div class="container my-4" style="max-width: 600px;">
  <div class="card shadow-sm border-0">
    <div class="card-header bg-dark text-white py-3">
      <h5 class="mb-0 fw-bold"><i class="bi bi-sliders me-2"></i>Pengaturan Sistem Kasir</h5>
    </div>
    <div class="card-body p-4">
      <?= $pesan; ?>

      <form method="POST">
        <div class="p-3 bg-light rounded border mb-4">
          <div class="form-check form-switch d-flex justify-content-between align-items-center ps-0">
            <div>
              <label class="form-check-label fw-bold d-block" for="fitur_stok">Fitur Pelacakan Stok Otomatis</label>
              <small class="text-muted d-block">
                Jika <strong>Aktif</strong>, stok barang akan berkurang otomatis saat terjadi penjualan.<br>
                Jika <strong>Nonaktif</strong>, transaksi kasir tetap lancar tanpa terpengaruh stok.
              </small>
            </div>
            <input class="form-check-input ms-3" type="checkbox" role="switch" id="fitur_stok" name="fitur_stok" <?= $statusStok ? 'checked' : ''; ?> style="width: 3em; height: 1.5em; cursor: pointer;">
          </div>
        </div>

        <div class="d-flex justify-content-between">
          <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Kembali ke Kasir</a>
          <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-save me-1"></i> Simpan Pengaturan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once BASE_PATH . 'partials/footer.php'; ?>