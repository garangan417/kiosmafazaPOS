<?php
// kulakan/index.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';
require_once BASE_PATH . 'database/query_kulakan.php';

// ============================================================
// HANDLER POST
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. TAMBAH ITEM
    if ($action === 'add_item') {
        $kemasanId = intval($_POST['kemasan_id'] ?? 0);
        $qty       = max(1, intval($_POST['qty'] ?? 1));
        $satuanPilihan = $_POST['satuan_pilihan'] ?? 'KEMASAN';
        
        if ($kemasanId > 0) {
            tambahItemKulakan($kemasanId, $qty, $satuanPilihan);
            $_SESSION['toast_success'] = "Item ditambahkan.";
        }
        header("Location: index.php"); exit;
    }

    // 2. TAMBAH BY BARCODE (Default masuk sebagai KEMASAN)
    if ($action === 'add_by_barcode') {
        $barcode = trim($_POST['barcode'] ?? '');
        $qty     = max(1, intval($_POST['qty'] ?? 1));
        
        if ($barcode !== '') {
            $stmt = $pdoBarang->prepare("SELECT barang_kemasan_id FROM barang_barcode WHERE barcode = ?");
            $stmt->execute([$barcode]);
            $kemasanId = (int) $stmt->fetchColumn();
            
            if ($kemasanId > 0) {
                tambahItemKulakan($kemasanId, $qty, 'KEMASAN');
                $_SESSION['toast_success'] = "Barcode ditambahkan ke keranjang.";
            } else {
                $_SESSION['toast_error'] = "Barcode tidak ditemukan.";
            }
        }
        header("Location: index.php"); exit;
    }

    // 3. UPDATE QTY
    if ($action === 'update_qty') {
        $key = $_POST['key'] ?? '';
        $qty = intval($_POST['qty'] ?? 0);
        updateQtyKulakan($key, $qty);
        header("Location: index.php"); exit;
    }

    // 4. HAPUS ITEM
    if ($action === 'remove_item') {
        $key = $_POST['key'] ?? '';
        hapusItemKulakan($key);
        header("Location: index.php"); exit;
    }

    // 5. CLEAR
    if ($action === 'clear_cart') {
        clearKeranjangKulakan();
        header("Location: index.php"); exit;
    }

    // 6. SIMPAN NOTA (RENCANA BELANJA)
    if ($action === 'simpan_nota') {
        $nama     = trim($_POST['nama_kulakan'] ?? '');
        $supplier = trim($_POST['supplier'] ?? '');
        $catatan  = trim($_POST['catatan'] ?? '');
        
        $res = simpanNotaKulakan($pdoBarang, $nama, $supplier, $catatan);
        if ($res['ok']) {
            $_SESSION['toast_success'] = "Rencana belanja {$res['no']} berhasil disimpan. Silakan belanja!";
            header("Location: riwayat.php"); exit;
        } else {
            $_SESSION['toast_error'] = "Gagal simpan: {$res['error']}";
            header("Location: index.php"); exit;
        }
    }
}

// ============================================================
// DATA UNTUK TAMPILAN
// ============================================================
$cart   = getKeranjangKulakanDetail($pdoBarang);
$search = trim($_GET['search'] ?? '');
$daftarBarang = [];

