<?php
// kasir/pengaturan_stok.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';
require_once BASE_PATH . 'database/query_pos.php';

// Endpoint khusus HTMX saat form dikirim via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fiturStok = isset($_POST['fitur_stok']) && $_POST['fitur_stok'] === '1';
    $stokMinus = isset($_POST['izinkan_stok_minus']) && $_POST['izinkan_stok_minus'] === '1';

    $resStok  = setFiturStok($pdoBarang, $fiturStok);
    $resMinus = setIzinkanStokMinus($pdoBarang, $stokMinus);

    if ($resStok && $resMinus) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>Pengaturan stok berhasil diperbarui!
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
    } else {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>Gagal memperbarui pengaturan stok.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
    }
    exit;
}

// Ambil Status Pengaturan Terbaru
$cfgStok = getPengaturanStok($pdoBarang);

// Memuat Partial Header Utama
require_once BASE_PATH . 'partials/header.php';
?>

<div class="container py-4" style="max-width: 650px;">
    <div class="d-flex align-items-center mb-4">
        <a href="../stok/index.php" class="btn btn-outline-secondary me-3">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
        <h3 class="mb-0 fw-bold"><i class="bi bi-gear me-2"></i>Pengaturan Stok</h3>
    </div>

    <!-- Container Alert Respon dari HTMX -->
    <div id="settingAlert"></div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form id="formPengaturanStok"
                  hx-post="pengaturan_stok.php"
                  hx-target="#settingAlert"
                  hx-swap="innerHTML">

                <!-- Toggle Fitur Stok Utama -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="mb-1 fw-bold">Aktifkan Fitur Pelacakan Stok</h6>
                        <small class="text-muted d-block">
                            Jika dinonaktifkan, transaksi kasir tidak akan mengecek sisa stok maupun mencatat log mutasi.
                        </small>
                    </div>
                    <div class="form-check form-switch fs-4">
                        <input class="form-check-input" type="checkbox" role="switch" id="fitur_stok" name="fitur_stok" value="1" <?= $cfgStok['fitur_stok'] ? 'checked' : ''; ?>>
                    </div>
                </div>

                <hr class="my-4">

                <!-- Toggle Opsi Stok Minus -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h6 class="mb-1 fw-bold">Izinkan Penjualan Stok Minus</h6>
                        <small class="text-muted d-block">
                            Jika diaktifkan, transaksi tetap dapat diproses meskipun stok barang di database bernilai 0/habis.
                        </small>
                    </div>
                    <div class="form-check form-switch fs-4">
                        <input class="form-check-input" type="checkbox" role="switch" id="izinkan_stok_minus" name="izinkan_stok_minus" value="1" <?= $cfgStok['izinkan_stok_minus'] ? 'checked' : ''; ?>>
                    </div>
                </div>

                <div class="mt-4 pt-2">
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
                        <i class="bi bi-save me-1"></i> Simpan Perubahan
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . 'partials/footer.php'; ?>