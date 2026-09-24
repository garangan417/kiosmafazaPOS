<footer class="bg-white fixed-bottom border-top shadow-lg">
<div class="container-fluid px-3 px-md-4 py-2">
<div class="d-flex justify-content-between align-items-center gap-2">

<!-- =============================== -->
<!-- KIRI: TOMBOL KELOLA FAVORIT      -->
<!-- =============================== -->
<div class="flex-shrink-0">
<button type="button"
class="btn btn-outline-warning text-dark fw-bold px-3"
onclick="openKelolaFavorit()">
<i class="bi bi-star-fill me-1"></i>
<span class="d-none d-md-inline">Kelola Favorit</span>
<span class="d-md-none">Favorit</span>
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
<div class="bg-dark text-white px-3 py-1 rounded text-end border border-secondary">
<small class="text-uppercase fw-semibold opacity-75 d-block"
style="font-size: 0.7rem; line-height: 1;">
Total
</small>
<span class="fs-5 fs-md-4 fw-bold font-monospace text-warning"
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

<!-- Helper.js dengan cache busting -->
<script src="/assets/js/helper.js?v=<?= filemtime('assets/js/helper.js'); ?>"></script>

<!-- Bootstrap JS -->
<script src="/assets/js/bootstrap.bundle.min.js"></script>

</body>
</html>
