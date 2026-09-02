<footer class="bg-white text-center text-lg-start mt-auto py-3 border-top">
  <div class="container text-center text-muted small">
    &copy; <?= date('Y'); ?> Mafaza App. All rights reserved.
  </div>
</footer>

<script>
// Auto-dismiss & interaktivitas untuk Global Toast Container
document.body.addEventListener('htmx:afterOnLoad', function(evt) {
    const container = document.getElementById('globalToastContainer');
    if (!container) return;

    // Aktifkan kembali pointer-events jika ada alert yang masuk
    const alerts = container.querySelectorAll('.alert');
    if (alerts.length > 0) {
        alerts.forEach(alertEl => {
            alertEl.style.pointerEvents = 'auto'; // Agar tombol [X] close tetap bisa diklik
            
            // Auto hilang dalam 3 detik
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
</body>
</html>