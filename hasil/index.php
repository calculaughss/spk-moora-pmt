<?php
require_once __DIR__ . '/../config/session.php';
requireLogin();

$pdo = getDB();
$pageTitle = 'Hasil Seleksi';

// =========================================================
// MODE ADMIN: monitor SELURUH batch perhitungan MOORA dari
// SEMUA posyandu & SEMUA bulan (bukan hanya batch terbaru),
// supaya admin bisa memantau riwayat tiap bulan per posyandu.
// =========================================================
if (isAdmin()) {
    $posyanduList = $pdo->query('SELECT id_posyandu, nama_posyandu FROM posyandu ORDER BY nama_posyandu ASC')->fetchAll();

    $posyanduFilter = $_GET['posyandu_id'] ?? '';
    $stmtBatchList = $pdo->prepare('
        SELECT DISTINCT rp.id_riwayat, rp.periode, rp.tanggal_perhitungan, rp.jumlah_data, rp.keterangan,
               p.id_posyandu, p.nama_posyandu
        FROM riwayat_perhitungan rp
        JOIN hasil_moora hm ON hm.id_riwayat = rp.id_riwayat
        JOIN balita b ON b.id_balita = hm.id_balita
        JOIN posyandu p ON p.id_posyandu = b.id_posyandu
        ' . ($posyanduFilter !== '' ? 'WHERE p.id_posyandu = :posyandu_id' : '') . '
        ORDER BY p.nama_posyandu ASC, rp.periode DESC, rp.id_riwayat DESC
    ');
    if ($posyanduFilter !== '') {
        $stmtBatchList->execute(['posyandu_id' => $posyanduFilter]);
    } else {
        $stmtBatchList->execute();
    }
    $batchList = $stmtBatchList->fetchAll();

    // Kelompokkan per posyandu, agar tiap posyandu bisa menampilkan SEMUA bulannya
    $groupsByPosyandu = [];
    foreach ($batchList as $b) {
        $groupsByPosyandu[$b['nama_posyandu']][] = $b;
    }

    // Batch yang sedang dibuka detailnya (default: batch pertama pada grup pertama)
    $riwayatId = $_GET['riwayat_id'] ?? ($batchList[0]['id_riwayat'] ?? null);
    $hasilDetail = [];
    $batchAktif = null;
    if ($riwayatId) {
        foreach ($batchList as $b) {
            if ($b['id_riwayat'] == $riwayatId) { $batchAktif = $b; break; }
        }
    }
    if ($batchAktif) {
        $stmtHasil = $pdo->prepare('
            SELECT h.*, b.nama_balita
            FROM hasil_moora h
            JOIN balita b ON b.id_balita = h.id_balita
            WHERE h.id_riwayat = :riwayat_id
            ORDER BY h.ranking ASC
        ');
        $stmtHasil->execute(['riwayat_id' => $riwayatId]);
        $hasilDetail = $stmtHasil->fetchAll();
    }

    require_once __DIR__ . '/../templates/header.php';
    require_once __DIR__ . '/../templates/sidebar.php';
    ?>
    <div class="main-content">
      <div class="container-fluid py-4">
        <div class="d-flex justify-content-between align-items-center mb-1 flex-wrap gap-2">
          <h4 class="mb-0"><i class="ti ti-list-numbers me-2"></i>Hasil Seleksi — Monitoring Semua Posyandu</h4>
          <form method="GET" class="d-flex gap-2 align-items-center">
            <label class="form-label mb-0 small">Posyandu:</label>
            <select name="posyandu_id" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
              <option value="">Semua Posyandu</option>
              <?php foreach ($posyanduList as $p): ?>
                <option value="<?= $p['id_posyandu'] ?>" <?= (string) $posyanduFilter === (string) $p['id_posyandu'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($p['nama_posyandu']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </form>
        </div>
        <p class="text-muted small mb-4">
          Semua batch perhitungan MOORA yang pernah dijalankan tiap kader, dikelompokkan per posyandu dan
          per bulan pengukuran, dapat dipantau di sini.
        </p>

        <?php if (!$groupsByPosyandu): ?>
          <div class="alert alert-info">Belum ada posyandu yang menjalankan perhitungan MOORA.</div>
        <?php endif; ?>

        <?php foreach ($groupsByPosyandu as $namaPosyandu => $batches): ?>
        <div class="card shadow-sm mb-4">
          <div class="card-header bg-white">
            <h6 class="mb-0"><i class="ti ti-building-community me-2"></i><?= htmlspecialchars($namaPosyandu) ?></h6>
          </div>
          <div class="card-body">
            <div class="table-responsive mb-2">
              <table class="table table-sm table-hover align-middle mb-0">
                <thead><tr><th>Bulan Periode</th><th>Dijalankan</th><th>Jumlah Data</th><th>Keterangan</th><th></th></tr></thead>
                <tbody>
                  <?php foreach ($batches as $b): ?>
                  <tr class="<?= $b['id_riwayat'] == $riwayatId ? 'table-success' : '' ?>">
                    <td><span class="badge bg-primary"><?= date('F Y', strtotime($b['periode'] . '-01')) ?></span></td>
                    <td class="small"><?= date('d/m/Y H:i', strtotime($b['tanggal_perhitungan'])) ?></td>
                    <td><?= $b['jumlah_data'] ?></td>
                    <td class="small"><?= htmlspecialchars($b['keterangan'] ?? '-') ?></td>
                    <td>
                      <a href="?riwayat_id=<?= $b['id_riwayat'] ?><?= $posyanduFilter !== '' ? '&posyandu_id=' . $posyanduFilter : '' ?>"
                         class="btn btn-sm btn-outline-success">
                        <i class="ti ti-eye me-1"></i>Lihat
                      </a>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <?php endforeach; ?>

        <?php if ($batchAktif): ?>
        <div class="card shadow-sm">
          <div class="card-header bg-white">
            <h6 class="mb-0">
              Hasil — <?= htmlspecialchars($batchAktif['nama_posyandu']) ?> &middot;
              <?= date('F Y', strtotime($batchAktif['periode'] . '-01')) ?>
            </h6>
          </div>
          <div class="card-body">
            <table class="table table-striped table-hover align-middle tblHasilGroup" style="width:100%">
              <thead>
                <tr>
                  <th>Ranking</th>
                  <th>Nama Balita</th>
                  <th>Nilai Yi</th>
                  <th>Status</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($hasilDetail as $h): ?>
                <tr>
                  <td><span class="badge bg-success">#<?= $h['ranking'] ?></span></td>
                  <td><?= htmlspecialchars($h['nama_balita']) ?></td>
                  <td><?= number_format($h['yi_value'], 5) ?></td>
                  <td>
                    <span class="badge <?= $h['status_kelayakan'] === 'Layak' ? 'bg-success' : 'bg-secondary' ?>">
                      <?= $h['status_kelayakan'] ?>
                    </span>
                  </td>
                  <td>
                    <a href="detail.php?id=<?= $h['id_hasil'] ?>" class="btn btn-sm btn-outline-success">
                      <i class="ti ti-list-details me-1"></i>Detail
                    </a>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <script>
    $(function () { $('.tblHasilGroup').DataTable({ order: [[0, 'asc']], paging: false, info: false }); });
    </script>
    <?php
    require_once __DIR__ . '/../templates/footer.php';
    exit;
}

// =========================================================
// MODE KADER: SEMUA batch perhitungan MOORA (semua bulan) di
// posyandu yang ia kelola — bisa berpindah bulan lewat dropdown.
// =========================================================
$stmtRiwayat = $pdo->prepare('
    SELECT DISTINCT rp.id_riwayat, rp.periode, rp.tanggal_perhitungan, rp.jumlah_data, rp.keterangan
    FROM riwayat_perhitungan rp
    JOIN hasil_moora hm ON hm.id_riwayat = rp.id_riwayat
    JOIN balita b ON b.id_balita = hm.id_balita
    WHERE b.id_posyandu = :posyandu_id
    ORDER BY rp.periode DESC, rp.id_riwayat DESC
');
$stmtRiwayat->execute(['posyandu_id' => currentPosyanduId()]);
$riwayatList = $stmtRiwayat->fetchAll();

$riwayatId = $_GET['riwayat_id'] ?? ($riwayatList[0]['id_riwayat'] ?? null);

$hasil = [];
if ($riwayatId) {
    // Pastikan batch yang diminta memang milik posyandu kader ini
    $valid = false;
    foreach ($riwayatList as $r) {
        if ($r['id_riwayat'] == $riwayatId) { $valid = true; break; }
    }
    if ($valid) {
        $stmt = $pdo->prepare('
            SELECT h.*, b.nama_balita
            FROM hasil_moora h
            JOIN balita b ON b.id_balita = h.id_balita
            WHERE h.id_riwayat = :id AND b.id_posyandu = :posyandu_id
            ORDER BY h.ranking ASC
        ');
        $stmt->execute(['id' => $riwayatId, 'posyandu_id' => currentPosyanduId()]);
        $hasil = $stmt->fetchAll();
    }
}

require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/sidebar.php';
?>
<div class="main-content">
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <h4 class="mb-0"><i class="ti ti-list-numbers me-2"></i>Hasil Seleksi
        <small class="text-muted fs-6">— <?= htmlspecialchars($_SESSION['nama_posyandu'] ?? '-') ?></small>
      </h4>
      <form method="GET" class="d-flex gap-2 align-items-center">
        <label class="form-label mb-0 small">Bulan / Batch:</label>
        <select name="riwayat_id" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
          <?php foreach ($riwayatList as $r): ?>
            <option value="<?= $r['id_riwayat'] ?>" <?= $riwayatId == $r['id_riwayat'] ? 'selected' : '' ?>>
              <?= date('F Y', strtotime($r['periode'] . '-01')) ?> — batch #<?= $r['id_riwayat'] ?>
              (<?= date('d/m/Y H:i', strtotime($r['tanggal_perhitungan'])) ?>, <?= $r['jumlah_data'] ?> data)
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <?php if (!$riwayatList): ?>
      <div class="alert alert-info">Belum ada perhitungan MOORA yang dijalankan untuk posyandu Anda. Jalankan di menu <a href="../perhitungan/index.php">Perhitungan MOORA</a>.</div>
    <?php else: ?>
    <div class="card shadow-sm">
      <div class="card-body">
        <table class="table table-striped table-hover align-middle" id="tblHasil" style="width:100%">
          <thead>
            <tr>
              <th>Ranking</th>
              <th>Nama Balita</th>
              <th>Nilai Yi</th>
              <th>Status</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($hasil as $h): ?>
            <tr>
              <td><span class="badge bg-success">#<?= $h['ranking'] ?></span></td>
              <td><?= htmlspecialchars($h['nama_balita']) ?></td>
              <td><?= number_format($h['yi_value'], 5) ?></td>
              <td>
                <span class="badge <?= $h['status_kelayakan'] === 'Layak' ? 'bg-success' : 'bg-secondary' ?>">
                  <?= $h['status_kelayakan'] ?>
                </span>
              </td>
              <td>
                <a href="detail.php?id=<?= $h['id_hasil'] ?>" class="btn btn-sm btn-outline-success">
                  <i class="ti ti-list-details me-1"></i>Detail Perhitungan
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
$(function () { $('#tblHasil').DataTable({ order: [[0, 'asc']] }); });
<?php if (isset($_GET['success'])): ?>
Swal.fire({ icon: 'success', title: 'Perhitungan MOORA selesai', timer: 1800, showConfirmButton: false });
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
