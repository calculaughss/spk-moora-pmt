<?php
require_once __DIR__ . '/../config/session.php';
requireLogin();
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$riwayatId = $_GET['riwayat_id'] ?? null;
if (!$riwayatId) {
    header('Location: index.php');
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare('SELECT * FROM riwayat_perhitungan WHERE id_riwayat = ?');
$stmt->execute([$riwayatId]);
$batch = $stmt->fetch();

if (!$batch) {
    header('Location: index.php');
    exit;
}

// Kader hanya boleh export batch milik posyandu yang ia kelola
if (isKader()) {
    $chk = $pdo->prepare('
        SELECT COUNT(*) FROM hasil_moora hm
        JOIN balita b ON b.id_balita = hm.id_balita
        WHERE hm.id_riwayat = ? AND b.id_posyandu = ?
    ');
    $chk->execute([$riwayatId, currentPosyanduId()]);
    if ((int) $chk->fetchColumn() === 0) {
        header('Location: index.php');
        exit;
    }
}

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

ob_start();
?>
<style>
body { font-family: sans-serif; font-size: 11px; }
h3 { text-align: center; margin-bottom: 2px; }
p.sub { text-align: center; margin-top: 0; color: #555; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; }
th, td { border: 1px solid #999; padding: 4px 6px; text-align: left; }
th { background: #e8f5e9; }
</style>
<h3>LAPORAN HASIL SELEKSI PENERIMA PMT</h3>
<p class="sub">Metode MOORA &amp; Z-score WHO — Batch #<?= $batch['id_riwayat'] ?> — <?= date('d/m/Y H:i', strtotime($batch['tanggal_perhitungan'])) ?></p>
<table>
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
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', false);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream('laporan_pmt_batch_' . $batch['id_riwayat'] . '.pdf', ['Attachment' => true]);
exit;
