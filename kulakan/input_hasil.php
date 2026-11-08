<?php
// kulakan/input_hasil.php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';
require_once BASE_PATH . 'database/query_kulakan.php';

$id = intval($_GET['id'] ?? 0);
$nota = $id > 0 ? getKulakanById($pdoBarang, $id) : null;

if (!$nota || $nota['status'] !== 'BELUM_DATANG') {
    $_SESSION['toast_error'] = "Nota tidak ditemukan atau sudah diproses.";
    header("Location: riwayat.php"); exit;
}

$items = getKulakanDetailItems($pdoBarang, $id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = inputHasilBelanjaKeStok($pdoBarang, $id, $_POST['qty_aktual'] ?? [], $_POST['harga_aktual'] ?? [], $_POST['item_dihapus'] ?? [], $_POST['satuan_aktual'] ?? []);
    if ($res['ok']) {
        $_SESSION['toast_success'] = "Stok berhasil diinput!";
        header("Location: riwayat.php");
    } else {
        $_SESSION['toast_error'] = "Gagal: " . $res['error'];
        header("Location: input_hasil.php?id=" . $id);
    }
    exit;
}

require_once BASE_PATH . 'partials/header.php';
?>

<main class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="fw-bold mb-0">Input Hasil Belanja</h4>
      <p class="text-muted small mb-0">Nota: <code><?= htmlspecialchars($nota['no_kulakan']); ?></code></p>
    </div>
    <a href="riwayat.php" class="btn btn-outline-secondary btn-sm">Kembali</a>
  </div>

  <form action="" method="POST" id="formInputHasil">
    <div id="wadahHapus"></div>
    <div class="card shadow-sm border-0 mb-3">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="tabelBelanja">
          <thead class="table-dark">
            <tr>
              <th>Barang</th>
              <th class="text-center">Qty Rencana</th>
              <th class="text-center" style="width: 150px;">Qty Aktual</th>
              <th class="text-end">Harga Rencana</th>
              <th class="text-end" style="width: 180px;">Harga Aktual</th>
              <th class="text-end">Subtotal</th>
              <th class="text-center">Aksi</th>
            </tr>
          </thead>
                      <tbody>
            <?php foreach ($items as $it): 
              // Baca data langsung dari database
              $satuanTersimpan = $it['satuan']; // Ini akan berisi "PAK" atau "BUNGKUS"
              $namaKemasanMaster = $it['nama_kemasan_master'] ?? $it['nama_kemasan'];
              $isi = (int) $it['isi'];
              $hargaAsli = (float) $it['harga_beli'];
              
              // Cek apakah yang tersimpan itu nama kemasan (PAK) atau satuan eceran (BUNGKUS)
              $isKemasan = ($satuanTersimpan === $namaKemasanMaster);
            ?>
              <tr class="item-row" id="row-<?= $it['id']; ?>" data-isi="<?= $isi; ?>" data-harga-pak="<?= $hargaAsli; ?>">
                <td>
                  <strong class="d-block text-dark"><?= htmlspecialchars($it['nama_barang']); ?></strong>
                  <span class="badge bg-info-subtle text-info border border-info extra-small">
                    <?= htmlspecialchars($namaKemasanMaster); ?>
                  </span>
                  <span class="text-muted extra-small"><?= $isi; ?> <?= htmlspecialchars($it['satuan_master'] ?? $it['satuan']); ?></span>
                </td>
                <td class="text-center fs-5 text-muted">
                    <!-- Qty Rencana Menampilkan Teks yang Tersimpan (PAK / BUNGKUS) -->
                    <?= (float) $it['qty']; ?> <?= htmlspecialchars($satuanTersimpan); ?>
                </td>
                <td>
                  <div class="d-flex gap-1">
                    <input type="number" name="qty_aktual[<?= $it['id']; ?>]" class="form-control text-center fw-bold input-qty" value="<?= (float) $it['qty']; ?>" min="1" required>
                    <select name="satuan_aktual[<?= $it['id']; ?>]" class="form-select form-select-sm input-satuan" style="width: 115px;">
                      <option value="KEMASAN" <?= $isKemasan ? 'selected' : ''; ?>><?= htmlspecialchars($namaKemasanMaster); ?></option>
                      <option value="BASE" <?= !$isKemasan ? 'selected' : ''; ?>><?= htmlspecialchars($it['satuan_master'] ?? $it['satuan']); ?></option>
                    </select>
                  </div>
                </td>
                <td class="text-end font-monospace text-muted small">
                  Rp <?= number_format($hargaAsli, 0, ',', '.'); ?> <br>
                  <small class="text-muted">per <?= htmlspecialchars($satuanTersimpan); ?></small>
                </td>
                <td>
                  <div class="input-group input-group-sm">
                    <span class="input-group-text">Rp</span>
                    <!-- Harga Aktual otomatis terisi sesuai database -->
                    <input type="number" name="harga_aktual[<?= $it['id']; ?>]" class="form-control text-end fw-bold input-harga" value="<?= round($hargaAsli); ?>" min="0" required>
                  </div>
                  <div class="extra-small text-muted text-end mt-1 label-harga">per <?= htmlspecialchars($satuanTersimpan); ?></div>
                </td>
                <td class="text-end font-monospace fw-bold text-primary">Rp <span class="subtotal-text">0</span></td>
                <td class="text-center">
                  <button type="button" class="btn btn-sm btn-outline-danger btn-hapus" data-id="<?= $it['id']; ?>"><i class="bi bi-trash"></i></button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
      <div class="card-body d-flex justify-content-between align-items-center bg-light rounded">
        <h5 class="mb-0 fw-bold">Total Modal Belanja (Aktual)</h5>
        <h3 class="mb-0 fw-bold text-primary font-monospace" id="grandTotalText">Rp 0</h3>
      </div>
    </div>

    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
      <button type="submit" class="btn btn-success btn-lg fw-bold px-5" onclick="return confirm('Simpan hasil belanja ini?');">
        <i class="bi bi-check-circle-fill me-2"></i> Konfirmasi & Masukkan ke Stok
      </button>
    </div>
  </form>
