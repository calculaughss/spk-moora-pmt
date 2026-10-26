<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/zscore.php';
requireKader();

$pdo = getDB();
$stmtBalitaList = $pdo->prepare('SELECT id_balita, nama_balita, jenis_kelamin, tanggal_lahir FROM balita WHERE id_posyandu = :posyandu_id ORDER BY nama_balita ASC');
$stmtBalitaList->execute(['posyandu_id' => currentPosyanduId()]);
$balitaList = $stmtBalitaList->fetchAll();

$selectedId = $_GET['balita_id'] ?? $_POST['balita_id'] ?? null;
$tanggalPengukuran = $_POST['tanggal_pengukuran'] ?? date('Y-m-d');
$beratInput  = $_POST['berat_badan'] ?? '';
$tinggiInput = $_POST['tinggi_badan'] ?? '';

$preview = null;
$error = null;

// Riwayat pengukuran balita terpilih (untuk konteks & mencegah duplikat bulan)
$riwayatBalita = [];
if ($selectedId) {
    $stmt = $pdo->prepare('SELECT tanggal_pengukuran, berat_badan, tinggi_badan, usia_bulan FROM penilaian WHERE id_balita = ? ORDER BY tanggal_pengukuran DESC');
    $stmt->execute([$selectedId]);
    $riwayatBalita = $stmt->fetchAll();
}

// Hitung pratinjau hanya jika form submit (POST) dengan berat/tinggi terisi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selectedId && $beratInput !== '' && $tinggiInput !== '') {
    $stmt = $pdo->prepare('SELECT * FROM balita WHERE id_balita = ?');
    $stmt->execute([$selectedId]);
    $balita = $stmt->fetch();

    if (!$balita) {
        $error = 'Balita tidak ditemukan.';
    } elseif (!is_numeric($beratInput) || $beratInput <= 0 || !is_numeric($tinggiInput) || $tinggiInput <= 0) {
        $error = 'Berat/tinggi badan harus berupa angka lebih dari 0.';
    } elseif (hitungUsiaBulan($balita['tanggal_lahir'], $tanggalPengukuran) > 60) {
        $error = 'Usia balita pada tanggal pengukuran ini sudah lebih dari 5 tahun (60 bulan) — '
               . 'balita dianggap sudah lulus dari Posyandu, sehingga pengukuran tidak dapat ditambahkan.';
    } else {
        try {
            $preview = hitungSemuaZScore(
                $pdo, $balita['jenis_kelamin'], $balita['tanggal_lahir'],
                $tanggalPengukuran, (float) $beratInput, (float) $tinggiInput
            );
            $preview['balita'] = $balita;
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
    }
}

