<?php
// cron-cleanup.php
require_once __DIR__ . '/config.php';

try {
    // Cari user yang waktu sejak login pertamanya sudah melebihi Session-Timeout
    $sql = "
        SELECT rc.username 
        FROM radcheck rc
        JOIN radreply rr ON rc.username = rr.username AND rr.attribute = 'Session-Timeout'
        JOIN (
            SELECT username, MIN(acctstarttime) AS first_login 
            FROM radacct 
            GROUP BY username
        ) ra ON rc.username = ra.username
        WHERE TIMESTAMPDIFF(SECOND, ra.first_login, NOW()) >= CAST(rr.value AS UNSIGNED)
          AND rc.attribute = 'Cleartext-Password'
    ";

    $stmt = $pdo->query($sql);
    $expiredUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (!empty($expiredUsers)) {
        $in = str_repeat('?,', count($expiredUsers) - 1) . '?';

        $pdo->beginTransaction();

        $deleteCheck = $pdo->prepare("DELETE FROM radcheck WHERE username IN ($in)");
        $deleteCheck->execute($expiredUsers);

        $deleteReply = $pdo->prepare("DELETE FROM radreply WHERE username IN ($in)");
        $deleteReply->execute($expiredUsers);

        $pdo->commit();

        echo "[" . date('Y-m-d H:i:s') . "] Berhasil menghapus " . count($expiredUsers) . " voucher kedaluwarsa.\n";
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error Cleanup: " . $e->getMessage() . "\n";
}