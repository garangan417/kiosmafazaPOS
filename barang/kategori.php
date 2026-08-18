<?php
// barang/kategori.php (atau kategori/index.php)
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';

// --- HANDLER HTMX: PROSES POST (Tambah, Edit, Hapus) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. TAMBAH KATEGORI
    if ($action === 'add') {
        $nama_kategori = mb_strtoupper(trim($_POST['nama_kategori'] ?? ''));

        if (!empty($nama_kategori)) {
            try {
                $stmt = $pdoBarang->prepare("INSERT INTO kategori (nama_kategori) VALUES (?)");
                $stmt->execute([$nama_kategori]);
                echo '<div class="alert alert-success alert-dismissible fade show shadow-sm mb-2" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>Kategori <strong>' . htmlspecialchars($nama_kategori) . '</strong> berhasil ditambahkan!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                      </div>';
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'UNIQUE constraint failed') !== false || strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    echo '<div class="alert alert-danger alert-dismissible fade show shadow-sm mb-2" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>Gagal: Kategori <strong>' . htmlspecialchars($nama_kategori) . '</strong> sudah ada!
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                          </div>';
                } else {
                    echo '<div class="alert alert-danger alert-dismissible fade show shadow-sm mb-2" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>Gagal menyimpan: ' . htmlspecialchars($e->getMessage()) . '
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                          </div>';
                }
            }
        } else {
            echo '<div class="alert alert-warning alert-dismissible fade show shadow-sm mb-2" role="alert">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>Nama kategori tidak boleh kosong!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                  </div>';
        }
    }

    // 2. EDIT KATEGORI
    elseif ($action === 'edit') {
        $id            = intval($_POST['id'] ?? 0);
        $nama_kategori = mb_strtoupper(trim($_POST['nama_kategori'] ?? ''));

        if ($id > 0 && !empty($nama_kategori)) {
            try {
                $stmt = $pdoBarang->prepare("UPDATE kategori SET nama_kategori = ? WHERE id = ?");
                $stmt->execute([$nama_kategori, $id]);
                echo '<div class="alert alert-success alert-dismissible fade show shadow-sm mb-2" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>Kategori berhasil diperbarui!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                      </div>';
            } catch (PDOException $e) {
                echo '<div class="alert alert-danger alert-dismissible fade show shadow-sm mb-2" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Gagal memperbarui: ' . htmlspecialchars($e->getMessage()) . '
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                      </div>';
            }
        }
    }

    // 3. HAPUS KATEGORI
    elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);

        if ($id > 0) {
            try {
                $stmt = $pdoBarang->prepare("DELETE FROM kategori WHERE id = ?");
                $stmt->execute([$id]);
                echo '<div class="alert alert-success alert-dismissible fade show shadow-sm mb-2" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i>Kategori berhasil dihapus!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                      </div>';
            } catch (PDOException $e) {
                echo '<div class="alert alert-danger alert-dismissible fade show shadow-sm mb-2" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>Gagal menghapus! Kategori ini masih digunakan oleh beberapa produk.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                      </div>';
            }
        }
    }
    exit;
}

