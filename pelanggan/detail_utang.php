<?php
// pelanggan/detail_utang.php
require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_pelanggan.php';

if (!function_exists('formatRupiah')) {
    function formatRupiah($nominal) {
        return 'Rp ' . number_format((float)$nominal, 0, ',', '.');
    }
}

$pelanggan_id = intval($_GET['id'] ?? 0);

if ($pelanggan_id <= 0) {
    echo '<div class="p-3"><div class="alert alert-danger m-0">ID Pelanggan tidak valid.</div></div>';
    exit;
}

// 1. Ambil Informasi Pelanggan
$stmtP = $pdoPelanggan->prepare("SELECT * FROM pelanggan WHERE id = ?");
$stmtP->execute([$pelanggan_id]);
$pelanggan = $stmtP->fetch(PDO::FETCH_ASSOC);

if (!$pelanggan) {
    echo '<div class="p-3"><div class="alert alert-danger m-0">Pelanggan tidak ditemukan.</div></div>';
    exit;
}

// 2. Ambil Riwayat Utang (Diurutkan dari TERLAMA ke TERBARU untuk kalkulasi sesi yang akurat)
$stmtU = $pdoPelanggan->prepare("SELECT * FROM utang WHERE pelanggan_id = ? ORDER BY created_at ASC, id ASC");
$stmtU->execute([$pelanggan_id]);
$riwayatAsc = $stmtU->fetchAll(PDO::FETCH_ASSOC);

// 3. Kalkulasi Sesi Aktif Utang
$totalUtangSesi = 0;
$totalBayarSesi = 0;

foreach ($riwayatAsc as $r) {
    $nominal = floatval($r['nominal']);
    
    if ($r['tipe'] === 'utang') {
        $totalUtangSesi += $nominal;
    } else {
        $totalBayarSesi += $nominal;
    }

    // Jika pembayaran sudah menutupi/melebihi utang pada titik ini,
    // anggap sesi lunas dan RESET hitungan ke 0 untuk sesi berikutnya.
    if ($totalUtangSesi > 0 && $totalBayarSesi >= $totalUtangSesi) {
        $totalUtangSesi = 0;
        $totalBayarSesi = 0;
    }
}

// Hitung sisa utang sesi saat ini (Jamin tidak minus dengan max(0, ...))
$sisaUtang = max(0, $totalUtangSesi - $totalBayarSesi);

// Data riwayat di-reverse kembali untuk tampilan tabel (terbaru di atas)
$riwayat = array_reverse($riwayatAsc);
?>

