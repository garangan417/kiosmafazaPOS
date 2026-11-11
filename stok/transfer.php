<?php
// stok/transfer.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';
require_once BASE_PATH . 'database/query_stok_v2.php';

$pesan = '';

// ============================================================
// PROSES TRANSFER STOK
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $kemasanSumber = intval($_POST['kemasan_sumber'] ?? 0);
    $kemasanTujuan = intval($_POST['kemasan_tujuan'] ?? 0);
    $qtySumber     = intval($_POST['qty_sumber'] ?? 1);
    $isiKonversi   = intval($_POST['isi_konversi'] ?? 1);

    if ($kemasanSumber > 0 && $kemasanTujuan > 0 && $qtySumber > 0) {
        $res = transferStokManual($pdoBarang, $kemasanSumber, $kemasanTujuan, $qtySumber, $isiKonversi);
        if ($res['ok']) {
            $_SESSION['toast_success'] = "Transfer berhasil! Stok telah dipindahkan.";
            header("Location: transfer.php");
            exit;
        } else {
            $_SESSION['toast_error'] = "Gagal transfer: " . $res['error'];
            header("Location: transfer.php");
            exit;
        }
    } else {
        $_SESSION['toast_error'] = "Data form tidak lengkap!";
        header("Location: transfer.php");
        exit;
    }
}

// ============================================================
// AMBIL DATA UNTUK DROPDOWN
// ============================================================

