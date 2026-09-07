<!-- partials/user-list.php -->
<?php
if (!isset($users)) {
    require_once __DIR__ . '/../config.php';
    
    $query = "
        SELECT 
            rc.id, 
            rc.username, 
            rc.value AS password, 
            MAX(CASE WHEN rr.attribute = 'Mikrotik-Rate-Limit' THEN rr.value END) AS rate_limit,
            MAX(CASE WHEN rr.attribute = 'Session-Timeout' THEN rr.value END) AS session_timeout
        FROM radcheck rc
        LEFT JOIN radreply rr ON rc.username = rr.username
        WHERE rc.attribute = 'Cleartext-Password'
        GROUP BY rc.id, rc.username, rc.value
        ORDER BY rc.id DESC
    ";
    $users = $pdo->query($query)->fetchAll();
}
?>

<div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Password</th>
                <th>Speed Limit</th>
                <th>Durasi (Timeout)</th>
                <th class="text-end">Aksi</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td><?= $u['id'] ?></td>
                <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                <td><code><?= htmlspecialchars($u['password']) ?></code></td>
                <td>
                    <span class="badge bg-info text-dark">
                        <?= htmlspecialchars($u['rate_limit'] ?? 'Unlimited') ?>
                    </span>
                </td>
                <td>
                    <span class="badge bg-warning text-dark">
                        <?= isset($u['session_timeout']) ? htmlspecialchars($u['session_timeout']) . ' dtk' : 'Unlimited' ?>
                    </span>
                </td>
                <td class="text-end">
                    <button class="btn btn-sm btn-outline-danger"
                            hx-delete="user-delete.php?user=<?= urlencode($u['username']) ?>"
                            hx-target="#user-list-container"
                            hx-confirm="Yakin mau hapus user '<?= htmlspecialchars($u['username']) ?>'?"
                            hx-swap="innerHTML">
                        Hapus
                    </button>
                </td>
            </tr>
            <?php endforeach; if (empty($users)): ?>
            <tr>
                <td colspan="6" class="text-center text-muted py-4">Belum ada user RADIUS.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>