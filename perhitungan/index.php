<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/zscore.php';
requireKader();

$pdo = getDB();

// Daftar bulan (periode) yang punya data penilaian di posyandu ini,
// beserta jumlah balita AKTIF yang diukur pada bulan tsb.
// Perhitungan MOORA sengaja dibatasi PER BULAN PENGUKURAN supaya adil:
// balita yang tidak hadir/tidak diukur bulan ini TIDAK ikut memakai
// data pengukuran bulan-bulan sebelumnya.
$stmtPeriode = $pdo->prepare("
    SELECT DATE_FORMAT(p.tanggal_pengukuran, '%Y-%m') AS periode,
           b.tanggal_lahir
    FROM penilaian p
    JOIN balita b ON b.id_balita = p.id_balita
    WHERE b.id_posyandu = :posyandu_id
");
$stmtPeriode->execute(['posyandu_id' => currentPosyanduId()]);
$semuaPengukuran = $stmtPeriode->fetchAll();

$periodeInfo = []; // periode => ['aktif' => n, 'lulus' => n]
foreach ($semuaPengukuran as $row) {
    $p = $row['periode'];
    if (!isset($periodeInfo[$p])) {
        $periodeInfo[$p] = ['aktif' => 0, 'lulus' => 0];
    }
    if (statusPosyandu($row['tanggal_lahir']) === 'Aktif') {
        $periodeInfo[$p]['aktif']++;
    } else {
        $periodeInfo[$p]['lulus']++;
    }
}
krsort($periodeInfo); // bulan terbaru di atas

$periodeTerpilih = $_GET['periode'] ?? array_key_first($periodeInfo);
$jumlahPenilaian = $periodeInfo[$periodeTerpilih]['aktif'] ?? 0;
$jumlahLulus = $periodeInfo[$periodeTerpilih]['lulus'] ?? 0;

$kriteria = $pdo->query('SELECT * FROM kriteria ORDER BY kode ASC')->fetchAll();
$totalBobot = array_sum(array_column($kriteria, 'bobot'));

$stmtRiwayat = $pdo->prepare('
    SELECT DISTINCT rp.*
    FROM riwayat_perhitungan rp
    JOIN hasil_moora hm ON hm.id_riwayat = rp.id_riwayat
    JOIN balita b ON b.id_balita = hm.id_balita
    WHERE b.id_posyandu = :posyandu_id
    ORDER BY rp.periode DESC, rp.id_riwayat DESC
');
$stmtRiwayat->execute(['posyandu_id' => currentPosyanduId()]);
$riwayat = $stmtRiwayat->fetchAll();

$pageTitle = 'Perhitungan MOORA';
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/sidebar.php';
?>
<div class="main-content">
  <div class="container-fluid py-4">
    <h4 class="mb-4"><i class="ti ti-calculator me-2"></i>Perhitungan MOORA</h4>

    <div class="card shadow-sm mb-4">
      <div class="card-body">

        <?php if (!$periodeInfo): ?>
          <div class="alert alert-warning mb-0">Belum ada data penilaian sama sekali. Tambahkan pengukuran dulu di menu Data Penilaian.</div>
        <?php else: ?>

        <p class="mb-3">
          Balita aktif dengan data penilaian bulan <strong><?= date('F Y', strtotime($periodeTerpilih . '-01')) ?></strong>:
          <strong><?= $jumlahPenilaian ?></strong>
          <?php if ($jumlahLulus > 0): ?>
            <span class="text-muted">(+<?= $jumlahLulus ?> balita lain sudah lulus posyandu &amp; otomatis dikecualikan)</span>
          <?php endif; ?>.
        </p>

        <?php if (abs($totalBobot - 1.0) > 0.0009): ?>
          <div class="alert alert-warning">
            <i class="ti ti-alert-triangle me-1"></i>
            Total bobot kriteria saat ini <strong><?= number_format($totalBobot, 3) ?></strong>, belum 1.000.
            Perbaiki dulu di menu <a href="../kriteria/index.php">Kriteria &amp; Bobot</a>.
          </div>
        <?php endif; ?>

        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label">Bulan Pengukuran</label>
            <form method="GET" id="formPeriode">
              <select name="periode" class="form-select" onchange="this.form.submit()">
                <?php foreach ($periodeInfo as $p => $info): ?>
                  <option value="<?= $p ?>" <?= $p === $periodeTerpilih ? 'selected' : '' ?>>
                    <?= date('F Y', strtotime($p . '-01')) ?> (<?= $info['aktif'] ?> balita aktif<?= $info['lulus'] ? ', +' . $info['lulus'] . ' lulus posyandu' : '' ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </form>
          </div>

          <form method="POST" action="proses.php" onsubmit="return confirmProses();" class="col-md-8">
            <input type="hidden" name="periode" value="<?= htmlspecialchars($periodeTerpilih ?? '') ?>">
            <div class="row g-3 align-items-end">
              <div class="col-md-4">
                <label class="form-label">Jumlah Kuota Penerima PMT</label>
                <input type="number" name="kuota" class="form-control" min="1" max="<?= max($jumlahPenilaian,1) ?>"
                       value="<?= max(1, intdiv($jumlahPenilaian, 2) ?: 1) ?>" required>
              </div>
              <div class="col-md-5">
                <label class="form-label">Keterangan</label>
                <input type="text" name="keterangan" class="form-control" placeholder="mis. Catatan tambahan untuk batch ini">
              </div>
              <div class="col-md-3">
                <button type="submit" class="btn btn-success w-100" <?= ($jumlahPenilaian < 1 || abs($totalBobot-1.0) > 0.0009) ? 'disabled' : '' ?>>
                  <i class="ti ti-player-play me-1"></i>Jalankan Perhitungan
                </button>
              </div>
            </div>
          </form>
        </div>

        <?php endif; ?>
      </div>
    </div>

    <h6 class="mb-3">Riwayat Perhitungan (Semua Bulan)</h6>
    <div class="card shadow-sm">
      <div class="card-body">
        <table class="table table-hover align-middle" id="tblRiwayat">
          <thead><tr><th>Bulan Periode</th><th>Dijalankan Pada</th><th>Jumlah Data</th><th>Keterangan</th><th>Aksi</th></tr></thead>
          <tbody>
            <?php foreach ($riwayat as $r): ?>
            <tr>
              <td><span class="badge bg-primary"><?= date('F Y', strtotime($r['periode'] . '-01')) ?></span></td>
              <td><?= date('d/m/Y H:i', strtotime($r['tanggal_perhitungan'])) ?></td>
              <td><?= $r['jumlah_data'] ?></td>
              <td><?= htmlspecialchars($r['keterangan'] ?? '-') ?></td>
              <td>
                <a href="../hasil/index.php?riwayat_id=<?= $r['id_riwayat'] ?>" class="btn btn-sm btn-outline-success">
                  <i class="ti ti-list-numbers me-1"></i>Lihat Hasil
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$riwayat): ?>
            <tr><td colspan="5" class="text-center text-muted">Belum ada riwayat perhitungan.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
function confirmProses() {
  return confirm('Jalankan perhitungan MOORA untuk bulan yang dipilih?');
}
$(function(){ $('#tblRiwayat').DataTable({ order: [[1, 'desc']], paging: false, searching: false, info: false }); });
<?php if (!empty($_GET['error'])): ?>
Swal.fire({ icon: 'error', title: 'Gagal', text: <?= json_encode($_GET['error']) ?> });
<?php endif; ?>
</script>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>