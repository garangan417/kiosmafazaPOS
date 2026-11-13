<?php
// stok/hubungkan.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';
require_once BASE_PATH . 'database/query_stok_v2.php'; // Panggil mesin V2

// ============================================================
// HANDLER POST: TAMBAH JEMBATAN
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'hubungkan') {
    $sumberId = intval($_POST['kemasan_sumber'] ?? 0);
    $tujuanId = intval($_POST['kemasan_tujuan'] ?? 0);
    $isiKonversi = intval($_POST['isi_konversi'] ?? 1);

    if ($sumberId > 0 && $tujuanId > 0 && $isiKonversi > 0) {
        try {
            // Cek apakah sudah ada jembatan untuk tujuan ini
            $stmtCek = $pdoBarang->prepare("SELECT id FROM jembatan_stok WHERE kemasan_tujuan_id = ?");
            $stmtCek->execute([$tujuanId]);
            if ($stmtCek->fetchColumn()) {
                $_SESSION['toast_error'] = "Barang tujuan ini sudah terhubung dengan barang lain!";
            } else {
                $stmt = $pdoBarang->prepare("INSERT INTO jembatan_stok (kemasan_sumber_id, kemasan_tujuan_id, isi_konversi, is_aktif_otomatis) VALUES (?, ?, ?, 0)");
                $stmt->execute([$sumberId, $tujuanId, $isiKonversi]);
                $_SESSION['toast_success'] = "Barang berhasil dihubungkan!";
            }
        } catch (PDOException $e) {
            $_SESSION['toast_error'] = "Gagal menghubungkan: " . $e->getMessage();
        }
    } else {
        $_SESSION['toast_error'] = "Data tidak lengkap!";
    }
    header("Location: hubungkan.php");
    exit;
}

// ============================================================
// HANDLER POST: PUTUSKAN JEMBATAN
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'putuskan') {
    $id = intval($_POST['id'] ?? 0);
    if ($id > 0) {
        $pdoBarang->prepare("DELETE FROM jembatan_stok WHERE id = ?")->execute([$id]);
        $_SESSION['toast_success'] = "Hubungan berhasil diputuskan!";
    }
    header("Location: hubungkan.php");
    exit;
}

// ============================================================
// HANDLER AJAX: TOGGLE SWITCH OTOMATIS (DENGAN AUTO-FILL STOK)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_otomatis') {
    header('Content-Type: application/json');
    $id = intval($_POST['id'] ?? 0);
    $status = intval($_POST['status'] ?? 0);
    
    if ($id > 0) {
        try {
            // 1. Update status saklar
            $stmt = $pdoBarang->prepare("UPDATE jembatan_stok SET is_aktif_otomatis = ? WHERE id = ?");
            $stmt->execute([$status, $id]);

            // 2. Jika saklar dinyalakan (status = 1), cek stok Etalase
            if ($status == 1) {
                // Ambil data jembatan
                $stmtJ = $pdoBarang->prepare("SELECT * FROM jembatan_stok WHERE id = ?");
                $stmtJ->execute([$id]);
                $jembatan = $stmtJ->fetch(PDO::FETCH_ASSOC);

                if ($jembatan) {
                    $kemasanTujuanId = (int) $jembatan['kemasan_tujuan_id'];
                    $kemasanSumberId = (int) $jembatan['kemasan_sumber_id'];
                    $isiKonversi = (int) $jembatan['isi_konversi'];

                    // Cek stok Etalase saat ini
                    $stmtT = $pdoBarang->prepare("SELECT barang_id FROM barang_kemasan WHERE id = ?");
                    $stmtT->execute([$kemasanTujuanId]);
                    $barangTujuanId = (int) $stmtT->fetchColumn();

                    $stokEtalase = getStokBase($pdoBarang, $barangTujuanId);

                    // JIKA STOK 0 (atau kurang dari 1), LANGSUNG TARIK 1 DARI GUDANG
                    if ($stokEtalase <= 0) {
                        $res = transferStokManual($pdoBarang, $kemasanSumberId, $kemasanTujuanId, 1, $isiKonversi);
                        if ($res['ok']) {
                            echo json_encode(['status' => 'success', 'message' => 'Otomatis aktif & stok berhasil diisi ulang!']);
                            exit;
                        } else {
                            echo json_encode(['status' => 'error', 'message' => 'Gagal isi ulang: ' . $res['error']]);
                            exit;
                        }
                    }
                }
            }

            echo json_encode(['status' => 'success', 'message' => 'Status berhasil diubah.']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'ID tidak valid.']);
    }
    exit;
}

