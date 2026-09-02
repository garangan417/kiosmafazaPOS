<?php
// Menerima variabel $listKategori, $kategoriId, $searchQuery, dan $barcodeFromUrl dari halaman utama
$kategoriId     = $kategoriId ?? 0;
$searchQuery    = $searchQuery ?? '';
$barcodeFromUrl = $barcodeFromUrl ?? '';
?>

<form action="" method="GET" class="d-flex gap-2">
  <?php if (!empty($barcodeFromUrl)): ?>
    <input type="hidden" name="barcode" value="<?= htmlspecialchars($barcodeFromUrl); ?>">
  <?php endif; ?>
  
  <!-- Dropdown Kategori -->
  <select name="kategori_id" class="form-select form-select-sm" style="max-width: 160px;" onchange="this.form.submit()">
    <option value="0">-- Semua Kategori --</option>
    <?php foreach ($listKategori as $kat): ?>
      <option value="<?= $kat['id']; ?>" <?= ($kategoriId === (int)$kat['id']) ? 'selected' : ''; ?>>
        <?= htmlspecialchars($kat['nama_kategori']); ?>
      </option>
    <?php endforeach; ?>
  </select>

  <!-- Input Search Text -->
  <div class="input-group input-group-sm">
    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
    <input type="text" 
           name="q" 
           class="form-control border-start-0 ps-0" 
           placeholder="Cari barang / barcode..." 
           value="<?= htmlspecialchars($searchQuery); ?>">
    
    <?php if (!empty($searchQuery) || $kategoriId > 0): ?>
      <a href="<?= $_SERVER['PHP_SELF'] . (!empty($barcodeFromUrl) ? '?barcode=' . urlencode($barcodeFromUrl) : ''); ?>" 
         class="btn btn-outline-secondary" 
         title="Reset Filter">
        <i class="bi bi-x-lg"></i>
      </a>
    <?php endif; ?>
    <button type="submit" class="btn btn-dark">Cari</button>
  </div>
</form>