<?php
// stok/index.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';
require_once BASE_PATH . 'database/query_pos.php';
require_once BASE_PATH . 'database/query_stok_v2.php';

// Ambil data barang + kemasan + stok V2
$listBarang = getDaftarStokV2($pdoBarang, '');

require_once BASE_PATH . 'partials/header.php';
?>

<div class="container-fluid py-3 px-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="m-0 fw-bold"><i class="bi bi-box-seam me-2 text-primary"></i>Pemantauan Stok</h4>
            <p class="text-muted small mb-0">Pantau sisa stok fisik secara real-time. Untuk menambah stok, gunakan menu Kulakan.</p>
        </div>
        <div class="d-flex flex-wrap gap-2 mt-3 mt-md-0">
            <a href="<?= BASE_URL; ?>stok/kartu_stok.php" class="btn btn-primary btn-sm fw-bold">
                <i class="bi bi-clock-history me-1"></i> Kartu Stok
            </a>
            <a href="<?= BASE_URL; ?>stok/hubungkan.php" class="btn btn-outline-dark btn-sm fw-bold">
                <i class="bi bi-link-45deg me-1"></i> Hubungkan Barang
            </a>
            <a href="<?= BASE_URL; ?>kulakan/index.php" class="btn btn-success btn-sm fw-bold">
                <i class="bi bi-cart-plus me-1"></i> Buka Kulakan (Restok)
            </a>
            <a href="../kasir/pengaturan_stok.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-gear me-1"></i> Pengaturan
            </a>
            <button class="btn btn-outline-primary btn-sm" 
                    hx-get="api_stok.php?action=load_tabel&page=1" 
                    hx-target="#tblStokBody" 
                    hx-include="#inputCariStok"
                    hx-swap="innerHTML">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Tabel Live Data Stok Barang -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-ul me-1"></i> Daftar Stok Barang</h6>
                </div>
                <div class="col-md-6 mt-2 mt-md-0">
                    <!-- Form Input Pencarian Realtime HTMX -->
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                        <input type="search" 
                               id="inputCariStok"
                               name="q" 
                               class="form-control" 
                               placeholder="Cari nama barang atau scan barcode..."
                               hx-get="api_stok.php?action=load_tabel&page=1" 
                               hx-trigger="keyup changed delay:300ms, search" 
                               hx-target="#tblStokBody"
                               hx-swap="innerHTML"
                               autofocus>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 30%;">Nama Barang</th>
                            <th style="width: 25%;">Rincian Kemasan</th>
                            <th class="text-center" style="width: 25%;">Sisa Stok Saat Ini</th>
                            <th class="text-end" style="width: 20%;">Aksi Penyesuaian</th>
                        </tr>
                    </thead>
                    <tbody id="tblStokBody" 
                           hx-get="api_stok.php?action=load_tabel" 
                           hx-trigger="load, reloadStok from:body" 
                           hx-include="#inputCariStok"
                           hx-swap="innerHTML">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
const Toast = Swal.mixin({
    toast: true, position: 'top-end', showConfirmButton: false, timer: 3000, timerProgressBar: true
});

// Listener untuk HTMX setelah request
document.body.addEventListener('htmx:afterRequest', function(evt) {
    const triggerHeader = evt.detail.xhr.getResponseHeader('HX-Trigger');
    if (triggerHeader) {
        try {
            const triggers = JSON.parse(triggerHeader);
            if (triggers.showToast) {
                Toast.fire({ icon: triggers.showToast.icon, title: triggers.showToast.title });
            }
        } catch (e) {}
    }
});

// Modal Opname
function setOpname(id, nama, stokSistemPcs, satuan) {
    const searchVal = document.getElementById('inputCariStok').value;
    Swal.fire({
        title: 'Stock Opname',
        html: `<strong>${nama}</strong><br><small class="text-muted">Stok sistem saat ini: <strong>${stokSistemPcs} ${satuan}</strong></small>`,
        input: 'number',
        inputLabel: `Masukkan jumlah stok fisik nyata (${satuan}):`,
        inputValue: stokSistemPcs,
        showCancelButton: true,
        confirmButtonText: 'Simpan Opname',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#0d6efd',
        inputValidator: (value) => {
            if (!value || value < 0) return 'Jumlah tidak boleh kosong atau minus!';
        }
    }).then((result) => {
        if (result.isConfirmed) {
            htmx.ajax('POST', 'api_stok.php?action=opname', {
                target: '#tblStokBody',
                swap: 'innerHTML',
                values: { 
                    kemasan_id: id, 
                    stok_fisik: parseInt(result.value),
                    q: searchVal
                }
            });
        }
    });
}

// Fungsi Minta Stok dari Gudang (Jembatan)
function mintaStok(kemasanId, namaBarang) {
    Swal.fire({
        title: 'Minta Stok dari Gudang?',
        text: "Sistem akan memindahkan 1 unit dari Gudang ke Etalase " + namaBarang,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        confirmButtonText: 'Ya, Minta Stok!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('api_stok.php?action=minta_stok', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'kemasan_id=' + kemasanId
            })
            .then(res => res.json())
            .then(res => {
                if (res.status === 'success') {
                    Toast.fire({ icon: 'success', title: res.message });
                    htmx.trigger('#tblStokBody', 'reloadStok');
                } else {
                    Swal.fire('Gagal!', res.message, 'error');
                }
            })
            .catch(err => {
                Swal.fire('Error!', 'Terjadi kesalahan jaringan.', 'error');
            });
        }
    });
}
</script>

<?php require_once BASE_PATH . 'partials/footer.php'; ?>
