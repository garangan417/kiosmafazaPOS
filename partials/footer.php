<footer class="bg-white text-center text-lg-start mt-auto py-3 border-top">
  <div class="container text-center text-muted small">
    <div>
      &copy; <?= date('Y'); ?> <strong>Mafaza App</strong>. All rights reserved.
    </div>
    <div class="mt-1" style="font-size: 0.8rem;">
      Licensed under <a href="https://github.com/garangan417/kiosmafazaPOS/blob/main/LICENSE" class="text-decoration-none text-secondary" target="_blank" rel="noopener">Non-Commercial License</a> | 
      Developed by <a href="https://github.com/garangan417" class="text-decoration-none text-secondary" target="_blank" rel="noopener">Garangan417</a> | 
      <!-- Tombol Pemicu Modal About -->
      <a href="#" class="text-decoration-none text-primary fw-semibold" data-bs-toggle="modal" data-bs-target="#aboutModal">Tentang Aplikasi</a>
    </div>
  </div>
</footer>

<!-- ============================================================ -->
<!-- MODAL ABOUT / TENTANG APLIKASI                               -->
<!-- ============================================================ -->
<div class="modal fade" id="aboutModal" tabindex="-1" aria-labelledby="aboutModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="aboutModalLabel">Tentang Aplikasi</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <div class="mb-3">
          <span class="fs-1">🛒</span>
          <h5 class="fw-bold mb-1">Kios Mafaza POS</h5>
          <p class="text-muted small">Point of Sales, Inventory & Debt Management System</p>
        </div>
        
        <div class="text-start bg-light p-3 rounded small mb-3">
          <p class="mb-1"><strong>Pengembang:</strong> Garangan417</p>
          <p class="mb-1"><strong>Lisensi:</strong> Non-Commercial Public License (NC-PL)</p>
          <p class="mb-0"><strong>Basis Data:</strong> Dual Support (MariaDB & SQLite)</p>
        </div>

        <p class="text-muted small mb-0">
          Aplikasi ini dikembangkan untuk tujuan pembelajaran, portofolio, dan penggunaan non-komersial. Dilarang memperjualbelikan atau mendistribusikan ulang untuk keuntungan komersial tanpa izin resmi.
        </p>
      </div>
      <div class="modal-footer justify-content-between">
        <a href="https://github.com/garangan417/kiosmafazaPOS" target="_blank" rel="noopener" class="btn btn-sm btn-outline-dark">
          <i class="bi bi-github"></i> Repository GitHub
        </a>
        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

<!-- Watermark Komentar Tersembunyi -->
<!-- 
  ========================================================================
  MAFAZA APP - POINT OF SALE SYSTEM
  Copyright (c) <?= date('Y'); ?> Garangan417. All Rights Reserved.
  
  Licensed under the Non-Commercial Public License (NC-PL).
  ========================================================================
-->

<script src="/assets/js/helper.js"></script>

<script>
// Auto-dismiss & interaktivitas untuk Global Toast Container
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

<!-- Bootstrap JS -->
<script src="/assets/js/bootstrap.bundle.min.js"></script>

<!-- ============================================================ -->
<!-- TOAST GLOBAL DARI SESSION (SUCCESS & ERROR)                  -->
<!-- ============================================================ -->
<script src="/assets/js/sweetalert2.all.min.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  if (typeof Swal === 'undefined') {
    console.warn('[Toast] SweetAlert2 belum di-load!');
    return;
  }

  const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 4000,
    timerProgressBar: true,
    didOpen: (toast) => {
      toast.addEventListener('mouseenter', Swal.stopTimer);
      toast.addEventListener('mouseleave', Swal.resumeTimer);
    }
  });

  <?php if (!empty($_SESSION['toast_success'])): ?>
  Toast.fire({
    icon: 'success',
    title: <?= json_encode($_SESSION['toast_success']); ?>
  });
  <?php unset($_SESSION['toast_success']); ?>
  <?php endif; ?>

  <?php if (!empty($_SESSION['toast_error'])): ?>
  Toast.fire({
    icon: 'error',
    title: <?= json_encode($_SESSION['toast_error']); ?>
  });
  <?php unset($_SESSION['toast_error']); ?>
  <?php endif; ?>
});
</script>

</body>
</html>