$pageTitle = 'Tambah Pengukuran';
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/sidebar.php';
?>
<div class="main-content">
  <div class="container-fluid py-4">
    <h4 class="mb-4"><i class="ti ti-clipboard-data me-2"></i>Tambah Pengukuran Bulanan</h4>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <form method="POST" action="tambah.php">
          <div class="row g-3">
            <div class="col-md-5">
              <label class="form-label">Pilih Balita</label>
              <select name="balita_id" class="form-select select2" required>
                <option value="">-- pilih balita --</option>
                <?php foreach ($balitaList as $b): ?>
                  <?php $statusB = statusPosyandu($b['tanggal_lahir']); ?>
                  <option value="<?= $b['id_balita'] ?>" <?= $selectedId == $b['id_balita'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($b['nama_balita']) ?> (lahir <?= date('d/m/Y', strtotime($b['tanggal_lahir'])) ?>)<?= $statusB === 'Lulus' ? ' — Lulus Posyandu' : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Tanggal Pengukuran</label>
              <input type="date" name="tanggal_pengukuran" class="form-control" required
                     max="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($tanggalPengukuran) ?>">
            </div>
            <div class="col-md-2">
              <label class="form-label">Berat (kg)</label>
              <input type="number" step="0.1" min="0" name="berat_badan" class="form-control"
                     value="<?= htmlspecialchars($beratInput) ?>" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Tinggi (cm)</label>
              <input type="number" step="0.1" min="0" name="tinggi_badan" class="form-control"
                     value="<?= htmlspecialchars($tinggiInput) ?>" required>
            </div>
          </div>
          <button type="submit" class="btn btn-outline-success mt-3"><i class="ti ti-calculator me-1"></i>Hitung Pratinjau</button>
        </form>
      </div>
    </div>

    <?php if ($riwayatBalita): ?>
    <div class="alert alert-secondary small">
      <i class="ti ti-history me-1"></i>Balita ini sudah punya <?= count($riwayatBalita) ?> riwayat pengukuran.
      Pengukuran terakhir: <?= date('d/m/Y', strtotime($riwayatBalita[0]['tanggal_pengukuran'])) ?>
      (usia <?= $riwayatBalita[0]['usia_bulan'] ?> bulan, <?= $riwayatBalita[0]['berat_badan'] ?> kg / <?= $riwayatBalita[0]['tinggi_badan'] ?> cm).
      Satu balita hanya boleh punya 1 pengukuran per tanggal yang sama persis.
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
      <div class="alert alert-danger"><i class="ti ti-alert-triangle me-1"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($preview): ?>
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="mb-3">Pratinjau Perhitungan Z-score - <?= htmlspecialchars($preview['balita']['nama_balita']) ?>
          (<?= date('d/m/Y', strtotime($tanggalPengukuran)) ?>, usia <?= $preview['usia_bulan'] ?> bulan)</h6>

        <div class="table-responsive mb-3">
          <table class="table table-bordered table-sm">
            <thead><tr><th>Indikator</th><th>L</th><th>M</th><th>S</th><th>X</th><th>Z-score</th><th>Status Gizi</th></tr></thead>
            <tbody>
              <tr>
                <td>BB/U</td>
                <td><?= $preview['lms_bbu']['L'] ?></td><td><?= $preview['lms_bbu']['M'] ?></td><td><?= $preview['lms_bbu']['S'] ?></td>
                <td><?= $preview['berat_badan'] ?> kg</td>
                <td><?= $preview['bbu_zscore'] ?></td>
                <td><span class="badge bg-success"><?= $preview['status_bbu'] ?></span></td>
              </tr>
              <tr>
                <td>TB/U</td>
                <td><?= $preview['lms_tbu']['L'] ?></td><td><?= $preview['lms_tbu']['M'] ?></td><td><?= $preview['lms_tbu']['S'] ?></td>
                <td><?= $preview['tinggi_badan'] ?> cm</td>
                <td><?= $preview['tbu_zscore'] ?></td>
                <td><span class="badge bg-success"><?= $preview['status_tbu'] ?></span></td>
              </tr>
              <tr>
                <td>BB/TB</td>
                <td><?= $preview['lms_bbtb']['L'] ?></td><td><?= $preview['lms_bbtb']['M'] ?></td><td><?= $preview['lms_bbtb']['S'] ?></td>
                <td><?= $preview['berat_badan'] ?> kg</td>
                <td><?= $preview['bbtb_zscore'] ?></td>
                <td><span class="badge bg-success"><?= $preview['status_bbtb'] ?></span></td>
              </tr>
              <tr>
                <td>IMT/U</td>
                <td><?= $preview['lms_imtu']['L'] ?></td><td><?= $preview['lms_imtu']['M'] ?></td><td><?= $preview['lms_imtu']['S'] ?></td>
                <td><?= $preview['imt'] ?> kg/m&sup2;</td>
                <td><?= $preview['imtu_zscore'] ?></td>
                <td><span class="badge bg-success"><?= $preview['status_imtu'] ?></span></td>
              </tr>
            </tbody>
          </table>
        </div>
        <p class="small text-muted">
          Rumus: Z = ((X/M)^L − 1) / (L×S) jika L≠0, atau Z = ln(X/M)/S jika L=0.
        </p>

        <form method="POST" action="simpan.php">
          <input type="hidden" name="balita_id" value="<?= $preview['balita']['id_balita'] ?>">
          <input type="hidden" name="tanggal_pengukuran" value="<?= htmlspecialchars($tanggalPengukuran) ?>">
          <input type="hidden" name="berat_badan" value="<?= htmlspecialchars($beratInput) ?>">
          <input type="hidden" name="tinggi_badan" value="<?= htmlspecialchars($tinggiInput) ?>">
          <button type="submit" class="btn btn-success"><i class="ti ti-device-floppy me-1"></i>Simpan Pengukuran</button>
          <a href="index.php" class="btn btn-outline-secondary">Batal</a>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<script>$(function(){ $('.select2').select2({ width: 'resolve' }); });</script>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
