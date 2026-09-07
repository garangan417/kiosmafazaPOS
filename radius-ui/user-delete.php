<?php
// user-delete.php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $user = $_GET['user'] ?? '';

    if (!empty($user)) {
        try {
            $pdo->beginTransaction();

            // Hapus dari radcheck
            $stmt1 = $pdo->prepare("DELETE FROM radcheck WHERE username = ?");
            $stmt1->execute([$user]);

            // Hapus dari radreply
            $stmt2 = $pdo->prepare("DELETE FROM radcheck WHERE username = ?");
            $stmt2->execute([$user]);

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            http_response_code(500);
            echo "Gagal menghapus user: " . $e->getMessage();
            exit;
        }
    }
}

// Return partial tabel terbaru untuk di-swap oleh HTMX
require 'partials/user-list.php';