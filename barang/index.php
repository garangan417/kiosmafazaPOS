<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';
require_once BASE_PATH . 'database/query_barang.php';

// TANGKAP PARAMETER URL (SEARCH & BARCODE REDIRECT)
$barcodeFromUrl = isset($_GET['barcode']) ? trim($_GET['barcode']) : '';
$searchQuery    = isset($_GET['q']) ? trim($_GET['q']) : '';

// PROSES POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. TAMBAH BARANG BARU
    if ($action === 'add_barang') {
        $kategori_id  = intval($_POST['kategori_id'] ?? 0);
        $nama_barang  = mb_strtoupper(trim($_POST['nama_barang'] ?? ''));
        $nama_kemasan = mb_strtoupper(trim($_POST['nama_kemasan'] ?? ''));
        $satuan       = mb_strtoupper(trim($_POST['satuan'] ?? 'PCS'));
        $isi          = max(1, intval($_POST['isi'] ?? 1));
        $barcode      = trim($_POST['barcode'] ?? '');

        // VALIDASI BARCODE GANDA
        if (!empty($barcode) && isBarcodeExists($pdoBarang, $barcode)) {
            $_SESSION['toast_error'] = "Gagal: Barcode '{$barcode}' sudah terdaftar pada produk/kemasan lain!";
        } elseif ($kategori_id > 0 && !empty($nama_barang) && !empty($nama_kemasan)) {
            try {
                $pdoBarang->beginTransaction();

                $stmtB = $pdoBarang->prepare("INSERT INTO barang (kategori_id, nama_barang) VALUES (?, ?)");
                $stmtB->execute([$kategori_id, $nama_barang]);
                $barang_id = $pdoBarang->lastInsertId();

                $stmtK = $pdoBarang->prepare("INSERT INTO barang_kemasan (barang_id, nama_kemasan, satuan, isi) VALUES (?, ?, ?, ?)");
                $stmtK->execute([$barang_id, $nama_kemasan, $satuan, $isi]);
                $kemasan_id = $pdoBarang->lastInsertId();

                if (!empty($barcode)) {
                    $stmtBC = $pdoBarang->prepare("INSERT INTO barang_barcode (barang_kemasan_id, barcode) VALUES (?, ?)");
                    $stmtBC->execute([$kemasan_id, $barcode]);
                }

                $pdoBarang->commit();
                $_SESSION['toast_success'] = "Barang baru berhasil ditambahkan!";
            } catch (PDOException $e) {
                $pdoBarang->rollBack();
                $_SESSION['toast_error'] = "Gagal menyimpan data: " . $e->getMessage();
            }
        } else {
            $_SESSION['toast_error'] = "Kategori, Nama Barang, dan Nama Kemasan wajib diisi!";
        }
    }

    // 2. EDIT MASTER BARANG (TERMASUK UBAH KATEGORI)
    elseif ($action === 'edit_barang') {
        $barang_id   = intval($_POST['barang_id'] ?? 0);
        $kategori_id = intval($_POST['kategori_id'] ?? 0);
        $nama_barang = mb_strtoupper(trim($_POST['nama_barang'] ?? ''));

        if ($barang_id > 0 && $kategori_id > 0 && !empty($nama_barang)) {
            try {
                $stmt = $pdoBarang->prepare("UPDATE barang SET kategori_id = ?, nama_barang = ? WHERE id = ?");
                $stmt->execute([$kategori_id, $nama_barang, $barang_id]);
                $_SESSION['toast_success'] = "Data produk berhasil diperbarui!";
            } catch (PDOException $e) {
                $_SESSION['toast_error'] = "Gagal memperbarui produk: " . $e->getMessage();
            }
        } else {
            $_SESSION['toast_error'] = "Kategori dan Nama Barang wajib diisi!";
        }
    }

    // 3. EDIT KEMASAN BARANG
    elseif ($action === 'edit_kemasan') {
        $kemasan_id   = intval($_POST['kemasan_id'] ?? 0);
        $nama_kemasan = mb_strtoupper(trim($_POST['nama_kemasan'] ?? ''));
        $satuan       = mb_strtoupper(trim($_POST['satuan'] ?? 'PCS'));
        $isi          = max(1, intval($_POST['isi'] ?? 1));

        if ($kemasan_id > 0 && !empty($nama_kemasan) && !empty($satuan)) {
            try {
                $stmt = $pdoBarang->prepare("UPDATE barang_kemasan SET nama_kemasan = ?, satuan = ?, isi = ? WHERE id = ?");
                $stmt->execute([$nama_kemasan, $satuan, $isi, $kemasan_id]);
                $_SESSION['toast_success'] = "Detail kemasan berhasil diperbarui!";
            } catch (PDOException $e) {
                $_SESSION['toast_error'] = "Gagal memperbarui kemasan: " . $e->getMessage();
            }
        } else {
            $_SESSION['toast_error'] = "Nama Kemasan dan Satuan wajib diisi!";
        }
    }

    // 4. TAMBAH VARIAN KEMASAN BARU
    elseif ($action === 'add_kemasan') {
        $barang_id    = intval($_POST['barang_id'] ?? 0);
        $nama_kemasan = mb_strtoupper(trim($_POST['nama_kemasan'] ?? ''));
        $satuan       = mb_strtoupper(trim($_POST['satuan'] ?? 'PCS'));
        $isi          = max(1, intval($_POST['isi'] ?? 1));
        $barcode      = trim($_POST['barcode'] ?? '');

        if (!empty($barcode) && isBarcodeExists($pdoBarang, $barcode)) {
            $_SESSION['toast_error'] = "Gagal: Barcode '{$barcode}' sudah terdaftar pada produk/kemasan lain!";
        } elseif ($barang_id > 0 && !empty($nama_kemasan)) {
            try {
                $pdoBarang->beginTransaction();

                $stmtK = $pdoBarang->prepare("INSERT INTO barang_kemasan (barang_id, nama_kemasan, satuan, isi) VALUES (?, ?, ?, ?)");
                $stmtK->execute([$barang_id, $nama_kemasan, $satuan, $isi]);
                $kemasan_id = $pdoBarang->lastInsertId();

                if (!empty($barcode)) {
                    $stmtBC = $pdoBarang->prepare("INSERT INTO barang_barcode (barang_kemasan_id, barcode) VALUES (?, ?)");
                    $stmtBC->execute([$kemasan_id, $barcode]);
                }

                $pdoBarang->commit();
                $_SESSION['toast_success'] = "Kemasan baru berhasil ditambahkan!";
            } catch (PDOException $e) {
                $pdoBarang->rollBack();
                $_SESSION['toast_error'] = "Gagal menambah kemasan: " . $e->getMessage();
            }
        }
    }

    // 5. TAMBAH BARCODE BARU KE KEMASAN ADA
    elseif ($action === 'add_barcode') {
        $kemasan_id = intval($_POST['kemasan_id'] ?? 0);
        $barcode    = trim($_POST['barcode'] ?? '');

        if (empty($barcode)) {
            $_SESSION['toast_error'] = "Gagal: Barcode tidak boleh kosong!";
        } elseif (isBarcodeExists($pdoBarang, $barcode)) {
            $_SESSION['toast_error'] = "Gagal: Barcode '{$barcode}' sudah terdaftar pada produk/kemasan lain!";
        } elseif ($kemasan_id > 0) {
            try {
                $stmtBC = $pdoBarang->prepare("INSERT INTO barang_barcode (barang_kemasan_id, barcode) VALUES (?, ?)");
                $stmtBC->execute([$kemasan_id, $barcode]);
                $_SESSION['toast_success'] = "Barcode baru berhasil dikaitkan!";
            } catch (PDOException $e) {
                $_SESSION['toast_error'] = "Gagal menyimpan barcode: " . $e->getMessage();
            }
        }
    }

    // 6. HAPUS KEMASAN SPESIFIK
    elseif ($action === 'delete_kemasan') {
        $kemasan_id = intval($_POST['kemasan_id'] ?? 0);
        if ($kemasan_id > 0) {
            try {
                $stmt = $pdoBarang->prepare("DELETE FROM barang_kemasan WHERE id = ?");
                $stmt->execute([$kemasan_id]);
                $_SESSION['toast_success'] = "Kemasan berhasil dihapus!";
            } catch (PDOException $e) {
                $_SESSION['toast_error'] = "Gagal menghapus kemasan: " . $e->getMessage();
            }
        }
    }

    // 7. HAPUS MASTER BARANG TOTAL
    elseif ($action === 'delete_barang') {
        $barang_id = intval($_POST['barang_id'] ?? 0);
        if ($barang_id > 0) {
            try {
                $stmt = $pdoBarang->prepare("DELETE FROM barang WHERE id = ?");
                $stmt->execute([$barang_id]);
                $_SESSION['toast_success'] = "Master Barang beserta seluruh kemasannya berhasil dihapus!";
            } catch (PDOException $e) {
                $_SESSION['toast_error'] = "Gagal menghapus master barang: " . $e->getMessage();
            }
        }
    }

    // Redirect dengan mempertahankan parameter query URL
    $queryParams = [];
    if (!empty($barcodeFromUrl)) $queryParams['barcode'] = $barcodeFromUrl;
    if (!empty($searchQuery)) $queryParams['q'] = $searchQuery;
    if (isset($_GET['page'])) $queryParams['page'] = intval($_GET['page']);

    $queryString = !empty($queryParams) ? '?' . http_build_query($queryParams) : '';
    header("Location: " . $_SERVER['PHP_SELF'] . $queryString);
    exit;
}

