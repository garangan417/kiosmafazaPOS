<?php
// kasir/api_search_pelanggan.php
require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_pelanggan.php';

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');

try {
    if (!empty($q)) {
        $stmt = $pdoPelanggan->prepare("SELECT id, nama, no_hp FROM pelanggan WHERE nama LIKE ? OR no_hp LIKE ? ORDER BY nama ASC LIMIT 10");
        $stmt->execute(["%$q%", "%$q%"]);
    } else {
        $stmt = $pdoPelanggan->query("SELECT id, nama, no_hp FROM pelanggan ORDER BY nama ASC LIMIT 20");
    }
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'data' => $data]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}