<?php
require_once __DIR__ . '/../config/session.php';
requireLogin();

$pdo = getDB();

if (isAdmin()) {
    $riwayatList = $pdo->query('
        SELECT rp.id_riwayat, rp.tanggal_perhitungan, rp.jumlah_data, rp.keterangan,
               (SELECT p.nama_posyandu FROM hasil_moora hm
                  JOIN balita b ON b.id_balita = hm.id_balita
                  JOIN posyandu p ON p.id_posyandu = b.id_posyandu
                  WHERE hm.id_riwayat = rp.id_riwayat LIMIT 1) AS nama_posyandu
        FROM riwayat_perhitungan rp
        ORDER BY rp.id_riwayat DESC
    ')->fetchAll();
} else {
    $stmtList = $pdo->prepare('
        SELECT DISTINCT rp.id_riwayat, rp.tanggal_perhitungan, rp.jumlah_data, rp.keterangan,
               :nama_posyandu AS nama_posyandu
        FROM riwayat_perhitungan rp
        JOIN hasil_moora hm ON hm.id_riwayat = rp.id_riwayat
        JOIN balita b ON b.id_balita = hm.id_balita
        WHERE b.id_posyandu = :posyandu_id
        ORDER BY rp.id_riwayat DESC
    ');
    $stmtList->execute([
        'posyandu_id' => currentPosyanduId(),
        'nama_posyandu' => $_SESSION['nama_posyandu'] ?? '',
    ]);
    $riwayatList = $stmtList->fetchAll();
}
$riwayatId = $_GET['riwayat_id'] ?? ($riwayatList[0]['id_riwayat'] ?? null);

$batch = null;
$hasil = [];
if ($riwayatId) {
    // Pastikan batch yang dipilih memang ada di daftar yang diizinkan untuk role ini
    $allowedIds = array_column($riwayatList, 'id_riwayat');
    if (in_array($riwayatId, $allowedIds)) {
        $stmt = $pdo->prepare('SELECT * FROM riwayat_perhitungan WHERE id_riwayat = ?');
        $stmt->execute([$riwayatId]);
        $batch = $stmt->fetch();

        $stmt = $pdo->prepare('
            SELECT h.*, b.nama_balita, b.jenis_kelamin, p.usia_bulan, p.status_bbu, p.status_tbu, p.status_bbtb
            FROM hasil_moora h
            JOIN balita b ON b.id_balita = h.id_balita
            JOIN penilaian p ON p.id_penilaian = h.id_penilaian
            WHERE h.id_riwayat = :id
            ORDER BY h.ranking ASC
        ');
        $stmt->execute(['id' => $riwayatId]);
        $hasil = $stmt->fetchAll();
    }
}

$pageTitle = 'Laporan';
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/sidebar.php';
?>
<div class="main-content">
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print">
      <h4 class="mb-0"><i class="ti ti-file-report me-2"></i>Laporan Hasil Seleksi PMT</h4>
      <form method="GET" class="d-flex gap-2 align-items-center">
        <select name="riwayat_id" class="form-select form-select-sm" style="width:auto" onchange="this.form.submit()">
          <?php foreach ($riwayatList as $r): ?>
            <option value="<?= $r['id_riwayat'] ?>" <?= $riwayatId == $r['id_riwayat'] ? 'selected' : '' ?>>
              #<?= $r['id_riwayat'] ?> - <?= date('d/m/Y H:i', strtotime($r['tanggal_perhitungan'])) ?><?= !empty($r['nama_posyandu']) ? ' - ' . htmlspecialchars($r['nama_posyandu']) : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <?php if (!$batch): ?>
      <div class="alert alert-info no-print">Belum ada data perhitungan untuk dilaporkan.</div>
    <?php else: ?>

    <div class="d-flex gap-2 mb-3 no-print">
      <button class="btn btn-outline-secondary btn-sm" onclick="window.print()"><i class="ti ti-printer me-1"></i>Print</button>
      <a class="btn btn-outline-danger btn-sm" href="export_pdf.php?riwayat_id=<?= $riwayatId ?>"><i class="ti ti-file-type-pdf me-1"></i>Export PDF</a>
      <a class="btn btn-outline-success btn-sm" href="export_excel.php?riwayat_id=<?= $riwayatId ?>"><i class="ti ti-file-type-xls me-1"></i>Export Excel</a>
    </div>

    <div class="card shadow-sm">
      <div class="card-body" id="areaLaporan">
        <div class="text-center mb-4">
          <h5 class="mb-0">LAPORAN HASIL SELEKSI PENERIMA PMT</h5>
          <small>Metode MOORA &amp; Z-score WHO - Batch #<?= $batch['id_riwayat'] ?> - <?= date('d/m/Y H:i', strtotime($batch['tanggal_perhitungan'])) ?></small>
        </div>
        <table class="table table-bordered table-sm">
          <thead>
            <tr>
              <th>Rank</th><th>Nama</th><th>JK</th><th>Usia</th>
              <th>Status BB/U</th><th>Status TB/U</th><th>Status BB/TB</th>
              <th>Nilai Yi</th><th>Status PMT</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($hasil as $h): ?>
            <tr>
              <td>#<?= $h['ranking'] ?></td>
              <td><?= htmlspecialchars($h['nama_balita']) ?></td>
              <td><?= $h['jenis_kelamin'] ?></td>
              <td><?= $h['usia_bulan'] ?> bln</td>
              <td><?= $h['status_bbu'] ?></td>
              <td><?= $h['status_tbu'] ?></td>
              <td><?= $h['status_bbtb'] ?></td>
              <td><?= number_format($h['yi_value'], 5) ?></td>
              <td><?= $h['status_kelayakan'] ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<style>
@media print {
  .app-navbar, .sidebar, .no-print { display: none !important; }
  .main-content { margin-left: 0 !important; }
}
</style>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
