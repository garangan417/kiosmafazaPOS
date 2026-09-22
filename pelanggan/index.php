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
    $query = $pdoPelanggan->query("SELECT * FROM pelanggan ORDER BY id DESC");
    $pelangganRaw = $query->fetchAll(PDO::FETCH_ASSOC);

    $stmtUtang = $pdoPelanggan->query("
    SELECT pelanggan_id, tipe, nominal, created_at, id
    FROM utang
    ORDER BY pelanggan_id ASC, created_at ASC, id ASC
    ");
    $utangRaw = $stmtUtang->fetchAll(PDO::FETCH_ASSOC);

    $utangByPelanggan = [];
    foreach ($utangRaw as $u) {
        $utangByPelanggan[$u['pelanggan_id']][] = $u;
    }

    foreach ($pelangganRaw as $p) {
        $riwayat = $utangByPelanggan[$p['id']] ?? [];
        $p['sisa_utang'] = hitungSisaUtangDariRiwayat($riwayat);
        $dataPelanggan[] = $p;
    }

    // SORTING: Pelanggan dengan utang aktif di atas (urut terbesar),
    //          setelah itu pelanggan tanpa utang (urut nama A-Z)
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

<!-- ============================================ -->
<!-- MODAL BAYAR CEPAT (BARU)                     -->
<!-- ============================================ -->
<div class="modal fade" id="modalBayarCepat" tabindex="-1" aria-hidden="true">
<div class="modal-dialog modal-dialog-centered">
<div class="modal-content">
<div class="modal-header bg-success text-white">
<h6 class="modal-title fw-bold">
<i class="bi bi-cash-coin me-2"></i>Bayar Utang
</h6>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
</div>

<form method="POST" action="<?= BASE_URL; ?>utang/index.php" id="formBayarCepat">
<div class="modal-body">
<input type="hidden" name="pelanggan_id" id="bayar_pelanggan_id">
<input type="hidden" name="tipe" value="bayar">
<input type="hidden" name="tanggal" id="bayar_tanggal">

<!-- Info Pelanggan & Sisa Utang -->
<div class="p-2 mb-3 bg-light rounded border">
<strong class="d-block text-dark" id="bayar_nama">-</strong>
<small class="text-muted">
Sisa Utang:
<strong id="bayar_sisa" class="text-danger">Rp 0</strong>
</small>
</div>

<!-- Input Nominal -->
<div class="mb-3">
<label class="form-label small fw-semibold">Nominal Bayar</label>
<div class="input-group">
<span class="input-group-text fw-bold">Rp</span>
<input type="text"
name="nominal"
id="bayar_nominal"
class="form-control form-control-lg text-end fw-bold font-monospace"
placeholder="0"
onkeyup="formatInputRibuan(this)"
autocomplete="off"
required>
</div>

<!-- Tombol Cepat -->
<div class="d-flex gap-1 mt-2 flex-wrap">
<button type="button" class="btn btn-sm btn-success fw-bold" onclick="setBayarPas()">
<i class="bi bi-check-lg"></i> Uang Pas
</button>
<button type="button" class="btn btn-sm btn-outline-secondary" onclick="setBayarNominal(10000)">
10rb
</button>
<button type="button" class="btn btn-sm btn-outline-secondary" onclick="setBayarNominal(20000)">
20rb
</button>
<button type="button" class="btn btn-sm btn-outline-secondary" onclick="setBayarNominal(50000)">
50rb
</button>
<button type="button" class="btn btn-sm btn-outline-secondary" onclick="setBayarNominal(100000)">
100rb
</button>
</div>
</div>

<!-- Keterangan -->
<div class="mb-2">
<label class="form-label small fw-semibold">Keterangan (Opsional)</label>
<input type="text"
name="keterangan"
class="form-control form-control-sm"
placeholder="Catatan...">
</div>
</div>

<div class="modal-footer py-2">
<button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Batal</button>
<button type="submit" class="btn btn-success btn-sm fw-bold">
<i class="bi bi-check-lg me-1"></i> Bayar Sekarang
</button>
</div>
</form>
</div>
</div>
</div>

<!-- ============================================ -->
<!-- JAVASCRIPT MODAL BAYAR CEPAT (BARU)          -->
<!-- ============================================ -->
<script>
let currentSisaUtang = 0;

/**
 * Buka modal bayar cepat dengan data pelanggan
 */
function bukaModalBayar(id, nama, sisaUtang) {
    document.getElementById('bayar_pelanggan_id').value = id;
    document.getElementById('bayar_nama').innerText = nama;
    document.getElementById('bayar_sisa').innerText = 'Rp ' + Math.round(sisaUtang).toLocaleString('id-ID');
    document.getElementById('bayar_nominal').value = '';

    // Set tanggal & waktu browser
    const now = new Date();
    const yyyy = now.getFullYear();
    const mm = String(now.getMonth() + 1).padStart(2, '0');
    const dd = String(now.getDate()).padStart(2, '0');
    const hh = String(now.getHours()).padStart(2, '0');
    const ii = String(now.getMinutes()).padStart(2, '0');
    const ss = String(now.getSeconds()).padStart(2, '0');

    document.getElementById('bayar_tanggal').value =
    `${yyyy}-${mm}-${dd} ${hh}:${ii}:${ss}`;

    currentSisaUtang = sisaUtang;

    // Tampilkan modal
    const modal = new bootstrap.Modal(document.getElementById('modalBayarCepat'));
    modal.show();

    // Auto-focus ke input nominal
    setTimeout(() => {
        document.getElementById('bayar_nominal').focus();
    }, 300);
}

/**
 * Set nominal bayar dari tombol cepat
 */
function setBayarNominal(val) {
    const input = document.getElementById('bayar_nominal');
    input.value = new Intl.NumberFormat('id-ID').format(val);
    input.focus();
}

/**
 * Set nominal bayar = sisa utang (uang pas)
 */
function setBayarPas() {
    const input = document.getElementById('bayar_nominal');
    input.value = new Intl.NumberFormat('id-ID').format(Math.round(currentSisaUtang));
    input.focus();
}

/**
 * Format input ribuan (live)
 */
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
