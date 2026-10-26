<?php
require_once __DIR__ . '/../config/session.php';
requireAdmin();

$pdo = getDB();
$rows = $pdo->query('
    SELECT u.*, p.nama_posyandu
    FROM users u
    LEFT JOIN posyandu p ON p.id_posyandu = u.id_posyandu
    ORDER BY u.role ASC, u.nama_lengkap ASC
')->fetchAll();

$posyanduList = $pdo->query('SELECT id_posyandu, nama_posyandu FROM posyandu ORDER BY nama_posyandu ASC')->fetchAll();

$editData = ['id_user' => '', 'username' => '', 'nama_lengkap' => '', 'role' => 'kader', 'id_posyandu' => '', 'is_active' => 1];
if (!empty($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id_user = ?');
    $stmt->execute([$_GET['edit']]);
    $found = $stmt->fetch();
    if ($found) $editData = $found;
}

$pageTitle = 'Data Pengguna';
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/sidebar.php';
?>
<div class="main-content">
  <div class="container-fluid py-4">
    <h4 class="mb-4"><i class="ti ti-users me-2"></i>Data Pengguna</h4>

    <div class="row g-3">
      <div class="col-lg-4">
        <div class="card shadow-sm">
          <div class="card-body">
            <h6 class="mb-3"><?= $editData['id_user'] ? 'Edit Pengguna' : 'Tambah Pengguna' ?></h6>
            <form method="POST" action="simpan.php" id="formPengguna">
              <input type="hidden" name="id" value="<?= htmlspecialchars($editData['id_user']) ?>">

              <div class="mb-3">
                <label class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required
                       value="<?= htmlspecialchars($editData['username']) ?>">
              </div>
              <div class="mb-3">
                <label class="form-label">Nama Lengkap</label>
                <input type="text" name="nama_lengkap" class="form-control" required
                       value="<?= htmlspecialchars($editData['nama_lengkap']) ?>">
              </div>
              <div class="mb-3">
                <label class="form-label">Password <?= $editData['id_user'] ? '(kosongkan jika tidak diubah)' : '' ?></label>
                <input type="password" name="password" class="form-control" minlength="6"
                       <?= $editData['id_user'] ? '' : 'required' ?>>
              </div>
              <div class="mb-3">
                <label class="form-label">Role</label>
                <select name="role" id="selRole" class="form-select select2" required>
                  <option value="kader" <?= $editData['role'] === 'kader' ? 'selected' : '' ?>>Kader — kelola data balita, penilaian &amp; perhitungan MOORA</option>
                  <option value="admin" <?= $editData['role'] === 'admin' ? 'selected' : '' ?>>Admin — kelola kriteria, LMS WHO &amp; pengguna</option>
                </select>
              </div>
              <div class="mb-3" id="groupPosyandu">
                <label class="form-label">Posyandu yang Dikelola</label>
                <select name="posyandu_id" class="form-select select2">
                  <option value="">- Pilih Posyandu -</option>
                  <?php foreach ($posyanduList as $p): ?>
                    <option value="<?= $p['id_posyandu'] ?>" <?= (string)$editData['id_posyandu'] === (string)$p['id_posyandu'] ? 'selected' : '' ?>>
                      <?= htmlspecialchars($p['nama_posyandu']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <small class="text-muted">Wajib diisi untuk role kader. Diabaikan untuk role admin.</small>
              </div>
              <div class="mb-3 form-check">
                <input type="checkbox" name="is_active" class="form-check-input" id="chkAktif" value="1"
                       <?= $editData['is_active'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="chkAktif">Akun Aktif</label>
              </div>

              <button type="submit" class="btn btn-success"><i class="ti ti-device-floppy me-1"></i>Simpan</button>
              <?php if ($editData['id_user']): ?>
                <a href="index.php" class="btn btn-outline-secondary">Batal</a>
              <?php endif; ?>
            </form>
          </div>
        </div>
      </div>

      <div class="col-lg-8">
        <div class="card shadow-sm">
          <div class="card-body">
            <table class="table table-striped table-hover align-middle" id="tblPengguna" style="width:100%">
              <thead>
                <tr>
                  <th>Username</th>
                  <th>Nama Lengkap</th>
                  <th>Role</th>
                  <th>Posyandu</th>
                  <th>Status</th>
                  <th>Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($rows as $r): ?>
                <tr>
                  <td><?= htmlspecialchars($r['username']) ?></td>
                  <td><?= htmlspecialchars($r['nama_lengkap']) ?></td>
                  <td>
                    <span class="badge <?= $r['role'] === 'admin' ? 'bg-primary' : 'bg-success' ?>">
                      <?= ucfirst($r['role']) ?>
                    </span>
                  </td>
                  <td><?= htmlspecialchars($r['nama_posyandu'] ?? '-') ?></td>
                  <td>
                    <span class="badge <?= $r['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                      <?= $r['is_active'] ? 'Aktif' : 'Nonaktif' ?>
                    </span>
                  </td>
                  <td class="text-nowrap">
                    <a href="index.php?edit=<?= $r['id_user'] ?>" class="btn btn-primary btn-sm">Edit</a>
                    <?php if ((int) $r['id_user'] !== (int) $_SESSION['user_id']): ?>
                    <button class="btn btn-danger btn-sm btn-hapus" data-id="<?= $r['id_user'] ?>">Hapus</button>
                    <?php endif; ?>
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
  $('.select2').select2({ width: '100%' });
  $('#tblPengguna').DataTable({ order: [[2, 'asc']] });

  function toggleGroupPosyandu() {
    $('#groupPosyandu').toggle($('#selRole').val() === 'kader');
  }
  toggleGroupPosyandu();
  $('#selRole').on('change', toggleGroupPosyandu);

  $('.btn-hapus').on('click', function () {
    const id = $(this).data('id');
    Swal.fire({
      title: 'Hapus pengguna ini?', icon: 'warning',
      showCancelButton: true, confirmButtonText: 'Ya, hapus',
      confirmButtonColor: '#dc3545', cancelButtonText: 'Batal'
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