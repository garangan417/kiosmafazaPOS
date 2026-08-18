<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';
require_once BASE_PATH . 'database/query_harga.php';

// TANGKAP QUERY PENCARIAN DARI URL
$search = trim($_GET['search'] ?? '');

function cleanCurrency($value) {
    return floatval(preg_replace('/[^0-9]/', '', $value));
}

// PROSES POST SAVE/UPDATE HARGA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_harga') {
        $kemasan_id   = intval($_POST['kemasan_id'] ?? 0);
        $harga_beli   = max(0, cleanCurrency($_POST['harga_beli'] ?? '0'));
        $harga_ecer   = max(0, cleanCurrency($_POST['harga_jual_ecer'] ?? '0'));
        $harga_grosir = max(0, cleanCurrency($_POST['harga_jual_grosir'] ?? '0'));
        $min_grosir   = max(1, intval($_POST['min_qty_grosir'] ?? 1));

        if ($kemasan_id > 0) {
            try {
                if (saveOrUpdateHarga($pdoBarang, $kemasan_id, $harga_beli, $harga_ecer, $harga_grosir, $min_grosir)) {
                    $_SESSION['toast_success'] = "Harga berhasil diperbarui!";
                } else {
                    $_SESSION['toast_error'] = "Gagal memperbarui harga.";
                }
            } catch (PDOException $e) {
                $_SESSION['toast_error'] = "Error Database: " . $e->getMessage();
            }
        } else {
            $_SESSION['toast_error'] = "Kemasan tidak valid!";
        }
    }

    // Redirect untuk menjaga PRG pattern & mempertahankan parameter search di URL jika ada
    $redirectUrl = $_SERVER['PHP_SELF'] . (!empty($search) ? '?search=' . urlencode($search) : '');
    header("Location: " . $redirectUrl);
    exit;
}

// AMBIL DAFTAR HARGA BARANG (SESUAI SEARCH)
$daftarHarga = getDaftarHargaLengkap($pdoBarang, $search);

require_once BASE_PATH . 'partials/header.php';
?>

