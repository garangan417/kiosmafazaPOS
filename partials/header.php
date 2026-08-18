<?php
// Pastikan BASE_URL sudah terdefinisi
if (!defined('BASE_URL')) {
    define('BASE_URL', '/');
}

// Set timezone default agar jam PHP sinkron dengan server
date_default_timezone_set('Asia/Makassar');

// Deteksi URL aktif untuk indikator menu active
$current_page = $_SERVER['REQUEST_URI'];
?>
<!DOCTYPE html>
<html lang="id" class="h-100">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Kios App</title>

  <!-- CSS Assets (Lokal) -->
  <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
  <link rel="stylesheet" href="/assets/css/style.css">

<style>
/* CSS Tampilan Khusus Struk 58mm */
.receipt-58mm {
  font-family: 'Courier New', Courier, monospace;
  font-size: 12px;
  line-height: 1.2;
  color: #000;
  background: #fff;
  padding: 5px;
}

.border-top-dashed {
  border-top: 1px dashed #000;
}

.receipt-table td {
  padding: 2px 0;
  vertical-align: top;
}

/* ========================================== */
/* ATURAN MEDIA PRINT UNTUK PRINTER 58MM      */
/* ========================================== */
@media print {
  /* Sembunyikan seluruh elemen di layar saat print */
  body * {
    visibility: hidden;
  }
  
  /* Tampilkan HANYA area receipt */
  #receiptArea, #receiptArea * {
    visibility: visible;
  }

  #receiptArea {
    position: absolute;
    left: 0;
    top: 0;
    width: 48mm; /* Lebar printable thermal 58mm */
    margin: 0;
    padding: 0;
  }

  @page {
    size: 58mm auto; /* Ukuran kertas thermal 58mm, panjang otomatis */
    margin: 0;
  }
}
</style>

  <!-- HTMX JS (Lokal) -->
  <script src="/assets/js/htmx.min.js" defer></script>
</head>
<body class="d-flex flex-column h-100 bg-light">

<!-- GLOBAL FLOATING ALERT CONTAINER (Melayang di pojok kanan atas) -->
<div id="globalToastContainer" 
     class="position-fixed top-0 end-0 p-3" 
     style="z-index: 9999; pointer-events: none; max-width: 400px; width: 100%;">
</div>

<header class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm sticky-top">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?= BASE_URL; ?>">Kios App</a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto gap-lg-1 align-items-lg-center">
        
        <!-- Widget Waktu Server (Di dalam UL agar HTML Valid) -->
        <li class="nav-item me-lg-2 my-2 my-lg-0">
          <div class="badge bg-secondary bg-opacity-25 text-light fw-normal border border-secondary px-3 py-1 text-start text-lg-center">
            <small class="d-block text-muted" style="font-size: 0.65rem; line-height: 1;">WAKTU SERVER</small>
            <span id="server-clock-date" class="me-2 text-warning fw-bold"><?= date('d/m/Y'); ?></span>
            <span id="server-clock-time" class="font-monospace fw-bold">00:00:00</span>
          </div>
        </li>

        <!-- Menu Home / Beranda -->
        <li class="nav-item">
          <a class="nav-link <?= (stristr($current_page, 'index.php') || $current_page === BASE_URL || $current_page === BASE_URL . 'index.php') ? 'active' : ''; ?>" 
             href="<?= BASE_URL; ?>">Home</a>
        </li>

        <!-- Menu Pelanggan -->
        <li class="nav-item">
          <a class="nav-link <?= stristr($current_page, '/pelanggan/') ? 'active' : ''; ?>" 
             href="<?= BASE_URL; ?>pelanggan/">Pelanggan</a>
        </li>

        <!-- Menu Utang / Piutang -->
        <li class="nav-item">
          <a class="nav-link <?= stristr($current_page, '/keuangan/') ? 'active' : ''; ?>" 
             href="<?= BASE_URL; ?>keuangan/">Transaksi</a>
        </li>

        <!-- Dropdown Kelola Barang -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= stristr($current_page, '/barang/') ? 'active' : ''; ?>" 
             href="#" 
             id="navbarDropdownBarang" 
             role="button" 
             data-bs-toggle="dropdown" 
             aria-expanded="false">
            Kelola Barang
          </a>
          <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="navbarDropdownBarang">
            <li>
              <a class="dropdown-item <?= (stristr($current_page, '/barang/') && !stristr($current_page, 'harga.php') && !stristr($current_page, 'kategori.php')) ? 'active' : ''; ?>" 
                 href="<?= BASE_URL; ?>barang/">
                <i class="bi bi-box-seam me-2"></i>Data Barang
              </a>
            </li>
            <li>
              <a class="dropdown-item <?= stristr($current_page, 'kategori.php') ? 'active' : ''; ?>" 
                 href="<?= BASE_URL; ?>barang/kategori.php">
                <i class="bi bi-tags me-2"></i>Kategori Barang
              </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <a class="dropdown-item <?= stristr($current_page, 'harga.php') ? 'active' : ''; ?>" 
                 href="<?= BASE_URL; ?>barang/harga.php">
                <i class="bi bi-tag me-2"></i>Atur Harga Barang
              </a>
            </li>
          </ul>
        </li>

        <!-- Dropdown Kasir & Stok -->
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle <?= (stristr($current_page, '/kasir/') || stristr($current_page, '/stok/')) ? 'active' : ''; ?>" 
             href="#" 
             id="navbarDropdownKasir" 
             role="button" 
             data-bs-toggle="dropdown" 
             aria-expanded="false">
            Kasir
          </a>
          <ul class="dropdown-menu dropdown-menu-dark" aria-labelledby="navbarDropdownKasir">
            <li>
              <a class="dropdown-item <?= stristr($current_page, '/kasir/') && !stristr($current_page, 'laporan.php') ? 'active' : ''; ?>" 
                 href="<?= BASE_URL; ?>kasir/">
                <i class="bi bi-wallet2 me-2"></i>Kasir
              </a>
            </li>
            <li>
              <a class="dropdown-item <?= stristr($current_page, '/stok/') ? 'active' : ''; ?>" 
                 href="<?= BASE_URL; ?>stok/">
                <i class="bi bi-boxes me-2"></i>Stok
              </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
              <a class="dropdown-item <?= stristr($current_page, 'kasir/laporan.php') ? 'active' : ''; ?>" 
                 href="<?= BASE_URL; ?>kasir/laporan.php">
                <i class="bi bi-file-earmark-text me-2"></i>Laporan
              </a>
            </li>
          </ul>
        </li>

      </ul>
    </div>
  </div>
</header>

<script>
document.addEventListener("DOMContentLoaded", function() {
  let serverTime = <?= floor(microtime(true) * 1000); ?>;

  function updateClock() {
    serverTime += 1000;
    let d = new Date(serverTime);

    let hh = String(d.getHours()).padStart(2, '0');
    let mm = String(d.getMinutes()).padStart(2, '0');
    let ss = String(d.getSeconds()).padStart(2, '0');
    
    let day = String(d.getDate()).padStart(2, '0');
    let month = String(d.getMonth() + 1).padStart(2, '0');
    let year = d.getFullYear();

    let clockTimeElem = document.getElementById('server-clock-time');
    let clockDateElem = document.getElementById('server-clock-date');

    if (clockTimeElem) clockTimeElem.innerText = `${hh}:${mm}:${ss}`;
    if (clockDateElem) clockDateElem.innerText = `${day}/${month}/${year}`;
  }

  updateClock();
  setInterval(updateClock, 1000);
});
</script>