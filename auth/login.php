<?php
require_once __DIR__ . '/../config/session.php';

if (isLoggedIn()) {
    header('Location: ' . BASE_URL . 'dashboard/index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - SPK PMT MOORA</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.47.0/dist/tabler-icons.min.css">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="login-page d-flex align-items-center justify-content-center">
  <div class="card login-card shadow-lg">
    <div class="card-body p-5">
      <div class="text-center mb-4">
        <i class="ti ti-heart-plus login-icon"></i>
        <h4 class="fw-bold mt-2 mb-0">Sistem Pendukung Keputusan Penerima PMT</h4>
        <small class="text-muted">Desa Karangmulya, Kecamatan Jamanis, Kab. Tasikmalaya</small>
      </div>
      <form id="formLogin" method="POST" action="process_login.php">
        <div class="mb-3">
          <label class="form-label">Username</label>
          <input type="text" name="username" class="form-control" required autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-success w-100">
          <i class="ti ti-login-2 me-1"></i> Masuk
        </button>
      </form>
    </div>
  </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
<?php if (isset($_GET['error'])): ?>
Swal.fire({
  icon: 'error',
  title: 'Login Gagal',
  text: '<?= $_GET['error'] === 'invalid' ? 'Username atau password salah.' : 'Terjadi kesalahan, coba lagi.' ?>'
});
<?php endif; ?>
<?php if (isset($_GET['logout'])): ?>
Swal.fire({ icon: 'success', title: 'Berhasil Logout', timer: 1500, showConfirmButton: false });
<?php endif; ?>
</script>
</body>
</html>
