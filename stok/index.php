<?php
// stok/index.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';
require_once BASE_PATH . 'database/query_pos.php';

// Ambil data barang + kemasan + rujukan isi
$sql = "SELECT 
            bk.id AS kemasan_id,
            b.nama_barang,
            bk.nama_kemasan,
            bk.satuan,
            COALESCE(bk.isi, 1) AS isi,
            COALESCE(bk.stok, 0) AS stok
        FROM barang_kemasan bk
        JOIN barang b ON bk.barang_id = b.id
        ORDER BY b.nama_barang ASC";
$listBarang = $pdoBarang->query($sql)->fetchAll();

require_once BASE_PATH . 'partials/header.php';
?>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0 fw-bold"><i class="bi bi-box-seam me-2"></i> Manajemen & Restok Barang</h4>
        <div class="d-flex gap-2">
            <a href="../kasir/pengaturan_stok.php" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-gear me-1"></i> Pengaturan Stok
            </a>
            <button class="btn btn-outline-secondary btn-sm" 
                    hx-get="api_stok.php?action=load_tabel" 
                    hx-target="#tblStokBody" 
                    hx-include="#inputCariStok"
                    hx-swap="innerHTML">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh Tabel
            </button>
        </div>
    </div>

    <div class="row g-3">
        <!-- Form Restok Barang -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-primary text-white font-weight-bold">
                    <i class="bi bi-plus-circle me-1"></i> Restok Barang (Stok Masuk)
                </div>
                <div class="card-body">
                    <form id="formRestok"
                          hx-post="api_stok.php?action=tambah_stok" 
                          hx-target="#tblStokBody" 
                          hx-include="#inputCariStok"
                          hx-swap="innerHTML">

                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Pilih Barang</label>
                            <select id="selectBarang" name="kemasan_id" class="form-select" onchange="updateOpsiSatuan()" required>
                                <option value="" data-isi="1" data-kemasan="" data-satuan="">-- Pilih Barang --</option>
                                <?php foreach ($listBarang as $b): ?>
                                    <option value="<?= $b['kemasan_id'] ?>" 
                                            data-isi="<?= $b['isi'] ?>" 
                                            data-kemasan="<?= htmlspecialchars($b['nama_kemasan']) ?>" 
                                            data-satuan="<?= htmlspecialchars($b['satuan']) ?>">
                                        <?= htmlspecialchars($b['nama_barang']) ?> (1 <?= htmlspecialchars($b['nama_kemasan']) ?> = <?= $b['isi'] ?> <?= htmlspecialchars($b['satuan']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-7">
                                <label class="form-label font-weight-bold">Jumlah Masuk</label>
                                <input type="number" id="inputQty" name="qty" class="form-control" min="1" placeholder="Contoh: 1" oninput="hitungPreviewStok()" required>
                            </div>
                            <div class="col-5">
                                <label class="form-label font-weight-bold">Satuan</label>
                                <select id="selectSatuan" name="opsi_satuan" class="form-select" onchange="hitungPreviewStok()">
                                    <option value="GROSIR">Kemasan</option>
                                    <option value="ECER">Ecer/Pcs</option>
                                </select>
                            </div>
                        </div>

                        <div id="boxPreview" class="alert alert-info py-2 px-3 small d-none">
                            <i class="bi bi-info-circle me-1"></i> Konversi: <span id="txtPreviewKonversi">0 Pcs</span>
                        </div>

                        <div class="mb-3">
                            <label class="form-label font-weight-bold">Keterangan / Catatan</label>
                            <input type="text" name="keterangan" class="form-control" placeholder="Contoh: Kulakan Toko / Restok Supplier">
                        </div>

                        <button type="submit" class="btn btn-success w-100 fw-semibold">
                            <i class="bi bi-save me-1"></i> Simpan Stok Masuk
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tabel Live Data Stok Barang -->
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-2">
                    <!-- Form Input Pencarian Realtime HTMX -->
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                        <input type="search" 
                               id="inputCariStok"
                               name="q" 
                               class="form-control" 
                               placeholder="Cari nama barang atau scan barcode..."
                               hx-get="api_stok.php?action=load_tabel" 
                               hx-trigger="keyup changed delay:300ms, search" 
                               hx-target="#tblStokBody"
                               hx-swap="innerHTML"
                               autofocus>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nama Barang</th>
                                    <th>Rincian Kemasan</th>
                                    <th class="text-center">Stok Akhir (Pcs)</th>
                                    <th class="text-end">Aksi Opname</th>
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
    </div>
</div>


<script>
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true
});