// Pencarian Barang (hanya yang punya harga beli > 0)
if ($search !== '') {
    $stmt = $pdoBarang->prepare("
        SELECT bk.id AS kemasan_id, b.nama_barang, k.nama_kategori, 
               bk.nama_kemasan, bk.satuan, COALESCE(bk.isi,1) AS isi,
               COALESCE(h.harga_beli,0) AS harga_beli
        FROM barang_kemasan bk
        JOIN barang b ON bk.barang_id = b.id
        JOIN kategori k ON b.kategori_id = k.id
        LEFT JOIN harga_barang h ON bk.id = h.barang_kemasan_id
        WHERE COALESCE(h.harga_beli, 0) > 0 
          AND (b.nama_barang LIKE ? OR bk.nama_kemasan LIKE ? OR k.nama_kategori LIKE ?)
        ORDER BY b.nama_barang ASC
        LIMIT 30
    ");
    $term = "%$search%";
    $stmt->execute([$term, $term, $term]);
    $daftarBarang = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once BASE_PATH . 'partials/header.php';
?>

<main class="container-fluid my-4 px-4 flex-grow-1">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="fw-bold mb-0"><i class="bi bi-cart-plus me-2 text-primary"></i>Rencana Belanja Kulakan</h4>
    <a href="riwayat.php" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-clock-history me-1"></i> Daftar Rencana
    </a>
  </div>

  <div class="row g-4">
    <!-- KOLOM KIRI: PENCARIAN -->
    <div class="col-lg-5">
      <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-dark text-white py-2">
          <h6 class="mb-0 fw-bold"><i class="bi bi-upc-scan me-2"></i>Scan Barcode</h6>
        </div>
        <div class="card-body">
          <form action="" method="POST" class="d-flex gap-2">
            <input type="hidden" name="action" value="add_by_barcode">
            <input type="text" name="barcode" class="form-control" placeholder="Scan barcode (otomatis PAK)..." autofocus autocomplete="off" required>
            <input type="number" name="qty" class="form-control" value="1" min="1" style="max-width: 80px;">
            <button class="btn btn-dark" type="submit"><i class="bi bi-plus-lg"></i></button>
          </form>
        </div>
      </div>

      <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-2">
          <form action="" method="GET" class="d-flex gap-2">
            <div class="input-group input-group-sm">
              <span class="input-group-text bg-light"><i class="bi bi-search text-muted"></i></span>
              <input type="text" name="search" class="form-control" placeholder="Cari nama barang..." value="<?= htmlspecialchars($search); ?>">
              <button class="btn btn-dark" type="submit">Cari</button>
            </div>
          </form>
        </div>
        <div class="card-body p-0" style="max-height: 500px; overflow-y: auto;">
          <?php if ($search === ''): ?>
            <div class="p-4 text-center text-muted small">Ketik nama barang untuk mencari.</div>
          <?php elseif (empty($daftarBarang)): ?>
            <div class="p-4 text-center text-muted small">Tidak ditemukan (atau belum diset harga belinya).</div>
          <?php else: ?>
            <table class="table table-sm table-hover mb-0 align-middle">
              <tbody>
                <?php foreach ($daftarBarang as $b): ?>
                  <tr>
                    <td>
                      <strong class="d-block small"><?= htmlspecialchars($b['nama_barang']); ?></strong>
                      <span class="badge bg-info-subtle text-info border border-info extra-small"><?= htmlspecialchars($b['nama_kemasan']); ?></span>
                      <span class="text-muted extra-small"><?= $b['isi']; ?> <?= htmlspecialchars($b['satuan']); ?></span>
                    </td>
                    <td class="text-end font-monospace small">Rp <?= number_format($b['harga_beli'], 0, ',', '.'); ?></td>
                    <td>
                      <form action="" method="POST" class="d-flex gap-1">
                        <input type="hidden" name="action" value="add_item">
                        <input type="hidden" name="kemasan_id" value="<?= $b['kemasan_id']; ?>">
                        <input type="number" name="qty" value="1" min="1" class="form-control form-control-sm" style="max-width: 50px;">
                        
                        <!-- Dropdown Pilih Satuan -->
                        <select name="satuan_pilihan" class="form-select form-select-sm" style="width: 75px;">
                            <option value="KEMASAN"><?= htmlspecialchars($b['nama_kemasan']); ?></option>
                            <option value="BASE"><?= htmlspecialchars($b['satuan']); ?></option>
                        </select>
                        
                        <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-plus-lg"></i></button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- KOLOM KANAN: KERANJANG -->
    <div class="col-lg-7">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
          <h5 class="mb-0 fw-bold"><i class="bi bi-basket me-2 text-primary"></i>Daftar Belanja</h5>
          <?php if (!empty($cart['items'])): ?>
            <form action="" method="POST" onsubmit="return confirm('Kosongkan?');">
              <input type="hidden" name="action" value="clear_cart">
              <button class="btn btn-sm btn-outline-danger">Kosongkan</button>
            </form>
          <?php endif; ?>
        </div>

        <div class="card-body p-0">
          <?php if (empty($cart['items'])): ?>
            <div class="p-5 text-center text-muted"><i class="bi bi-basket display-4 d-block mb-2"></i>Belum ada rencana belanja.</div>
          <?php else: ?>
            <table class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>Barang</th>
                  <th class="text-end">Estimasi Modal</th>
                  <th class="text-center" style="width: 160px;">Qty</th>
                  <th class="text-end">Subtotal</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                              <?php foreach ($cart['items'] as $it): ?>
                  <tr>
                    <td>
                      <strong class="d-block small"><?= htmlspecialchars($it['nama_barang']); ?></strong>
                      <span class="badge bg-info-subtle text-info border border-info extra-small"><?= htmlspecialchars($it['nama_kemasan']); ?></span>
                    </td>
                    <td class="text-end font-monospace small">
                      <!-- PERBAIKAN: Tampilkan harga sesuai satuan yang dipilih -->
                      Rp <?= number_format($it['harga_beli_tampil'], 0, ',', '.'); ?>
                      <div class="extra-small text-muted">per <?= htmlspecialchars($it['satuan_pilihan'] === 'BASE' ? $it['satuan'] : $it['nama_kemasan']); ?></div>
                    </td>
                    <td>
                      <form action="" method="POST" class="d-flex gap-1 justify-content-center align-items-center">
                        <input type="hidden" name="action" value="update_qty">
                        <input type="hidden" name="key" value="<?= htmlspecialchars($it['key']); ?>">
                        <input type="number" name="qty" value="<?= $it['qty']; ?>" min="0" class="form-control form-control-sm text-center" style="max-width: 60px;">
                        <span class="small text-muted" style="min-width: 60px; white-space: nowrap;">
                          <?= htmlspecialchars($it['satuan_pilihan'] === 'BASE' ? $it['satuan'] : $it['nama_kemasan']); ?>
                        </span>
                        <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-check2"></i></button>
                      </form>
                    </td>
                    <td class="text-end font-monospace fw-bold">Rp <?= number_format($it['subtotal_modal'], 0, ',', '.'); ?></td>
                    <td class="text-end">
                      <form action="" method="POST">
                        <input type="hidden" name="action" value="remove_item">
                        <input type="hidden" name="key" value="<?= htmlspecialchars($it['key']); ?>">
                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>

            <div class="p-3 border-top bg-light text-end">
              <div class="text-muted small">Total Estimasi Uang Dibawa</div>
              <div class="fw-bold fs-3 text-primary font-monospace">Rp <?= number_format($cart['total_modal'], 0, ',', '.'); ?></div>
            </div>

            <div class="p-3 border-top">
              <form action="" method="POST">
                <input type="hidden" name="action" value="simpan_nota">
                <div class="row g-2 mb-3">
                  <div class="col-md-6">
                    <label class="form-label small fw-semibold">Nama Rencana *</label>
                    <input type="text" name="nama_kulakan" class="form-control form-control-sm" placeholder="Misal: Belanja Surya 10 Feb" required>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label small fw-semibold">Supplier (Opsional)</label>
                    <input type="text" name="supplier" class="form-control form-control-sm" placeholder="Nama supplier">
                  </div>
                  <div class="col-12">
                    <label class="form-label small fw-semibold">Catatan (Opsional)</label>
                    <textarea name="catatan" class="form-control form-control-sm" rows="2"></textarea>
                  </div>
                </div>
                <button type="submit" class="btn btn-dark w-100 fw-bold">
                  <i class="bi bi-save me-1"></i> Simpan Rencana & Pergi Belanja
                </button>
              </form>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</main>

<script>
  const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 1500, timerProgressBar: true });
  <?php if (isset($_SESSION['toast_success'])): ?>
    Toast.fire({ icon: 'success', title: '<?= htmlspecialchars($_SESSION['toast_success']); ?>' });
    <?php unset($_SESSION['toast_success']); ?>
  <?php endif; ?>
  <?php if (isset($_SESSION['toast_error'])): ?>
    Toast.fire({ icon: 'error', title: '<?= htmlspecialchars($_SESSION['toast_error']); ?>' });
    <?php unset($_SESSION['toast_error']); ?>
  <?php endif; ?>
</script>

<?php require_once BASE_PATH . 'partials/footer.php'; ?>
