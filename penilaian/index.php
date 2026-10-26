<?php
require_once __DIR__ . '/../config/session.php';
requireKader();

$pdo = getDB();
$stmt = $pdo->prepare("
    SELECT p.*, b.nama_balita
    FROM penilaian p
    JOIN balita b ON b.id_balita = p.id_balita
    WHERE b.id_posyandu = :posyandu_id
    ORDER BY p.tanggal_pengukuran DESC
");
$stmt->execute(['posyandu_id' => currentPosyanduId()]);
$rows = $stmt->fetchAll();

$pageTitle = 'Data Penilaian';
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/sidebar.php';
?>
<div class="main-content">
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="mb-0"><i class="ti ti-clipboard-data me-2"></i>Data Penilaian (Riwayat Pengukuran Bulanan)</h4>
      <a href="tambah.php" class="btn btn-success"><i class="ti ti-plus me-1"></i>Tambah Pengukuran</a>
    </div>
    <p class="text-muted small">Satu balita bisa muncul berkali-kali di sini — satu baris mewakili satu bulan pengukuran.</p>

    <div class="card shadow-sm">
      <div class="card-body">
        <table class="table table-striped table-hover align-middle" id="tblPenilaian" style="width:100%">
          <thead>
            <tr>
              <th>Tgl Pengukuran</th>
              <th>Nama Balita</th>
              <th>Usia (bln)</th>
              <th>BB (kg)</th>
              <th>TB (cm)</th>
              <th>Z BB/U</th>
              <th>Status BB/U</th>
              <th>Z TB/U</th>
              <th>Status TB/U</th>
              <th>Z BB/TB</th>
              <th>Status BB/TB</th>
              <th>IMT</th>
              <th>Z IMT/U</th>
              <th>Status IMT/U</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= date('d/m/Y', strtotime($r['tanggal_pengukuran'])) ?></td>
              <td><?= htmlspecialchars($r['nama_balita']) ?></td>
              <td><?= $r['usia_bulan'] ?></td>
              <td><?= number_format($r['berat_badan'], 1) ?></td>
              <td><?= number_format($r['tinggi_badan'], 1) ?></td>
              <td><?= number_format($r['bbu_zscore'], 2) ?></td>
              <td><span class="badge bg-secondary"><?= $r['status_bbu'] ?></span></td>
              <td><?= number_format($r['tbu_zscore'], 2) ?></td>
              <td><span class="badge bg-secondary"><?= $r['status_tbu'] ?></span></td>
              <td><?= number_format($r['bbtb_zscore'], 2) ?></td>
              <td><span class="badge bg-secondary"><?= $r['status_bbtb'] ?></span></td>
              <td><?= number_format($r['imt'], 2) ?></td>
              <td><?= number_format($r['imtu_zscore'], 2) ?></td>
              <td><span class="badge bg-secondary"><?= $r['status_imtu'] ?></span></td>
              <td class="text-nowrap">
                <a href="../balita/riwayat.php?balita_id=<?= $r['id_balita'] ?>" class="btn btn-primary btn-sm">Riwayat</a>
                <button class="btn btn-danger btn-sm btn-hapus" data-id="<?= $r['id_penilaian'] ?>">Hapus</button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>

<script>
$(function () {
  $('#tblPenilaian').DataTable({ order: [[0, 'desc']] });

  $('.btn-hapus').on('click', function () {
    const id = $(this).data('id');
    Swal.fire({
      title: 'Hapus data pengukuran ini?', icon: 'warning',
      showCancelButton: true, confirmButtonText: 'Ya, hapus', confirmButtonColor: '#dc3545'
    }).then((res) => { if (res.isConfirmed) window.location.href = 'hapus.php?id=' + id; });
  });

  <?php if (isset($_GET['success'])): ?>
  Swal.fire({ icon: 'success', title: 'Pengukuran berhasil dihitung & disimpan', timer: 1800, showConfirmButton: false });
  <?php endif; ?>
  <?php if (isset($_GET['deleted'])): ?>
  Swal.fire({ icon: 'success', title: 'Data berhasil dihapus', timer: 1500, showConfirmButton: false });
  <?php endif; ?>
});
</script>