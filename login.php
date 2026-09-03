<?php
// login.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Jika sudah login, langsung lempar ke halaman kasir
if (isset($_SESSION['user_id'])) {
    header("Location: /");
    exit;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/database/db_users.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi!';
    } else {
        try {
            $stmt = $pdoUsers->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // Simpan data user ke Session
                $_SESSION['user_id']      = $user['id'];
                $_SESSION['username']     = $user['username'];
                $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                $_SESSION['role']         = $user['role'];

                header("Location: /");
                exit;
            } else {
                $error = 'Username atau password salah!';
            }
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan sistem: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - Mafaza App</title>
  <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
  <style>
    body {
      background-color: #f8f9fa;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .card-login {
      width: 100%;
      max-width: 400px;
      border-radius: 12px;
    }
  </style>
</head>
<body>

<div class="card card-login border-0 shadow-sm p-4 bg-white">
  <div class="text-center mb-4">
    <h4 class="fw-bold text-primary mb-1">Mafaza App</h4>
    <p class="text-muted small">Silakan login untuk mengakses sistem</p>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger py-2 small shadow-sm" role="alert">
      <?= htmlspecialchars($error); ?>
    </div>
  <?php endif; ?>

  <form action="login.php" method="POST">
    <div class="mb-3">
      <label class="form-label small fw-semibold">Username</label>
      <input type="text" name="username" class="form-control" placeholder="Masukkan username" required autofocus>
    </div>

    <div class="mb-4">
      <label class="form-label small fw-semibold">Password</label>
      <input type="password" name="password" class="form-control" placeholder="Masukkan password" required>
    </div>

    <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">
      Masuk Sistem
    </button>
  </form>

  <div class="text-center mt-4">
    <small class="text-muted">&copy; <?= date('Y'); ?> Kios Mafaza</small>
  </div>
</div>

<script src="/assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>