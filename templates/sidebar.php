<?php
$current = basename($_SERVER['SCRIPT_NAME'], '.php');
$dir = basename(dirname($_SERVER['SCRIPT_NAME']));
function navActive($cond) { return $cond ? 'active' : ''; }
?>
<div class="sidebar" id="appSidebar">
  <ul class="nav flex-column">
    <li class="nav-item">
      <a class="nav-link <?= navActive($dir === 'dashboard') ?>" href="<?= BASE_URL ?>dashboard/index.php">
        <i class="ti ti-layout-dashboard me-2"></i>Dashboard
      </a>
    </li>

    <?php if (isKader()): ?>
    <li class="nav-item">
      <a class="nav-link <?= navActive($dir === 'balita') ?>" href="<?= BASE_URL ?>balita/index.php">
        <i class="ti ti-baby-carriage me-2"></i>Data Balita
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= navActive($dir === 'penilaian') ?>" href="<?= BASE_URL ?>penilaian/index.php">
        <i class="ti ti-clipboard-data me-2"></i>Data Penilaian
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= navActive($dir === 'perhitungan') ?>" href="<?= BASE_URL ?>perhitungan/index.php">
        <i class="ti ti-calculator me-2"></i>Perhitungan MOORA
      </a>
    </li>
    <?php endif; ?>

    <?php if (isAdmin()): ?>
    <li class="nav-item">
      <a class="nav-link <?= navActive($dir === 'kriteria') ?>" href="<?= BASE_URL ?>kriteria/index.php">
        <i class="ti ti-adjustments me-2"></i>Kriteria &amp; Bobot
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= navActive($dir === 'pengaturan') ?>" href="<?= BASE_URL ?>pengaturan/import_lms.php">
        <i class="ti ti-settings me-2"></i>LMS WHO
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= navActive($dir === 'posyandu') ?>" href="<?= BASE_URL ?>posyandu/index.php">
        <i class="ti ti-building-community me-2"></i>Data Posyandu
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= navActive($dir === 'pengguna') ?>" href="<?= BASE_URL ?>pengguna/index.php">
        <i class="ti ti-users me-2"></i>Data Pengguna
      </a>
    </li>
    <?php endif; ?>

    <li class="nav-item">
      <a class="nav-link <?= navActive($dir === 'hasil') ?>" href="<?= BASE_URL ?>hasil/index.php">
        <i class="ti ti-list-numbers me-2"></i>Hasil Seleksi
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link <?= navActive($dir === 'laporan') ?>" href="<?= BASE_URL ?>laporan/index.php">
        <i class="ti ti-file-report me-2"></i>Laporan
      </a>
    </li>
  </ul>
</div>
