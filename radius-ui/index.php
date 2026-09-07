<!-- index.php -->
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Voucher Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
</head>
<body class="bg-light">
<div class="container py-4">
    <h2 class="mb-4">Hotspot Voucher Manager</h2>

    <!-- Form Massal -->
    <?php include __DIR__ . '/partials/user-batch-form.php'; ?>

    <!-- Container Daftar User Aktif (Auto refresh tiap 10 detik via htmx) -->
    <div id="active-user-container" hx-get="partials/user-active.php" hx-trigger="load, every 10s" hx-swap="innerHTML">
        <?php include __DIR__ . '/partials/user-active.php'; ?>
    </div>

    <!-- Container List Semua Voucher -->
    <div id="user-list-container">
        <?php include __DIR__ . '/partials/user-list.php'; ?>
    </div>
</div>
</body>
</html>