// ============================================================
// AMBIL DATA UNTUK DROPDOWN
// ============================================================
$listBarang = $pdoBarang->query("
    SELECT bk.id AS kemasan_id, b.nama_barang, bk.nama_kemasan, bk.satuan, bk.isi
    FROM barang_kemasan bk
    JOIN barang b ON bk.barang_id = b.id
    ORDER BY b.nama_barang ASC
")->fetchAll();

// Ambil daftar jembatan yang sudah ada
$listJembatan = $pdoBarang->query("
    SELECT j.*, 
           b1.nama_barang AS nama_sumber, k1.nama_kemasan AS kemasan_sumber, k1.satuan AS satuan_sumber,
           b2.nama_barang AS nama_tujuan, k2.nama_kemasan AS kemasan_tujuan, k2.satuan AS satuan_tujuan
    FROM jembatan_stok j
    JOIN barang_kemasan k1 ON j.kemasan_sumber_id = k1.id
    JOIN barang b1 ON k1.barang_id = b1.id
    JOIN barang_kemasan k2 ON j.kemasan_tujuan_id = k2.id
    JOIN barang b2 ON k2.barang_id = b2.id
")->fetchAll();

require_once BASE_PATH . 'partials/header.php';
?>

<main class="container my-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="fw-bold mb-0"><i class="bi bi-link-45deg me-2 text-primary"></i>Hubungkan Barang (Jembatan Stok)</h4>
      <p class="text-muted small mb-0">Hubungkan barang sumber dan tujuan agar tombol "Minta Stok" muncul otomatis.</p>
    </div>
    <a href="<?= BASE_URL; ?>stok/index.php" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i> Kembali ke Stok
    </a>
  </div>

  <div class="row g-4">
    <!-- Form Hubungkan -->
    <div class="col-md-5">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-dark text-white py-3">
          <h6 class="mb-0 fw-bold">Buat Hubungan Baru</h6>
        </div>
        <div class="card-body">
          <form action="" method="POST">
            <input type="hidden" name="action" value="hubungkan">
            
            <div class="mb-3">
              <label class="form-label fw-bold">1. Barang Sumber (Gudang)</label>
              <select name="kemasan_sumber" class="form-select" required>
                <option value="">-- Pilih Sumber --</option>
                <?php foreach ($listBarang as $b): ?>
                  <option value="<?= $b['kemasan_id']; ?>">
                    <?= htmlspecialchars($b['nama_barang']); ?> - <?= htmlspecialchars($b['nama_kemasan']); ?> (<?= $b['isi']; ?> <?= htmlspecialchars($b['satuan']); ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label fw-bold">2. Barang Tujuan (Etalase)</label>
              <select name="kemasan_tujuan" class="form-select" required>
                <option value="">-- Pilih Tujuan --</option>
                <?php foreach ($listBarang as $b): ?>
                  <option value="<?= $b['kemasan_id']; ?>">
                    <?= htmlspecialchars($b['nama_barang']); ?> - <?= htmlspecialchars($b['nama_kemasan']); ?> (<?= $b['isi']; ?> <?= htmlspecialchars($b['satuan']); ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label fw-bold">3. Isi Konversi (1 Sumber = ? Tujuan)</label>
              <input type="number" name="isi_konversi" class="form-control" value="12" min="1" required>
              <div class="extra-small text-muted mt-1">Contoh: Jika 1 PAK = 12 BATANG, isi angka 12.</div>
            </div>

            <button type="submit" class="btn btn-primary w-100 fw-bold" onclick="return confirm('Hubungkan kedua barang ini?');">
              <i class="bi bi-link me-1"></i> Hubungkan Barang
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- Tabel Hubungan Aktif -->
    <div class="col-md-7">
      <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
          <h6 class="mb-0 fw-bold text-dark">Daftar Barang yang Sudah Terhubung</h6>
        </div>
        <div class="card-body p-0">
          <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
              <tr>
                <th>Sumber (Gudang)</th>
                <th>Tujuan (Etalase)</th>
                <th class="text-center">Konversi</th>
                <th class="text-center">Mode</th>
                <th class="text-end">Putuskan</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($listJembatan)): ?>
                <tr>
                  <td colspan="5" class="text-center text-muted py-4">Belum ada barang yang dihubungkan.</td>
                </tr>
              <?php else: ?>
                <?php foreach ($listJembatan as $j): ?>
                  <tr>
                    <td>
                      <strong class="d-block text-dark"><?= htmlspecialchars($j['nama_sumber']); ?></strong>
                      <span class="badge bg-info-subtle text-info border border-info"><?= htmlspecialchars($j['kemasan_sumber']); ?></span>
                    </td>
                    <td>
                      <strong class="d-block text-dark"><?= htmlspecialchars($j['nama_tujuan']); ?></strong>
                      <span class="badge bg-success-subtle text-success border border-success"><?= htmlspecialchars($j['kemasan_tujuan']); ?></span>
                    </td>
                    <td class="text-center fw-bold font-monospace">
                      1 : <?= $j['isi_konversi']; ?>
                    </td>
                    <td class="text-center">
                      <div class="form-check form-switch d-flex align-items-center justify-content-center">
                        <input class="form-check-input me-2" type="checkbox" role="switch" 
                               id="switchOtomatis<?= $j['id']; ?>" 
                               <?= $j['is_aktif_otomatis'] == 1 ? 'checked' : ''; ?>
                               onchange="toggleOtomatis(<?= $j['id']; ?>, this.checked)">
                        <label class="form-check-label small fw-bold" for="switchOtomatis<?= $j['id']; ?>" style="min-width: 60px; text-align: left;">
                            <?= $j['is_aktif_otomatis'] == 1 ? 'Otomatis' : 'Manual'; ?>
                        </label>
                      </div>
                    </td>
                    <td class="text-end">
                      <form action="" method="POST" onsubmit="return confirm('Putuskan hubungan ini?');">
                        <input type="hidden" name="action" value="putuskan">
                        <input type="hidden" name="id" value="<?= $j['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</main>

<script>
const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 1500, timerProgressBar: true });

<?php if (isset($_SESSION['toast_success'])): ?>
    Toast.fire({ icon: 'success', title: '<?= htmlspecialchars($_SESSION['toast_success']); ?>' });
    <?php unset($_SESSION['toast_success']); ?>
<?php endif; ?>
<?php if (isset($_SESSION['toast_error'])): ?>
    Toast.fire({ icon: 'error', title: '<?= htmlspecialchars($_SESSION['toast_error']); ?>' });
    <?php unset($_SESSION['toast_error']); ?>
<?php endif; ?>

function toggleOtomatis(id, status) {
    fetch('hubungkan.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=toggle_otomatis&id=${id}&status=${status ? 1 : 0}`
    })
    .then(res => res.json())
    .then(res => {
        if (res.status === 'success') {
            Toast.fire({ icon: 'success', title: res.message });
            // Update label teks di sebelah switch
            const label = document.querySelector(`label[for="switchOtomatis${id}"]`);
            if (label) {
                label.innerText = status ? 'Otomatis' : 'Manual';
            }
            // Refresh tabel agar stok terbaru muncul (jika auto-fill berjalan)
            setTimeout(() => location.reload(), 1000);
        } else {
            Swal.fire('Gagal!', res.message, 'error');
        }
    })
    .catch(err => {
        Swal.fire('Error!', 'Terjadi kesalahan jaringan.', 'error');
    });
}
</script>

<?php require_once BASE_PATH . 'partials/footer.php'; ?>
