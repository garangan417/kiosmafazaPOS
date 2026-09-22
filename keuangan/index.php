<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db.php';

// ============================================================
// KONFIGURASI PAGINASI
// ============================================================
$limit = 30; // Transaksi per halaman

// Cek apakah request datang dari HTMX
$isHtmx = isset($_SERVER['HTTP_HX_REQUEST']);

// ============================================================
// HANDLE HTMX GET (PAGINASI VIA HTMX)
// ============================================================
if ($isHtmx && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $page   = max(1, intval($_GET['page'] ?? 1));
    $offset = ($page - 1) * $limit;

    // Hitung total transaksi
    $stmtCount  = $pdo->query("SELECT COUNT(*) FROM transaksi");
    $totalRows  = (int) $stmtCount->fetchColumn();
    $totalPages = max(1, (int) ceil($totalRows / $limit));

    // Clamp page
    if ($page > $totalPages) {
        $page   = $totalPages;
        $offset = ($page - 1) * $limit;
    }

    // Query transaksi dengan LIMIT + OFFSET
    $stmtTx = $pdo->prepare("
        SELECT id, tanggal, tipe, kategori, nominal, saldo_akhir, keterangan 
        FROM transaksi 
        ORDER BY tanggal DESC, id DESC 
        LIMIT ? OFFSET ?
    ");
    $stmtTx->bindValue(1, $limit, PDO::PARAM_INT);
    $stmtTx->bindValue(2, $offset, PDO::PARAM_INT);
    $stmtTx->execute();
    $transaksi = $stmtTx->fetchAll(PDO::FETCH_ASSOC);

    // Ambil saldo terkini
    $stmtCurrentSaldo = $pdo->query("SELECT saldo_akhir FROM transaksi ORDER BY id DESC LIMIT 1");
    $currentSaldoRow  = $stmtCurrentSaldo->fetch(PDO::FETCH_ASSOC);
    $saldoKas         = $currentSaldoRow ? floatval($currentSaldoRow['saldo_akhir']) : 0;

    // Susun array paginasi
    $pagination = [
        'total_items'  => $totalRows,
        'total_pages'  => $totalPages,
        'current_page' => $page,
        'per_page'     => $limit,
        'from'         => $totalRows > 0 ? $offset + 1 : 0,
        'to'           => min($offset + $limit, $totalRows),
    ];

    // Render partial saja (target HTMX swap)
    renderAreaKeuangan($saldoKas, $transaksi, $pagination);
    exit;
}

// ============================================================
// PROSES SIMPAN TRANSAKSI (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipe       = $_POST['tipe'] ?? 'masuk';
    $tanggal    = !empty($_POST['tanggal']) ? $_POST['tanggal'] : date('Y-m-d');
    $kategori   = trim($_POST['kategori'] ?? '');
    
    // Bersihkan titik pemisah ribuan dari input nominal
    $rawNominal = str_replace('.', '', $_POST['nominal'] ?? '0');
    $nominal    = floatval($rawNominal);
    $keterangan = trim($_POST['keterangan'] ?? '');

    $errorMsg   = '';
    $successMsg = '';

    if ($nominal > 0 && !empty($kategori)) {
        // 1. Ambil saldo_akhir dari transaksi paling terakhir
        $stmtLast = $pdo->query("SELECT saldo_akhir FROM transaksi ORDER BY id DESC LIMIT 1");
        $lastRow  = $stmtLast->fetch(PDO::FETCH_ASSOC);
        
        $saldoTerakhir = $lastRow ? floatval($lastRow['saldo_akhir']) : 0;

        // 2. Hitung saldo_akhir baru & Validasi Uang Keluar
        if ($tipe === 'masuk') {
            $saldoAkhirBaru = $saldoTerakhir + $nominal;
        } else {
            if ($nominal > $saldoTerakhir) {
                $errorMsg = "Gagal: Nominal keluar (" . formatRupiah($nominal) . ") melebihi saldo (" . formatRupiah($saldoTerakhir) . ")!";
            } else {
                $saldoAkhirBaru = $saldoTerakhir - $nominal;
            }
        }

        // 3. Simpan ke database jika tidak ada error
        if (empty($errorMsg)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO transaksi (tanggal, tipe, kategori, nominal, saldo_akhir, keterangan) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$tanggal, $tipe, $kategori, $nominal, $saldoAkhirBaru, $keterangan]);
                $successMsg = "Transaksi berhasil disimpan!";
            } catch (PDOException $e) {
                $errorMsg = "Gagal menyimpan transaksi: " . $e->getMessage();
            }
        }
    } else {
        $errorMsg = "Nominal dan Kategori wajib diisi dengan benar!";
    }

    // ============================================================
    // A. PENANGANAN REQUEST VIA HTMX
    // ============================================================
    if ($isHtmx) {
        // Setelah simpan, selalu balik ke halaman 1 (transaksi terbaru di atas)
        $page   = 1;
        $offset = 0;

        // Query transaksi dengan LIMIT + OFFSET
        $stmtTx = $pdo->prepare("
            SELECT id, tanggal, tipe, kategori, nominal, saldo_akhir, keterangan 
            FROM transaksi 
            ORDER BY tanggal DESC, id DESC 
            LIMIT ? OFFSET ?
        ");
        $stmtTx->bindValue(1, $limit, PDO::PARAM_INT);
        $stmtTx->bindValue(2, $offset, PDO::PARAM_INT);
        $stmtTx->execute();
        $transaksi = $stmtTx->fetchAll(PDO::FETCH_ASSOC);

        // Ambil saldo terkini
        $stmtCurrentSaldo = $pdo->query("SELECT saldo_akhir FROM transaksi ORDER BY id DESC LIMIT 1");
        $currentSaldoRow  = $stmtCurrentSaldo->fetch(PDO::FETCH_ASSOC);
        $saldoKas         = $currentSaldoRow ? floatval($currentSaldoRow['saldo_akhir']) : 0;

        // Query COUNT ulang (karena data bertambah)
        $stmtCount  = $pdo->query("SELECT COUNT(*) FROM transaksi");
        $totalRows  = (int) $stmtCount->fetchColumn();
        $totalPages = max(1, (int) ceil($totalRows / $limit));

        $pagination = [
            'total_items'  => $totalRows,
            'total_pages'  => $totalPages,
            'current_page' => 1,
            'per_page'     => $limit,
            'from'         => $totalRows > 0 ? 1 : 0,
            'to'           => min($limit, $totalRows),
        ];

        if (!empty($successMsg)) {
            header('HX-Trigger: ' . json_encode(['showToast' => ['icon' => 'success', 'title' => $successMsg]]));
        } elseif (!empty($errorMsg)) {
            header('HX-Trigger: ' . json_encode(['showToast' => ['icon' => 'error', 'title' => $errorMsg]]));
        }

        renderAreaKeuangan($saldoKas, $transaksi, $pagination);
        exit;
    }

    // ============================================================
    // B. FALLBACK REDIRECT / PRG (tanpa HTMX)
    // ============================================================
    if (!empty($successMsg)) {
        $_SESSION['toast_success'] = $successMsg;
    }
    if (!empty($errorMsg)) {
        $_SESSION['toast_error'] = $errorMsg;
    }

    header("Location: " . BASE_URL . "keuangan/");
    exit;
}

