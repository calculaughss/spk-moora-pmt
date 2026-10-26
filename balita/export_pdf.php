<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/zscore.php';
requireKader();
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$pdo = getDB();
$stmt = $pdo->prepare("
    SELECT b.*,
           (SELECT COUNT(*) FROM penilaian p WHERE p.id_balita = b.id_balita) AS jumlah_pengukuran,
           (SELECT MAX(tanggal_pengukuran) FROM penilaian p WHERE p.id_balita = b.id_balita) AS pengukuran_terakhir
    FROM balita b
    WHERE b.id_posyandu = :posyandu_id
    ORDER BY b.nama_balita ASC
");
$stmt->execute(['posyandu_id' => currentPosyanduId()]);
$rows = $stmt->fetchAll();

ob_start();
?>
<style>
body { font-family: sans-serif; font-size: 10px; }
h3 { text-align: center; margin-bottom: 2px; }
p.sub { text-align: center; margin-top: 0; color: #555; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; }
th, td { border: 1px solid #999; padding: 4px 5px; text-align: left; }
th { background: #e8f5e9; }
</style>
<h3>DATA BALITA</h3>
<p class="sub">SPK Penerima PMT - Metode MOORA &amp; Z-score WHO - Dicetak <?= date('d/m/Y H:i') ?></p>
<table>
  <thead>
    <tr>
      <th>No</th><th>Nama Balita</th><th>JK</th><th>Tgl Lahir</th><th>Usia (bln)</th>
      <th>Pendapatan Ortu</th><th>Tanggungan</th><th>Riwayat Sakit</th>
      <th>Nama Ortu</th><th>Alamat</th><th>Jml Ukur</th><th>Ukur Terakhir</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $i => $r): ?>
    <tr>
      <td><?= $i + 1 ?></td>
      <td><?= htmlspecialchars($r['nama_balita']) ?></td>
      <td><?= $r['jenis_kelamin'] ?></td>
      <td><?= date('d/m/Y', strtotime($r['tanggal_lahir'])) ?></td>
      <td><?= hitungUsiaBulan($r['tanggal_lahir']) ?></td>
      <td>Rp <?= number_format($r['pendapatan_ortu'], 0, ',', '.') ?></td>
      <td><?= $r['jumlah_tanggungan'] ?></td>
      <td><?= $r['riwayat_penyakit'] ?></td>
      <td><?= htmlspecialchars($r['nama_ortu']) ?></td>
      <td><?= htmlspecialchars($r['alamat']) ?></td>
      <td><?= $r['jumlah_pengukuran'] ?></td>
      <td><?= $r['pengukuran_terakhir'] ? date('d/m/Y', strtotime($r['pengukuran_terakhir'])) : '-' ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<p style="margin-top:8px;">Total balita: <strong><?= count($rows) ?></strong></p>
<?php
$html = ob_get_clean();

$options = new Options();
$options->set('isRemoteEnabled', false);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream('data_balita_' . date('Ymd_His') . '.pdf', ['Attachment' => true]);
exit;
