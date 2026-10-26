<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/zscore.php';
requireLogin();

$pdo = getDB();
$id = $_GET['id'] ?? null;

$stmt = $pdo->prepare('
    SELECT h.*, b.*, p.usia_bulan, p.tanggal_pengukuran,
           p.berat_badan, p.tinggi_badan, h.id_hasil AS hasil_id
    FROM hasil_moora h
    JOIN balita b ON b.id_balita = h.id_balita
    JOIN penilaian p ON p.id_penilaian = h.id_penilaian
    WHERE h.id_hasil = :id
');
$stmt->execute(['id' => $id]);
$h = $stmt->fetch();

if (!$h) {
    header('Location: index.php');
    exit;
}

// Kader hanya boleh melihat detail balita di posyandu yang ia kelola
if (isKader() && (int) $h['id_posyandu'] !== currentPosyanduId()) {
    header('Location: index.php');
    exit;
}

$asli        = json_decode($h['matriks_asli'], true);
$subInfo     = json_decode($h['matriks_subkriteria'] ?? '{}', true) ?: [];
$normalisasi = json_decode($h['matriks_normalisasi'], true);
$terbobot    = json_decode($h['matriks_terbobot'], true);

$kriteria = $pdo->query('SELECT * FROM kriteria ORDER BY kode ASC')->fetchAll();

// LMS lookup ulang untuk ditampilkan sebagai dokumentasi langkah Z-score
try {
    $z = hitungSemuaZScore(
        $pdo, $h['jenis_kelamin'], $h['tanggal_lahir'],
        $h['tanggal_pengukuran'], (float) $h['berat_badan'], (float) $h['tinggi_badan']
    );
} catch (RuntimeException $e) {
    $z = null;
}

// Ambil seluruh matriks batch ini untuk konteks matriks keputusan/normalisasi lengkap
$stmtAll = $pdo->prepare('
    SELECT h2.*, b2.nama_balita
    FROM hasil_moora h2
    JOIN balita b2 ON b2.id_balita = h2.id_balita
    WHERE h2.id_riwayat = :rid
    ORDER BY h2.ranking ASC
');
$stmtAll->execute(['rid' => $h['id_riwayat']]);
$batchAll = $stmtAll->fetchAll();

$pageTitle = 'Detail Perhitungan';
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/sidebar.php';
?>
<div class="main-content">
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="mb-0"><i class="ti ti-list-details me-2"></i>Detail Perhitungan - <?= htmlspecialchars($h['nama_balita']) ?></h4>
      <a href="index.php?riwayat_id=<?= $h['id_riwayat'] ?>" class="btn btn-outline-secondary btn-sm">
        <i class="ti ti-arrow-left me-1"></i>Kembali
      </a>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <h6>1. Data Balita &amp; Pengukuran</h6>
        <div class="row small">
          <div class="col-md-3">Nama: <strong><?= htmlspecialchars($h['nama_balita']) ?></strong></div>
          <div class="col-md-3">JK: <strong><?= $h['jenis_kelamin'] ?></strong></div>
          <div class="col-md-3">Tanggal Pengukuran: <strong><?= date('d/m/Y', strtotime($h['tanggal_pengukuran'])) ?></strong></div>
          <div class="col-md-3">Usia saat diukur: <strong><?= $h['usia_bulan'] ?> bulan</strong></div>
          <div class="col-md-3 mt-2">BB / TB: <strong><?= $h['berat_badan'] ?> kg / <?= $h['tinggi_badan'] ?> cm</strong></div>
          <div class="col-md-3 mt-2">Pendapatan Ortu: <strong>Rp <?= number_format($h['pendapatan_ortu'], 0, ',', '.') ?></strong></div>
          <div class="col-md-3 mt-2">Jumlah Tanggungan: <strong><?= $h['jumlah_tanggungan'] ?></strong></div>
        </div>
      </div>
    </div>

    <?php if ($z): ?>
    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <h6>2. Langkah Perhitungan Z-score LMS WHO</h6>
        <div class="table-responsive">
          <table class="table table-bordered table-sm">
            <thead><tr><th>Indikator</th><th>X</th><th>L</th><th>M</th><th>S</th><th>Rumus</th><th>Z-score</th><th>Status Gizi</th></tr></thead>
            <tbody>
              <tr>
                <td>BB/U</td><td><?= $h['berat_badan'] ?></td>
                <td><?= $z['lms_bbu']['L'] ?></td><td><?= $z['lms_bbu']['M'] ?></td><td><?= $z['lms_bbu']['S'] ?></td>
                <td class="small"><?= abs($z['lms_bbu']['L']) > 0.0000001 ? '((X/M)^L−1)/(L×S)' : 'ln(X/M)/S' ?></td>
                <td><strong><?= $z['bbu_zscore'] ?></strong></td>
                <td><span class="badge bg-success"><?= $z['status_bbu'] ?></span></td>
              </tr>
              <tr>
                <td>TB/U</td><td><?= $h['tinggi_badan'] ?></td>
                <td><?= $z['lms_tbu']['L'] ?></td><td><?= $z['lms_tbu']['M'] ?></td><td><?= $z['lms_tbu']['S'] ?></td>
                <td class="small"><?= abs($z['lms_tbu']['L']) > 0.0000001 ? '((X/M)^L−1)/(L×S)' : 'ln(X/M)/S' ?></td>
                <td><strong><?= $z['tbu_zscore'] ?></strong></td>
                <td><span class="badge bg-success"><?= $z['status_tbu'] ?></span></td>
              </tr>
              <tr>
                <td>BB/TB</td><td><?= $h['berat_badan'] ?></td>
                <td><?= $z['lms_bbtb']['L'] ?></td><td><?= $z['lms_bbtb']['M'] ?></td><td><?= $z['lms_bbtb']['S'] ?></td>
                <td class="small"><?= abs($z['lms_bbtb']['L']) > 0.0000001 ? '((X/M)^L−1)/(L×S)' : 'ln(X/M)/S' ?></td>
                <td><strong><?= $z['bbtb_zscore'] ?></strong></td>
                <td><span class="badge bg-success"><?= $z['status_bbtb'] ?></span></td>
              </tr>
              <tr>
                <td>IMT/U</td><td><?= $z['imt'] ?> kg/m&sup2;</td>
                <td><?= $z['lms_imtu']['L'] ?></td><td><?= $z['lms_imtu']['M'] ?></td><td><?= $z['lms_imtu']['S'] ?></td>
                <td class="small"><?= abs($z['lms_imtu']['L']) > 0.0000001 ? '((X/M)^L−1)/(L×S)' : 'ln(X/M)/S' ?></td>
                <td><strong><?= $z['imtu_zscore'] ?></strong></td>
                <td><span class="badge bg-success"><?= $z['status_imtu'] ?></span></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <h6>3. Matriks Keputusan (Xij = bobot subkriteria, seluruh balita batch #<?= $h['id_riwayat'] ?>)</h6>
        <p class="small text-muted">
          Nilai pada tabel ini adalah <strong>bobot subkriteria</strong> hasil klasifikasi nilai mentah tiap
          kriteria (lihat menu Kriteria &gt; Subkriteria), bukan nilai mentahnya langsung — inilah Xij yang
          dipakai pada normalisasi MOORA.
        </p>
        <div class="table-responsive">
          <table class="table table-bordered table-sm">
            <thead>
              <tr>
                <th>Balita</th>
                <?php foreach ($kriteria as $k): ?><th><?= $k['kode'] ?></th><?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($batchAll as $row):
                $rowSub = json_decode($row['matriks_subkriteria'] ?? '{}', true) ?: []; ?>
              <tr class="<?= $row['id_hasil'] == $h['hasil_id'] ? 'table-success' : '' ?>">
                <td><?= htmlspecialchars($row['nama_balita']) ?></td>
                <?php foreach ($kriteria as $k):
                    $kode = $k['kode'];
                    $cell = $rowSub[$kode] ?? null; ?>
                <td>
                  <?php if ($cell): ?>
                    <?= number_format($cell['bobot'], 2) ?>
                    <span class="text-muted small">(<?= htmlspecialchars($cell['nama']) ?>)</span>
                    <?php if (!empty($cell['celah'])): ?>
                      <span class="badge bg-warning text-dark" title="Nilai berada di luar rentang subkriteria yang terdefinisi, dipetakan ke baris terdekat.">celah</span>
                    <?php endif; ?>
                  <?php else: ?>
                    -
                  <?php endif; ?>
                </td>
                <?php endforeach; ?>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <p class="small text-muted mb-0">Baris berwarna hijau adalah data balita yang sedang ditampilkan.</p>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <h6>4. Normalisasi &amp; Pembobotan - <?= htmlspecialchars($h['nama_balita']) ?></h6>
        <p class="small text-muted">Rumus normalisasi: x* = Xij / √(Σxij²). Nilai terbobot: vij = x* × bobot kriteria.</p>
        <div class="table-responsive">
          <table class="table table-bordered table-sm">
            <thead>
              <tr><th>Kriteria</th><th>Jenis</th><th>Nilai Mentah</th><th>Subkriteria (Xij)</th><th>Bobot Kriteria</th><th>Normalisasi (x*)</th><th>Terbobot (v)</th></tr>
            </thead>
            <tbody>
              <?php foreach ($kriteria as $k):
                $kode = $k['kode'];
                $sub = $subInfo[$kode] ?? null; ?>
              <tr>
                <td><?= $kode ?> - <?= htmlspecialchars($k['nama_kriteria']) ?></td>
                <td><span class="badge <?= $k['jenis'] === 'benefit' ? 'bg-primary' : 'bg-warning text-dark' ?>"><?= ucfirst($k['jenis']) ?></span></td>
                <td><?= $asli[$kode] ?? '-' ?></td>
                <td>
                  <?php if ($sub): ?>
                    <strong><?= number_format($sub['bobot'], 2) ?></strong>
                    <span class="text-muted small">(<?= htmlspecialchars($sub['nama']) ?>)</span>
                  <?php else: ?>-<?php endif; ?>
                </td>
                <td><?= number_format($k['bobot'], 3) ?></td>
                <td><?= $normalisasi[$kode] ?? '-' ?></td>
                <td><?= $terbobot[$kode] ?? '-' ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <h6>5. Perhitungan Yi &amp; Ranking Akhir</h6>
        <table class="table table-bordered table-sm w-auto">
          <tbody>
            <tr><th>Σ Benefit (v)</th><td><?= $h['sum_benefit'] ?></td></tr>
            <tr><th>Σ Cost (v)</th><td><?= $h['sum_cost'] ?></td></tr>
            <tr><th>Yi = Σ Benefit − Σ Cost</th><td><strong><?= $h['yi_value'] ?></strong></td></tr>
            <tr><th>Ranking</th><td><span class="badge bg-success">#<?= $h['ranking'] ?></span></td></tr>
            <tr><th>Status Kelayakan PMT</th>
                <td><span class="badge <?= $h['status_kelayakan'] === 'Layak' ? 'bg-success' : 'bg-secondary' ?>"><?= $h['status_kelayakan'] ?></span></td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
