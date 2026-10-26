<?php
// Pastikan session.php sudah di-include sebelum file ini dipakai
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> - SPK PMT MOORA</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.47.0/dist/tabler-icons.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg app-navbar">
  <div class="container-fluid">
    <button class="btn btn-sm btn-outline-light d-lg-none" id="sidebarToggle"><i class="ti ti-menu-2"></i></button>
    <span class="navbar-brand ms-2 fw-bold"><i class="ti ti-heart-plus me-1"></i>SPK PMT - MOORA</span>
    <div class="ms-auto dropdown">
      <button class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown">
        <i class="ti ti-user-circle me-1"></i><?= htmlspecialchars($_SESSION['nama_lengkap'] ?? '') ?>
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
        <li><a class="dropdown-item" href="<?= BASE_URL ?>auth/ganti_password.php"><i class="ti ti-key me-2"></i>Ganti Password</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>auth/logout.php"><i class="ti ti-logout-2 me-2"></i>Logout</a></li>
      </ul>
    </div>
  </div>
</nav>
