<?php
// kasir/api_search_pelanggan.php
require_once __DIR__ . '/../config.php';
require_once BASE_PATH . 'database/db_pelanggan.php';

header('Content-Type: application/json');

// Bersihkan input dari spasi berlebih di awal/akhir
$q = trim($_GET['q'] ?? '');

try {
    if (!empty($q)) {
        // Query dengan sistem prioritas/relevansi:
        // 1. Nama yang diawali dengan kata kunci didahulukan
        // 2. Nama yang mengandung kata kunci menyusul
        // 3. Nomor HP yang cocok
        $sql = "SELECT id, nama, no_hp 
                FROM pelanggan 
                WHERE nama LIKE ? OR no_hp LIKE ? 
                ORDER BY 
                    CASE 
                        WHEN nama LIKE ? THEN 1 
                        WHEN nama LIKE ? THEN 2 
                        ELSE 3 
                    END ASC, 
                    nama ASC 
                LIMIT 100";

        $stmt = $pdoPelanggan->prepare($sql);
        
        // Eksekusi dengan parameter (pastikan urutan wildcard-nya pas)
        $stmt->execute([
            "%$q%",  // Untuk WHERE nama LIKE ?
            "%$q%",  // Untuk WHERE no_hp LIKE ?
            "$q%",   // Untuk CASE WHEN nama LIKE ? (diawali)
            "%$q%"   // Untuk CASE WHEN nama LIKE ? (mengandung)
        ]);
    } else {
        // Jika kotak pencarian kosong, tampilkan daftar awal dengan limit 60
        $stmt = $pdoPelanggan->query("SELECT id, nama, no_hp FROM pelanggan ORDER BY nama ASC LIMIT 60");
    }

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['status' => 'success', 'data' => $data]);

} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}