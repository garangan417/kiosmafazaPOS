<footer class="bg-white fixed-bottom border-top shadow-lg">

  <!-- =============================== -->
  <!-- TOMBOL AKSI KASIR & TOTAL PEMBAYARAN -->
  <!-- =============================== -->
  <div class="container-fluid px-4 py-2">
    <div class="d-flex justify-content-between align-items-center">

      <!-- TOMBOL KELOLA FAVORIT (Sisi Kiri) -->
      <div>
        <button type="button"
                class="btn btn-outline-warning text-dark fw-bold px-3"
                onclick="openKelolaFavorit()">
          <i class="bi bi-star-fill me-1"></i>
          Kelola Favorit
        </button>
      </div>

<li class="nav-item me-lg-2 my-3 my-lg-0">
  <!-- Tambahkan fs-5 dan atur py-2 agar ruang badge ikut menyesuaikan -->
  <div class="badge bg-secondary bg-opacity-25 text-dark fw-normal border border-secondary px-3 py-2 fs-5 text-start text-lg-center">
    <span id="server-clock-date" class="me-4 text-dark fw-bold"><?= date('d/m/Y'); ?></span>
    <span id="server-clock-time" class="font-monospace fw-bold">00:00:00</span>
  </div>
</li>

      <!-- AREA TOTAL PEMBAYARAN & PROSES (Sisi Kanan) -->
      <div class="d-flex align-items-center gap-3">
        <!-- TAMPILAN TOTAL PEMBAYARAN -->
        <div class="bg-dark text-white px-3 py-1 rounded text-end border border-secondary">
          <small class="text-uppercase fw-semibold opacity-75 d-block" style="font-size: 0.7rem; line-height: 1;">Total Pembayaran</small>
          <span class="fs-4 fw-bold font-monospace text-warning" id="displayTotal">Rp 0</span>
        </div>

        <!-- SIMPAN & PROSES -->
        <button id="btnCheckout"
                type="button"
                class="btn btn-success btn-lg fw-bold px-4"
                onclick="prosesCheckout()"
                disabled>
          <i class="bi bi-printer me-2"></i>
          SIMPAN & PROSES
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


<script src="/assets/js/helper.js"></script>

<!-- Bootstrap JS -->
<script src="/assets/js/bootstrap.bundle.min.js"></script>

</body>
</html>