</main>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    const tabel = document.getElementById('tabelBelanja');
    const grandTotalText = document.getElementById('grandTotalText');
    const wadahHapus = document.getElementById('wadahHapus');

       function calculateTotals() {
      let grandTotal = 0;
      const rows = document.querySelectorAll('.item-row');

      rows.forEach(row => {
        if (row.style.display !== 'none') {
          const qtyInput = row.querySelector('.input-qty');
          const hargaInput = row.querySelector('.input-harga');
          const satuanSelect = row.querySelector('.input-satuan');
          const subtotalText = row.querySelector('.subtotal-text');

          // parseFloat penting untuk desimal (0.5)
          const qty = parseFloat(qtyInput.value) || 0;
          const harga = parseFloat(hargaInput.value) || 0;
          
          let subtotal = 0;
          if (satuanSelect.value === 'BASE') {
            // Jika eceran, subtotal = qty * harga
            subtotal = qty * harga;
          } else {
            // Jika kemasan, subtotal = qty * harga (dimana qty adalah jumlah pak)
            subtotal = qty * harga;
          }

          subtotalText.innerText = Math.round(subtotal).toLocaleString('id-ID');
          grandTotal += subtotal;
        }
      });

      grandTotalText.innerText = 'Rp ' + Math.round(grandTotal).toLocaleString('id-ID');
    }

    tabel.addEventListener('input', function(e) {
      if (e.target.classList.contains('input-qty') || e.target.classList.contains('input-harga')) {
        calculateTotals();
      }
    });

    tabel.addEventListener('change', function(e) {
      if (e.target.classList.contains('input-satuan')) {
        const row = e.target.closest('.item-row');
        const hargaInput = row.querySelector('.input-harga');
        const isiKemasan = parseInt(row.getAttribute('data-isi')) || 1;
        // Ambil harga acuan PAK dari database (di tabel rencana)
        const hargaAcuanPak = parseFloat(row.querySelector('td:nth-child(4)').innerText.replace(/[^0-9]/g, '')) || 0;

        if (e.target.value === 'BASE') {
            hargaInput.value = Math.round(hargaAcuanPak / isiKemasan);
        } else {
            hargaInput.value = hargaAcuanPak;
        }
        calculateTotals();
      }
    });

    tabel.addEventListener('click', function(e) {
      const btnHapus = e.target.closest('.btn-hapus');
      if (!btnHapus) return;
      const id = btnHapus.getAttribute('data-id');
      const row = document.getElementById('row-' + id);
      
      if (confirm('Hapus barang ini?')) {
        row.style.display = 'none';
        row.querySelectorAll('input, select').forEach(el => el.disabled = true);
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'item_dihapus[]';
        hiddenInput.value = id;
        wadahHapus.appendChild(hiddenInput);
        calculateTotals();
      }
    });

    calculateTotals();
  });
</script>

<?php require_once BASE_PATH . 'partials/footer.php'; ?>
