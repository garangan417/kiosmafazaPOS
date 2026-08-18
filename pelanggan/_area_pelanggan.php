<!-- pelanggan/_area_pelanggan.php -->
<div id="area-pelanggan">

  <!-- Alert Status -->
  <?php if (!empty($successMsg)): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm mb-3" role="alert">
      <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($successMsg); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <?php if (!empty($errorMsg)): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-3" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($errorMsg); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <div class="card shadow-sm border-0 p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h5 class="card-title mb-0 fw-bold">Daftar Pelanggan</h5>
      <span class="badge bg-secondary">Total: <?= count($dataPelanggan); ?></span>
    </div>

    <div class="table-responsive">
      <table class="table table-hover align-middle">
        <thead class="table-light">
          <tr>
            <th>Nama Pelanggan</th>
            <th>No. HP</th>
            <th>Status Utang</th>
            <th class="text-center" style="width: 140px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($dataPelanggan)): ?>
            <tr>
              <td colspan="4" class="text-center text-muted py-3">Belum ada data pelanggan.</td>
            </tr>
          <?php else: ?>
            <?php foreach ($dataPelanggan as $row): ?>
              <?php $hasUtang = $row['sisa_utang'] > 0; ?>
              <tr>
                <td class="fw-bold text-dark">
                  <?= htmlspecialchars($row['nama']); ?><br>
                  <small class="text-muted fw-normal"><?= htmlspecialchars($row['alamat'] ?: '-'); ?></small>
                </td>
                <td>
                  <?php if (!empty($row['no_hp'])): ?>
                    <a href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $row['no_hp']); ?>" target="_blank" class="text-decoration-none text-success fw-semibold">
                      <i class="bi bi-whatsapp me-1"></i><?= htmlspecialchars($row['no_hp']); ?>
                    </a>
                  <?php else: ?>
                    <span class="text-muted">-</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($hasUtang): ?>
                    <span class="badge bg-danger fs-6"><?= formatRupiah($row['sisa_utang']); ?></span>
                  <?php else: ?>
                    <span class="badge bg-success-subtle text-success border border-success">Lunas (Rp 0)</span>
                  <?php endif; ?>
                </td>
                <td class="text-center">
                  <!-- Tombol Detail Utang -->
                  <button class="btn btn-sm btn-outline-info me-1" 
                          hx-get="<?= BASE_URL; ?>pelanggan/detail_utang.php?id=<?= $row['id']; ?>"
                          hx-target="#container-modal-detail"
                          hx-swap="innerHTML"
                          hx-on::after-request="if(event.detail.successful) { new bootstrap.Modal(document.getElementById('modalDetailUtang')).show(); }"
                          title="Detail Riwayat Utang">
                    <i class="bi bi-receipt"></i>
                  </button>

                  <!-- Tombol Edit -->
                  <button class="btn btn-sm btn-outline-primary me-1" 
                          data-bs-toggle="modal" 
                          data-bs-target="#modalEdit<?= $row['id']; ?>"
                          title="Edit">
                    <i class="bi bi-pencil-square"></i>
                  </button>

                  <!-- Form Hapus Pelanggan -->
                  <form hx-post="<?= BASE_URL; ?>pelanggan/index.php" 
                        hx-target="#area-pelanggan" 
                        hx-swap="outerHTML" 
                        hx-confirm="<?= $hasUtang ? 'PERHATIAN: Pelanggan ini punya utang ' . formatRupiah($row['sisa_utang']) . '. Yakin mencoba hapus?' : 'Yakin ingin menghapus pelanggan ' . htmlspecialchars($row['nama']) . '?'; ?>"
                        class="d-inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $row['id']; ?>">
                    <button type="submit" 
                            class="btn btn-sm <?= $hasUtang ? 'btn-outline-secondary opacity-50' : 'btn-outline-danger'; ?>" 
                            title="<?= $hasUtang ? 'Tidak bisa dihapus (Masih ada utang)' : 'Hapus Pelanggan'; ?>">
                      <i class="bi bi-trash"></i>
                    </button>
                  </form>
                </td>
              </tr>

              <!-- Modal Edit -->
              <div class="modal fade" id="modalEdit<?= $row['id']; ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h5 class="modal-title fw-bold">Edit Pelanggan</h5>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form hx-post="<?= BASE_URL; ?>pelanggan/index.php" 
                          hx-target="#area-pelanggan" 
                          hx-swap="outerHTML"
                          hx-on::after-request="bootstrap.Modal.getInstance(document.getElementById('modalEdit<?= $row['id']; ?>')).hide();">
                      <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="<?= $row['id']; ?>">

                        <div class="mb-3">
                          <label class="form-label small fw-semibold">Nama Lengkap</label>
                          <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($row['nama']); ?>" required>
                        </div>

                        <div class="mb-3">
                          <label class="form-label small fw-semibold">No. WhatsApp / HP</label>
                          <input type="text" name="no_hp" class="form-control" value="<?= htmlspecialchars($row['no_hp']); ?>">
                        </div>

                        <div class="mb-3">
                          <label class="form-label small fw-semibold">Alamat / Catatan</label>
                          <textarea name="alamat" class="form-control" rows="2"><?= htmlspecialchars($row['alamat']); ?></textarea>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary btn-sm">Simpan Perubahan</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>