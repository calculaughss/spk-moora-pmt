<?php
require_once __DIR__ . '/../config/session.php';
requireAdmin();

$pdo = getDB();

$kriteriaId = (int) ($_GET['kriteria_id'] ?? 0);
$stmtK = $pdo->prepare('SELECT * FROM kriteria WHERE id_kriteria = ?');
$stmtK->execute([$kriteriaId]);
$kriteriaAktif = $stmtK->fetch();

if (!$kriteriaAktif) {
    header('Location: index.php');
    exit;
}

$semuaKriteria = $pdo->query('SELECT id_kriteria, kode, nama_kriteria FROM kriteria ORDER BY kode ASC')->fetchAll();

$stmtSub = $pdo->prepare('SELECT * FROM subkriteria WHERE id_kriteria = ? ORDER BY urutan ASC, id_subkriteria ASC');
$stmtSub->execute([$kriteriaId]);
$subList = $stmtSub->fetchAll();

$editData = [
    'id_subkriteria' => '', 'id_kriteria' => $kriteriaId, 'nama_subkriteria' => '',
    'batas_bawah' => '', 'batas_bawah_inklusif' => 1,
    'batas_atas' => '', 'batas_atas_inklusif' => 1,
    'bobot' => '', 'urutan' => count($subList) + 1,
];
if (!empty($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM subkriteria WHERE id_subkriteria = ? AND id_kriteria = ?');
    $stmt->execute([$_GET['edit'], $kriteriaId]);
    $found = $stmt->fetch();
    if ($found) $editData = $found;
}

// Deteksi mode form: "tunggal" kalau batas bawah & atas sama persis dan
// sama-sama inklusif (artinya subkriteria ini memang cuma cocok utk 1 nilai).
$tipeNilaiAwal = 'rentang';
if ($editData['batas_bawah'] !== '' && $editData['batas_bawah'] !== null
    && $editData['batas_atas'] !== '' && $editData['batas_atas'] !== null
    && (float) $editData['batas_bawah'] === (float) $editData['batas_atas']
    && (int) $editData['batas_bawah_inklusif'] === 1 && (int) $editData['batas_atas_inklusif'] === 1) {
    $tipeNilaiAwal = 'tunggal';
}

$pageTitle = 'Subkriteria - ' . $kriteriaAktif['kode'];
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/sidebar.php';
?>
<div class="main-content">
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <h4 class="mb-0"><i class="ti ti-list-details me-2"></i>Subkriteria — <?= htmlspecialchars($kriteriaAktif['kode']) ?> (<?= htmlspecialchars($kriteriaAktif['nama_kriteria']) ?>)</h4>
      <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="ti ti-arrow-left me-1"></i>Kembali ke Kriteria</a>
    </div>

    <ul class="nav nav-pills mb-3">
      <?php foreach ($semuaKriteria as $k): ?>
      <li class="nav-item">
        <a class="nav-link <?= $k['id_kriteria'] == $kriteriaId ? 'active' : '' ?>" href="subkriteria.php?kriteria_id=<?= $k['id_kriteria'] ?>">
          <?= htmlspecialchars($k['kode']) ?>
        </a>
      </li>
      <?php endforeach; ?>
    </ul>

    <div class="row g-3">
      <div class="col-lg-5">
        <div class="card shadow-sm">
          <div class="card-body">
            <h6 class="mb-3"><?= $editData['id_subkriteria'] ? 'Edit Subkriteria' : 'Tambah Subkriteria' ?></h6>
            <form method="POST" action="subkriteria_simpan.php" id="formSubkriteria">
              <input type="hidden" name="id" value="<?= htmlspecialchars($editData['id_subkriteria']) ?>">
              <input type="hidden" name="kriteria_id" value="<?= $kriteriaId ?>">
              <div class="mb-3">
                <label class="form-label">Nama Subkriteria</label>
                <input type="text" name="nama_subkriteria" class="form-control" required
                       value="<?= htmlspecialchars($editData['nama_subkriteria']) ?>" placeholder="mis. Gizi Kurang">
              </div>

              <div class="mb-3">
                <label class="form-label d-block">Tipe Nilai</label>
                <div class="btn-group w-100" role="group">
                  <input type="radio" class="btn-check" name="tipe_nilai" id="tipeRentang" value="rentang" <?= $tipeNilaiAwal === 'rentang' ? 'checked' : '' ?>>
                  <label class="btn btn-outline-success" for="tipeRentang">Rentang</label>
                  <input type="radio" class="btn-check" name="tipe_nilai" id="tipeTunggal" value="tunggal" <?= $tipeNilaiAwal === 'tunggal' ? 'checked' : '' ?>>
                  <label class="btn btn-outline-success" for="tipeTunggal">Nilai Tunggal</label>
                </div>
                <small class="text-muted">"Nilai Tunggal" dipakai kalau nilai mentahnya bukan rentang, mis. cuma cocok saat nilainya persis sama dengan satu angka tertentu.</small>
              </div>

              <div id="blokTunggal" class="mb-3" style="<?= $tipeNilaiAwal === 'tunggal' ? '' : 'display:none' ?>">
                <label class="form-label">Nilai Persis</label>
                <input type="number" step="any" name="nilai_tunggal" class="form-control"
                       value="<?= htmlspecialchars($tipeNilaiAwal === 'tunggal' ? (string) $editData['batas_bawah'] : '') ?>">
                <small class="text-muted">Subkriteria ini hanya akan cocok jika nilai mentah balita persis sama dengan angka ini (tanpa batas atas/bawah terpisah).</small>
              </div>

              <div id="blokRentang" style="<?= $tipeNilaiAwal === 'tunggal' ? 'display:none' : '' ?>">
                <div class="row g-2 mb-3">
                  <div class="col-7">
                    <label class="form-label">Batas Bawah </label>
                    <input type="number" step="any" name="batas_bawah" class="form-control rentang-input"
                           value="<?= htmlspecialchars($tipeNilaiAwal === 'tunggal' ? '' : (string) $editData['batas_bawah']) ?>">
                  </div>
                  <div class="col-5">
                    <label class="form-label">Operator</label>
                    <select name="batas_bawah_inklusif" class="form-select">
                      <option value="1" <?= (int) $editData['batas_bawah_inklusif'] === 1 ? 'selected' : '' ?>>&ge; (termasuk)</option>
                      <option value="0" <?= (int) $editData['batas_bawah_inklusif'] === 0 ? 'selected' : '' ?>>&gt; (tidak termasuk)</option>
                    </select>
                  </div>
                </div>
                <div class="row g-2 mb-3">
                  <div class="col-7">
                    <label class="form-label">Batas Atas </label>
                    <input type="number" step="any" name="batas_atas" class="form-control rentang-input"
                           value="<?= htmlspecialchars($tipeNilaiAwal === 'tunggal' ? '' : (string) $editData['batas_atas']) ?>">
                  </div>
                  <div class="col-5">
                    <label class="form-label">Operator</label>
                    <select name="batas_atas_inklusif" class="form-select">
                      <option value="1" <?= (int) $editData['batas_atas_inklusif'] === 1 ? 'selected' : '' ?>>&le; (termasuk)</option>
                      <option value="0" <?= (int) $editData['batas_atas_inklusif'] === 0 ? 'selected' : '' ?>>&lt; (tidak termasuk)</option>
                    </select>
                  </div>
                </div>
              </div>

              <div class="row g-2 mb-3">
                <div class="col-6">
                  <label class="form-label">Bobot (nilai Xij)</label>
                  <input type="number" step="0.01" min="0" name="bobot" class="form-control" required
                         value="<?= htmlspecialchars((string) $editData['bobot']) ?>">
                </div>
                <div class="col-6">
                  <label class="form-label">Urutan Tampil</label>
                  <input type="number" step="1" min="0" name="urutan" class="form-control"
                         value="<?= htmlspecialchars((string) $editData['urutan']) ?>">
                </div>
              </div>
              <button type="submit" class="btn btn-success"><i class="ti ti-device-floppy me-1"></i>Simpan</button>
              <?php if ($editData['id_subkriteria']): ?>
                <a href="subkriteria.php?kriteria_id=<?= $kriteriaId ?>" class="btn btn-outline-secondary">Batal Edit</a>
              <?php endif; ?>
            </form>
          </div>
        </div>
      </div>

      <div class="col-lg-7">
        <div class="card shadow-sm">
          <div class="card-body">
            <table class="table table-bordered table-sm align-middle">
              <thead>
                <tr>
                  <th>Urutan</th>
                  <th>Nama Subkriteria</th>
                  <th>Rentang Nilai</th>
                  <th>Bobot</th>
                  <th style="width:110px">Aksi</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($subList as $s):
                    $isTunggal = $s['batas_bawah'] !== null && $s['batas_atas'] !== null
                        && (float) $s['batas_bawah'] === (float) $s['batas_atas']
                        && (int) $s['batas_bawah_inklusif'] === 1 && (int) $s['batas_atas_inklusif'] === 1;
                    $bawah = $s['batas_bawah'] === null ? '−∞' : rtrim(rtrim((string)(float)$s['batas_bawah'], '0'), '.');
                    $atas  = $s['batas_atas']  === null ? '+∞' : rtrim(rtrim((string)(float)$s['batas_atas'], '0'), '.');
                    $opBawah = (int) $s['batas_bawah_inklusif'] === 1 ? '≤' : '<';
                    $opAtas  = (int) $s['batas_atas_inklusif']  === 1 ? '≤' : '<';
                    $tanpaBatas = $s['batas_bawah'] === null && $s['batas_atas'] === null;
                ?>
                <tr>
                  <td><?= $s['urutan'] ?></td>
                  <td><?= htmlspecialchars($s['nama_subkriteria']) ?></td>
                  <td class="small">
                    <?php if ($isTunggal): ?>
                      x = <?= $bawah ?>
                    <?php elseif ($tanpaBatas): ?>
                      <span class="text-muted">semua nilai (tanpa batas)</span>
                    <?php else: ?>
                      <?= $bawah ?> <?= $opBawah ?> x <?= $opAtas ?> <?= $atas ?>
                    <?php endif; ?>
                  </td>
                  <td><span class="badge bg-success"><?= number_format((float) $s['bobot'], 2) ?></span></td>
                  <td class="text-nowrap">
                    <a href="subkriteria.php?kriteria_id=<?= $kriteriaId ?>&edit=<?= $s['id_subkriteria'] ?>" class="btn btn-sm btn-outline-primary">
                      <i class="ti ti-edit"></i>
                    </a>
                    <button class="btn btn-sm btn-outline-danger btn-hapus" data-id="<?= $s['id_subkriteria'] ?>">
                      <i class="ti ti-trash"></i>
                    </button>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if (!$subList): ?>
                <tr><td colspan="5" class="text-center text-muted">Belum ada subkriteria untuk kriteria ini.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function toggleTipeNilai() {
  const tunggal = $('input[name=tipe_nilai]:checked').val() === 'tunggal';
  $('#blokTunggal').toggle(tunggal);
  $('#blokRentang').toggle(!tunggal);
}
$('input[name=tipe_nilai]').on('change', toggleTipeNilai);
$(function () { toggleTipeNilai(); });

$('.btn-hapus').on('click', function () {
  const id = $(this).data('id');
  Swal.fire({
    title: 'Hapus subkriteria ini?', icon: 'warning',
    showCancelButton: true, confirmButtonText: 'Ya, hapus', confirmButtonColor: '#dc3545'
  }).then((res) => {
    if (res.isConfirmed) window.location.href = 'subkriteria_hapus.php?id=' + id + '&kriteria_id=<?= $kriteriaId ?>';
  });
});

<?php if (isset($_GET['success'])): ?>
Swal.fire({ icon: 'success', title: 'Subkriteria berhasil disimpan', timer: 1500, showConfirmButton: false });
<?php endif; ?>
<?php if (!empty($_GET['error'])): ?>
Swal.fire({ icon: 'error', title: 'Gagal', text: <?= json_encode($_GET['error']) ?> });
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
