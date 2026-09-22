<?php
require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_pelanggan.php';

// Pastikan fungsi formatRupiah ada agar tidak error jika belum ter-include dari config
if (!function_exists('formatRupiah')) {
    function formatRupiah($nominal) {
        return 'Rp ' . number_format((float)$nominal, 0, ',', '.');
    }
}

/**
 * Helper: Hitung sisa utang per pelanggan dari riwayat yang SUDAH DI-GROUP.
 * (Menggantikan hitungSisaUtangSesi agar tidak N+1 query)
 */
if (!function_exists('hitungSisaUtangDariRiwayat')) {
    function hitungSisaUtangDariRiwayat(array $riwayat): float {
        $totalUtangSesi = 0;
        $totalBayarSesi = 0;

        foreach ($riwayat as $r) {
            $nominal = floatval($r['nominal']);

            if ($r['tipe'] === 'utang') {
                $totalUtangSesi += $nominal;
            } else {
                $totalBayarSesi += $nominal;
            }

            // Jika sesi lunas/lebih, reset untuk sesi berikutnya
            if ($totalUtangSesi > 0 && $totalBayarSesi >= $totalUtangSesi) {
                $totalUtangSesi = 0;
                $totalBayarSesi = 0;
            }
        }

        return max(0, $totalUtangSesi - $totalBayarSesi);
    }
}

$errorMsg   = '';
$successMsg = '';

// Cek request HTMX
$isHtmx = isset($_SERVER['HTTP_HX_REQUEST']);

// ============================================================
// 1. PROSES POST (TAMBAH / EDIT / HAPUS)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // A. TAMBAH PELANGGAN
    if ($action === 'add') {
        $nama   = mb_strtoupper(trim($_POST['nama'] ?? ''));
        $no_hp  = trim($_POST['no_hp'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');

        if (!empty($nama)) {
            try {
                $stmt = $pdoPelanggan->prepare("INSERT INTO pelanggan (nama, no_hp, alamat) VALUES (?, ?, ?)");
                $stmt->execute([$nama, $no_hp, $alamat]);
                $successMsg = "Pelanggan berhasil ditambahkan!";
            } catch (PDOException $e) {
                $errorMsg = "Gagal menyimpan ke database: " . $e->getMessage();
            }
        } else {
            $errorMsg = "Nama pelanggan tidak boleh kosong!";
        }
    }

    // B. EDIT PELANGGAN
    elseif ($action === 'edit') {
        $id     = intval($_POST['id'] ?? 0);
        $nama   = mb_strtoupper(trim($_POST['nama'] ?? ''));
        $no_hp  = trim($_POST['no_hp'] ?? '');
        $alamat = trim($_POST['alamat'] ?? '');

        if ($id > 0 && !empty($nama)) {
            try {
                $stmt = $pdoPelanggan->prepare("UPDATE pelanggan SET nama = ?, no_hp = ?, alamat = ? WHERE id = ?");
                $stmt->execute([$nama, $no_hp, $alamat, $id]);
                $successMsg = "Data pelanggan berhasil diperbarui!";
            } catch (PDOException $e) {
                $errorMsg = "Gagal memperbarui data: " . $e->getMessage();
            }
        } else {
            $errorMsg = "Data edit tidak valid!";
        }
    }

    // C. HAPUS PELANGGAN
    elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id > 0) {
            // Cek sisa utang (hanya untuk pelanggan ini)
            $stmtU = $pdoPelanggan->prepare("SELECT tipe, nominal FROM utang WHERE pelanggan_id = ? ORDER BY created_at ASC, id ASC");
            $stmtU->execute([$id]);
            $sisaUtang = hitungSisaUtangDariRiwayat($stmtU->fetchAll(PDO::FETCH_ASSOC));

            if ($sisaUtang > 0) {
                $errorMsg = "Pelanggan tidak bisa dihapus karena masih memiliki sisa utang sebesar " . formatRupiah($sisaUtang) . "!";
            } else {
                try {
                    $stmt = $pdoPelanggan->prepare("DELETE FROM pelanggan WHERE id = ?");
                    $stmt->execute([$id]);
                    $successMsg = "Pelanggan berhasil dihapus!";
                } catch (PDOException $e) {
                    $errorMsg = "Gagal menghapus pelanggan: " . $e->getMessage();
                }
            }
        }
    }

    // Jika POST biasa (non-HTMX) dan tidak ada error
    if (!$isHtmx && empty($errorMsg)) {
        header("Location: " . BASE_URL . "pelanggan/");
        exit;
    }
}

// ============================================================
// 2. AMBIL DATA PELANGGAN + UTANG (EFISIEN, TANPA N+1)
// ============================================================
$dataPelanggan = [];

