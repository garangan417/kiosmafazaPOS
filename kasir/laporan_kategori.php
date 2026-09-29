<?php
// kasir/laporan_kategori.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';

// Filter Tanggal & Input Parameter
$tglMulai   = $_GET['tgl_mulai'] ?? date('Y-m-d');
$tglSelesai = $_GET['tgl_selesai'] ?? date('Y-m-t');
$kategoriId = isset($_GET['kategori_id']) ? intval($_GET['kategori_id']) : 0;
$keyword    = isset($_GET['keyword']) ? trim($_GET['keyword']) : '';

// Cek Request HTMX
$isHtmx = !empty($_SERVER['HTTP_HX_REQUEST']);

// Ambil list kategori untuk dropdown filter (Hanya saat Akses Penuh)
$listKategori = [];

if (!$isHtmx) {
    try {
        $stmtKat = $pdoBarang->query("
        SELECT id, nama_kategori
        FROM kategori
        ORDER BY nama_kategori ASC
        ");

        $listKategori = $stmtKat->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        $listKategori = [];
    }
}

try {

    /*
     | ---------------------------------------------------*-----------------------
     | 1. QUERY DETAIL BARANG TERJUAL PER KATEGORI (PISAH PER METODE BAYAR)
     |--------------------------------------------------------------------------
     |
     | Yang berubah: tambah UPPER(p.metode_bayar) di SELECT dan GROUP BY
     | supaya bisa dipisah TUNAI vs QRIS.
     |
     |--------------------------------------------------------------------------
     */

    $sqlDetail = "
    SELECT

    k.id AS kategori_id,
    k.nama_kategori,

    b.id AS barang_id,
    b.nama_barang,

    pd.nama_kemasan,
    pd.satuan,

    /* METODE BAYAR — untuk pisah TUNAI vs QRIS */
    UPPER(p.metode_bayar) AS metode_bayar,

    /*
     * QTY BERSIH
     * Penjualan dikurangi jumlah yang diretur.
     */
    SUM(
        pd.qty
        - COALESCE(r.qty_retur, 0)
        ) AS total_qty,

        /*
         * OMZET BERSIH
         * Subtotal penjualan dikurangi nilai retur.
         */
        COALESCE(
            SUM(
                pd.subtotal
                - COALESCE(r.subtotal_retur, 0)
                ),
                0
                ) AS total_omzet,

                /*
                 * MODAL / HPP BERSIH
                 */
                COALESCE(
                    SUM(
                        (pd.qty * COALESCE(pd.harga_beli, 0))
                        - COALESCE(r.modal_retur, 0)
                        ),
                        0
                        ) AS total_modal,

                        /*
                         * LABA BERSIH
                         */
                        COALESCE(
                            SUM(
                                (
                                    pd.subtotal
                                    - COALESCE(r.subtotal_retur, 0)
                                    )
                                    -
                                    (
                                        (pd.qty * COALESCE(pd.harga_beli, 0))
                                        - COALESCE(r.modal_retur, 0)
                                        )
                                        ),
                                        0
                                        ) AS total_keuntungan

                                        FROM penjualan_detail pd

                                        JOIN penjualan p
                                        ON pd.penjualan_id = p.id

                                        JOIN barang_kemasan bk
                                        ON pd.barang_kemasan_id = bk.id

                                        JOIN barang b
                                        ON bk.barang_id = b.id

                                        JOIN kategori k
                                        ON b.kategori_id = k.id

                                        /*
                                         | -----------------------------------------------*---------------------------
                                         | AGREGASI RETUR
                                         |--------------------------------------------------------------------------
                                         */
                                        LEFT JOIN (

                                            SELECT

                                            rpd.penjualan_detail_id,

                                            SUM(rpd.qty_retur) AS qty_retur,

                                            SUM(
                                                COALESCE(rpd.subtotal, 0)
                                                ) AS subtotal_retur,

                                                SUM(
                                                    rpd.qty_retur
                                                    * COALESCE(rpd.harga_beli, 0)
                                                    ) AS modal_retur

                                                    FROM retur_penjualan_detail rpd

                                                    JOIN retur_penjualan rp
                                                    ON rpd.retur_penjualan_id = rp.id

                                                    GROUP BY rpd.penjualan_detail_id

                                                    ) r
                                                    ON r.penjualan_detail_id = pd.id

                                                    WHERE DATE(p.tanggal) BETWEEN ? AND ?
                                                    ";

    $params = [
        $tglMulai,
        $tglSelesai
    ];

    /*
     | ---------------------------------------------------*-----------------------
     | FILTER KATEGORI
     |--------------------------------------------------------------------------
     */

    if ($kategoriId > 0) {

        $sqlDetail .= " AND k.id = ?";

        $params[] = $kategoriId;
    }

    /*
     | ---------------------------------------------------*-----------------------
     | FILTER NAMA BARANG / BARCODE
     |--------------------------------------------------------------------------
     */

    if (!empty($keyword)) {

        $sqlDetail .= "
        AND (
            b.nama_barang LIKE ?

            OR EXISTS (
                SELECT 1
                FROM barang_barcode bb
                WHERE bb.barang_kemasan_id = bk.id
                AND bb.barcode LIKE ?
                )
                )
                ";

        $params[] = "%" . $keyword . "%";
        $params[] = "%" . $keyword . "%";
    }

    /*
     | ---------------------------------------------------*-----------------------
     | GROUP BY (TAMBAH metode_bayar)
     |--------------------------------------------------------------------------
     */

    $sqlDetail .= "
    GROUP BY
    k.id,
    k.nama_kategori,
    b.id,
    b.nama_barang,
    pd.nama_kemasan,
    pd.satuan,
    UPPER(p.metode_bayar)

    ORDER BY
    k.nama_kategori ASC,
    total_omzet DESC
    ";

    $stmt = $pdoBarang->prepare($sqlDetail);
    $stmt->execute($params);

    $rawDetail = $stmt->fetchAll(PDO::FETCH_ASSOC);


    /*
     | ---------------------------------------------------*-----------------------
     | GROUPING DATA BERDASARKAN KATEGORI & METODE BAYAR
     |--------------------------------------------------------------------------
     |
     | Struktur:
     |   $rekapKategori[$katId] = [
     |       'nama_kategori' => ...,
     |       'TUNAI' => [omzet, modal, laba, qty],
     |       'QRIS'  => [omzet, modal, laba, qty],
     |       'TOTAL' => [omzet, modal, laba, qty],
     |       'items' => [ ... ]  // untuk accordion detail
     |   ]
     |
     */

    $rekapKategori = [];

    // Grand Total (semua kategori)
    $grandOmzet = 0;
    $grandModal = 0;
    $grandLaba  = 0;
    $grandQty   = 0;

    // Grand Total per METODE (untuk card utama)
    $grandTunai = [
        'omzet' => 0,
        'modal' => 0,
        'laba'  => 0,
        'qty'   => 0,
    ];
    $grandQris = [
        'omzet' => 0,
        'modal' => 0,
        'laba'  => 0,
        'qty'   => 0,
    ];


    foreach ($rawDetail as $row) {

        $katId = $row['kategori_id'];
        $metode = $row['metode_bayar']; // 'TUNAI' atau 'QRIS'

        /*
         | -----------------------------------------------*---------------------------
         | Buat kategori jika belum ada
         |--------------------------------------------------------------------------
         */

        if (!isset($rekapKategori[$katId])) {

            $rekapKategori[$katId] = [

                'nama_kategori' => $row['nama_kategori'],

                'TUNAI' => [
                    'omzet' => 0,
                    'modal' => 0,
                    'laba'  => 0,
                    'qty'   => 0,
                ],

                'QRIS' => [
                    'omzet' => 0,
                    'modal' => 0,
                    'laba'  => 0,
                    'qty'   => 0,
                ],

                'TOTAL' => [
                    'omzet' => 0,
                    'modal' => 0,
                    'laba'  => 0,
                    'qty'   => 0,
                ],

                'items' => []
            ];
        }


        /*
         | -----------------------------------------------*---------------------------
         | AKUMULASI PER KATEGORI & METODE
         |--------------------------------------------------------------------------
         */

        if ($metode === 'TUNAI') {

            $rekapKategori[$katId]['TUNAI']['omzet'] += $row['total_omzet'];
            $rekapKategori[$katId]['TUNAI']['modal'] += $row['total_modal'];
            $rekapKategori[$katId]['TUNAI']['laba']  += $row['total_keuntungan'];
            $rekapKategori[$katId]['TUNAI']['qty']   += $row['total_qty'];

        } elseif ($metode === 'QRIS') {

            $rekapKategori[$katId]['QRIS']['omzet'] += $row['total_omzet'];
            $rekapKategori[$katId]['QRIS']['modal'] += $row['total_modal'];
            $rekapKategori[$katId]['QRIS']['laba']  += $row['total_keuntungan'];
            $rekapKategori[$katId]['QRIS']['qty']   += $row['total_qty'];

        }

        // Akumulasi TOTAL per kategori
        $rekapKategori[$katId]['TOTAL']['omzet'] += $row['total_omzet'];
        $rekapKategori[$katId]['TOTAL']['modal'] += $row['total_modal'];
        $rekapKategori[$katId]['TOTAL']['laba']  += $row['total_keuntungan'];
        $rekapKategori[$katId]['TOTAL']['qty']   += $row['total_qty'];


        /*
         | -----------------------------------------------*---------------------------
         | Simpan Detail Item (untuk accordion)
         |--------------------------------------------------------------------------
         */

        $rekapKategori[$katId]['items'][] = $row;


        /*
         | -----------------------------------------------*---------------------------
         | GRAND TOTAL — per metode
         |--------------------------------------------------------------------------
         */

        if ($metode === 'TUNAI') {
            $grandTunai['omzet'] += $row['total_omzet'];
            $grandTunai['modal'] += $row['total_modal'];
            $grandTunai['laba']  += $row['total_keuntungan'];
            $grandTunai['qty']   += $row['total_qty'];
        } elseif ($metode === 'QRIS') {
            $grandQris['omzet'] += $row['total_omzet'];
            $grandQris['modal'] += $row['total_modal'];
            $grandQris['laba']  += $row['total_keuntungan'];
            $grandQris['qty']   += $row['total_qty'];
        }

        /*
         | -----------------------------------------------*---------------------------
         | GRAND TOTAL — keseluruhan
         |--------------------------------------------------------------------------
         */

        $grandOmzet += $row['total_omzet'];
        $grandModal += $row['total_modal'];
        $grandLaba  += $row['total_keuntungan'];
        $grandQty   += $row['total_qty'];
    }


} catch (PDOException $e) {

    echo '
    <div class="alert alert-danger shadow-sm">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    Error Database:
    ' . htmlspecialchars($e->getMessage()) . '
    </div>
    ';

    exit;
}


/*
 | -------------------------------------------------------*-------------------
 | FUNCTION HELPER RENDER KONTEN
 |--------------------------------------------------------------------------
 */

function renderKontenLaporan(
    $rekapKategori,
    $grandTunai,
    $grandQris,
    $tglMulai,
    $tglSelesai
) {

    ?>

    <!-- ============================================================ -->
    <!-- CARD RINGKASAN: TUNAI (FISIK) — 1 CARD FULL WIDTH -->
    <!-- ============================================================ -->
    <div class="card border-0 shadow-sm bg-primary text-white mb-3">
    <div class="card-body p-3">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-start mb-3">
    <div>
    <h6 class="text-uppercase fw-bold mb-0">
    <i class="bi bi-cash-stack me-1"></i>TUNAI (Fisik)
    </h6>
    <small class="opacity-75">Uang fisik di laci (Tunai + Utang lunas)</small>
    </div>
    <span class="badge bg-white text-primary fw-bold">
    <i class="bi bi-box-seam me-1"></i><?= number_format($grandTunai['qty']); ?> Qty
    </span>
    </div>

    <!-- 3 KOLOM INFO -->
    <div class="row g-3 text-center">

    <!-- Modal -->
    <div class="col-4 border-end border-white border-opacity-25">
    <small class="opacity-75 d-block mb-1" style="font-size: 0.8rem;">MODAL</small>
    <div class="fw-bold font-monospace fs-5 text-nowrap">
    Rp <?= number_format($grandTunai['modal'], 0, ',', '.'); ?>
    </div>
    </div>

    <!-- Laba -->
    <div class="col-4 border-end border-white border-opacity-25">
    <small class="opacity-75 d-block mb-1" style="font-size: 0.8rem;">LABA</small>
    <div class="fw-bold font-monospace fs-5 text-warning text-nowrap">
    Rp <?= number_format($grandTunai['laba'], 0, ',', '.'); ?>
    </div>
    </div>

    <!-- Total Omzet (fokus) -->
    <div class="col-4">
    <small class="opacity-75 d-block mb-1" style="font-size: 0.8rem;">TOTAL OMZET</small>
    <div class="fw-bold font-monospace fs-3 text-nowrap">
    Rp <?= number_format($grandTunai['omzet'], 0, ',', '.'); ?>
    </div>
    </div>

    </div>

    </div>
    </div>

    <!-- ============================================================ -->
    <!-- CARD RINGKASAN: QRIS — 1 CARD FULL WIDTH -->
    <!-- ============================================================ -->
    <div class="card border-0 shadow-sm bg-secondary text-white mb-4">
    <div class="card-body p-3">

    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-start mb-3">
    <div>
    <h6 class="text-uppercase fw-bold mb-0">
    <i class="bi bi-qr-code-scan me-1"></i>QRIS
    </h6>
    <small class="opacity-75">Masuk rekening (non-fisik)</small>
    </div>
    <span class="badge bg-white text-secondary fw-bold">
    <i class="bi bi-box-seam me-1"></i><?= number_format($grandQris['qty']); ?> Qty
    </span>
    </div>

    <!-- 3 KOLOM INFO -->
    <div class="row g-3 text-center">

    <!-- Modal -->
    <div class="col-4 border-end border-white border-opacity-25">
    <small class="opacity-75 d-block mb-1" style="font-size: 0.8rem;">MODAL</small>
    <div class="fw-bold font-monospace fs-5 text-nowrap">
    Rp <?= number_format($grandQris['modal'], 0, ',', '.'); ?>
    </div>
    </div>

    <!-- Laba -->
    <div class="col-4 border-end border-white border-opacity-25">
    <small class="opacity-75 d-block mb-1" style="font-size: 0.8rem;">LABA</small>
    <div class="fw-bold font-monospace fs-5 text-warning text-nowrap">
    Rp <?= number_format($grandQris['laba'], 0, ',', '.'); ?>
    </div>
    </div>

    <!-- Total Omzet (fokus) -->
    <div class="col-4">
    <small class="opacity-75 d-block mb-1" style="font-size: 0.8rem;">TOTAL OMZET</small>
    <div class="fw-bold font-monospace fs-3 text-nowrap">
    Rp <?= number_format($grandQris['omzet'], 0, ',', '.'); ?>
    </div>
    </div>

    </div>

    </div>
    </div>


    <!-- ============================================================ -->
    <!-- RINCIAN PENJUALAN PER KATEGORI (TIDAK DIUBAH) -->
    <!-- ============================================================ -->
    <div class="card shadow-sm border-0">

    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">

    <h6 class="mb-0 fw-bold">
    <i class="bi bi-tags-fill me-2 text-primary"></i>
    Rincian Penjualan Per Kategori & Detail Item
    </h6>

    <span class="badge bg-light text-dark border">
    Periode:
    <?= date('d/m/Y', strtotime($tglMulai)); ?>
    s/d
    <?= date('d/m/Y', strtotime($tglSelesai)); ?>
    </span>

    </div>

    <div class="card-body p-0">

    <?php if (empty($rekapKategori)): ?>

    <div class="text-center text-muted py-5">
    <i class="bi bi-inbox display-5 d-block mb-2 text-muted"></i>
    Tidak ada data penjualan kategori pada rentang tanggal / pencarian ini.
    </div>

    <?php else: ?>

    <div class="accordion accordion-flush" id="accordionKategori">

    <?php $index = 0; ?>

    <?php foreach ($rekapKategori as $katId => $kat): ?>

    <?php
    $index++;

    // Hitung % omzet dari grand total keseluruhan
    $grandOmzetAll = 0;
    foreach ($rekapKategori as $k) {
        $grandOmzetAll += $k['TOTAL']['omzet'];
    }

    $persenOmzet = ($grandOmzetAll > 0)
    ? ($kat['TOTAL']['omzet'] / $grandOmzetAll) * 100
    : 0;
    ?>

    <div class="accordion-item border-bottom">

    <h2 class="accordion-header" id="heading-<?= $katId; ?>">

    <button
    class="accordion-button <?= $index > 1 ? 'collapsed' : ''; ?> bg-light"
    type="button"
    data-bs-toggle="collapse"
    data-bs-target="#collapse-<?= $katId; ?>"
    aria-expanded="<?= $index === 1 ? 'true' : 'false'; ?>"
    aria-controls="collapse-<?= $katId; ?>"
    >

    <div class="w-100 d-flex flex-wrap justify-content-between align-items-center me-3">

    <div>
    <i class="bi bi-folder2-open me-2 text-primary fw-bold"></i>
    <strong class="fs-6 text-dark">
    <?= htmlspecialchars($kat['nama_kategori']); ?>
    </strong>
    <span class="badge bg-secondary ms-2">
    <?= count($kat['items']); ?> Item
    </span>
    </div>

    <div class="d-flex align-items-center gap-3 mt-2 mt-md-0 font-monospace fs-6">

    <small class="text-muted fw-normal">
    Qty:
    <strong>
    <?= number_format($kat['TOTAL']['qty']); ?>
    </strong>
    </small>

    <small class="text-muted fw-normal">
    Omzet:
    <strong class="text-dark">
    Rp <?= number_format($kat['TOTAL']['omzet'], 0, ',', '.'); ?>
    </strong>
    </small>

    <small class="text-muted fw-normal">
    Laba:
    <strong class="<?= $kat['TOTAL']['laba'] >= 0 ? 'text-success' : 'text-danger'; ?>">
    Rp <?= number_format($kat['TOTAL']['laba'], 0, ',', '.'); ?>
    </strong>
    </small>

    <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace small">
    <?= number_format($persenOmzet, 1); ?>% Omzet
    </span>

    </div>

    </div>

    </button>

    </h2>

    <div
    id="collapse-<?= $katId; ?>"
    class="accordion-collapse collapse <?= $index === 1 ? 'show' : ''; ?>"
    aria-labelledby="heading-<?= $katId; ?>"
    data-bs-parent="#accordionKategori"
    >

    <div class="accordion-body p-0">

    <div class="table-responsive">

    <table class="table table-sm table-hover align-middle mb-0">

    <thead class="table-light text-muted small">
    <tr>
    <th class="ps-4">Nama Barang</th>
    <th>Kemasan / Satuan</th>
    <th class="text-center">Metode</th>
    <th class="text-center">Total Terjual</th>
    <th class="text-end">Total Omzet</th>
    <th class="text-end">Total Modal</th>
    <th class="text-end pe-4">Estimasi Laba</th>
    </tr>
    </thead>

    <tbody>

    <?php foreach ($kat['items'] as $item): ?>

    <tr>

    <td class="ps-4 fw-semibold text-dark">
    <i class="bi bi-box-seam me-2 text-secondary"></i>
    <?= htmlspecialchars($item['nama_barang']); ?>
    </td>

    <td>
    <span class="badge bg-light text-dark border">
    <?= htmlspecialchars($item['nama_kemasan']); ?>
    (<?= htmlspecialchars($item['satuan']); ?>)
    </span>
    </td>

    <td class="text-center">
    <?php if ($item['metode_bayar'] === 'QRIS'): ?>
    <span class="badge bg-secondary">
    <i class="bi bi-qr-code-scan me-1"></i>QRIS
    </span>
    <?php else: ?>
    <span class="badge bg-primary">
    <i class="bi bi-cash-stack me-1"></i>TUNAI
    </span>
    <?php endif; ?>
    </td>

    <td class="text-center font-monospace fw-bold">
    <?= number_format($item['total_qty']); ?>
    </td>

    <td class="text-end font-monospace">
    Rp <?= number_format($item['total_omzet'], 0, ',', '.'); ?>
    </td>

    <td class="text-end font-monospace text-muted">
    Rp <?= number_format($item['total_modal'], 0, ',', '.'); ?>
    </td>

    <td class="text-end pe-4 font-monospace fw-bold <?= $item['total_keuntungan'] >= 0 ? 'text-success' : 'text-danger'; ?>">
    Rp <?= number_format($item['total_keuntungan'], 0, ',', '.'); ?>
    </td>

    </tr>

    <?php endforeach; ?>

    </tbody>

    </table>

    </div>

    </div>

    </div>

    </div>

    <?php endforeach; ?>

    </div>

    <?php endif; ?>

    </div>

    </div>

    <?php
}


/*
 | -------------------------------------------------------*-------------------
 | JIKA REQUEST DARI HTMX
 |--------------------------------------------------------------------------
 */

if ($isHtmx) {

    renderKontenLaporan(
        $rekapKategori,
        $grandTunai,
        $grandQris,
        $tglMulai,
        $tglSelesai
    );

    exit;
}


/*
 | -------------------------------------------------------*-------------------
 | REQUEST BIASA BROWSER
 |--------------------------------------------------------------------------
 */

require_once BASE_PATH . 'partials/header.php';

?>

<div class="container-fluid my-4 px-4">

<!-- HEADER & FILTER -->
<div class="row align-items-center mb-4">

<div class="col-md-4">
<h4 class="fw-bold mb-1">
<i class="bi bi-grid-3x3-gap-fill text-primary me-2"></i>
Laporan Per Kategori
</h4>
<p class="text-muted small mb-0">
Analisis omzet, modal, dan keuntungan per kategori & barang.
</p>
</div>

<div class="col-md-8">

<form
id="filterFormKategori"
hx-get="laporan_kategori.php"
hx-target="#laporan-kategori-container"
hx-trigger="keyup changed delay:500ms from:input[name='keyword'], change, submit"
hx-indicator="#loading-spinner"
class="row g-2 justify-content-md-end align-items-center"
>

<!-- Input Pencarian Nama Barang / Barcode -->
<div class="col-auto">
<div class="input-group input-group-sm">
<span class="input-group-text bg-white">
<i class="bi bi-search text-muted"></i>
</span>
<input
type="text"
name="keyword"
class="form-control form-control-sm"
placeholder="Cari Barang / Barcode..."
value="<?= htmlspecialchars($keyword); ?>"
autocomplete="off"
>
</div>
</div>

<div class="col-auto">
<select name="kategori_id" class="form-select form-select-sm">
<option value="0">-- Semua Kategori --</option>
<?php foreach ($listKategori as $kat): ?>
<option
value="<?= $kat['id']; ?>"
<?= $kategoriId == $kat['id'] ? 'selected' : ''; ?>
>
<?= htmlspecialchars($kat['nama_kategori']); ?>
</option>
<?php endforeach; ?>
</select>
</div>

<div class="col-auto">
<input
type="date"
name="tgl_mulai"
class="form-control form-control-sm"
value="<?= htmlspecialchars($tglMulai); ?>"
>
</div>

<div class="col-auto align-self-center small text-muted">
s/d
</div>

<div class="col-auto">
<input
type="date"
name="tgl_selesai"
class="form-control form-control-sm"
value="<?= htmlspecialchars($tglSelesai); ?>"
>
</div>

<div class="col-auto">
<button type="submit" class="btn btn-sm btn-primary">
<i class="bi bi-filter me-1"></i> Filter
</button>
<a href="laporan.php" class="btn btn-sm btn-outline-secondary">
<i class="bi bi-arrow-left me-1"></i> Laporan Umum
</a>
</div>

</form>

</div>

</div>

<!-- KONTAINER UTAMA HASIL DATA -->
<div id="laporan-kategori-container">

<?php
renderKontenLaporan(
    $rekapKategori,
    $grandTunai,
    $grandQris,
    $tglMulai,
    $tglSelesai
);
?>

</div>

</div>

<?php require_once BASE_PATH . 'partials/footer.php'; ?>
