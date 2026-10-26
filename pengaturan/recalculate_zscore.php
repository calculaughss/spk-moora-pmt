<?php
/**
 * Recalculate ulang bbu_zscore, tbu_zscore, bbtb_zscore, imt, imtu_zscore,
 * dan status_bbu/tbu/bbtb/imtu untuk SEMUA baris di tabel `penilaian`,
 * memakai zscore.php versi terbaru (yang sudah diperbaiki).
 *
 * Data lama yang diinput SEBELUM perbaikan lms_bbtb/lms_bbu/lms_tbu/lms_imtu
 * masih menyimpan Z-score versi LAMA (salah) — script ini menimpanya dengan
 * hasil hitung ulang yang benar, supaya hasil MOORA & tampilan detail per
 * anak konsisten dengan data referensi WHO yang sudah diperbaiki.
 *
 * Taruh file ini di folder /pengaturan/ (sejajar dengan import_lms.php),
 * lalu akses lewat browser sebagai admin. Halaman ini akan menampilkan
 * PRATINJAU perubahan dulu (tidak langsung menulis ke database) — baru
 * benar-benar mengupdate kalau tombol "Jalankan & Simpan" ditekan.
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/zscore.php';
requireAdmin();

$pdo = getDB();

$stmt = $pdo->query('
    SELECT p.*, b.nama_balita, b.jenis_kelamin, b.tanggal_lahir
    FROM penilaian p
    JOIN balita b ON b.id_balita = p.id_balita
    ORDER BY p.id_penilaian ASC
');
$rows = $stmt->fetchAll();

$hasil = [];
$jumlahBerubah = 0;
$jumlahGagal = 0;

foreach ($rows as $row) {
    try {
        $z = hitungSemuaZScore(
            $pdo,
            $row['jenis_kelamin'],
            $row['tanggal_lahir'],
            $row['tanggal_pengukuran'],
            (float) $row['berat_badan'],
            (float) $row['tinggi_badan']
        );
    } catch (Throwable $e) {
        $jumlahGagal++;
        $hasil[] = [
            'row' => $row,
            'error' => $e->getMessage(),
        ];
        continue;
    }

    $baru = [
        'bbu_zscore'   => round($z['bbu_zscore'], 3),
        'tbu_zscore'   => round($z['tbu_zscore'], 3),
        'bbtb_zscore'  => round($z['bbtb_zscore'], 3),
        'imt'          => round($z['imt'], 2),
        'imtu_zscore'  => round($z['imtu_zscore'], 3),
        'status_bbu'   => $z['status_bbu'],
        'status_tbu'   => $z['status_tbu'],
        'status_bbtb'  => $z['status_bbtb'],
        'status_imtu'  => $z['status_imtu'],
    ];

    $lama = [
        'bbu_zscore'   => (float) $row['bbu_zscore'],
        'tbu_zscore'   => (float) $row['tbu_zscore'],
        'bbtb_zscore'  => (float) $row['bbtb_zscore'],
        'imt'          => (float) $row['imt'],
        'imtu_zscore'  => (float) $row['imtu_zscore'],
        'status_bbu'   => $row['status_bbu'],
        'status_tbu'   => $row['status_tbu'],
        'status_bbtb'  => $row['status_bbtb'],
        'status_imtu'  => $row['status_imtu'],
    ];

    $berubah =
        abs($lama['bbu_zscore']  - $baru['bbu_zscore'])  > 0.0005 ||
        abs($lama['tbu_zscore']  - $baru['tbu_zscore'])  > 0.0005 ||
        abs($lama['bbtb_zscore'] - $baru['bbtb_zscore']) > 0.0005 ||
        abs($lama['imtu_zscore'] - $baru['imtu_zscore']) > 0.0005 ||
        $lama['status_bbu']  !== $baru['status_bbu']  ||
        $lama['status_tbu']  !== $baru['status_tbu']  ||
        $lama['status_bbtb'] !== $baru['status_bbtb'] ||
        $lama['status_imtu'] !== $baru['status_imtu'];

    if ($berubah) {
        $jumlahBerubah++;
    }

    $hasil[] = [
        'row'    => $row,
        'lama'   => $lama,
        'baru'   => $baru,
        'berubah'=> $berubah,
    ];
}

$disimpan = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['konfirmasi'] ?? '') === 'ya') {
    $stmtUpdate = $pdo->prepare('
        UPDATE penilaian SET
            bbu_zscore = :bbu_zscore,
            tbu_zscore = :tbu_zscore,
            bbtb_zscore = :bbtb_zscore,
            imt = :imt,
            imtu_zscore = :imtu_zscore,
            status_bbu = :status_bbu,
            status_tbu = :status_tbu,
            status_bbtb = :status_bbtb,
            status_imtu = :status_imtu
        WHERE id_penilaian = :id
    ');

    $pdo->beginTransaction();
    foreach ($hasil as $h) {
        if (empty($h['berubah'])) {
            continue;
        }
        $stmtUpdate->execute([
            'bbu_zscore'  => $h['baru']['bbu_zscore'],
            'tbu_zscore'  => $h['baru']['tbu_zscore'],
            'bbtb_zscore' => $h['baru']['bbtb_zscore'],
            'imt'         => $h['baru']['imt'],
            'imtu_zscore' => $h['baru']['imtu_zscore'],
            'status_bbu'  => $h['baru']['status_bbu'],
            'status_tbu'  => $h['baru']['status_tbu'],
            'status_bbtb' => $h['baru']['status_bbtb'],
            'status_imtu' => $h['baru']['status_imtu'],
            'id'          => $h['row']['id_penilaian'],
        ]);
    }
    $pdo->commit();
    $disimpan = true;
}

$pageTitle = 'Recalculate Z-score';
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/sidebar.php';
?>
<div class="main-content">
  <div class="container-fluid py-4">
    <h4 class="mb-4"><i class="ti ti-refresh me-2"></i>Recalculate Z-score (Data Lama)</h4>

    <?php if ($disimpan): ?>
      <div class="alert alert-success">
        <i class="ti ti-circle-check me-1"></i>
        Berhasil! <?= $jumlahBerubah ?> baris di tabel <code>penilaian</code> sudah diupdate dengan Z-score baru.
        Silakan jalankan ulang Perhitungan MOORA supaya hasil ranking ikut memakai data yang sudah diperbaiki.
      </div>
    <?php else: ?>
      <div class="alert alert-info">
        Total <?= count($rows) ?> baris penilaian dicek.
        <strong><?= $jumlahBerubah ?> baris</strong> punya Z-score/status yang berbeda dari sebelumnya (akan diupdate).
        <?php if ($jumlahGagal > 0): ?>
          <span class="text-danger"><?= $jumlahGagal ?> baris GAGAL dihitung (lihat detail di bawah, ditandai merah) — baris ini TIDAK akan diupdate.</span>
        <?php endif; ?>
        Ini baru <strong>pratinjau</strong>, belum ada yang ditulis ke database.
      </div>

      <?php if ($jumlahBerubah > 0): ?>
      <form method="POST" onsubmit="return confirm('Yakin update <?= $jumlahBerubah ?> baris penilaian dengan Z-score baru? Aksi ini tidak bisa di-undo (tapi bisa dijalankan ulang kapan saja).');">
        <input type="hidden" name="konfirmasi" value="ya">
        <button type="submit" class="btn btn-success mb-3">
          <i class="ti ti-device-floppy me-1"></i>Jalankan &amp; Simpan <?= $jumlahBerubah ?> Perubahan
        </button>
      </form>
      <?php else: ?>
        <p class="text-muted">Tidak ada perubahan — semua data penilaian sudah konsisten dengan zscore.php saat ini.</p>
      <?php endif; ?>
    <?php endif; ?>

    <div class="table-responsive">
      <table class="table table-sm table-bordered">
        <thead>
          <tr>
            <th>Balita</th>
            <th>Tgl Ukur</th>
            <th>BB/U lama → baru</th>
            <th>TB/U lama → baru</th>
            <th>BB/TB lama → baru</th>
            <th>IMT/U lama → baru</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($hasil as $h): ?>
            <?php if (isset($h['error'])): ?>
            <tr class="table-danger">
              <td><?= htmlspecialchars($h['row']['nama_balita']) ?></td>
              <td><?= $h['row']['tanggal_pengukuran'] ?></td>
              <td colspan="4">Gagal dihitung: <?= htmlspecialchars($h['error']) ?></td>
            </tr>
            <?php elseif ($h['berubah']): ?>
            <tr class="table-warning">
              <td><?= htmlspecialchars($h['row']['nama_balita']) ?></td>
              <td><?= $h['row']['tanggal_pengukuran'] ?></td>
              <td><?= $h['lama']['bbu_zscore'] ?> → <strong><?= $h['baru']['bbu_zscore'] ?></strong></td>
              <td><?= $h['lama']['tbu_zscore'] ?> → <strong><?= $h['baru']['tbu_zscore'] ?></strong></td>
              <td><?= $h['lama']['bbtb_zscore'] ?> → <strong><?= $h['baru']['bbtb_zscore'] ?></strong></td>
              <td><?= $h['lama']['imtu_zscore'] ?> → <strong><?= $h['baru']['imtu_zscore'] ?></strong></td>
            </tr>
            <?php else: ?>
            <tr>
              <td><?= htmlspecialchars($h['row']['nama_balita']) ?></td>
              <td><?= $h['row']['tanggal_pengukuran'] ?></td>
              <td><?= $h['lama']['bbu_zscore'] ?></td>
              <td><?= $h['lama']['tbu_zscore'] ?></td>
              <td><?= $h['lama']['bbtb_zscore'] ?></td>
              <td><?= $h['lama']['imtu_zscore'] ?></td>
            </tr>
            <?php endif; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
