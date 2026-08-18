<?php
// kasir/api_favorit.php
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_barang.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// 1. AMBIL / CARI SEMUA BARANG KEMASAN BESERTA STATUS FAVORIT
if ($action === 'search') {
    $q = trim($_GET['q'] ?? '');
    try {
        $sql = "SELECT 
                    bk.id AS kemasan_id,
                    b.nama_barang,
                    bk.nama_kemasan,
                    bk.satuan,
                    bk.isi,
                    bk.is_favorite
                FROM barang_kemasan bk
                JOIN barang b ON bk.barang_id = b.id
                WHERE b.nama_barang LIKE ? OR bk.nama_kemasan LIKE ?
                ORDER BY bk.is_favorite DESC, b.nama_barang ASC
                LIMIT 30";

        $searchTerm = '%' . $q . '%';
        $stmt = $pdoBarang->prepare($sql);
        $stmt->execute([$searchTerm, $searchTerm]);
        
        echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// 2. TOGGLE (UBAH) STATUS FAVORIT
if ($action === 'toggle') {
    $kemasanId = intval($_POST['kemasan_id'] ?? 0);
    $status    = intval($_POST['status'] ?? 0); // 1 = Tambah Favorit, 0 = Hapus Favorit

    if ($kemasanId > 0) {
        try {
            $stmt = $pdoBarang->prepare("UPDATE barang_kemasan SET is_favorite = ? WHERE id = ?");
            $stmt->execute([$status, $kemasanId]);

            echo json_encode(['status' => 'success', 'message' => 'Status favorit diperbarui']);
        } catch (PDOException $e) {
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Kemasan ID tidak valid']);
    }
    exit;
}