// AMBIL DATA KATEGORI UNTUK DROPDOWN
$listKategori = $pdoBarang->query("SELECT * FROM kategori ORDER BY nama_kategori ASC")->fetchAll();

// AMBIL SEMUA DAFTAR BARANG
$allBarang = getDaftarBarangLengkap($pdoBarang);

// FITUR PENCARIAN (Berdasarkan Nama Barang, Kemasan, atau Barcode)
if (!empty($searchQuery)) {
    $searchLower = mb_strtolower($searchQuery);
    $allBarang = array_filter($allBarang, function ($item) use ($searchLower) {
        $namaBarang  = mb_strtolower($item['nama_barang'] ?? '');
        $namaKemasan = mb_strtolower($item['nama_kemasan'] ?? '');
        $listBarcode = mb_strtolower($item['list_barcode'] ?? '');

        return str_contains($namaBarang, $searchLower)
            || str_contains($namaKemasan, $searchLower)
            || str_contains($listBarcode, $searchLower);
    });

    // Re-index array setelah difilter
    $allBarang = array_values($allBarang);
}

// LOGIKA PAGINASI
$totalItems   = count($allBarang);
$limit        = 50; // Jumlah item per halaman
$totalPages   = max(1, ceil($totalItems / $limit));
$currentPage  = max(1, min($totalPages, intval($_GET['page'] ?? 1)));
$offset       = ($currentPage - 1) * $limit;