<!-- HTML MODAL DETAIL UTANG PELANGGAN -->
<div class="modal fade" id="modalDetailUtang" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content shadow-lg border-0">
      
      <!-- Header Modal -->
      <div class="modal-header bg-dark text-white">
        <div>
          <h5 class="modal-title fw-bold mb-0">
            <i class="bi bi-receipt me-2"></i>Riwayat Utang - <?= htmlspecialchars($pelanggan['nama']); ?>
          </h5>
          <small class="text-white-50"><?= htmlspecialchars($pelanggan['no_hp'] ?: 'Tidak ada No. HP'); ?></small>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <!-- Body Modal -->
      <div class="modal-body p-4">
        
        <!-- Summary Cards (Menampilkan Ringkasan Sesi Aktif) -->
        <div class="row g-2 mb-3">
          <div class="col-4">
            <div class="p-2 border rounded bg-light text-center">
              <small class="text-muted d-block">Utang Sesi Ini</small>
              <strong class="text-danger"><?= formatRupiah($totalUtangSesi); ?></strong>
            </div>
          </div>
          <div class="col-4">
            <div class="p-2 border rounded bg-light text-center">
              <small class="text-muted d-block">Dibayar Sesi Ini</small>
              <strong class="text-success"><?= formatRupiah($totalBayarSesi); ?></strong>
            </div>
          </div>
          <div class="col-4">
            <div class="p-2 border rounded bg-primary-subtle text-center border-primary">
              <small class="text-primary fw-semibold d-block">Sisa Utang</small>
              <strong class="text-primary fs-6"><?= formatRupiah($sisaUtang); ?></strong>
            </div>
          </div>
        </div>

        <!-- Form Quick Action (Murni HTML POST) -->
        <div class="card bg-light border-0 p-3 mb-4">
          <h6 class="fw-bold mb-2 text-dark"><i class="bi bi-plus-circle me-1"></i> Transaksi Cepat</h6>
          
          <form action="<?= BASE_URL; ?>utang/index.php" method="POST" onsubmit="setWaktuBrowserDetailUtang(this)">
            <input type="hidden" name="pelanggan_id" value="<?= $pelanggan['id']; ?>">
            <!-- Input Tanggal Diisi Secara Client-Side (Waktu Browser Kasir) -->
            <input type="hidden" name="tanggal" class="input-waktu-browser">

            <div class="row g-2">
              <div class="col-md-3">
                <select name="tipe" class="form-select form-select-sm fw-semibold" required>
                  <option value="utang">+ Utang Baru</option>
                  <option value="bayar" selected>- Bayar / Cicil</option>
                </select>
              </div>

              <div class="col-md-4">
                <div class="input-group input-group-sm">
                  <span class="input-group-text fw-bold">Rp</span>
                  <input type="text" name="nominal" class="form-control text-end fw-bold" placeholder="Nominal" onkeyup="formatInputRibuan(this)" required autocomplete="off">
                </div>
              </div>

              <div class="col-md-3">
                <textarea name="keterangan" class="form-control form-control-sm" rows="1" placeholder="Rincian barang / Catatan (Tekan Enter untuk baris baru)"></textarea>
              </div>

              <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-dark w-100 fw-bold">Simpan</button>
              </div>
            </div>
          </form>
        </div>

        <!-- Tabel Riwayat Transaksi Lengkap -->
        <h6 class="fw-bold mb-2"><i class="bi bi-clock-history me-1"></i> Rincian Transaksi</h6>
        <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
          <table class="table table-hover table-striped align-middle mb-0">
            <thead class="table-light sticky-top">
              <tr>
                <th>Tanggal & Waktu</th>
                <th>Tipe</th>
                <th>Keterangan</th>
                <th class="text-end">Nominal</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($riwayat)): ?>
                <tr>
                  <td colspan="4" class="text-center text-muted py-3">Belum ada riwayat utang atau pembayaran.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($riwayat as $item): ?>
                  <tr>
                    <td class="small fw-semibold text-muted">
                      <?= date('d/m/Y H:i', strtotime($item['created_at'])); ?>
                    </td>
                    <td>
                      <span class="badge bg-<?= $item['tipe'] === 'utang' ? 'danger' : 'success'; ?>">
                        <?= strtoupper($item['tipe']); ?>
                      </span>
                    </td>
                    <td class="small">
                      <?php if (!empty($item['keterangan'])): ?>
                        <div><?= nl2br(htmlspecialchars(trim($item['keterangan']))); ?></div>
                      <?php else: ?>
                        <span class="text-muted">-</span>
                      <?php endif; ?>
                    </td>
                    <td class="text-end fw-bold text-<?= $item['tipe'] === 'utang' ? 'danger' : 'success'; ?>">
                      <?= $item['tipe'] === 'utang' ? '+' : '-'; ?> <?= formatRupiah($item['nominal']); ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      </div>

      <!-- Footer Modal -->
      <div class="modal-footer bg-light">
        <button type="button" class="btn-close-modal btn btn-secondary btn-sm" data-bs-dismiss="modal">Tutup</button>
      </div>

    </div>
  </div>
</div>

<script>
if (typeof formatInputRibuan !== 'function') {
  function formatInputRibuan(input) {
    let val = input.value.replace(/[^0-9]/g, '');
    if (!val) { input.value = ''; return; }
    input.value = new Intl.NumberFormat('id-ID').format(val);
  }
}

// Skrip penentuan waktu dari browser saat modal utang dikirim
function setWaktuBrowserDetailUtang(formElement) {
  const now = new Date();
  const yyyy = now.getFullYear();
  const mm = String(now.getMonth() + 1).padStart(2, '0');
  const dd = String(now.getDate()).padStart(2, '0');
  const hh = String(now.getHours()).padStart(2, '0');
  const ii = String(now.getMinutes()).padStart(2, '0');
  const ss = String(now.getSeconds()).padStart(2, '0');

  const inputWaktu = formElement.querySelector('.input-waktu-browser');
  if (inputWaktu) {
    inputWaktu.value = `${yyyy}-${mm}-${dd} ${hh}:${ii}:${ss}`;
  }
}
</script>