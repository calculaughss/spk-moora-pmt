<?php
require_once __DIR__ . '/../config/session.php';
requireAdmin();

$pdo = getDB();
$kriteria = $pdo->query('SELECT * FROM kriteria ORDER BY kode ASC')->fetchAll();
$totalBobot = array_sum(array_column($kriteria, 'bobot'));

// Jumlah subkriteria per kriteria, untuk indikator "sudah lengkap / belum" di tabel
$jumlahSub = $pdo->query('SELECT id_kriteria, COUNT(*) AS n FROM subkriteria GROUP BY id_kriteria')
    ->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'Kriteria & Bobot';
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/sidebar.php';
?>
<div class="main-content">
  <div class="container-fluid py-4">
    <h4 class="mb-4"><i class="ti ti-adjustments me-2"></i>Kriteria &amp; Bobot MOORA</h4>
    <div class="card shadow-sm">
      <div class="card-body">
        <form method="POST" action="simpan.php" id="formBobot">
          <table class="table table-bordered align-middle">
            <thead>
              <tr>
                <th>Kode</th>
                <th>Nama Kriteria</th>
                <th>Jenis</th>
                <th style="width:150px">Bobot</th>
                <th>Keterangan</th>
                <th style="width:160px">Subkriteria</th>
                <th style="width:110px">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($kriteria as $k): ?>
              <tr>
                <td><span class="badge bg-success"><?= htmlspecialchars($k['kode']) ?></span></td>
                <td><?= htmlspecialchars($k['nama_kriteria']) ?></td>
                <td>
                  <span class="badge <?= $k['jenis'] === 'benefit' ? 'bg-primary' : 'bg-warning text-dark' ?>">
                    <?= ucfirst($k['jenis']) ?>
                  </span>
                </td>
                <td>
                  <input type="hidden" name="id[]" value="<?= $k['id_kriteria'] ?>">
                  <input type="number" step="0.001" min="0" max="1" name="bobot[]"
                         class="form-control bobot-input" value="<?= number_format($k['bobot'], 3) ?>" required>
                </td>
                <td class="small text-muted"><?= htmlspecialchars($k['keterangan']) ?></td>
                <td>
                  <a href="subkriteria.php?kriteria_id=<?= $k['id_kriteria'] ?>" class="btn btn-sm btn-outline-success">
                    <i class="ti ti-list-details me-1"></i><?= (int) ($jumlahSub[$k['id_kriteria']] ?? 0) ?> baris
                  </a>
                </td>
                <td class="text-nowrap">
                  <button type="button" class="btn btn-primary btn-sm btn-edit-kriteria"
                          data-bs-toggle="modal" data-bs-target="#modalEdit<?= $k['id_kriteria'] ?>">
                    Edit
                  </button>
                  <button type="button" class="btn btn-danger btn-sm btn-hapus-kriteria"
                          data-id="<?= $k['id_kriteria'] ?>" data-kode="<?= htmlspecialchars($k['kode']) ?>"
                          data-jumlahsub="<?= (int) ($jumlahSub[$k['id_kriteria']] ?? 0) ?>">
                    Hapus
                  </button>
                </td>
              </tr>

              <!-- Modal edit kriteria <?= htmlspecialchars($k['kode']) ?> -->
              <div class="modal fade" id="modalEdit<?= $k['id_kriteria'] ?>" tabindex="-1">
                <div class="modal-dialog">
                  <div class="modal-content">
                    <form method="POST" action="edit_simpan.php">
                      <div class="modal-header">
                        <h6 class="modal-title">Edit Kriteria <?= htmlspecialchars($k['kode']) ?></h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <input type="hidden" name="id" value="<?= $k['id_kriteria'] ?>">
                        <div class="mb-3">
                          <label class="form-label">Kode</label>
                          <input type="text" class="form-control" value="<?= htmlspecialchars($k['kode']) ?>" disabled>
                          <small class="text-muted">Kode tidak bisa diubah karena dipakai sebagai acuan tetap pada rumus perhitungan.</small>
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Nama Kriteria</label>
                          <input type="text" name="nama_kriteria" class="form-control" required
                                 value="<?= htmlspecialchars($k['nama_kriteria']) ?>">
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Jenis</label>
                          <select name="jenis" class="form-select" required>
                            <option value="benefit" <?= $k['jenis'] === 'benefit' ? 'selected' : '' ?>>Benefit</option>
                            <option value="cost" <?= $k['jenis'] === 'cost' ? 'selected' : '' ?>>Cost</option>
                          </select>
                        </div>
                        <div class="mb-3">
                          <label class="form-label">Bobot</label>
                          <input type="number" step="0.001" min="0" max="1" name="bobot" class="form-control" required
                                 value="<?= number_format($k['bobot'], 3) ?>">
                          <small class="text-muted">Pastikan total seluruh bobot kriteria tetap 1.000 setelah disimpan.</small>
                        </div>
                        <div class="mb-1">
                          <label class="form-label">Keterangan</label>
                          <textarea name="keterangan" class="form-control" rows="2"><?= htmlspecialchars($k['keterangan']) ?></textarea>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-success"><i class="ti ti-device-floppy me-1"></i>Simpan</button>
                      </div>
                    </form>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </tbody>
            <tfoot>
              <tr>
                <th colspan="3" class="text-end">Total Bobot:</th>
                <th><span id="totalBobot"><?= number_format($totalBobot, 3) ?></span></th>
                <th colspan="3"></th>
              </tr>
            </tfoot>
          </table>
          <button type="submit" class="btn btn-success"><i class="ti ti-device-floppy me-1"></i>Simpan Bobot</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>

