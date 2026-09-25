<footer class="bg-white fixed-bottom border-top shadow-lg">
<div class="container-fluid px-3 px-md-4 py-2">
<div class="d-flex justify-content-between align-items-center gap-2">

<!-- =============================== -->
<!-- KIRI: TOMBOL KELOLA FAVORIT      -->
<!-- =============================== -->
<!-- <div class="flex-shrink-0">
<button type="button"
class="btn btn-outline-warning text-dark fw-bold px-3"
onclick="openKelolaFavorit()">
<i class="bi bi-star-fill me-1"></i>
<span class="d-none d-md-inline">Kelola Favorit</span>
<span class="d-md-none">Favorit</span>
</button>
</div> -->
<!-- =============================== -->
<!-- KIRI: TOMBOL KELOLA FAVORIT + PELANGGAN UTANG -->
<!-- =============================== -->
<div class="flex-shrink-0 d-flex gap-2">
    <button type="button"
            class="btn btn-outline-warning text-dark fw-bold px-3"
            onclick="openKelolaFavorit()">
        <i class="bi bi-star-fill me-1"></i>
        <span class="d-none d-md-inline">Kelola Favorit</span>
        <span class="d-md-none">Favorit</span>
    </button>

    <!-- ⬇️ TOMBOL BARU: PELANGGAN UTANG ⬇️ -->
    <button type="button"
            class="btn btn-outline-danger text-dark fw-bold px-3"
            onclick="openModalDaftarUtang()">
        <i class="bi bi-cash-coin me-1"></i>
        <span class="d-none d-md-inline">Pelanggan Utang</span>
        <span class="d-md-none">Utang</span>
    </button>
</div>


<!-- =============================== -->
<!-- TENGAH: JAM (sembunyi di mobile) -->
<!-- =============================== -->
<div class="d-none d-lg-block flex-grow-1 text-center">
<div class="badge bg-secondary bg-opacity-25 text-dark fw-normal border border-secondary px-3 py-2 fs-6">
<span id="server-clock-date" class="me-3 text-dark fw-bold"><?= date('d/m/Y'); ?></span>
<span id="server-clock-time" class="font-monospace fw-bold">00:00:00</span>
</div>
</div>

<!-- =============================== -->
<!-- KANAN: TOTAL + TOMBOL BAYAR      -->
<!-- =============================== -->
<div class="d-flex align-items-center gap-2 flex-shrink-0">

<!-- TAMPILAN TOTAL PEMBAYARAN -->
<div class="bg-dark text-white px-3 py-1 rounded border border-secondary d-flex align-items-right gap-2">
  <small class="text-uppercase fw-semibold opacity-75" style="font-size: 1rem; fw-bold line-height: 1;">
    Total         :
  </small> 
  <span class="fs-2 fs-md-2  font-monospace text-light" id="displayTotal">
    Rp 0
  </span>
</div>
<span class="fs-2 fs-md-2  font-monospace text-light"
id="displayTotal">Rp 0</span>
</div>

<!-- SIMPAN & PROSES -->
<button id="btnCheckout"
type="button"
class="btn btn-success btn-lg fw-bold px-3 px-md-4"
onclick="prosesCheckout()"
disabled>
<i class="bi bi-printer me-1"></i>
<span class="d-none d-md-inline">SIMPAN & PROSES</span>
<span class="d-md-none">SIMPAN</span>
</button>

</div>

</div>
</div>
</footer>

