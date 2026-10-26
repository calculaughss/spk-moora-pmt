<?php
require_once __DIR__ . '/../config/session.php';
requireAdmin();

$pdo = getDB();
$rows = $pdo->query('
    SELECT p.*,
           (SELECT COUNT(*) FROM balita b WHERE b.id_posyandu = p.id_posyandu) AS jumlah_balita,
           (SELECT COUNT(*) FROM users u WHERE u.id_posyandu = p.id_posyandu) AS jumlah_kader
    FROM posyandu p
    ORDER BY p.nama_posyandu ASC
')->fetchAll();

$editData = ['id_posyandu' => '', 'nama_posyandu' => '', 'alamat' => ''];
if (!empty($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM posyandu WHERE id_posyandu = ?');
    $stmt->execute([$_GET['edit']]);
    $found = $stmt->fetch();
    if ($found) $editData = $found;
}

$pageTitle = 'Data Posyandu';
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/sidebar.php';
?>
<div class="main-content">
  <div class="container-fluid py-4">
    <h4 class="mb-4"><i class="ti ti-building-community me-2"></i>Data Posyandu</h4>

    <div class="row g-3">
      <div class="col-lg-4">
        <div class="card shadow-sm">
          <div class="card-body">
            <h6 class="mb-3"><?= $editData['id_posyandu'] ? 'Edit Posyandu' : 'Tambah Posyandu' ?></h6>
            <form method="POST" action="simpan.php">
              <input type="hidden" name="id" value="<?= htmlspecialchars($editData['id_posyandu']) ?>">
              <div class="mb-3">
                <label class="form-label">Nama Posyandu</label>
                <input type="text" name="nama_posyandu" class="form-control" required
                       value="<?= htmlspecialchars($editData['nama_posyandu']) ?>">
              </div>
              <div class="mb-3">
                <label class="form-label">Alamat (opsional)</label>
                <input type="text" name="alamat" class="form-control"
                       value="<?= htmlspecialchars($editData['alamat'] ?? '') ?>">
              </div>
              <button type="submit" class="btn btn-success"><i class="ti ti-device-floppy me-1"></i>Simpan</button>
              <?php if ($editData['id_posyandu']): ?>
                <a href="index.php" class="btn btn-outline-secondary">Batal</a>
              <?php endif; ?>
            </form>
          </div>
        </div>
      </div>

      <div class="col-lg-8">
        <div class="card shadow-sm">
          <div class="card-body">
            <table class="table table-striped table-hover align-middle" id="tblPosyandu" style="width:100%">
              <thead>
                <tr>
                  <th>Nama Posyandu</th>
                  <th>Alamat</th>
                  <th>Jml Balita</th>
                  <th>Jml Kader</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['nama_posyandu']) ?></td>
                  <td><?= htmlspecialchars($r['alamat'] ?? '-') ?></td>
                  <td><span class="badge bg-success"><?= $r['jumlah_balita'] ?></span></td>
                  <td><span class="badge bg-secondary"><?= $r['jumlah_kader'] ?></span></td>
                  <td class="text-nowrap">
                    <a href="index.php?edit=<?= $r['id_posyandu'] ?>" class="btn btn-primary btn-sm">Edit</a>
                    <button class="btn btn-danger btn-sm btn-hapus" data-id="<?= $r['id_posyandu'] ?>">Hapus</button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>

<script>
$(function () {
  $('#tblPosyandu').DataTable({ order: [[0, 'asc']] });

  $('.btn-hapus').on('click', function () {
    const id = $(this).data('id');
    Swal.fire({
      title: 'Hapus posyandu ini?',
      text: 'Posyandu hanya bisa dihapus jika belum punya data balita atau kader.',
      icon: 'warning', showCancelButton: true,
      confirmButtonText: 'Ya, hapus', confirmButtonColor: '#dc3545', cancelButtonText: 'Batal'
    }).then((res) => { if (res.isConfirmed) window.location.href = 'hapus.php?id=' + id; });
  });

  <?php if (isset($_GET['success'])): ?>
  Swal.fire({ icon: 'success', title: 'Berhasil disimpan', timer: 1500, showConfirmButton: false });
  <?php endif; ?>
  <?php if (isset($_GET['deleted'])): ?>
  Swal.fire({ icon: 'success', title: 'Data berhasil dihapus', timer: 1500, showConfirmButton: false });
  <?php endif; ?>
  <?php if (!empty($_GET['error'])): ?>
  Swal.fire({ icon: 'error', title: 'Gagal', text: <?= json_encode($_GET['error']) ?> });
  <?php endif; ?>
});
</script>