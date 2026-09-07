<!-- partials/user-active.php -->
<?php
if (!isset($activeUsers)) {
    require_once __DIR__ . '/../config.php';
    
    $query = "
        SELECT 
            ra.username,
            ra.framedipaddress AS ip_address,
            ra.callingstationid AS mac_address,
            ra.acctstarttime AS login_time,
            CAST(rr.value AS UNSIGNED) AS total_timeout,
            TIMESTAMPDIFF(SECOND, ra.acctstarttime, NOW()) AS used_seconds
        FROM radacct ra
        JOIN radreply rr ON ra.username = rr.username AND rr.attribute = 'Session-Timeout'
        WHERE ra.acctstoptime IS NULL
        ORDER BY ra.acctstarttime DESC
    ";
    $activeUsers = $pdo->query($query)->fetchAll();
}

function formatDuration($seconds) {
    if ($seconds <= 0) return '0 dtk';
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    
    $result = [];
    if ($hours > 0) $result[] = "{$hours} jam";
    if ($minutes > 0) $result[] = "{$minutes} mnt";
    if ($secs > 0 && $hours == 0) $result[] = "{$secs} dtk";
    
    return implode(' ', $result);
}
?>

<div class="card shadow-sm mb-4 border-success">
    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0">🟢 User Aktif Online (<?= count($activeUsers) ?>)</h5>
        <button class="btn btn-sm btn-light" hx-get="partials/user-active.php" hx-target="#active-user-container" hx-swap="innerHTML">
            🔄 Refresh
        </button>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Username</th>
                        <th>IP / MAC Address</th>
                        <th>Waktu Login</th>
                        <th>Durasi Terpakai</th>
                        <th>Sisa Waktu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activeUsers as $u): 
                        $remaining = $u['total_timeout'] - $u['used_seconds'];
                        $isExpiringSoon = $remaining <= 300; // Warning jika sisa <= 5 menit
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                        <td>
                            <small class="d-block text-muted">IP: <?= htmlspecialchars($u['ip_address'] ?? '-') ?></small>
                            <small class="d-block text-muted">MAC: <?= htmlspecialchars($u['mac_address'] ?? '-') ?></small>
                        </td>
                        <td><small><?= htmlspecialchars($u['login_time']) ?></small></td>
                        <td>
                            <span class="badge bg-secondary">
                                <?= formatDuration($u['used_seconds']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($remaining > 0): ?>
                                <span class="badge <?= $isExpiringSoon ? 'bg-danger' : 'bg-primary' ?>">
                                    <?= formatDuration($remaining) ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-dark">Proses Cleanup...</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; if (empty($activeUsers)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">Tidak ada user yang sedang aktif online.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>