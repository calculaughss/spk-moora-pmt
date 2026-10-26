<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/zscore.php';
requireKader();

$pdo = getDB();
$stmt = $pdo->prepare("
    SELECT b.*,
           (SELECT COUNT(*) FROM penilaian p WHERE p.id_balita = b.id_balita) AS jumlah_pengukuran,
           (SELECT MAX(tanggal_pengukuran) FROM penilaian p WHERE p.id_balita = b.id_balita) AS pengukuran_terakhir
    FROM balita b
    WHERE b.id_posyandu = :posyandu_id
    ORDER BY b.created_at DESC
");
$stmt->execute(['posyandu_id' => currentPosyanduId()]);
$rows = $stmt->fetchAll();

$pageTitle = 'Data Balita';
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/sidebar.php';
?>
<div class="main-content">
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <h4 class="mb-0"><i class="ti ti-baby-carriage me-2"></i>Data Balita
        <small class="text-muted fs-6">— <?= htmlspecialchars($_SESSION['nama_posyandu'] ?? '-') ?></small>
      </h4>
      <div class="d-flex gap-2">
        <a href="import.php" class="btn btn-outline-primary"><i class="ti ti-file-import me-1"></i>Import Data</a>
        <a href="export_pdf.php" class="btn btn-outline-danger"><i class="ti ti-file-type-pdf me-1"></i>Export PDF</a>
        <a href="export_excel.php" class="btn btn-outline-success"><i class="ti ti-file-type-xls me-1"></i>Export Excel</a>
        <a href="form.php" class="btn btn-success"><i class="ti ti-plus me-1"></i>Tambah Balita</a>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <table class="table table-striped table-hover align-middle" id="tblBalita" style="width:100%">
          <thead>
            <tr>
              <th>Nama Balita</th>
              <th>JK</th>
              <th>Usia Sekarang (bln)</th>
              <th>Status Posyandu</th>
              <th>Pendapatan</th>
              <th>Tanggungan</th>
              <th>Jml Pengukuran</th>
              <th>Pengukuran Terakhir</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['nama_balita']) ?></td>
              <td><?= $r['jenis_kelamin'] ?></td>
              <td><?= hitungUsiaBulan($r['tanggal_lahir']) ?></td>
              <td>
                <?php $statusR = statusPosyandu($r['tanggal_lahir']); ?>
                <span class="badge <?= $statusR === 'Lulus' ? 'bg-warning text-dark' : 'bg-success' ?>">
                  <?= $statusR === 'Lulus' ? 'Lulus Posyandu' : 'Aktif' ?>
                </span>
              </td>
              <td>Rp <?= number_format($r['pendapatan_ortu'], 0, ',', '.') ?></td>
              <td><?= $r['jumlah_tanggungan'] ?></td>
              <td><span class="badge bg-success"><?= $r['jumlah_pengukuran'] ?></span></td>
              <td><?= $r['pengukuran_terakhir'] ? date('d/m/Y', strtotime($r['pengukuran_terakhir'])) : '-' ?></td>
              <td class="text-nowrap">
                <a href="riwayat.php?balita_id=<?= $r['id_balita'] ?>" class="btn btn-primary btn-sm">Riwayat</a>
                <?php if ($statusR === 'Aktif'): ?>
                <a href="../penilaian/tambah.php?balita_id=<?= $r['id_balita'] ?>" class="btn btn-success btn-sm">Ukur</a>
                <?php else: ?>
                <button class="btn btn-secondary btn-sm" disabled title="Sudah lulus posyandu, pengukuran tidak diperlukan lagi">Ukur</button>
                <?php endif; ?>
                <a href="form.php?id=<?= $r['id_balita'] ?>" class="btn btn-secondary btn-sm">Edit</a>
                <button class="btn btn-danger btn-sm btn-hapus" data-id="<?= $r['id_balita'] ?>">Hapus</button>
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
  $('#tblBalita').DataTable({ order: [[0, 'asc']] });

  $('.btn-hapus').on('click', function () {
    const id = $(this).data('id');
    Swal.fire({
      title: 'Hapus data balita ini?',
      text: 'Seluruh riwayat penilaian & hasil MOORA terkait juga akan terhapus.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Ya, hapus',
      confirmButtonColor: '#dc3545',
      cancelButtonText: 'Batal'
    }).then((res) => {
      if (res.isConfirmed) {
        window.location.href = 'hapus.php?id=' + id;
      }
    });
  });

  <?php if (isset($_GET['success'])): ?>
  Swal.fire({
    icon: 'success', title: 'Berhasil disimpan', timer: 1500, showConfirmButton: false
  }).then(() => {
    <?php if (!empty($_GET['new_id'])): ?>
    if (confirm('Balita baru berhasil ditambahkan. Input pengukuran bulan ini sekarang?')) {
      window.location.href = '../penilaian/tambah.php?balita_id=<?= (int)$_GET['new_id'] ?>';
    }
    <?php endif; ?>
  });
  <?php endif; ?>
  <?php if (isset($_GET['deleted'])): ?>
  Swal.fire({ icon: 'success', title: 'Data berhasil dihapus', timer: 1500, showConfirmButton: false });
  <?php endif; ?>
});
</script>