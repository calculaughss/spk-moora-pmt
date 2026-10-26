<?php
require_once __DIR__ . '/../config/session.php';
requireKader();

$pdo = getDB();
$editMode = false;
$data = [
    'id_balita' => '', 'nama_balita' => '', 'jenis_kelamin' => 'L',
    'tanggal_lahir' => '',
    'pendapatan_ortu' => '', 'jumlah_tanggungan' => 1, 'riwayat_penyakit' => 'Tidak',
    'nama_ortu' => '', 'alamat' => '',
];

if (!empty($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT * FROM balita WHERE id_balita = ? AND id_posyandu = ?');
    $stmt->execute([$_GET['id'], currentPosyanduId()]);
    $found = $stmt->fetch();
    if ($found) {
        $data = $found;
        $editMode = true;
    }
}

$pageTitle = $editMode ? 'Edit Balita' : 'Tambah Balita';
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/sidebar.php';
?>
<div class="main-content">
  <div class="container-fluid py-4">
    <h4 class="mb-4"><i class="ti ti-baby-carriage me-2"></i><?= $pageTitle ?></h4>
    <div class="card shadow-sm">
      <div class="card-body">
        <form method="POST" action="simpan.php" id="formBalita">
          <input type="hidden" name="id" value="<?= htmlspecialchars($data['id_balita']) ?>">
          <div class="row g-3">

            <div class="col-md-4">
              <label class="form-label">Nama Balita</label>
              <input type="text" name="nama_balita" class="form-control" required
                     value="<?= htmlspecialchars($data['nama_balita']) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Jenis Kelamin</label>
              <select name="jenis_kelamin" class="form-select select2" required>
                <option value="L" <?= $data['jenis_kelamin'] === 'L' ? 'selected' : '' ?>>Laki-laki</option>
                <option value="P" <?= $data['jenis_kelamin'] === 'P' ? 'selected' : '' ?>>Perempuan</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Tanggal Lahir</label>
              <input type="text" name="tanggal_lahir" class="form-control" required
                     placeholder="dd-mm-yyyy atau yyyy-mm-dd"
                     pattern="\d{1,2}[-\/]\d{1,2}[-\/]\d{4}|\d{4}[-\/]\d{1,2}[-\/]\d{1,2}"
                     value="<?= htmlspecialchars($data['tanggal_lahir']) ?>">
            </div>

            <div class="col-md-4">
              <label class="form-label">Posyandu</label>
              <input type="text" class="form-control" disabled value="<?= htmlspecialchars($_SESSION['nama_posyandu'] ?? '-') ?>">
              <small class="text-muted">Otomatis mengikuti posyandu yang Anda kelola.</small>
            </div>
            <div class="col-md-4">
              <label class="form-label">Pendapatan Orang Tua (Rp/bulan)</label>
              <input type="number" min="0" name="pendapatan_ortu" class="form-control" required
                     value="<?= htmlspecialchars($data['pendapatan_ortu']) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Jumlah Tanggungan</label>
              <input type="number" min="1" name="jumlah_tanggungan" class="form-control" required
                     value="<?= htmlspecialchars($data['jumlah_tanggungan']) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Nama Orang Tua</label>
              <input type="text" name="nama_ortu" class="form-control" required
                     value="<?= htmlspecialchars($data['nama_ortu']) ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label">Alamat</label>
              <input type="text" name="alamat" class="form-control" required
                     value="<?= htmlspecialchars($data['alamat']) ?>">
            </div>

            <?php if ($editMode): ?>
            <div class="col-12">
              <div class="alert alert-info py-2 px-3 mb-0">
                <i class="ti ti-info-circle me-1"></i>
                Berat &amp; tinggi badan dicatat setiap bulan di menu
                <a href="../penilaian/tambah.php?balita_id=<?= $data['id_balita'] ?>">Data Penilaian</a>, bukan di sini,
                karena nilainya berubah tiap pengukuran.
              </div>
            </div>
            <?php endif; ?>

          </div>

          <div class="mt-4">
            <button type="submit" class="btn btn-success"><i class="ti ti-device-floppy me-1"></i>Simpan</button>
            <a href="index.php" class="btn btn-outline-secondary">Batal</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<script>
$(function(){ $('.select2').select2({ width: '100%' }); });
<?php if (!empty($_GET['error'])): ?>
Swal.fire({ icon: 'error', title: 'Gagal Menyimpan', text: <?= json_encode($_GET['error']) ?> });
<?php endif; ?>
</script>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>