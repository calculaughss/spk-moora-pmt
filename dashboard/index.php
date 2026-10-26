<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/zscore.php';
requireLogin();

$pdo = getDB();

$jumlahBalita   = (int) $pdo->query('SELECT COUNT(*) FROM balita')->fetchColumn();
$jumlahPenilaian= (int) $pdo->query('SELECT COUNT(*) FROM penilaian')->fetchColumn();
$jumlahKriteria = (int) $pdo->query('SELECT COUNT(*) FROM kriteria')->fetchColumn();

$semuaTglLahir = $pdo->query('SELECT tanggal_lahir FROM balita')->fetchAll();
$jumlahLulusPosyandu = 0;
foreach ($semuaTglLahir as $b) {
    if (statusPosyandu($b['tanggal_lahir']) === 'Lulus') {
        $jumlahLulusPosyandu++;
    }
}

$latestRiwayatId = $pdo->query('SELECT id_riwayat FROM riwayat_perhitungan ORDER BY id_riwayat DESC LIMIT 1')->fetchColumn();

$jumlahHasil = 0;
$layak = 0;
$tidakLayak = 0;

if ($latestRiwayatId) {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM hasil_moora WHERE id_riwayat = ?');
    $stmt->execute([$latestRiwayatId]);
    $jumlahHasil = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM hasil_moora WHERE id_riwayat = ? AND status_kelayakan = 'Layak'");
    $stmt->execute([$latestRiwayatId]);
    $layak = (int) $stmt->fetchColumn();

    $tidakLayak = $jumlahHasil - $layak;
}

function statusCount(PDO $pdo, string $column): array
{
    // Hanya hitung pengukuran TERBARU tiap balita, supaya distribusi mencerminkan
    // status gizi saat ini (bukan menghitung ulang seluruh riwayat bulanan).
    $sql = "
        SELECT p.$column AS status, COUNT(*) AS jumlah
        FROM penilaian p
        INNER JOIN (
            SELECT id_balita, MAX(id_penilaian) AS max_id FROM penilaian GROUP BY id_balita
        ) latest ON latest.max_id = p.id_penilaian
        GROUP BY p.$column
    ";
    $rows = $pdo->query($sql)->fetchAll();
    $result = [];
    foreach ($rows as $r) {
        $result[$r['status']] = (int) $r['jumlah'];
    }
    return $result;
}

$distBBU  = statusCount($pdo, 'status_bbu');
$distTBU  = statusCount($pdo, 'status_tbu');
$distBBTB = statusCount($pdo, 'status_bbtb');
$distIMTU = statusCount($pdo, 'status_imtu');

$pageTitle = 'Dashboard';
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/sidebar.php';
?>
<div class="main-content">
  <div class="container-fluid py-4">
    <h4 class="mb-4"><i class="ti ti-layout-dashboard me-2"></i>Dashboard</h4>

    <div class="row g-3 mb-4">
      <div class="col-md-3 col-6">
        <div class="card stat-card bg-green p-3">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="small">Jumlah Balita</div>
              <div class="fs-3 fw-bold"><?= $jumlahBalita ?></div>
            </div>
            <i class="ti ti-baby-carriage"></i>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="card stat-card bg-green p-3">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="small">Jumlah Kriteria</div>
              <div class="fs-3 fw-bold"><?= $jumlahKriteria ?></div>
            </div>
            <i class="ti ti-adjustments"></i>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="card stat-card bg-gray p-3">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="small">Hasil Seleksi Terakhir</div>
              <div class="fs-3 fw-bold"><?= $jumlahHasil ?></div>
            </div>
            <i class="ti ti-list-numbers"></i>
          </div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="card stat-card bg-green p-3">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="small">Lulus Posyandu (&gt;5 tahun)</div>
              <div class="fs-3 fw-bold"><?= $jumlahLulusPosyandu ?></div>
            </div>
            <i class="ti ti-school"></i>
          </div>
        </div>
      </div>
    </div>

    <div class="row g-3">
      <div class="col-lg-4">
        <div class="card p-3 shadow-sm">
          <h6 class="mb-3">Distribusi Status Gizi BB/U</h6>
          <canvas id="chartBBU"></canvas>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="card p-3 shadow-sm">
          <h6 class="mb-3">Distribusi Status Gizi TB/U</h6>
          <canvas id="chartTBU"></canvas>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="card p-3 shadow-sm">
          <h6 class="mb-3">Distribusi Status Gizi BB/TB</h6>
          <canvas id="chartBBTB"></canvas>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="card p-3 shadow-sm">
          <h6 class="mb-3">Distribusi Status Gizi IMT/U</h6>
          <canvas id="chartIMTU"></canvas>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card p-3 shadow-sm">
          <h6 class="mb-3">Balita Layak vs Tidak Layak PMT (batch terakhir)</h6>
          <canvas id="chartLayak"></canvas>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
const palette = ['#2e7d32', '#66bb6a', '#a5d6a7', '#ef5350', '#ffa726', '#6c757d'];

function buildPie(id, labelsObj) {
  const labels = Object.keys(labelsObj);
  const data = Object.values(labelsObj);
  new Chart(document.getElementById(id), {
    type: 'doughnut',
    data: { labels: labels, datasets: [{ data: data, backgroundColor: palette }] },
    options: { plugins: { legend: { position: 'bottom' } } }
  });
}

buildPie('chartBBU', <?= json_encode($distBBU) ?>);
buildPie('chartTBU', <?= json_encode($distTBU) ?>);
buildPie('chartBBTB', <?= json_encode($distBBTB) ?>);
buildPie('chartIMTU', <?= json_encode($distIMTU) ?>);

new Chart(document.getElementById('chartLayak'), {
  type: 'bar',
  data: {
    labels: ['Layak', 'Tidak Layak'],
    datasets: [{ label: 'Jumlah Balita', data: [<?= $layak ?>, <?= $tidakLayak ?>], backgroundColor: ['#2e7d32', '#ef5350'] }]
  },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
});
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