<script>
function hitungTotal() {
  let total = 0;
  $('.bobot-input').each(function () { total += parseFloat($(this).val()) || 0; });
  $('#totalBobot').text(total.toFixed(3));
  $('#totalBobot').toggleClass('text-danger fw-bold', Math.abs(total - 1) > 0.0009);
}
$(document).on('input', '.bobot-input', hitungTotal);
$(function(){ hitungTotal(); });

$('#formBobot').on('submit', function (e) {
  let total = 0;
  $('.bobot-input').each(function () { total += parseFloat($(this).val()) || 0; });
  if (Math.abs(total - 1) > 0.0009) {
    e.preventDefault();
    Swal.fire({ icon: 'error', title: 'Total bobot harus 1.000', text: 'Total saat ini: ' + total.toFixed(3) });
  }
});

$('.btn-hapus-kriteria').on('click', function () {
  const id = $(this).data('id');
  const kode = $(this).data('kode');
  const jumlahSub = $(this).data('jumlahsub');
  Swal.fire({
    title: `Hapus kriteria ${kode}?`,
    html: `Kriteria ini akan dihapus permanen beserta <strong>${jumlahSub} baris subkriteria</strong> di dalamnya.
           Perhitungan MOORA berikutnya tidak akan lagi memakai kriteria ini — pastikan total bobot kriteria
           lain tetap disesuaikan ke 1.000 setelahnya. Riwayat hasil perhitungan yang sudah ada tidak terpengaruh.`,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Ya, hapus',
    confirmButtonColor: '#dc3545',
    cancelButtonText: 'Batal'
  }).then((res) => {
    if (res.isConfirmed) window.location.href = 'hapus.php?id=' + id;
  });
});

<?php if (isset($_GET['success'])): ?>
Swal.fire({ icon: 'success', title: 'Bobot berhasil disimpan', timer: 1500, showConfirmButton: false });
<?php endif; ?>
<?php if (isset($_GET['edited'])): ?>
Swal.fire({ icon: 'success', title: 'Kriteria berhasil diperbarui', timer: 1500, showConfirmButton: false });
<?php endif; ?>
<?php if (isset($_GET['deleted'])): ?>
Swal.fire({ icon: 'success', title: 'Kriteria berhasil dihapus', timer: 1500, showConfirmButton: false });
<?php endif; ?>
<?php if (!empty($_GET['error'])): ?>
Swal.fire({ icon: 'error', title: 'Gagal', text: <?= json_encode($_GET['error']) ?> });
<?php endif; ?>
</script>