// Potong data array sesuai halaman saat ini
$daftarBarang = array_slice($allBarang, $offset, $limit);

// Helper Query String untuk Pagination Link
$buildPageUrl = function ($page) use ($searchQuery, $barcodeFromUrl) {
    $params = ['page' => $page];
    if (!empty($searchQuery)) $params['q'] = $searchQuery;
    if (!empty($barcodeFromUrl)) $params['barcode'] = $barcodeFromUrl;
    return '?' . http_build_query($params);
};

require_once BASE_PATH . 'partials/header.php';
?>

<main class="container-fluid my-4 px-4 flex-grow-1">

  <div class="row g-4">
    
    <!-- FORM TAMBAH BARANG UTAMA -->
    <div class="col-lg-4">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-dark text-white py-3">
          <h5 class="card-title mb-0 fw-bold"><i class="bi bi-box-seam me-2"></i>Tambah Barang Baru</h5>
        </div>
        <div class="card-body">
          <form action="" method="POST">
            <input type="hidden" name="action" value="add_barang">

            <div class="mb-3">
              <div class="d-flex justify-content-between align-items-center mb-1">
                <label class="form-label small fw-semibold mb-0">Kategori Barang</label>
                <a href="<?= BASE_URL; ?>barang/kategori.php" class="extra-small text-decoration-none text-primary fw-semibold">+ Kelola Kategori</a>
              </div>
              <select name="kategori_id" class="form-select" required>
                <option value="">-- Pilih Kategori --</option>
                <?php foreach ($listKategori as $kat): ?>
                  <option value="<?= $kat['id']; ?>"><?= htmlspecialchars($kat['nama_kategori']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-semibold">Nama Barang Utama</label>
              <input type="text" name="nama_barang" class="form-control text-uppercase" placeholder="Contoh: AQUA AIR MINERAL" required>
            </div>

            <hr class="my-3 text-muted">
            <h6 class="fw-bold text-secondary mb-3 small">Spesifikasi Kemasan Pertama:</h6>

            <div class="row g-2 mb-2">
              <div class="col-7">
                <label class="form-label small fw-semibold">Nama Kemasan</label>
                <input type="text" name="nama_kemasan" class="form-control text-uppercase" placeholder="Contoh: DUS / ECERAN" required>
              </div>
              <div class="col-5">
                <label class="form-label small fw-semibold">Satuan</label>
                <input type="text" name="satuan" class="form-control text-uppercase" placeholder="PCS / DUS" value="PCS" required>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-semibold">Isi per Kemasan</label>
              <input type="number" name="isi" class="form-control" value="1" min="1" required>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-semibold">Barcode (Opsional)</label>
              <input type="text" 
                     name="barcode" 
                     class="form-control" 
                     placeholder="Scan / Ketik Barcode" 
                     value="<?= htmlspecialchars($barcodeFromUrl); ?>" 
                     <?= !empty($barcodeFromUrl) ? 'autofocus' : ''; ?>>
            </div>

            <button type="submit" class="btn btn-dark w-100 fw-bold">
              <i class="bi bi-plus-lg me-1"></i> Simpan Barang Baru
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- TABEL DAFTAR BARANG -->
    <div class="col-lg-8">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
          <div class="row g-2 align-items-center">
            <div class="col-md-5 d-flex align-items-center gap-2">
              <h5 class="card-title mb-0 fw-bold text-dark">Daftar Barang & Kemasan</h5>
              <span class="badge bg-secondary"><?= $totalItems; ?> Varian Item</span>
            </div>

            <!-- FORM PENCARIAN -->
            <div class="col-md-7">
              <form action="" method="GET" class="d-flex gap-2">
                <?php if (!empty($barcodeFromUrl)): ?>
                  <input type="hidden" name="barcode" value="<?= htmlspecialchars($barcodeFromUrl); ?>">
                <?php endif; ?>
                
                <div class="input-group input-group-sm">
                  <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                  <input type="text" 
                         name="q" 
                         class="form-control border-start-0 ps-0" 
                         placeholder="Cari nama barang atau barcode..." 
                         value="<?= htmlspecialchars($searchQuery); ?>">
                  <?php if (!empty($searchQuery)): ?>
                    <a href="<?= $_SERVER['PHP_SELF'] . (!empty($barcodeFromUrl) ? '?barcode=' . urlencode($barcodeFromUrl) : ''); ?>" 
                       class="btn btn-outline-secondary" 
                       title="Reset Pencarian">
                      <i class="bi bi-x-lg"></i>
                    </a>
                  <?php endif; ?>
                  <button type="submit" class="btn btn-dark">Cari</button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Nama Barang & Kemasan</th>
                <th>Kategori</th>
                <th>Isi / Satuan</th>
                <th>Daftar Barcode</th>
                <th class="text-center" style="width: 170px;">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($daftarBarang)): ?>
                <tr>
                  <td colspan="5" class="text-center text-muted py-4">
                    <?php if (!empty($searchQuery)): ?>
                      <i class="bi bi-search d-block fs-3 mb-2 text-secondary"></i>
                      Tidak ditemukan barang dengan kata kunci "<strong><?= htmlspecialchars($searchQuery); ?></strong>".
                    <?php else: ?>
                      Belum ada data barang.
                    <?php endif; ?>
                  </td>
                </tr>
              <?php else: ?>
                <?php foreach ($daftarBarang as $row): ?>
                  <tr>
                    <td>
                      <strong class="text-dark d-block"><?= htmlspecialchars($row['nama_barang']); ?></strong>
                      <span class="badge bg-info-subtle text-info border border-info extra-small">
                        <?= htmlspecialchars($row['nama_kemasan']); ?>
                      </span>
                    </td>
                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['nama_kategori']); ?></span></td>
                    <td><span class="fw-semibold"><?= $row['isi']; ?></span> <?= htmlspecialchars($row['satuan']); ?></td>
                    <td>
                      <?php if (!empty($row['list_barcode'])): ?>
                        <?php foreach (explode(', ', $row['list_barcode']) as $bc): ?>
                          <span class="badge bg-dark font-monospace mb-1 d-inline-block"><?= htmlspecialchars($bc); ?></span>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <span class="text-muted small italic">Tanpa Barcode</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-center">
                      <!-- 1. Tombol Tambah Barcode -->
                      <button class="btn btn-sm btn-outline-secondary mb-1" 
                              data-bs-toggle="modal" 
                              data-bs-target="#modalBarcode<?= $row['kemasan_id']; ?>" 
                              title="Tambah Barcode Baru">
                        <i class="bi bi-qr-code-scan"></i>
                      </button>

                      <!-- 2. Tombol Tambah Varian Kemasan -->
                      <button class="btn btn-sm btn-outline-primary mb-1" 
                              data-bs-toggle="modal" 
                              data-bs-target="#modalKemasan<?= $row['barang_id']; ?>" 
                              title="Tambah Varian Kemasan Baru">
                        <i class="bi bi-box-arrow-in-down"></i>
                      </button>

                      <!-- 3. Dropdown Menu Edit & Hapus -->
                      <div class="dropdown d-inline-block mb-1">
                        <button class="btn btn-sm btn-outline-dark dropdown-toggle" type="button" data-bs-toggle="dropdown">
                          <i class="bi bi-pencil-square"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm small">
                          <li>
                            <button class="dropdown-item text-dark" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#modalEditBarang<?= $row['barang_id']; ?>">
                              <i class="bi bi-pencil me-2 text-warning"></i>Edit Produk & Kategori
                            </button>
                          </li>
                          <li>
                            <button class="dropdown-item text-dark" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#modalEditKemasan<?= $row['kemasan_id']; ?>">
                              <i class="bi bi-box-seam me-2 text-info"></i>Edit Kemasan Ini
                            </button>
                          </li>
                          <li><hr class="dropdown-divider"></li>
                          <li>
                            <button class="dropdown-item text-danger" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#modalDelKemasan<?= $row['kemasan_id']; ?>">
                              <i class="bi bi-x-circle me-2"></i>Hapus Kemasan Ini
                            </button>
                          </li>
                          <li>
                            <button class="dropdown-item text-danger fw-bold" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#modalDelBarang<?= $row['barang_id']; ?>">
                              <i class="bi bi-trash-fill me-2"></i>Hapus Master Produk
                            </button>
                          </li>
                        </ul>
                      </div>
                    </td>
                  </tr>

                  <!-- MODAL EDIT MASTER PRODUK & KATEGORI -->
                  <div class="modal fade" id="modalEditBarang<?= $row['barang_id']; ?>" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                      <div class="modal-content">
                        <div class="modal-header bg-dark text-white">
                          <h6 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit Produk & Kategori</h6>
                          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form action="" method="POST">
                          <div class="modal-body text-start">
                            <input type="hidden" name="action" value="edit_barang">
                            <input type="hidden" name="barang_id" value="<?= $row['barang_id']; ?>">

                            <div class="mb-3">
                              <label class="form-label small fw-semibold">Kategori Produk</label>
                              <select name="kategori_id" class="form-select" required>
                                <option value="">-- Pilih Kategori --</option>
                                <?php foreach ($listKategori as $kat): ?>
                                  <option value="<?= $kat['id']; ?>" <?= ($kat['id'] == $row['kategori_id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($kat['nama_kategori']); ?>
                                  </option>
                                <?php endforeach; ?>
                              </select>
                            </div>

                            <div class="mb-3">
                              <label class="form-label small fw-semibold">Nama Barang Utama</label>
                              <input type="text" name="nama_barang" class="form-control text-uppercase" value="<?= htmlspecialchars($row['nama_barang']); ?>" required>
                            </div>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-dark btn-sm">Simpan Perubahan</button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>

                  <!-- MODAL EDIT KEMASAN INI -->
                  <div class="modal fade" id="modalEditKemasan<?= $row['kemasan_id']; ?>" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                      <div class="modal-content">
                        <div class="modal-header bg-info text-white">
                          <h6 class="modal-title fw-bold"><i class="bi bi-box-seam me-2"></i>Edit Varian Kemasan</h6>
                          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form action="" method="POST">
                          <div class="modal-body text-start">
                            <input type="hidden" name="action" value="edit_kemasan">
                            <input type="hidden" name="kemasan_id" value="<?= $row['kemasan_id']; ?>">

                            <div class="row g-2 mb-3">
                              <div class="col-7">
                                <label class="form-label small fw-semibold">Nama Kemasan</label>
                                <input type="text" name="nama_kemasan" class="form-control text-uppercase" value="<?= htmlspecialchars($row['nama_kemasan']); ?>" required>
                              </div>
                              <div class="col-5">
                                <label class="form-label small fw-semibold">Satuan</label>
                                <input type="text" name="satuan" class="form-control text-uppercase" value="<?= htmlspecialchars($row['satuan']); ?>" required>
                              </div>
                            </div>

                            <div class="mb-3">
                              <label class="form-label small fw-semibold">Isi per Kemasan</label>
                              <input type="number" name="isi" class="form-control" value="<?= $row['isi']; ?>" min="1" required>
                            </div>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-info text-white btn-sm">Simpan Kemasan</button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>

                  <!-- MODAL TAMBAH BARCODE -->
                  <div class="modal fade" id="modalBarcode<?= $row['kemasan_id']; ?>" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered modal-sm">
                      <div class="modal-content">
                        <div class="modal-header">
                          <h6 class="modal-title fw-bold">Tambah Barcode Baru</h6>
                          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form action="" method="POST">
                          <div class="modal-body text-start">
                            <input type="hidden" name="action" value="add_barcode">
                            <input type="hidden" name="kemasan_id" value="<?= $row['kemasan_id']; ?>">
                            <p class="small text-muted mb-2">Item: <strong><?= htmlspecialchars($row['nama_barang'] . ' (' . $row['nama_kemasan'] . ')'); ?></strong></p>
                            <input type="text" name="barcode" class="form-control" placeholder="Scan / Ketik Barcode" required autofocus>
                          </div>
                          <div class="modal-footer">
                            <button type="submit" class="btn btn-dark btn-sm w-100">Simpan Barcode</button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>

                  <!-- MODAL TAMBAH KEMASAN/VARIAN -->
                  <div class="modal fade" id="modalKemasan<?= $row['barang_id']; ?>" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                      <div class="modal-content">
                        <div class="modal-header">
                          <h6 class="modal-title fw-bold">Tambah Varian Kemasan Baru</h6>
                          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form action="" method="POST">
                          <div class="modal-body text-start">
                            <input type="hidden" name="action" value="add_kemasan">
                            <input type="hidden" name="barang_id" value="<?= $row['barang_id']; ?>">
                            <p class="small text-muted mb-3">Untuk Barang: <strong><?= htmlspecialchars($row['nama_barang']); ?></strong></p>

                            <div class="row g-2 mb-2">
                              <div class="col-7">
                                <label class="form-label small fw-semibold">Nama Kemasan</label>
                                <input type="text" name="nama_kemasan" class="form-control text-uppercase" placeholder="Contoh: DUS ISI 12" required>
                              </div>
                              <div class="col-5">
                                <label class="form-label small fw-semibold">Satuan</label>
                                <input type="text" name="satuan" class="form-control text-uppercase" placeholder="DUS / PCS" required>
                              </div>
                            </div>

                            <div class="mb-3">
                              <label class="form-label small fw-semibold">Isi per Kemasan</label>
                              <input type="number" name="isi" class="form-control" value="1" min="1" required>
                            </div>

                            <div class="mb-3">
                              <label class="form-label small fw-semibold">Barcode Baru (Opsional)</label>
                              <input type="text" name="barcode" class="form-control" placeholder="Scan Barcode">
                            </div>
                          </div>
                          <div class="modal-footer">
                            <button type="submit" class="btn btn-primary btn-sm w-100">Simpan Kemasan Baru</button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>

                  <!-- MODAL HAPUS KEMASAN INI -->
                  <div class="modal fade" id="modalDelKemasan<?= $row['kemasan_id']; ?>" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered modal-sm">
                      <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                          <h6 class="modal-title fw-bold">Hapus Varian Kemasan</h6>
                          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form action="" method="POST">
                          <div class="modal-body text-start">
                            <input type="hidden" name="action" value="delete_kemasan">
                            <input type="hidden" name="kemasan_id" value="<?= $row['kemasan_id']; ?>">
                            <p class="small text-muted mb-0">Hapus kemasan <strong><?= htmlspecialchars($row['nama_barang'] . ' - ' . $row['nama_kemasan']); ?></strong>?</p>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-danger btn-sm">Ya, Hapus</button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>

                  <!-- MODAL HAPUS MASTER PRODUK -->
                  <div class="modal fade" id="modalDelBarang<?= $row['barang_id']; ?>" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered modal-sm">
                      <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                          <h6 class="modal-title fw-bold">Hapus Master Produk</h6>
                          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <form action="" method="POST">
                          <div class="modal-body text-start">
                            <input type="hidden" name="action" value="delete_barang">
                            <input type="hidden" name="barang_id" value="<?= $row['barang_id']; ?>">
                            <p class="small text-muted mb-0">Hapus produk <strong><?= htmlspecialchars($row['nama_barang']); ?></strong> beserta <strong>SELURUH kemasan & barcodenya</strong>?</p>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                            <button type="submit" class="btn btn-danger btn-sm">Ya, Hapus Semua</button>
                          </div>
                        </form>
                      </div>
                    </div>
                  </div>

                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- NAVIGASI PAGINATION -->
        <?php if ($totalPages > 1): ?>
          <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center">
            <span class="small text-muted">
              Menampilkan <strong><?= $totalItems > 0 ? $offset + 1 : 0; ?></strong> - <strong><?= min($offset + $limit, $totalItems); ?></strong> dari <strong><?= $totalItems; ?></strong> item
            </span>
            <nav aria-label="Page navigation">
              <ul class="pagination pagination-sm mb-0">
                <!-- Tombol Previous -->
                <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : ''; ?>">
                  <a class="page-link" href="<?= $buildPageUrl($currentPage - 1); ?>">
                    &laquo; Prev
                  </a>
                </li>

                <!-- Angka Halaman -->
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                  <li class="page-item <?= ($i === $currentPage) ? 'active' : ''; ?>">
                    <a class="page-link" href="<?= $buildPageUrl($i); ?>">
                      <?= $i; ?>
                    </a>
                  </li>
                <?php endfor; ?>

                <!-- Tombol Next -->
                <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : ''; ?>">
                  <a class="page-link" href="<?= $buildPageUrl($currentPage + 1); ?>">
                    Next &raquo;
                  </a>
                </li>
              </ul>
            </nav>
          </div>
        <?php endif; ?>

      </div>
    </div>

  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true
  });

  <?php if (isset($_SESSION['toast_success'])): ?>
    Toast.fire({
      icon: 'success',
      title: '<?= htmlspecialchars($_SESSION['toast_success']); ?>'
    });
    <?php unset($_SESSION['toast_success']); ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['toast_error'])): ?>
    Toast.fire({
      icon: 'error',
      title: '<?= htmlspecialchars($_SESSION['toast_error']); ?>'
    });
    <?php unset($_SESSION['toast_error']); ?>
  <?php endif; ?>
</script>

<?php require_once BASE_PATH . 'partials/footer.php'; ?>