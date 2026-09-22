<footer class="bg-white text-center text-lg-start mt-auto py-3 border-top">
<div class="container text-center text-muted small">
&copy; <?= date('Y'); ?> Mafaza App. All rights reserved.
</div>
</footer>

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
  // Cek apakah SweetAlert sudah load
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
