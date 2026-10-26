<?php
require_once __DIR__ . '/../config/session.php';
requireAdmin();

$pdo = getDB();
$countBBU  = (int) $pdo->query('SELECT COUNT(*) FROM lms_bbu')->fetchColumn();
$countTBU  = (int) $pdo->query('SELECT COUNT(*) FROM lms_tbu')->fetchColumn();
$countBBTB = (int) $pdo->query('SELECT COUNT(*) FROM lms_bbtb')->fetchColumn();

$pageTitle = 'Pengaturan';
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/sidebar.php';
?>
<div class="main-content">
  <div class="container-fluid py-4">
    <h4 class="mb-4"><i class="ti ti-settings me-2"></i>Pengaturan</h4>

    <div class="row g-3">
      <div class="col-md-4">
        <div class="card shadow-sm h-100">
          <div class="card-body text-center">
            <i class="ti ti-adjustments" style="font-size:2rem;color:#2e7d32;"></i>
            <h6 class="mt-2">Kriteria &amp; Bobot MOORA</h6>
            <a href="../kriteria/index.php" class="btn btn-sm btn-outline-success mt-2">Kelola</a>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card shadow-sm h-100">
          <div class="card-body text-center">
            <i class="ti ti-table-import" style="font-size:2rem;color:#2e7d32;"></i>
            <h6 class="mt-2">Import Referensi LMS WHO</h6>
            <p class="small text-muted mb-1">
              BB/U: <?= $countBBU ?> baris · TB/U: <?= $countTBU ?> baris · BB/TB: <?= $countBBTB ?> baris
            </p>
            <a href="import_lms.php" class="btn btn-sm btn-outline-success mt-1">Kelola</a>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="card shadow-sm h-100">
          <div class="card-body text-center">
            <i class="ti ti-key" style="font-size:2rem;color:#2e7d32;"></i>
            <h6 class="mt-2">Ganti Password Akun</h6>
            <a href="../auth/ganti_password.php" class="btn btn-sm btn-outline-success mt-2">Kelola</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
