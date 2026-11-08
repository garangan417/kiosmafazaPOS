<?php
// kasir/pengaturan_stok.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';
require_once BASE_PATH . 'database/query_pos.php';

$pesan = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fiturStok = isset($_POST['fitur_stok']);
    
    if (setFiturStok($pdoBarang, $fiturStok)) {
        $pesan = '<div class="alert alert-success shadow-sm"><i class="bi bi-check-circle-fill me-2"></i>Pengaturan stok berhasil diperbarui!</div>';
    } else {
        $pesan = '<div class="alert alert-danger shadow-sm"><i class="bi bi-exclamation-triangle-fill me-2"></i>Gagal memperbarui pengaturan.</div>';
    }
}

$cfgStok = getPengaturanStok($pdoBarang);

require_once BASE_PATH . 'partials/header.php';
?>

<div class="container py-4" style="max-width: 650px;">
    <div class="d-flex align-items-center mb-4">
        <a href="../stok/index.php" class="btn btn-outline-secondary me-3">
            <i class="bi bi-arrow-left"></i> Kembali
        </a>
        <h3 class="mb-0 fw-bold"><i class="bi bi-gear me-2"></i>Pengaturan Stok</h3>
    </div>

    <?= $pesan; ?>

    <div class="card shadow-sm border-0">
        <div class="card-body p-4">
            <form method="POST">
                <div class="p-3 bg-light rounded border mb-4">
                    <div class="form-check form-switch d-flex justify-content-between align-items-center ps-0">
                        <div>
                            <label class="form-check-label fw-bold d-block" for="fitur_stok">Fitur Pelacakan Stok Otomatis</label>
                            <small class="text-muted d-block">
                                Jika <strong>Aktif</strong>, stok barang akan berkurang otomatis saat terjadi penjualan di kasir (menggunakan Stok V2).<br>
                                Jika <strong>Nonaktif</strong>, transaksi kasir tetap lancar tanpa memotong stok.
                            </small>
                        </div>
                        <input class="form-check-input ms-3" type="checkbox" role="switch" id="fitur_stok" name="fitur_stok" <?= $cfgStok['fitur_stok'] ? 'checked' : ''; ?> style="width: 3em; height: 1.5em; cursor: pointer;">
                    </div>
                </div>

                <!-- Info Tambahan -->
                <div class="alert alert-info border-info d-flex gap-2">
                    <i class="bi bi-info-circle-fill fs-5"></i>
                    <div class="small">
                        <strong>Catatan Sistem V2:</strong> Opsi "Izinkan Stok Minus" sudah dihapus. Sistem sekarang secara otomatis menolak transaksi jika stok tidak mencukupi, untuk menjaga akurasi data.
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