// ============================================================
// GET BIASA: RENDER FULL PAGE
// ============================================================

// Setup paginasi
$page   = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Query COUNT
$stmtCount  = $pdo->query("SELECT COUNT(*) FROM transaksi");
$totalRows  = (int) $stmtCount->fetchColumn();
$totalPages = max(1, (int) ceil($totalRows / $limit));

if ($page > $totalPages) {
    $page   = $totalPages;
    $offset = ($page - 1) * $limit;
}

// Query transaksi dengan LIMIT + OFFSET
$stmtTx = $pdo->prepare("
    SELECT id, tanggal, tipe, kategori, nominal, saldo_akhir, keterangan 
    FROM transaksi 
    ORDER BY tanggal DESC, id DESC 
    LIMIT ? OFFSET ?
");
$stmtTx->bindValue(1, $limit, PDO::PARAM_INT);
$stmtTx->bindValue(2, $offset, PDO::PARAM_INT);
$stmtTx->execute();
$transaksi = $stmtTx->fetchAll(PDO::FETCH_ASSOC);

// Ambil saldo terkini
$stmtCurrentSaldo = $pdo->query("SELECT saldo_akhir FROM transaksi ORDER BY id DESC LIMIT 1");
$currentSaldoRow  = $stmtCurrentSaldo->fetch(PDO::FETCH_ASSOC);
$saldoKas         = $currentSaldoRow ? floatval($currentSaldoRow['saldo_akhir']) : 0;

// Susun array paginasi
$pagination = [
    'total_items'  => $totalRows,
    'total_pages'  => $totalPages,
    'current_page' => $page,
    'per_page'     => $limit,
    'from'         => $totalRows > 0 ? $offset + 1 : 0,
    'to'           => min($offset + $limit, $totalRows),
];

require_once BASE_PATH . 'partials/header.php';
?>

<main class="container my-4 flex-grow-1">

  <div class="row">
    <!-- Form Input Transaksi (Menggunakan HTMX) -->
    <div class="col-lg-4 mb-4">
      <div class="card shadow-sm border-0 p-3">
        <h5 class="card-title mb-3 fw-bold">Tambah Transaksi</h5>
        
        <form hx-post="<?= BASE_URL; ?>keuangan/index.php" 
              hx-target="#area-keuangan" 
              hx-swap="outerHTML"
              hx-on::after-request="if(event.detail.successful) { this.reset(); setBrowserDate(); }">
          
          <div class="mb-3">
            <label class="form-label d-block fw-semibold">Jenis Transaksi</label>
            <div class="btn-group w-100" role="group">
              <input type="radio" class="btn-check" name="tipe" id="tipeMasuk" value="masuk" checked>
              <label class="btn btn-outline-success" for="tipeMasuk">Uang Masuk</label>

              <input type="radio" class="btn-check" name="tipe" id="tipeKeluar" value="keluar">
              <label class="btn btn-outline-danger" for="tipeKeluar">Uang Keluar</label>
            </div>
          </div>

          <!-- INPUT TANGGAL (NILAI DISET VIA JAVASCRIPT BROWSER) -->
          <div class="mb-3">
            <label class="form-label small fw-semibold">Tanggal</label>
            <input type="date" id="inputTanggal" name="tanggal" class="form-control" required>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Kategori</label>
            <input type="text" name="kategori" class="form-control" placeholder="Contoh: Penjualan Kios / Beli Stok" required>
          </div>

          <!-- INPUT NOMINAL DENGAN AUTO FORMAT TITIK -->
          <div class="mb-3">
            <label class="form-label small fw-semibold">Nominal (Rp)</label>
            <div class="input-group">
              <span class="input-group-text fw-bold">Rp</span>
              <input type="text" 
                     id="inputNominal" 
                     name="nominal" 
                     class="form-control fw-bold fs-5 text-end" 
                     placeholder="0" 
                     onkeyup="formatInputRibuan(this)" 
                     required 
                     autocomplete="off">
            </div>
            <div class="form-text text-muted">Titik otomatis muncul saat mengetik agar nol tidak tertukar.</div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Keterangan (Opsional)</label>
            <textarea name="keterangan" class="form-control" rows="2" placeholder="Catatan tambahan..."></textarea>
          </div>

          <button type="submit" class="btn btn-dark w-100 d-flex align-items-center justify-content-center gap-2">
            <span>Simpan Transaksi</span>
            <!-- Indicator Loading HTMX -->
            <div class="spinner-border spinner-border-sm htmx-indicator" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
          </button>
        </form>
      </div>
    </div>

    <!-- Area Card & Tabel Riwayat yang di-target HTMX -->
    <div class="col-lg-8">
      <?php renderAreaKeuangan($saldoKas, $transaksi, $pagination); ?>
    </div>
  </div>

</main>

<script>
  // Set tanggal otomatis dari Browser (Client-side)
  function setBrowserDate() {
    const today = new Date();
    const yyyy = today.getFullYear();
    const mm = String(today.getMonth() + 1).padStart(2, '0');
    const dd = String(today.getDate()).padStart(2, '0');
    const inputTanggal = document.getElementById('inputTanggal');
    if (inputTanggal) {
      inputTanggal.value = `${yyyy}-${mm}-${dd}`;
    }
  }

  // Jalankan saat halaman pertama kali selesai di-load
  document.addEventListener("DOMContentLoaded", function() {
    setBrowserDate();
  });

  // Konfigurasi SweetAlert2 Toast
  const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true
  });

  // Listener untuk trigger HTMX
  document.body.addEventListener('showToast', function(evt) {
    Toast.fire({
      icon: evt.detail.icon,
      title: evt.detail.title
    });
  });

  // Fallback Alert HTTP/PRG Biasa
  <?php if (isset($_SESSION['toast_success'])): ?>
    Toast.fire({
      icon: 'success',
      title: '<?= htmlspecialchars($_SESSION['toast_success']); ?>'
    });
    <?php unset($_SESSION['toast_success']); ?>
  <?php endif; ?>

  <?php if (isset($_SESSION['toast_error'])): ?>
    Toast.fire({
      icon: 'error',
      title: '<?= htmlspecialchars($_SESSION['toast_error']); ?>'
    });
    <?php unset($_SESSION['toast_error']); ?>
  <?php endif; ?>

  function formatInputRibuan(input) {
    let val = input.value.replace(/[^0-9]/g, '');
    if (!val) {
      input.value = '';
      return;
    }
    input.value = new Intl.NumberFormat('id-ID').format(val);
  }
