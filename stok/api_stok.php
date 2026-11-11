<?php
// stok/api_stok.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';
require_once BASE_PATH . 'database/query_stok_v2.php'; // Panggil mesin V2

function sendHtmxToast($icon, $title) {
    header('HX-Trigger: ' . json_encode([
        'showToast' => ['icon' => $icon, 'title' => $title]
    ]));
}

function renderTabelStok($pdo, $searchQuery = '', $page = 1) {
    $result = getDaftarStokV2($pdo, trim($searchQuery), $page);
    $list = $result['items'];
    $totalPages = $result['total_pages'];
    $currentPage = $result['current_page'];

    if (empty($list)) {
        echo '<tr><td colspan="4" class="text-center text-muted py-4"><i class="bi bi-inbox d-block fs-3 mb-1"></i>Data barang tidak ditemukan.</td></tr>';
        return;
    }

    foreach ($list as $b) {
        $stokBase = (int) $b['stok_base'];
        $isi = max(1, (int) $b['isi']);
        $stokKemasan = floor($stokBase / $isi);
        
        if ($stokBase <= 0) { $badgeColor = 'bg-danger'; $badgeText = 'Habis'; }
        elseif ($stokBase <= 5) { $badgeColor = 'bg-warning text-dark'; $badgeText = 'Menipis'; }
        else { $badgeColor = 'bg-success'; $badgeText = 'Aman'; }

        // Cek apakah kemasan ini punya jembatan (untuk tombol Minta Stok)
        $stmtCekJembatan = $pdo->prepare("SELECT id FROM jembatan_stok WHERE kemasan_tujuan_id = ? AND is_aktif_otomatis = 0");
        $stmtCekJembatan->execute([$b['kemasan_id']]);
        $punyaJembatan = $stmtCekJembatan->fetchColumn() !== false;
        ?>
        <tr>
            <td>
                <strong class="text-dark d-block"><?= htmlspecialchars($b['nama_barang']) ?></strong>
                <span class="badge <?= $badgeColor ?> mt-1" style="font-size: 0.65rem;"><?= $badgeText ?></span>
            </td>
            <td>
                <span class="badge bg-info-subtle text-info border border-info me-1">
                    <?= htmlspecialchars($b['nama_kemasan']) ?>
                </span>
                <small class="text-muted d-block mt-1">(1 <?= htmlspecialchars($b['nama_kemasan']) ?> = <?= $isi ?> <?= htmlspecialchars($b['satuan']) ?>)</small>
            </td>
            <td class="text-center">
                <span class="fs-5 fw-bold font-monospace"><?= number_format($stokBase, 0, ',', '.') ?></span> 
                <small class="text-muted"><?= htmlspecialchars($b['satuan']) ?></small>
                <?php if ($isi > 1): ?>
                    <div class="extra-small text-muted">(Setara <?= $stokKemasan ?> <?= htmlspecialchars($b['nama_kemasan']) ?>)</div>
                <?php endif; ?>
            </td>
            <td class="text-end">
                <?php if ($punyaJembatan): ?>
                    <button type="button" class="btn btn-sm btn-success fw-bold me-1" onclick="mintaStok(<?= $b['kemasan_id'] ?>, '<?= addslashes(htmlspecialchars($b['nama_barang'])) ?>')">
                        <i class="bi bi-box-arrow-in-down me-1"></i> Minta Stok
                    </button>
                <?php endif; ?>
                
                <button type="button" 
                        class="btn btn-sm btn-outline-secondary"
                        onclick="setOpname(<?= $b['kemasan_id'] ?>, '<?= addslashes(htmlspecialchars($b['nama_barang'])) ?>', <?= $stokBase ?>, '<?= htmlspecialchars($b['satuan']) ?>')">
                    <i class="bi bi-pencil-square me-1"></i> Opname
                </button>
            </td>
        </tr>
        <?php
    }

    // Render Tombol Paginasi
    if ($totalPages > 1) {
        echo '<tr><td colspan="4" class="p-3 border-top bg-light">';
        echo '<div class="d-flex justify-content-between align-items-center">';
        echo '<small class="text-muted">Halaman ' . $currentPage . ' dari ' . $totalPages . '</small>';
        echo '<nav><ul class="pagination pagination-sm mb-0">';
        
        // Tombol Prev
        $prevDisabled = ($currentPage <= 1) ? 'disabled' : '';
        echo '<li class="page-item ' . $prevDisabled . '"><button class="page-link" hx-get="api_stok.php?action=load_tabel&q=' . urlencode($searchQuery) . '&page=' . ($currentPage - 1) . '" hx-target="#tblStokBody">Prev</button></li>';
        
        // Angka Halaman (Batasi maksimal 5 tombol)
        $start = max(1, $currentPage - 2);
        $end = min($totalPages, $currentPage + 2);
        
        for ($i = $start; $i <= $end; $i++) {
            $active = ($i == $currentPage) ? 'active' : '';
            echo '<li class="page-item ' . $active . '"><button class="page-link" hx-get="api_stok.php?action=load_tabel&q=' . urlencode($searchQuery) . '&page=' . $i . '" hx-target="#tblStokBody">' . $i . '</button></li>';
        }
        
        // Tombol Next
        $nextDisabled = ($currentPage >= $totalPages) ? 'disabled' : '';
        echo '<li class="page-item ' . $nextDisabled . '"><button class="page-link" hx-get="api_stok.php?action=load_tabel&q=' . urlencode($searchQuery) . '&page=' . ($currentPage + 1) . '" hx-target="#tblStokBody">Next</button></li>';
        
        echo '</ul></nav></div></td></tr>';
    }
}