// 1. Cari barang yang punya stok (sebagai sumber)
$stmtSumber = $pdoBarang->query("
    SELECT bk.id AS kemasan_id, b.nama_barang, bk.nama_kemasan, bk.satuan, 
           COALESCE(bs.stok_base, 0) AS stok_base
    FROM barang_kemasan bk
    JOIN barang b ON bk.barang_id = b.id
    LEFT JOIN barang_stok bs ON b.id = bs.barang_id
    WHERE COALESCE(bs.stok_base, 0) > 0
    ORDER BY b.nama_barang ASC
");
$listSumber = $stmtSumber->fetchAll(PDO::FETCH_ASSOC);

// 2. Ambil semua barang (sebagai tujuan)
$stmtTujuan = $pdoBarang->query("
    SELECT bk.id AS kemasan_id, b.nama_barang, bk.nama_kemasan, bk.satuan, bk.isi
    FROM barang_kemasan bk
    JOIN barang b ON bk.barang_id = b.id
    ORDER BY b.nama_barang ASC
");
$listTujuan = $stmtTujuan->fetchAll(PDO::FETCH_ASSOC);

require_once BASE_PATH . 'partials/header.php';
?>

<main class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-arrow-left-right me-2 text-primary"></i>Transfer Stok Manual</h4>
      <p class="text-muted small mb-0">Pindahkan stok dari Gudang (Sumber) ke Etalase (Tujuan).</p>
    </div>
    <a href="<?= BASE_URL; ?>stok/index.php" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i> Kembali ke Stok
    </a>
  </div>

  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-dark text-white py-3">
          <h6 class="mb-0 fw-bold"><i class="bi bi-box-arrow-in-right me-2"></i>Form Transfer</h6>
        </div>
        <div class="card-body p-4">
          <form action="" method="POST" id="formTransfer">
            
            <!-- Pilih Sumber -->
            <div class="mb-3">
              <label class="form-label fw-bold">1. Pilih Barang Sumber (Gudang / Toko A)</label>
              <select name="kemasan_sumber" id="kemasan_sumber" class="form-select" required onchange="updateSumberInfo()">
                <option value="">-- Pilih Barang Sumber --</option>
                <?php foreach ($listSumber as $s): ?>
                  <option value="<?= $s['kemasan_id']; ?>" 
                          data-stok="<?= $s['stok_base']; ?>" 
                          data-satuan="<?= htmlspecialchars($s['satuan']); ?>"
                          data-nama="<?= htmlspecialchars($s['nama_barang'] . ' (' . $s['nama_kemasan'] . ')'); ?>">
                    <?= htmlspecialchars($s['nama_barang']); ?> (<?= htmlspecialchars($s['nama_kemasan']); ?>) - Sisa: <?= number_format($s['stok_base'], 0, ',', '.'); ?> <?= htmlspecialchars($s['satuan']); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="extra-small text-muted mt-1" id="infoSumber"></div>
            </div>

            <!-- Input Qty Sumber -->
            <div class="mb-3">
              <label class="form-label fw-bold">2. Jumlah yang Dipindahkan (dari Sumber)</label>
              <input type="number" name="qty_sumber" class="form-control" value="1" min="1" required>
              <div class="extra-small text-muted mt-1">Contoh: Ketik 1 untuk memindahkan 1 Bungkus.</div>
            </div>

            <!-- Pilih Tujuan -->
            <div class="mb-3">
              <label class="form-label fw-bold">3. Pilih Barang Tujuan (Etalase / Toko B)</label>
              <select name="kemasan_tujuan" id="kemasan_tujuan" class="form-select" required onchange="updateTujuanInfo()">
                <option value="">-- Pilih Barang Tujuan --</option>
                <?php foreach ($listTujuan as $t): ?>
                  <option value="<?= $t['kemasan_id']; ?>" 
                          data-isi="<?= $t['isi']; ?>"
                          data-satuan="<?= htmlspecialchars($t['satuan']); ?>"
                          data-nama="<?= htmlspecialchars($t['nama_barang'] . ' (' . $t['nama_kemasan'] . ')'); ?>">
                    <?= htmlspecialchars($t['nama_barang']); ?> (<?= htmlspecialchars($t['nama_kemasan']); ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Hidden Input untuk Konversi -->
            <input type="hidden" name="isi_konversi" id="isi_konversi" value="1">

            <!-- Info Preview Konversi -->
            <div id="boxPreview" class="alert alert-info py-2 px-3 small d-none">
              <i class="bi bi-info-circle me-1"></i> Konversi: <span id="txtPreview">1 Bungkus = 12 Batang</span>
            </div>

            <hr class="my-4">

            <button type="submit" class="btn btn-primary w-100 fw-bold" onclick="return confirm('Proses pemindahan stok ini?');">
              <i class="bi bi-arrow-left-right me-1"></i> Proses Transfer
            </button>
          </form>
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

function updateSumberInfo() {
    const select = document.getElementById('kemasan_sumber');
    const selected = select.options[select.selectedIndex];
    const info = document.getElementById('infoSumber');
    if (selected.value) {
        info.innerHTML = `Stok tersedia: <strong>${selected.getAttribute('data-stok')} ${selected.getAttribute('data-satuan')}</strong>`;
    } else {
        info.innerHTML = '';
    }
}

function updateTujuanInfo() {
    const sumber = document.getElementById('kemasan_sumber');
    const tujuan = document.getElementById('kemasan_tujuan');
    const boxPreview = document.getElementById('boxPreview');
    const txtPreview = document.getElementById('txtPreview');
    const isiKonversi = document.getElementById('isi_konversi');

    if (sumber.value && tujuan.value) {
        const selectedTujuan = tujuan.options[tujuan.selectedIndex];
        const isi = parseInt(selectedTujuan.getAttribute('data-isi')) || 1;
        const satuanTujuan = selectedTujuan.getAttribute('data-satuan');
        const qtySumber = parseInt(document.querySelector('input[name="qty_sumber"]').value) || 1;
        const selectedSumber = sumber.options[sumber.selectedIndex];
        const satuanSumber = selectedSumber.getAttribute('data-satuan');

        isiKonversi.value = isi;

        const totalTambah = qtySumber * isi;
        txtPreview.innerText = `${qtySumber} ${satuanSumber} = ${totalTambah} ${satuanTujuan}`;
        boxPreview.classList.remove('d-none');
    } else {
        boxPreview.classList.add('d-none');
        isiKonversi.value = 1;
    }
}
</script>

<?php require_once BASE_PATH . 'partials/footer.php'; ?>
