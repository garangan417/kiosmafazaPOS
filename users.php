<?php
// users.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/database/db_users.php';

// HANYA ROLE 'admin' YANG BOLEH MEMBUKA HALAMAN INI
check_role(['admin']);

$error   = '';
$success = '';

// --------------------------------------------------------------------------
// HANDLER ACTION (TAMBAH / EDIT / HAPUS USER)
// --------------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. TAMBAH USER BARU
    if ($action === 'create') {
        $username    = trim($_POST['username'] ?? '');
        $password    = trim($_POST['password'] ?? '');
        $namaLengkap = trim($_POST['nama_lengkap'] ?? '');
        $role        = $_POST['role'] ?? 'kasir';

        if (empty($username) || empty($password) || empty($namaLengkap)) {
            $error = 'Semua kolom wajib diisi!';
        } else {
            try {
                $hashPassword = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdoUsers->prepare("INSERT INTO users (username, password, nama_lengkap, role) VALUES (?, ?, ?, ?)");
                $stmt->execute([$username, $hashPassword, $namaLengkap, $role]);
                $success = 'User baru berhasil ditambahkan!';
            } catch (PDOException $e) {
                if (str_contains($e->getMessage(), 'UNIQUE')) {
                    $error = 'Username sudah digunakan, cari username lain!';
                } else {
                    $error = 'Gagal menambah user: ' . $e->getMessage();
                }
            }
        }
    }

    // 2. EDIT USER (NAMA, ROLE, & RESET PASSWORD)
    elseif ($action === 'update') {
        $id          = intval($_POST['id'] ?? 0);
        $namaLengkap = trim($_POST['nama_lengkap'] ?? '');
        $role        = $_POST['role'] ?? 'kasir';
        $newPassword = trim($_POST['password'] ?? '');

        if ($id <= 0 || empty($namaLengkap)) {
            $error = 'Data tidak valid!';
        } else {
            try {
                if (!empty($newPassword)) {
                    // Update dengan password baru
                    $hashPassword = password_hash($newPassword, PASSWORD_BCRYPT);
                    $stmt = $pdoUsers->prepare("UPDATE users SET nama_lengkap = ?, role = ?, password = ? WHERE id = ?");
                    $stmt->execute([$namaLengkap, $role, $hashPassword, $id]);
                } else {
                    // Update tanpa mengubah password
                    $stmt = $pdoUsers->prepare("UPDATE users SET nama_lengkap = ?, role = ? WHERE id = ?");
                    $stmt->execute([$namaLengkap, $role, $id]);
                }
                $success = 'Data user berhasil diperbarui!';
            } catch (PDOException $e) {
                $error = 'Gagal memperbarui user: ' . $e->getMessage();
            }
        }
    }

    // 3. HAPUS USER
    elseif ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);

        // Mencegah admin menghapus akunnya sendiri yang sedang digunakan
        if ($id === $_SESSION['user_id']) {
            $error = 'Anda tidak bisa menghapus akun Anda sendiri yang sedang aktif!';
        } else {
            try {
                $stmt = $pdoUsers->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$id]);
                $success = 'User berhasil dihapus!';
            } catch (PDOException $e) {
                $error = 'Gagal menghapus user: ' . $e->getMessage();
            }
        }
    }
}

// AMBIL SEMUA DATA USER UNTUK TABEL
$stmtUsers  = $pdoUsers->query("SELECT id, username, nama_lengkap, role, created_at FROM users ORDER BY id DESC");
$listUsers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

require_once BASE_PATH . 'partials/header.php';
?>

