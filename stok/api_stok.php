<?php
// stok/api_stok.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';

// ==========================================
// 1. DEKLARASI FUNGSI HELPER
// ==========================================

// Helper: Send HTMX Toast Header
function sendHtmxToast($icon, $title) {
    header('HX-Trigger: ' . json_encode([
        'showToast' => ['icon' => $icon, 'title' => $title]
    ]));
}

// Helper: Render Isi Tabel Stok dengan Filter Pencarian Nama & Barcode
function renderTabelStok($pdo, $searchQuery = '') {
    $searchQuery = trim($searchQuery);
    
    $sql = "SELECT DISTINCT
                bk.id AS kemasan_id,
                b.nama_barang,
                bk.nama_kemasan,
                bk.satuan,
                COALESCE(bk.isi, 1) AS isi,
                COALESCE(bk.stok, 0) AS stok
            FROM barang_kemasan bk
            JOIN barang b ON bk.barang_id = b.id
            LEFT JOIN barang_barcode bb ON bk.id = bb.barang_kemasan_id";
    
    $params = [];
    if ($searchQuery !== '') {
        $sql .= " WHERE b.nama_barang LIKE ? OR bk.nama_kemasan LIKE ? OR bb.barcode LIKE ?";
        $searchTerm = '%' . $searchQuery . '%';
        $params = [$searchTerm, $searchTerm, $searchTerm];
    }
    
    $sql .= " ORDER BY b.nama_barang ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($list)) {
        echo '<tr><td colspan="4" class="text-center text-muted py-4"><i class="bi bi-inbox d-block fs-3 mb-1"></i>Data barang tidak ditemukan.</td></tr>';
        return;
    }

    foreach ($list as $b) {
        ?>
        <tr>
            <td><strong class="text-dark"><?= htmlspecialchars($b['nama_barang']) ?></strong></td>
            <td>
                <span class="badge bg-info-subtle text-info border border-info me-1">
                    <?= htmlspecialchars($b['nama_kemasan']) ?>
                </span>
                <small class="text-muted">(1 <?= htmlspecialchars($b['nama_kemasan']) ?> = <?= $b['isi'] ?> <?= htmlspecialchars($b['satuan']) ?>)</small>
            </td>
            <td class="text-center font-monospace fw-bold fs-6">
                <?= number_format($b['stok'], 0, ',', '.') ?> <small class="fw-normal text-muted"><?= htmlspecialchars($b['satuan']) ?></small>
            </td>
            <td class="text-end">
                <button type="button" 
                        class="btn btn-sm btn-outline-secondary"
                        onclick="setOpname(<?= $b['kemasan_id'] ?>, '<?= addslashes(htmlspecialchars($b['nama_barang'])) ?>', <?= $b['stok'] ?>, '<?= htmlspecialchars($b['satuan']) ?>')">
                    <i class="bi bi-pencil-square me-1"></i> Opname
                </button>
            </td>
        </tr>
        <?php
    }
}

// ==========================================
// 2. LOGIKA ROUTING EKSEKUSI
// ==========================================

$action      = $_GET['action'] ?? '';
$searchQuery = $_GET['q'] ?? $_POST['q'] ?? '';

if ($action === 'load_tabel') {
    renderTabelStok($pdoBarang, $searchQuery);
    exit;
}

if ($action === 'tambah_stok') {
    $kemasanId  = intval($_POST['kemasan_id'] ?? 0);
    $qty        = intval($_POST['qty'] ?? 0);
    $opsiSatuan = $_POST['opsi_satuan'] ?? 'ECER';
    $keterangan = trim($_POST['keterangan'] ?? 'Stok Masuk');

    if ($kemasanId <= 0 || $qty <= 0) {
        sendHtmxToast('error', 'Pilih barang dan jumlah stok dengan benar!');
        renderTabelStok($pdoBarang, $searchQuery);
        exit;
    }

    try {
        $stmt = $pdoBarang->prepare("SELECT isi, stok FROM barang_kemasan WHERE id = ?");
        $stmt->execute([$kemasanId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            sendHtmxToast('error', 'Data barang tidak ditemukan!');
            renderTabelStok($pdoBarang, $searchQuery);
            exit;
        }

        $isi = max(1, intval($row['isi']));
        $tambahanStokPcs = ($opsiSatuan === 'GROSIR') ? ($qty * $isi) : $qty;

        $pdoBarang->beginTransaction();
        $updateStmt = $pdoBarang->prepare("UPDATE barang_kemasan SET stok = stok + ? WHERE id = ?");
        $updateStmt->execute([$tambahanStokPcs, $kemasanId]);
        $pdoBarang->commit();

        sendHtmxToast('success', 'Stok berhasil ditambahkan!');
    } catch (Exception $e) {
        if ($pdoBarang->inTransaction()) {
            $pdoBarang->rollBack();
        }
        sendHtmxToast('error', 'Error Database: ' . $e->getMessage());
    }

    renderTabelStok($pdoBarang, $searchQuery);
    exit;
}

if ($action === 'opname') {
    $kemasanId = intval($_POST['kemasan_id'] ?? 0);
    $stokFisik = intval($_POST['stok_fisik'] ?? 0);

    if ($kemasanId > 0 && $stokFisik >= 0) {
        try {
            $stmt = $pdoBarang->prepare("UPDATE barang_kemasan SET stok = ? WHERE id = ?");
            $stmt->execute([$stokFisik, $kemasanId]);

            sendHtmxToast('success', 'Stock opname berhasil diperbarui!');
        } catch (Exception $e) {
            sendHtmxToast('error', 'Gagal Opname: ' . $e->getMessage());
        }
    } else {
        sendHtmxToast('error', 'Data Opname tidak valid!');
    }

    renderTabelStok($pdoBarang, $searchQuery);
    exit;
}