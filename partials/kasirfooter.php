<footer class="bg-white fixed-bottom border-top">

  <!-- =============================== -->
  <!-- TOMBOL AKSI KASIR -->
  <!-- =============================== -->
  <div class="container-fluid px-4 py-2">
    <div class="d-flex justify-content-center align-items-center gap-2">

      <!-- KELOLA FAVORIT -->
      <button type="button"
              class="btn btn-outline-warning text-dark fw-bold px-3"
              onclick="openKelolaFavorit()">
        <i class="bi bi-star-fill me-1"></i>
        Kelola Favorit
      </button>

      <!-- SIMPAN & PROSES -->
      <button id="btnCheckout"
              type="button"
              class="btn btn-success fw-bold px-4"
              onclick="prosesCheckout()"
              disabled>
        <i class="bi bi-printer me-2"></i>
        SIMPAN & PROSES
      </button>

    </div>
  </div>

  <!-- =============================== -->
  <!-- COPYRIGHT -->
  <!-- =============================== -->
 <!-- <div class="container text-center text-muted small py-2 border-top">
    &copy; <?= date('Y'); ?> Mafaza App. All rights reserved.
  </div> -->

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