<script>
// ==========================================
// GLOBAL TOAST
// ==========================================
document.body.addEventListener('htmx:afterOnLoad', function(evt) {
  const container = document.getElementById('globalToastContainer');
  if (!container) return;

  const alerts = container.querySelectorAll('.alert');
  if (alerts.length > 0) {
    alerts.forEach(alertEl => {
      alertEl.style.pointerEvents = 'auto';
    setTimeout(() => {
      alertEl.classList.remove('show');
      setTimeout(() => alertEl.remove(), 150);
    }, 3000);
    });
  }
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function() {
  // 1. Ambil timestamp server PHP (dalam milidetik) berdasarkan timezone 'Asia/Makassar'
  const serverTimestamp = <?= floor(microtime(true) * 1000); ?>;

  // 2. Hitung offset/selisih antara jam Server dengan jam lokal Perangkat
  const timeOffset = serverTimestamp - Date.now();

  const clockTimeElem = document.getElementById('server-clock-time');
  const clockDateElem = document.getElementById('server-clock-date');

  function updateClock() {
    // Jika elemen tidak ditemukan, hentikan eksekusi
    if (!clockTimeElem && !clockDateElem) return;

    // 3. Waktu Server Presisi = Jam Lokal Perangkat + Selisih Offset
    const accurateServerTime = new Date(Date.now() + timeOffset);

    // Format Jam: HH:MM:SS
    const hh = String(accurateServerTime.getHours()).padStart(2, '0');
    const mm = String(accurateServerTime.getMinutes()).padStart(2, '0');
    const ss = String(accurateServerTime.getSeconds()).padStart(2, '0');

    // Format Tanggal: DD/MM/YYYY
    const day = String(accurateServerTime.getDate()).padStart(2, '0');
    const month = String(accurateServerTime.getMonth() + 1).padStart(2, '0');
    const year = accurateServerTime.getFullYear();

    if (clockTimeElem) clockTimeElem.innerText = `${hh}:${mm}:${ss}`;
    if (clockDateElem) clockDateElem.innerText = `${day}/${month}/${year}`;
  }

  // Jalankan langsung sekali, lalu ulangi tiap 1 detik (1000ms)
  updateClock();
  setInterval(updateClock, 1000);
});
</script>

<!-- ========================================== -->
<!-- MODAL DAFTAR PELANGGAN UTANG AKTIF         -->
<!-- ========================================== -->
<div class="modal fade" id="modalDaftarUtang" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white py-2">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-cash-coin me-2"></i>Pelanggan dengan Utang Aktif
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="resetFocusScan()"></button>
            </div>

            <div class="modal-body p-3">
                <!-- Search -->
                <div class="input-group input-group-sm mb-3">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" id="inputSearchUtang" class="form-control"
                           placeholder="Cari nama atau no HP pelanggan..."
                           oninput="debounceCariUtang(this.value)" autocomplete="off">
                </div>

                <!-- Summary -->
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="p-2 border rounded bg-light text-center">
                            <small class="text-muted d-block">Total Pelanggan</small>
                            <strong class="text-dark" id="utangTotalCount">0</strong>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 border rounded bg-danger-subtle text-center border-danger">
                            <small class="text-danger fw-semibold d-block">Total Utang</small>
                            <strong class="text-danger" id="utangGrandTotal">Rp 0</strong>
                        </div>
                    </div>
                </div>

                <!-- Loading -->
                <div id="utangLoading" class="text-center py-4" style="display: none;">
                    <div class="spinner-border text-danger" role="status"></div>
                    <small class="d-block text-muted mt-2">Memuat data...</small>
                </div>

                <!-- Tabel -->
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th>Nama Pelanggan</th>
                                <th>No HP</th>
                                <th class="text-end">Sisa Utang</th>
                                <th class="text-center" style="width: 100px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="utangListBody">
                            <!-- Dinamis via JS -->
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-secondary fw-bold" data-bs-dismiss="modal" onclick="resetFocusScan()">
                    Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL BAYAR UTANG CEPAT                    -->
<!-- ========================================== -->
<div class="modal fade" id="modalBayarUtang" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white py-2">
                <h6 class="modal-title fw-bold">
                    <i class="bi bi-cash-stack me-2"></i>Bayar Utang
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="resetFocusScan()"></button>
            </div>

            <div class="modal-body p-3">
                <form id="formBayarUtangKasir" onsubmit="event.preventDefault(); submitBayarUtang();">
                    <input type="hidden" id="bayarUtangPelangganId">

                    <!-- Info Pelanggan -->
                    <div class="p-2 mb-3 bg-light rounded border">
                        <strong class="d-block text-dark" id="bayarUtangNama">-</strong>
                        <small class="text-muted">
                            Sisa Utang:
                            <strong id="bayarUtangSisa" class="text-danger">Rp 0</strong>
                        </small>
                    </div>

                    <!-- Input Nominal -->
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Nominal Bayar</label>
                        <div class="input-group">
                            <span class="input-group-text fw-bold">Rp</span>
                            <input type="text"
                                   id="bayarUtangNominal"
                                   class="form-control form-control-lg text-end fw-bold font-monospace"
                                   placeholder="0"
                                   oninput="formatInputRupiahJS(this)"
                                   autocomplete="off"
                                   required>
                        </div>

                        <!-- Tombol Cepat -->
                        <div class="d-flex gap-1 mt-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-success fw-bold" onclick="setBayarUtangPas()">
                                <i class="bi bi-check-lg"></i> Uang Pas
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setBayarUtangNominal(10000)">10rb</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setBayarUtangNominal(20000)">20rb</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setBayarUtangNominal(50000)">50rb</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="setBayarUtangNominal(100000)">100rb</button>
                        </div>
                    </div>

                    <!-- Keterangan -->
                    <div class="mb-2">
                        <label class="form-label small fw-bold">Keterangan (Opsional)</label>
                        <input type="text" id="bayarUtangKeterangan" class="form-control form-control-sm"
                               placeholder="Catatan...">
                    </div>

                    <div id="alertBayarUtang"></div>
                </form>
            </div>

            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal" onclick="resetFocusScan()">Batal</button>
                <button type="button" class="btn btn-sm btn-success fw-bold" onclick="submitBayarUtang()">
                    <i class="bi bi-check-lg me-1"></i> Bayar Sekarang
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Helper.js dengan cache busting -->
<script src="/assets/js/helper.js?v=<?= filemtime('assets/js/helper.js'); ?>"></script>


<!-- Helper.js dengan cache busting -->
<script src="/assets/js/helper.js?v=<?= filemtime('assets/js/helper.js'); ?>"></script>

<!-- Bootstrap JS -->
<script src="/assets/js/bootstrap.bundle.min.js"></script>

</body>
</html>
