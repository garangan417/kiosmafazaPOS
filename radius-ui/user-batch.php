<?php
// user-batch.php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $qty        = (int)($_POST['qty'] ?? 10);
    $rate_limit = trim($_POST['rate_limit'] ?? '');
    $validity   = (int)($_POST['validity'] ?? 3600); // dalam detik

    // Batasi maksimum sekali generate agar server tidak overload (misal max 100)
    if ($qty > 100) $qty = 100;
    if ($qty < 1) $qty = 1;

    try {
        $pdo->beginTransaction();

        $stmtCheck = $pdo->prepare("INSERT INTO radcheck (username, attribute, op, value) VALUES (?, 'Cleartext-Password', ':=', ?)");
        $stmtTimeout = $pdo->prepare("INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Session-Timeout', ':=', ?)");
        $stmtRate = $pdo->prepare("INSERT INTO radreply (username, attribute, op, value) VALUES (?, 'Mikrotik-Rate-Limit', ':=', ?)");

        for ($i = 0; $i < $qty; $i++) {
            // Generate angka acak 6 digit
            $randomCode = (string)random_int(100000, 999999);
            $username   = $randomCode;
            $password   = $randomCode; // username = password

            // 1. Password
            $stmtCheck->execute([$username, $password]);

            // 2. Durasi (Session-Timeout)
            if ($validity > 0) {
                $stmtTimeout->execute([$username, (string)$validity]);
            }

            // 3. Bandwidth (Mikrotik-Rate-Limit)
            if (!empty($rate_limit)) {
                $stmtRate->execute([$username, $rate_limit]);
            }
        }

        $pdo->commit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(500);
        echo "Gagal membuat batch voucher: " . $e->getMessage();
        exit;
    }
}

// Return partial tabel terbaru
require_once __DIR__ . '/partials/user-list.php';