<?php
// stok/kartu_stok.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';
require_once BASE_PATH . 'database/query_stok_v2.php';

// Ambil parameter pencarian
$search = trim($_GET['q'] ?? '');

// Ambil daftar barang untuk dropdown (hanya ambil yang punya stok/riwayat)
$listBarang = getDaftarStokV2($pdoBarang, $search);

// Ambil riwayat mutasi dari database
$riwayat = [];
if (!empty($_GET['barang_id'])) {
    $barangId = intval($_GET['barang_id']);
    
    // Query mutasi V2
    $stmt = $pdoBarang->prepare("
        SELECT m.*, b.nama_barang, bk.nama_kemasan, bk.satuan
        FROM stok_mutasi m
        JOIN barang b ON m.barang_id = b.id
        JOIN barang_kemasan bk ON m.barang_kemasan_id = bk.id
        WHERE m.barang_id = ?
        ORDER BY m.created_at DESC, m.id DESC
        LIMIT 100
    ");
    $stmt->execute([$barangId]);
    $riwayat = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

require_once BASE_PATH . 'partials/header.php';
?>

<div class="container-fluid py-3 px-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h4 class="m-0 fw-bold"><i class="bi bi-clock-history me-2 text-primary"></i>Kartu Stok (Riwayat Mutasi)</h4>
            <p class="text-muted small mb-0">Lacak keluar-masuk barang secara detail (Audit Trail).</p>
        </div>
        <div class="mt-3 mt-md-0">
            <a href="<?= BASE_URL; ?>stok/index.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Stok
            </a>
        </div>
    </div>

    <!-- Form Pencarian Barang -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-2 align-items-center">
                <div class="col-md-6">
                    <label class="form-label small fw-bold">Pilih atau Cari Barang</label>
                    <input type="text" name="q" class="form-control form-control-sm mb-2" placeholder="Ketik nama barang untuk memfilter..." value="<?= htmlspecialchars($search); ?>">
                    <select name="barang_id" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">-- Pilih Barang --</option>
                        <?php foreach ($listBarang as $b): ?>
                            <option value="<?= $b['barang_id'] ?>" <?= (isset($_GET['barang_id']) && $_GET['barang_id'] == $b['barang_id']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($b['nama_barang']) ?> (Sisa: <?= $b['stok_base'] ?> <?= htmlspecialchars($b['satuan']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 mt-md-4">
                    <button type="submit" class="btn btn-dark btn-sm w-100 mt-2"><i class="bi bi-search me-1"></i> Tampilkan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabel Riwayat -->
    <?php if (isset($_GET['barang_id']) && !empty($riwayat)): ?>
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-dark">Riwayat Mutasi Barang</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Waktu</th>
                            <th>Jenis Mutasi</th>
                            <th>Keterangan / Referensi</th>
                            <th class="text-center">Qty</th>
                            <th class="text-center">Stok Awal</th>
                            <th class="text-center">Stok Akhir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($riwayat as $r): 
                            $isMasuk = (strpos($r['jenis_mutasi'], 'MASUK') === 0);
                            $badgeColor = $isMasuk ? 'bg-success' : 'bg-danger';
                            $icon = $isMasuk ? 'bi-box-arrow-in-down' : 'bi-box-arrow-up';
                        ?>
                            <tr>
                                <td class="small text-muted font-monospace">
                                    <?= date('d/m/Y H:i', strtotime($r['created_at'])) ?>
                                </td>
                                <td>
                                    <span class="badge <?= $badgeColor ?>">
                                        <i class="bi <?= $icon ?> me-1"></i>
                                        <?= htmlspecialchars($r['jenis_mutasi']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="small"><?= htmlspecialchars($r['keterangan'] ?? '-') ?></span>
                                    <?php if ($r['ref_type']): ?>
                                        <br><small class="text-muted">Ref: <?= $r['ref_type'] ?> #<?= $r['ref_id'] ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center font-monospace fw-bold <?= $isMasuk ? 'text-success' : 'text-danger'; ?>">
                                    <?= $isMasuk ? '+' : '-'; ?><?= number_format($r['qty_base'], 0, ',', '.') ?> <?= htmlspecialchars($r['satuan']) ?>
                                    <?php if ($r['qty_kemasan'] && $r['isi_saat_itu'] > 1): ?>
                                        <div class="extra-small text-muted fw-normal">
                                            (<?= $r['qty_kemasan'] ?> <?= htmlspecialchars($r['nama_kemasan']) ?>)
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center font-monospace text-muted">
                                    <?= number_format($r['stok_sebelum'], 0, ',', '.') ?>
                                </td>
                                <td class="text-center font-monospace fw-bold">
                                    <?= number_format($r['stok_sesudah'], 0, ',', '.') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php elseif (isset($_GET['barang_id'])): ?>
        <div class="alert alert-warning">Belum ada riwayat mutasi untuk barang ini.</div>
    <?php else: ?>
        <div class="alert alert-info">Silakan pilih barang di atas untuk melihat riwayat mutasi stoknya.</div>
    <?php endif; ?>
</div>

<?php require_once BASE_PATH . 'partials/footer.php'; ?>
