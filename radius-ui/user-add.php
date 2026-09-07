<?php
// user-add.php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username   = trim($_POST['username'] ?? '');
    $password   = trim($_POST['password'] ?? '');
    $rate_limit = trim($_POST['rate_limit'] ?? '');
    $validity   = (int)($_POST['validity'] ?? 3600); // Default 1 jam (dalam detik)

    if (!empty($username) && !empty($password)) {
        try {
            $pdo->beginTransaction();

            // 1. Password utama (Standar RFC FreeRADIUS)
            $stmt = $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)");
            $stmt->execute([$username, $password]);

            // 2. Limit Durasi Sesi (Standar RFC FreeRADIUS -> dikirim ke MikroTik)
            if ($validity > 0) {
                $stmtTimeout = $pdo->prepare("INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Session-Timeout', ':=', ?)");
                $stmtTimeout->execute([$username, (string)$validity]);
            }

            // 3. Speed Limit MikroTik (Standar MikroTik VSA)
            if (!empty($rate_limit)) {
                $stmtRL = $pdo->prepare("INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Mikrotik-Rate-Limit', ':=', ?)");
                $stmtRL->execute([$username, $rate_limit]);
            }

            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            http_response_code(500);
            echo "Gagal menyimpan voucher: " . $e->getMessage();
            exit;
        }
    }
}

// Return partial tabel terbaru
require_once __DIR__ . '/partials/user-list.php';