<main class="container-fluid my-4 px-4 flex-grow-1">

  <div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3">
      <div class="row g-3 align-items-center justify-content-between">
        
        <!-- Judul -->
        <div class="col-md-5">
          <h5 class="card-title mb-0 fw-bold text-dark"><i class="bi bi-tags-fill me-2 text-primary"></i>Kelola Harga Barang</h5>
          <small class="text-muted">Atur harga beli modal dan harga jual ecer/grosir.</small>
        </div>

        <!-- Form Pencarian Nama / Barcode -->
        <div class="col-md-7">
          <form action="" method="GET" class="d-flex gap-2">
            <div class="input-group">
              <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
              <input type="text" name="search" id="input_search" class="form-control border-start-0" placeholder="Ketik nama barang / scan barcode..." value="<?= htmlspecialchars($search); ?>" autocomplete="off" autofocus>
              <?php if (!empty($search)): ?>
                <a href="harga.php" class="btn btn-outline-secondary" title="Reset Pencarian"><i class="bi bi-x-lg"></i></a>
              <?php endif; ?>
              <button type="submit" class="btn btn-primary fw-bold px-3">Cari</button>
            </div>
          </form>
        </div>

      </div>
    </div>

    <!-- Info hasil pencarian -->
    <?php if (!empty($search)): ?>
      <div class="bg-light px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
        <small class="text-muted">Hasil pencarian untuk: <strong class="text-dark">"<?= htmlspecialchars($search); ?>"</strong></small>
        <span class="badge bg-primary"><?= count($daftarHarga); ?> Ditemukan</span>
      </div>
    <?php endif; ?>

    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>Barang & Kemasan</th>
            <th>Isi</th>
            <th class="text-end">Harga Beli Kemasan</th>
            <th class="text-end">Modal / Satuan</th>
            <th class="text-end">Harga Jual Ecer</th>
            <th class="text-end">Harga Grosir</th>
            <th class="text-center">Keuntungan (Ecer)</th>
            <th class="text-center" style="width: 100px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($daftarHarga)): ?>
            <tr>
              <td colspan="8" class="text-center text-muted py-5">
                <i class="bi bi-box-seam display-6 d-block mb-2 text-muted"></i>
                <?= !empty($search) ? 'Barang atau barcode "' . htmlspecialchars($search) . '" tidak ditemukan.' : 'Belum ada data barang / kemasan.'; ?>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($daftarHarga as $row): ?>
              <?php 
                $isiKemasan = max(1, intval($row['isi']));
                $hargaBeli  = floatval($row['harga_beli']);
                $hargaEcer  = floatval($row['harga_jual_ecer']);
                
                $modalPerSatuan = $hargaBeli / $isiKemasan;
                $untungRp       = $hargaEcer - $modalPerSatuan;
              ?>
              <tr>
                <td>
                  <strong class="text-dark d-block"><?= htmlspecialchars($row['nama_barang']); ?></strong>
                  <span class="badge bg-info-subtle text-info border border-info extra-small me-1">
                    <?= htmlspecialchars($row['nama_kemasan']); ?>
                  </span>
                  <span class="badge bg-light text-muted border extra-small"><?= htmlspecialchars($row['nama_kategori']); ?></span>
                </td>
                <td><span class="fw-semibold"><?= $row['isi']; ?></span> <?= htmlspecialchars($row['satuan']); ?></td>
                
                <!-- Harga Beli Kemasan -->
                <td class="text-end font-monospace">
                  <?= $hargaBeli > 0 ? 'Rp ' . number_format($hargaBeli, 0, ',', '.') : '<span class="text-muted small italic">Belum Set</span>'; ?>
                </td>

                <!-- Modal Per Satuan -->
                <td class="text-end font-monospace text-muted">
                  <?= $hargaBeli > 0 ? 'Rp ' . number_format($modalPerSatuan, 0, ',', '.') . ' <small>/' . htmlspecialchars($row['satuan']) . '</small>' : '-'; ?>
                </td>

                <!-- Harga Ecer -->
                <td class="text-end font-monospace fw-bold text-success">
                  <?= $hargaEcer > 0 ? 'Rp ' . number_format($hargaEcer, 0, ',', '.') : '<span class="text-muted small italic fw-normal">Belum Set</span>'; ?>
                </td>

                <!-- Harga Grosir -->
                <td class="text-end font-monospace">
                  <?php if (floatval($row['harga_jual_grosir']) > 0): ?>
                    <span class="text-primary fw-semibold">Rp <?= number_format($row['harga_jual_grosir'], 0, ',', '.'); ?></span>
                    <div class="extra-small text-muted">Min. <?= $row['min_qty_grosir']; ?> <?= htmlspecialchars($row['satuan']); ?></div>
                  <?php else: ?>
                    <span class="text-muted small italic">-</span>
                  <?php endif; ?>
                </td>

                <!-- Keuntungan (Nominal Rp) -->
                <td class="text-center font-monospace">
                  <?php if ($hargaBeli > 0 && $hargaEcer > 0): ?>
                    <span class="badge <?= $untungRp >= 0 ? 'bg-success-subtle text-success border border-success' : 'bg-danger-subtle text-danger border border-danger'; ?> small">
                      <?= ($untungRp >= 0 ? '+Rp ' : '-Rp ') . number_format(abs($untungRp), 0, ',', '.'); ?>
                    </span>
                  <?php else: ?>
                    <span class="text-muted small">-</span>
                  <?php endif; ?>
                </td>

                <!-- Aksi -->
                <td class="text-center">
                  <button class="btn btn-sm btn-outline-primary" 
                          data-bs-toggle="modal" 
                          data-bs-target="#modalHarga<?= $row['kemasan_id']; ?>" 
                          title="Set / Edit Harga">
                    <i class="bi bi-pencil-square me-1"></i> Edit
                  </button>
                </td>
              </tr>

              <!-- MODAL EDIT HARGA -->
              <div class="modal fade" id="modalHarga<?= $row['kemasan_id']; ?>" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                  <div class="modal-content">
                    <div class="modal-header bg-dark text-white">
                      <h6 class="modal-title fw-bold"><i class="bi bi-tags me-2"></i>Atur Harga Barang</h6>
                      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form action="" method="POST">
                      <div class="modal-body">
                        <input type="hidden" name="action" value="save_harga">
                        <input type="hidden" name="kemasan_id" value="<?= $row['kemasan_id']; ?>">

                        <div class="p-2 mb-3 bg-light rounded border">
                          <strong class="d-block text-dark"><?= htmlspecialchars($row['nama_barang']); ?></strong>
                          <small class="text-muted">Kemasan: <strong><?= htmlspecialchars($row['nama_kemasan']); ?></strong> (Isi <?= $row['isi'] . ' ' . htmlspecialchars($row['satuan']); ?>)</small>
                        </div>

                        <!-- Harga Beli Kemasan -->
                        <div class="mb-3">
                          <label class="form-label small fw-semibold">Harga Beli Kemasan / Modal Kulakan (Rp)</label>
                          <div class="input-group input-group-sm">
                            <span class="input-group-text">Rp</span>
                            <input type="text" name="harga_beli" id="harga_beli_<?= $row['kemasan_id']; ?>" class="form-control input-rupiah" value="<?= number_format($row['harga_beli'], 0, ',', '.'); ?>" placeholder="0" oninput="formatAndCalculate(this, <?= $row['kemasan_id']; ?>, <?= $row['isi']; ?>)" required autocomplete="off">
                          </div>
                          <div class="form-text extra-small text-muted">Contoh: Isikan 80.000 untuk harga 1 PAK.</div>
                        </div>

                        <!-- Info Kalkulasi Modal per Satuan -->
                        <div class="p-2 mb-3 bg-info-subtle border border-info rounded extra-small text-dark">
                          Modal per <strong><?= htmlspecialchars($row['satuan']); ?></strong>: 
                          <span id="modal_satuan_text_<?= $row['kemasan_id']; ?>" class="fw-bold font-monospace">Rp 0</span>
                        </div>

                        <!-- Harga Jual Ecer -->
                        <div class="mb-3">
                          <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label small fw-semibold text-success mb-0">Harga Jual Eceran (Rp / <?= htmlspecialchars($row['satuan']); ?>)</label>
                            <span id="margin_text_<?= $row['kemasan_id']; ?>" class="badge bg-secondary extra-small font-monospace">Untung: Rp 0</span>
                          </div>
                          <div class="input-group input-group-sm">
                            <span class="input-group-text">Rp</span>
                            <input type="text" name="harga_jual_ecer" id="harga_ecer_<?= $row['kemasan_id']; ?>" class="form-control fw-bold input-rupiah" value="<?= number_format($row['harga_jual_ecer'], 0, ',', '.'); ?>" placeholder="0" oninput="formatAndCalculate(this, <?= $row['kemasan_id']; ?>, <?= $row['isi']; ?>)" required autocomplete="off">
                          </div>
                        </div>

                        <hr class="my-3 text-muted">

                        <!-- Harga Grosir & Min Qty -->
                        <div class="row g-2 mb-2">
                          <div class="col-7">
                            <label class="form-label small fw-semibold text-primary">Harga Grosir (Opsional)</label>
                            <div class="input-group input-group-sm">
                              <span class="input-group-text">Rp</span>
                              <input type="text" name="harga_jual_grosir" class="form-control input-rupiah" value="<?= number_format($row['harga_jual_grosir'], 0, ',', '.'); ?>" placeholder="0" oninput="formatRupiah(this)" autocomplete="off">
                            </div>
                          </div>
                          <div class="col-5">
                            <label class="form-label small fw-semibold">Min. Qty Grosir</label>
                            <div class="input-group input-group-sm">
                              <input type="number" name="min_qty_grosir" class="form-control" value="<?= intval($row['min_qty_grosir']); ?>" min="1">
                              <span class="input-group-text"><?= htmlspecialchars($row['satuan']); ?></span>
                            </div>
                          </div>
                        </div>

                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-dark btn-sm fw-bold">Simpan Harga</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>

              <script>
                document.addEventListener('DOMContentLoaded', function() {
                  const elBeli = document.getElementById('harga_beli_<?= $row['kemasan_id']; ?>');
                  if (elBeli) {
                    formatAndCalculate(elBeli, <?= $row['kemasan_id']; ?>, <?= $row['isi']; ?>);
                  }
                });
              </script>

            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
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

  function formatRupiahValue(angka) {
    let number_string = angka.toString().replace(/[^,\d]/g, ''),
        split   = number_string.split(','),
        sisa    = split[0].length % 3,
        rupiah  = split[0].substr(0, sisa),
        ribuan  = split[0].substr(sisa).match(/\d{3}/gi);

    if (ribuan) {
      let separator = sisa ? '.' : '';
      rupiah += separator + ribuan.join('.');
    }

    return split[1] !== undefined ? rupiah + ',' + split[1] : rupiah;
  }

  function parseRupiahValue(str) {
    if (!str) return 0;
    return parseFloat(str.replace(/\./g, '').replace(',', '.')) || 0;
  }

  function formatRupiah(element) {
    element.value = formatRupiahValue(element.value);
  }

  function formatAndCalculate(element, id, isi) {
    element.value = formatRupiahValue(element.value);

    const hargaBeliStr = document.getElementById('harga_beli_' + id).value;
    const hargaEcerStr = document.getElementById('harga_ecer_' + id).value;

    const hargaBeli = parseRupiahValue(hargaBeliStr);
    const hargaEcer = parseRupiahValue(hargaEcerStr);
    
    const modalSatuan = isi > 0 ? (hargaBeli / isi) : 0;
    document.getElementById('modal_satuan_text_' + id).innerText = 'Rp ' + Math.round(modalSatuan).toLocaleString('id-ID');
    
    const untungBadge = document.getElementById('margin_text_' + id);
    if (modalSatuan > 0 && hargaEcer > 0) {
      const untungRp = hargaEcer - modalSatuan;
      const prefix = untungRp >= 0 ? '+Rp ' : '-Rp ';
      
      untungBadge.innerText = 'Untung: ' + prefix + Math.abs(Math.round(untungRp)).toLocaleString('id-ID');
      untungBadge.className = 'badge extra-small font-monospace ' + (untungRp >= 0 ? 'bg-success' : 'bg-danger');
    } else {
      untungBadge.innerText = 'Untung: Rp 0';
      untungBadge.className = 'badge bg-secondary extra-small font-monospace';
    }
  }
</script>

<?php require_once BASE_PATH . 'partials/footer.php'; ?>