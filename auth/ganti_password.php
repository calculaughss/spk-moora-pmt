<?php
require_once __DIR__ . '/../config/session.php';
requireLogin();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lama   = $_POST['password_lama'] ?? '';
    $baru   = $_POST['password_baru'] ?? '';
    $ulang  = $_POST['password_ulang'] ?? '';

    if ($lama === '' || $baru === '' || $ulang === '') {
        $errors[] = 'Semua field wajib diisi.';
    } elseif (strlen($baru) < 6) {
        $errors[] = 'Password baru minimal 6 karakter.';
    } elseif ($baru !== $ulang) {
        $errors[] = 'Konfirmasi password baru tidak cocok.';
    }

    if (empty($errors)) {
        $pdo = getDB();
        $stmt = $pdo->prepare('SELECT password FROM users WHERE id_user = :id');
        $stmt->execute(['id' => $_SESSION['user_id']]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($lama, $row['password'])) {
            $errors[] = 'Password lama tidak sesuai.';
        } else {
            $hash = password_hash($baru, PASSWORD_BCRYPT);
            $update = $pdo->prepare('UPDATE users SET password = :password WHERE id_user = :id');
            $update->execute(['password' => $hash, 'id' => $_SESSION['user_id']]);
            $success = true;
        }
    }
}

require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/sidebar.php';
?>
<div class="main-content">
  <div class="container-fluid py-4">
    <h4 class="mb-4"><i class="ti ti-key me-2"></i>Ganti Password</h4>
    <div class="card shadow-sm" style="max-width: 500px;">
      <div class="card-body">
        <?php if ($errors): ?>
          <div class="alert alert-danger">
            <ul class="mb-0">
              <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>
        <form method="POST">
          <div class="mb-3">
            <label class="form-label">Password Lama</label>
            <input type="password" name="password_lama" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Password Baru</label>
            <input type="password" name="password_baru" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Ulangi Password Baru</label>
            <input type="password" name="password_ulang" class="form-control" required>
          </div>
          <button type="submit" class="btn btn-success"><i class="ti ti-check me-1"></i>Simpan</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php if ($success): ?>
<script>
Swal.fire({ icon: 'success', title: 'Password berhasil diubah', timer: 1800, showConfirmButton: false });
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