function fireToast(icon, title) {
    Toast.fire({ icon: icon, title: title });
}

document.body.addEventListener('htmx:afterRequest', function(evt) {
    if (evt.detail.elt.id === 'formRestok' && evt.detail.successful) {
        document.getElementById('formRestok').reset();
        document.getElementById('boxPreview').classList.add('d-none');
    }

    const triggerHeader = evt.detail.xhr.getResponseHeader('HX-Trigger');
    if (triggerHeader) {
        try {
            const triggers = JSON.parse(triggerHeader);
            if (triggers.showToast) {
                fireToast(triggers.showToast.icon, triggers.showToast.title);
            }
        } catch (e) {
            console.error('Error parsing HX-Trigger header:', e);
        }
    }
});

function updateOpsiSatuan() {
    const select = document.getElementById('selectBarang');
    const selected = select.options[select.selectedIndex];
    const isi = parseInt(selected.getAttribute('data-isi')) || 1;
    const kemasan = selected.getAttribute('data-kemasan') || 'Kemasan';
    const satuan = selected.getAttribute('data-satuan') || 'PCS';
    const selectSatuan = document.getElementById('selectSatuan');

    if (isi > 1) {
        selectSatuan.options[0].text = `${kemasan} (${isi} ${satuan})`;
        selectSatuan.options[1].text = `${satuan} (Ecer)`;
        selectSatuan.value = 'GROSIR';
    } else {
        selectSatuan.options[0].text = `${satuan} (Ecer)`;
        selectSatuan.options[1].text = `${satuan} (Ecer)`;
        selectSatuan.value = 'ECER';
    }
    hitungPreviewStok();
}

function hitungPreviewStok() {
    const select = document.getElementById('selectBarang');
    const selected = select.options[select.selectedIndex];
    const isi = parseInt(selected.getAttribute('data-isi')) || 1;
    const satuan = selected.getAttribute('data-satuan') || 'PCS';

    const qty = parseInt(document.getElementById('inputQty').value) || 0;
    const opsi = document.getElementById('selectSatuan').value;
    const box = document.getElementById('boxPreview');
    const txt = document.getElementById('txtPreviewKonversi');

    if (qty > 0 && selected.value !== '') {
        box.classList.remove('d-none');
        if (opsi === 'GROSIR' && isi > 1) {
            let totalPcs = qty * isi;
            txt.innerHTML = `${qty} Kemasan × ${isi} = <strong>${totalPcs} ${satuan}</strong> yang akan ditambahkan ke stok.`;
        } else {
            txt.innerHTML = `<strong>${qty} ${satuan}</strong> yang akan ditambahkan ke stok.`;
        }
    } else {
        box.classList.add('d-none');
    }
}

function setOpname(id, nama, stokSistemPcs, satuan) {
    const searchVal = document.getElementById('inputCariStok').value;
    Swal.fire({
        title: 'Stock Opname',
        html: `<strong>${nama}</strong><br><small class="text-muted">Stok sistem saat ini: ${stokSistemPcs} ${satuan}</small>`,
        input: 'number',
        inputLabel: `Masukkan jumlah stok fisik nyata (${satuan}):`,
        inputValue: stokSistemPcs,
        showCancelButton: true,
        confirmButtonText: 'Simpan Opname',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#0d6efd',
        inputValidator: (value) => {
            if (!value || value < 0) {
                return 'Jumlah stok fisik tidak boleh kosong atau minus!';
            }
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
</script>

<?php require_once BASE_PATH . 'partials/footer.php'; ?>