</script>

<?php require_once BASE_PATH . 'partials/footer.php'; ?>

<?php
/**
 * FUNGSI HELPER UNTUK MENTERJEMAHKAN KOMPONEN AREA KEUANGAN (HTMX SWAP TARGET)
 */
function renderAreaKeuangan($saldoKas, $transaksi, $pagination) {
?>
  <div id="area-keuangan">
    
    <!-- Card Ringkasan Single (Hanya Sisa Saldo Kas) -->
    <div class="row mb-4">
      <div class="col-12">
        <div class="card border-0 shadow-sm bg-primary text-white p-3">
          <div class="small text-uppercase font-weight-bold opacity-75">Sisa Saldo Kas</div>
          <div class="h2 mb-0 fw-bold"><?= formatRupiah($saldoKas); ?></div>
        </div>
      </div>
    </div>

    <!-- Tabel Riwayat Transaksi -->
    <div class="card shadow-sm border-0 p-3">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="card-title mb-0 fw-bold">Riwayat Transaksi</h5>
        <span class="badge bg-light text-dark border">
          Total: <?= number_format($pagination['total_items']); ?> Transaksi
        </span>
      </div>
      
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Tanggal</th>
              <th>Kategori</th>
              <th class="text-end">Nominal</th>
              <th class="text-end">Saldo Akhir</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($transaksi)): ?>
              <tr>
                <td colspan="4" class="text-center text-muted py-3">Belum ada transaksi tercatat.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($transaksi as $row): ?>
                <tr>
                  <td class="small">
                    <?= htmlspecialchars($row['tanggal']); ?><br>
                    <small class="text-muted"><?= htmlspecialchars($row['keterangan'] ?: '-'); ?></small>
                  </td>
                  <td>
                    <span class="badge bg-<?= $row['tipe'] === 'masuk' ? 'success' : 'danger'; ?> me-1">
                      <?= strtoupper($row['tipe']); ?>
                    </span>
                    <?= htmlspecialchars($row['kategori']); ?>
                  </td>
                  <td class="text-end fw-bold text-<?= $row['tipe'] === 'masuk' ? 'success' : 'danger'; ?>">
                    <?= $row['tipe'] === 'masuk' ? '+' : '-'; ?> <?= formatRupiah($row['nominal']); ?>
                  </td>
                  <td class="text-end fw-semibold text-dark">
                    <?= formatRupiah($row['saldo_akhir']); ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- PAGINASI SQL (HTMX-compatible) -->
      <?php if ($pagination['total_pages'] > 1): ?>
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center mt-3 gap-2">
          <small class="text-muted">
            Menampilkan <strong><?= $pagination['from']; ?></strong>-<strong><?= $pagination['to']; ?></strong> 
            dari <strong><?= $pagination['total_items']; ?></strong> transaksi
          </small>

          <nav>
            <ul class="pagination pagination-sm mb-0">
              <?php
              $currentPage = $pagination['current_page'];
              $totalPages  = $pagination['total_pages'];
              ?>

              <!-- Tombol Prev -->
              <li class="page-item <?= ($currentPage <= 1) ? 'disabled' : ''; ?>">
                <a class="page-link" 
                   href="?page=<?= $currentPage - 1; ?>"
                   hx-get="?page=<?= $currentPage - 1; ?>"
                   hx-target="#area-keuangan"
                   hx-swap="outerHTML"
                   hx-push-url="true">Previous</a>
              </li>

              <!-- Nomor Halaman (Windowing) -->
              <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <?php if ($i == 1 || $i == $totalPages || abs($i - $currentPage) <= 1): ?>
                  <li class="page-item <?= ($i === $currentPage) ? 'active' : ''; ?>">
                    <a class="page-link" 
                       href="?page=<?= $i; ?>"
                       hx-get="?page=<?= $i; ?>"
                       hx-target="#area-keuangan"
                       hx-swap="outerHTML"
                       hx-push-url="true"><?= $i; ?></a>
                  </li>
                <?php elseif ($i == 2 || $i == $totalPages - 1): ?>
                  <li class="page-item disabled"><span class="page-link">…</span></li>
                <?php endif; ?>
              <?php endfor; ?>

              <!-- Tombol Next -->
              <li class="page-item <?= ($currentPage >= $totalPages) ? 'disabled' : ''; ?>">
                <a class="page-link" 
                   href="?page=<?= $currentPage + 1; ?>"
                   hx-get="?page=<?= $currentPage + 1; ?>"
                   hx-target="#area-keuangan"
                   hx-swap="outerHTML"
                   hx-push-url="true">Next</a>
              </li>
            </ul>
          </nav>
        </div>
      <?php endif; ?>

    </div>

  </div>
<?php
}
?>