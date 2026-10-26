<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/zscore.php';
requireKader();

$balitaId = $_GET['balita_id'] ?? null;
$pdo = getDB();

$stmt = $pdo->prepare('SELECT * FROM balita WHERE id_balita = ? AND id_posyandu = ?');
$stmt->execute([$balitaId, currentPosyanduId()]);
$balita = $stmt->fetch();

if (!$balita) {
    header('Location: index.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM penilaian WHERE id_balita = ? ORDER BY tanggal_pengukuran ASC');
$stmt->execute([$balitaId]);
$riwayat = $stmt->fetchAll();

$labels = array_map(fn($r) => date('M Y', strtotime($r['tanggal_pengukuran'])), $riwayat);
$berat  = array_map(fn($r) => (float) $r['berat_badan'], $riwayat);
$tinggi = array_map(fn($r) => (float) $r['tinggi_badan'], $riwayat);
$zBBU   = array_map(fn($r) => (float) $r['bbu_zscore'], $riwayat);
$zTBU   = array_map(fn($r) => (float) $r['tbu_zscore'], $riwayat);
$zBBTB  = array_map(fn($r) => (float) $r['bbtb_zscore'], $riwayat);

$pageTitle = 'Riwayat Pertumbuhan';
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/sidebar.php';
?>
<div class="main-content">
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="mb-0"><i class="ti ti-chart-line me-2"></i>Riwayat Pertumbuhan - <?= htmlspecialchars($balita['nama_balita']) ?></h4>
      <div>
        <a href="../penilaian/tambah.php?balita_id=<?= $balita['id_balita'] ?>" class="btn btn-success btn-sm">
          <i class="ti ti-plus me-1"></i>Tambah Pengukuran
        </a>
        <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="ti ti-arrow-left me-1"></i>Kembali</a>
      </div>
    </div>

    <?php if (!$riwayat): ?>
      <div class="alert alert-info">
        Belum ada riwayat pengukuran untuk balita ini.
        <a href="../penilaian/tambah.php?balita_id=<?= $balita['id_balita'] ?>">Tambahkan pengukuran pertama</a>.
      </div>
    <?php else: ?>

    <div class="row g-3 mb-3">
      <div class="col-lg-6">
        <div class="card shadow-sm p-3">
          <h6 class="mb-2">Tren Berat &amp; Tinggi Badan</h6>
          <canvas id="chartBBTB2"></canvas>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card shadow-sm p-3">
          <h6 class="mb-2">Tren Z-score (BB/U, TB/U, BB/TB)</h6>
          <canvas id="chartZscore"></canvas>
        </div>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <table class="table table-striped table-sm align-middle" id="tblRiwayat">
          <thead>
            <tr>
              <th>Tanggal Pengukuran</th><th>Usia (bln)</th><th>BB (kg)</th><th>TB (cm)</th>
              <th>Z BB/U</th><th>Status</th><th>Z TB/U</th><th>Status</th><th>Z BB/TB</th><th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (array_reverse($riwayat) as $r): ?>
            <tr>
              <td><?= date('d/m/Y', strtotime($r['tanggal_pengukuran'])) ?></td>
              <td><?= $r['usia_bulan'] ?></td>
              <td><?= number_format($r['berat_badan'], 1) ?></td>
              <td><?= number_format($r['tinggi_badan'], 1) ?></td>
              <td><?= number_format($r['bbu_zscore'], 2) ?></td>
              <td><span class="badge bg-secondary"><?= $r['status_bbu'] ?></span></td>
              <td><?= number_format($r['tbu_zscore'], 2) ?></td>
              <td><span class="badge bg-secondary"><?= $r['status_tbu'] ?></span></td>
              <td><?= number_format($r['bbtb_zscore'], 2) ?></td>
              <td><span class="badge bg-secondary"><?= $r['status_bbtb'] ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($riwayat): ?>
<script>
new Chart(document.getElementById('chartBBTB2'), {
  type: 'line',
  data: {
    labels: <?= json_encode($labels) ?>,
    datasets: [
      { label: 'Berat (kg)', data: <?= json_encode($berat) ?>, borderColor: '#2e7d32', yAxisID: 'y', tension: 0.2 },
      { label: 'Tinggi (cm)', data: <?= json_encode($tinggi) ?>, borderColor: '#66bb6a', yAxisID: 'y1', tension: 0.2 }
    ]
  },
  options: {
    scales: {
      y:  { type: 'linear', position: 'left', title: { display: true, text: 'kg' } },
      y1: { type: 'linear', position: 'right', title: { display: true, text: 'cm' }, grid: { drawOnChartArea: false } }
    }
  }
});

new Chart(document.getElementById('chartZscore'), {
  type: 'line',
  data: {
    labels: <?= json_encode($labels) ?>,
    datasets: [
      { label: 'Z BB/U', data: <?= json_encode($zBBU) ?>, borderColor: '#2e7d32', tension: 0.2 },
      { label: 'Z TB/U', data: <?= json_encode($zTBU) ?>, borderColor: '#ef5350', tension: 0.2 },
      { label: 'Z BB/TB', data: <?= json_encode($zBBTB) ?>, borderColor: '#ffa726', tension: 0.2 }
    ]
  },
  options: { scales: { y: { title: { display: true, text: 'Z-score' } } } }
});

$(function () { $('#tblRiwayat').DataTable({ order: [], paging: false, searching: false, info: false }); });
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