<div class="container-fluid my-4 px-4">
  
  <div class="d-flex justify-content-between align-items-center mb-4">
    <div>
      <h4 class="fw-bold mb-1"><i class="bi bi-people-fill text-primary me-2"></i>Pengelola User & Hak Akses</h4>
      <p class="text-muted small mb-0">Kelola akun admin dan kasir untuk mengakses aplikasi Mafaza.</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalTambahUser">
      <i class="bi bi-person-plus-fill me-1"></i> Tambah User Baru
    </button>
  </div>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
      <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <?php if (!empty($success)): ?>
    <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
      <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success); ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  <?php endif; ?>

  <!-- TABEL DAFTAR USER -->
  <div class="card shadow-sm border-0">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th class="ps-3">No</th>
              <th>Username</th>
              <th>Nama Lengkap</th>
              <th>Role / Hak Akses</th>
              <th>Tanggal Dibuat</th>
              <th class="text-center">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($listUsers)): ?>
              <tr>
                <td colspan="6" class="text-center text-muted py-4">Belum ada data user.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($listUsers as $no => $u): ?>
                <tr>
                  <td class="ps-3"><?= $no + 1; ?></td>
                  <td><strong class="text-dark"><?= htmlspecialchars($u['username']); ?></strong></td>
                  <td><?= htmlspecialchars($u['nama_lengkap']); ?></td>
                  <td>
                    <?php if ($u['role'] === 'admin'): ?>
                      <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                        <i class="bi bi-shield-lock-fill me-1"></i>Admin
                      </span>
                    <?php else: ?>
                      <span class="badge bg-success-subtle text-success border border-success-subtle">
                        <i class="bi bi-cart-fill me-1"></i>Kasir
                      </span>
                    <?php endif; ?>
                  </td>
                  <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($u['created_at'])); ?></td>
                  <td class="text-center">
                    <!-- Tombol Edit -->
                    <button class="btn btn-sm btn-outline-warning me-1" 
                            data-bs-toggle="modal" 
                            data-bs-target="#modalEditUser<?= $u['id']; ?>">
                      <i class="bi bi-pencil-square"></i>
                    </button>
                    
                    <!-- Tombol Hapus -->
                    <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                      <form action="users.php" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus user ini?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $u['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">
                          <i class="bi bi-trash-fill"></i>
                        </button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>

                <!-- MODAL EDIT USER -->
                <div class="modal fade" id="modalEditUser<?= $u['id']; ?>" tabindex="-1">
                  <div class="modal-dialog">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h6 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Edit User: <?= htmlspecialchars($u['username']); ?></h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <form action="users.php" method="POST">
                        <div class="modal-body">
                          <input type="hidden" name="action" value="update">
                          <input type="hidden" name="id" value="<?= $u['id']; ?>">

                          <div class="mb-3">
                            <label class="form-label small fw-semibold">Nama Lengkap</label>
                            <input type="text" name="nama_lengkap" class="form-control" value="<?= htmlspecialchars($u['nama_lengkap']); ?>" required>
                          </div>

                          <div class="mb-3">
                            <label class="form-label small fw-semibold">Role / Hak Akses</label>
                            <select name="role" class="form-select">
                              <option value="kasir" <?= $u['role'] === 'kasir' ? 'selected' : ''; ?>>Kasir</option>
                              <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            </select>
                          </div>

                          <div class="mb-3">
                            <label class="form-label small fw-semibold">Reset Password (Opsional)</label>
                            <input type="password" name="password" class="form-control" placeholder="Isi hanya jika ingin mengganti password">
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
                          <button type="submit" class="btn btn-sm btn-primary">Simpan Perubahan</button>
                        </div>
                      </form>
                    </div>
                  </div>
                </div>

              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

</div>

<!-- MODAL TAMBAH USER BARU -->
<div class="modal fade" id="modalTambahUser" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title fw-bold"><i class="bi bi-person-plus-fill me-2"></i>Tambah User Baru</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form action="users.php" method="POST">
        <div class="modal-body">
          <input type="hidden" name="action" value="create">

          <div class="mb-3">
            <label class="form-label small fw-semibold">Username</label>
            <input type="text" name="username" class="form-control" placeholder="Contoh: kasir1" required>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Nama Lengkap</label>
            <input type="text" name="nama_lengkap" class="form-control" placeholder="Contoh: Budi Santoso" required>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Password</label>
            <input type="password" name="password" class="form-control" placeholder="Masukkan password" required>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Role / Hak Akses</label>
            <select name="role" class="form-select">
              <option value="kasir">Kasir</option>
              <option value="admin">Admin</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button type="submit" class="btn btn-sm btn-primary">Tambah User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once BASE_PATH . 'partials/footer.php'; ?>