// --- HANDLER HTMX: RENDER ULANG TABEL KATEGORI ---
if (isset($_GET['action']) && $_GET['action'] === 'load_tabel') {
    $sql = "SELECT k.id, k.nama_kategori, k.created_at, COUNT(b.id) AS total_barang
            FROM kategori k
            LEFT JOIN barang b ON k.id = b.kategori_id
            GROUP BY k.id
            ORDER BY k.nama_kategori ASC";
    $listKategori = $pdoBarang->query($sql)->fetchAll();

    if (empty($listKategori)): ?>
        <tr>
            <td colspan="4" class="text-center text-muted py-4">Belum ada kategori terdaftar.</td>
        </tr>
    <?php else: 
        $no = 1; 
        foreach ($listKategori as $kat): ?>
        <tr>
            <td><?= $no++; ?></td>
            <td><strong class="text-dark"><?= htmlspecialchars($kat['nama_kategori']); ?></strong></td>
            <td class="text-center">
                <span class="badge bg-info-subtle text-info border border-info px-2 py-1">
                    <?= $kat['total_barang']; ?> Produk
                </span>
            </td>
            <td class="text-center">
                <!-- Tombol Edit -->
                <button class="btn btn-sm btn-outline-warning me-1" 
                        data-bs-toggle="modal" 
                        data-bs-target="#modalEdit<?= $kat['id']; ?>" 
                        title="Edit Kategori">
                    <i class="bi bi-pencil"></i>
                </button>

                <!-- Tombol Hapus -->
                <button class="btn btn-sm btn-outline-danger" 
                        data-bs-toggle="modal" 
                        data-bs-target="#modalHapus<?= $kat['id']; ?>" 
                        title="Hapus Kategori">
                    <i class="bi bi-trash"></i>
                </button>

                <!-- MODAL EDIT KATEGORI -->
                <div class="modal fade" id="modalEdit<?= $kat['id']; ?>" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered modal-sm">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h6 class="modal-title fw-bold">Edit Kategori</h6>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form hx-post="kategori.php" 
                                  hx-target="#globalToastContainer" 
                                  hx-swap="innerHTML"
                                  data-bs-dismiss="modal">
                                <div class="modal-body text-start">
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="id" value="<?= $kat['id']; ?>">
                                    <div class="mb-3">
                                        <label class="form-label small fw-semibold">Nama Kategori</label>
                                        <input type="text" name="nama_kategori" class="form-control text-uppercase" value="<?= htmlspecialchars($kat['nama_kategori']); ?>" required>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="submit" class="btn btn-warning btn-sm w-100 fw-bold">Update Kategori</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- MODAL HAPUS KATEGORI -->
                <div class="modal fade" id="modalHapus<?= $kat['id']; ?>" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered modal-sm">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h6 class="modal-title fw-bold">Konfirmasi Hapus</h6>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <form hx-post="kategori.php" 
                                  hx-target="#globalToastContainer" 
                                  hx-swap="innerHTML"
                                  data-bs-dismiss="modal">
                                <div class="modal-body text-start">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $kat['id']; ?>">
                                    <p class="small text-muted mb-0">Hapus kategori <strong><?= htmlspecialchars($kat['nama_kategori']); ?></strong>?</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                                    <button type="submit" class="btn btn-danger btn-sm">Ya, Hapus</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
        <?php endforeach;
    endif;
    exit;
}

// Header dipanggil DI SINI (Sebelum HTML Utama Dimuat)
require_once BASE_PATH . 'partials/header.php';
?>

<main class="container-fluid my-4 px-4 flex-grow-1">
    <div class="row g-4">
        
        <!-- FORM TAMBAH KATEGORI -->
        <div class="col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white py-3">
                    <h5 class="card-title mb-0 fw-bold"><i class="bi bi-tags me-2"></i>Tambah Kategori</h5>
                </div>
                <div class="card-body">
                    <form id="formTambahKategori"
                          hx-post="kategori.php" 
                          hx-target="#globalToastContainer" 
                          hx-swap="innerHTML">
                        <input type="hidden" name="action" value="add">

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Nama Kategori</label>
                            <input type="text" id="inputNamaKategori" name="nama_kategori" class="form-control text-uppercase" placeholder="Contoh: MINUMAN DINGIN" required autofocus>
                            <span class="form-text text-muted extra-small">Nama kategori akan otomatis tersimpan dalam huruf kapital.</span>
                        </div>

                        <button type="submit" class="btn btn-dark w-100 fw-bold">
                            <i class="bi bi-plus-lg me-1"></i> Simpan Kategori
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- TABEL DAFTAR KATEGORI -->
        <div class="col-md-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fw-bold text-dark">Daftar Kategori Barang</h5>
                    <button class="btn btn-sm btn-outline-secondary" 
                            hx-get="kategori.php?action=load_tabel" 
                            hx-target="#tblKategoriBody" 
                            hx-swap="innerHTML">
                        <i class="bi bi-arrow-clockwise me-1"></i> Refresh
                    </button>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">No</th>
                                <th>Nama Kategori</th>
                                <th class="text-center">Total Item</th>
                                <th class="text-center" style="width: 140px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="tblKategoriBody" 
                               hx-get="kategori.php?action=load_tabel" 
                               hx-trigger="load, reloadTabel from:body" 
                               hx-swap="innerHTML">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
// Auto reload tabel & reset form pasca submit HTMX
document.body.addEventListener('htmx:afterRequest', function(evt) {
    if (evt.detail.successful) {
        // Trigger reload tabel jika request datang dari form tambah/edit/hapus
        htmx.trigger('#tblKategoriBody', 'reloadTabel');
        
        // Reset input form tambah
        if (evt.detail.elt.id === 'formTambahKategori') {
            document.getElementById('inputNamaKategori').value = '';
        }
    }
});
</script>

<?php require_once BASE_PATH . 'partials/footer.php'; ?>