try {
    // 2A. Ambil SEMUA pelanggan dalam 1 query
    $query = $pdoPelanggan->query("SELECT * FROM pelanggan ORDER BY id DESC");
    $pelangganRaw = $query->fetchAll(PDO::FETCH_ASSOC);

    // 2B. Ambil SEMUA riwayat utang dalam 1 query
    $stmtUtang = $pdoPelanggan->query("
        SELECT pelanggan_id, tipe, nominal, created_at, id 
        FROM utang 
        ORDER BY pelanggan_id ASC, created_at ASC, id ASC
    ");
    $utangRaw = $stmtUtang->fetchAll(PDO::FETCH_ASSOC);

    // 2C. Group riwayat utang per pelanggan
    $utangByPelanggan = [];
    foreach ($utangRaw as $u) {
        $utangByPelanggan[$u['pelanggan_id']][] = $u;
    }

    // 2D. Hitung sisa utang tiap pelanggan
    foreach ($pelangganRaw as $p) {
        $riwayat = $utangByPelanggan[$p['id']] ?? [];
        $p['sisa_utang'] = hitungSisaUtangDariRiwayat($riwayat);
        $dataPelanggan[] = $p;
    }

    // 2E. SORTING: Pelanggan dengan utang aktif di atas (urut terbesar),
    //     setelah itu pelanggan tanpa utang (urut nama A-Z)
    usort($dataPelanggan, function($a, $b) {
        $aPunyaUtang = $a['sisa_utang'] > 0 ? 1 : 0;
        $bPunyaUtang = $b['sisa_utang'] > 0 ? 1 : 0;

        if ($aPunyaUtang !== $bPunyaUtang) {
            return $bPunyaUtang - $aPunyaUtang;
        }

        if ($aPunyaUtang === 1) {
            return $b['sisa_utang'] <=> $a['sisa_utang'];
        }

        return strcmp($a['nama'], $b['nama']);
    });

} catch (PDOException $e) {
    $errorMsg = "Gagal mengambil data pelanggan: " . $e->getMessage();
}

// ============================================================
// 3. PAGINASI DI PHP
// ============================================================
$page   = max(1, intval($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;

$totalItems = count($dataPelanggan);
$totalPages = max(1, (int) ceil($totalItems / $limit));

if ($page > $totalPages) {
    $page   = $totalPages;
    $offset = ($page - 1) * $limit;
}

$pelangganPage = array_slice($dataPelanggan, $offset, $limit);

$pagination = [
    'total_items'  => $totalItems,
    'total_pages'  => $totalPages,
    'current_page' => $page,
    'per_page'     => $limit,
    'from'         => $totalItems > 0 ? $offset + 1 : 0,
    'to'           => min($offset + $limit, $totalItems),
];

// Hanya halaman ini yang dirender
$dataPelanggan = $pelangganPage;

// ============================================================
// 4. HANDLE HTMX REQUEST
// ============================================================
if ($isHtmx) {
    include __DIR__ . '/_area_pelanggan.php';
    exit;
}

require_once BASE_PATH . 'partials/header.php';
?>

<main class="container my-4 flex-grow-1">
  <div class="row">
    <!-- Form Tambah Pelanggan -->
    <div class="col-lg-4 mb-4">
      <div class="card shadow-sm border-0 p-3">
        <h5 class="card-title mb-3 fw-bold">Tambah Pelanggan Baru</h5>

        <form hx-post="<?= BASE_URL; ?>pelanggan/" 
              hx-target="#area-pelanggan" 
              hx-swap="outerHTML"
              hx-on::after-request="if(event.detail.successful) this.reset();">

          <input type="hidden" name="action" value="add">

          <div class="mb-3">
            <label class="form-label small fw-semibold">Nama Lengkap</label>
            <input type="text" name="nama" class="form-control" placeholder="Contoh: Baron" required>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Alamat / Catatan</label>
            <textarea name="alamat" class="form-control" rows="2" placeholder="Catatan singkat..."></textarea>
          </div>

          <button type="submit" class="btn btn-dark w-100 d-flex align-items-center justify-content-center gap-2">
            <span>Simpan Pelanggan</span>
            <div class="spinner-border spinner-border-sm htmx-indicator" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
          </button>
        </form>
      </div>
    </div>

    <!-- Area Tabel & Modal -->
    <div class="col-lg-8">
      <?php include __DIR__ . '/_area_pelanggan.php'; ?>
    </div>
  </div>
</main>

<!-- Container Tempat Modal Detail Utang Dirender -->
<div id="container-modal-detail"></div>

<?php require_once BASE_PATH . 'partials/footer.php'; ?>