$action      = $_GET['action'] ?? '';
$searchQuery = $_GET['q'] ?? $_POST['q'] ?? '';
$page        = max(1, intval($_GET['page'] ?? 1));

if ($action === 'load_tabel') {
    renderTabelStok($pdoBarang, $searchQuery, $page);
    exit;
}

if ($action === 'opname') {
    $kemasanId = intval($_POST['kemasan_id'] ?? 0);
    $stokFisik = intval($_POST['stok_fisik'] ?? 0);

    if ($kemasanId > 0 && $stokFisik >= 0) {
        try {
            // Panggil catatMutasi dengan jenis OPNAME_SET
            $res = catatMutasi(
                $pdoBarang, 
                $kemasanId, 
                'OPNAME_SET', 
                $stokFisik, 
                null, 
                null, 
                "Stock Opname Manual"
            );

            if ($res['ok']) {
                sendHtmxToast('success', 'Stock opname berhasil diperbarui!');
            } else {
                sendHtmxToast('error', $res['error']);
            }
        } catch (Exception $e) {
            sendHtmxToast('error', 'Gagal Opname: ' . $e->getMessage());
        }
    } else {
        sendHtmxToast('error', 'Data Opname tidak valid!');
    }

    renderTabelStok($pdoBarang, $searchQuery, $page);
    exit;
}

// ============================================================
// HANDLER: MINTA STOK DARI JEMBATAN (MANUAL)
// ============================================================
if ($action === 'minta_stok') {
    header('Content-Type: application/json');
    $kemasanTujuanId = intval($_POST['kemasan_id'] ?? 0);
    
    // Cari jembatannya
    $stmtJ = $pdoBarang->prepare("SELECT * FROM jembatan_stok WHERE kemasan_tujuan_id = ? AND is_aktif_otomatis = 0");
    $stmtJ->execute([$kemasanTujuanId]);
    $jembatan = $stmtJ->fetch(PDO::FETCH_ASSOC);

    if (!$jembatan) {
        echo json_encode(['status' => 'error', 'message' => 'Barang ini tidak memiliki pasangan jembatan.']);
        exit;
    }

    // Panggil fungsi transfer manual
    $res = transferStokManual(
        $pdoBarang, 
        $jembatan['kemasan_sumber_id'], 
        $kemasanTujuanId, 
        1, 
        $jembatan['isi_konversi']
    );

    if ($res['ok']) {
        echo json_encode(['status' => 'success', 'message' => 'Stok berhasil diminta dari Gudang!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $res['error']]);
    }
    exit;
}
