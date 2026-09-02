<?php
// Menerima variabel $pagination dari controller/halaman
if (!isset($pagination) || $pagination['total_pages'] <= 1) return;

$currentPage = $pagination['current_page'];
$totalPages  = $pagination['total_pages'];

// Ambil parameter URL yang ada (search, kategori, dll) agar tidak hilang saat ganti halaman
$queryParams = $_GET;
?>

<div class="d-flex justify-content-between align-items-center mt-3 px-2">
  <small class="text-muted">
    Menampilkan <strong><?= $pagination['from']; ?>-<?= $pagination['to']; ?></strong> dari <strong><?= $pagination['total_items']; ?></strong> data
  </small>

  <nav>
    <ul class="pagination pagination-sm mb-0">
      <!-- Tombol Prev -->
      <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : ''; ?>">
        <?php $queryParams['page'] = $currentPage - 1; ?>
        <a class="page-item" href="?<?= http_build_query($queryParams); ?>" class="page-link">Previous</a>
      </li>

      <!-- Angka Halaman -->
      <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <?php 
          // Tampilkan 2 halaman di sekitar halaman aktif saja jika halaman banyak
          if ($i == 1 || $i == $totalPages || abs($i - $currentPage) <= 1): 
            $queryParams['page'] = $i;
        ?>
          <li class="page-item <?= ($i === $currentPage) ? 'active' : ''; ?>">
            <a class="page-link" href="?<?= http_build_query($queryParams); ?>"><?= $i; ?></a>
          </li>
        <?php elseif ($i == 2 || $i == $totalPages - 1): ?>
          <li class="page-item disabled"><span class="page-link">…</span></li>
        <?php endif; ?>
      <?php endfor; ?>

      <!-- Tombol Next -->
      <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : ''; ?>">
        <?php $queryParams['page'] = $currentPage + 1; ?>
        <a class="page-link" href="?<?= http_build_query($queryParams); ?>">Next</a>
      </li>
    </ul>
  </nav>
</div>