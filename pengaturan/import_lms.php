<?php
require_once __DIR__ . '/../config/session.php';
requireAdmin();

$pdo = getDB();

function tampilData(PDO $pdo, string $tabel, int $limit = 15): array
{
    $kolomUrut = ($tabel === 'lms_bbtb') ? 'tipe_ukur, tinggi_badan' : 'usia_hari';
    return $pdo->query("SELECT * FROM $tabel ORDER BY jenis_kelamin, $kolomUrut LIMIT $limit")->fetchAll();
}

$dataBBU  = tampilData($pdo, 'lms_bbu');
$dataTBU  = tampilData($pdo, 'lms_tbu');
$dataBBTBPanjang = $pdo->query("SELECT * FROM lms_bbtb WHERE tipe_ukur = 'panjang' ORDER BY jenis_kelamin, tinggi_badan LIMIT 15")->fetchAll();
$dataBBTBTinggi  = $pdo->query("SELECT * FROM lms_bbtb WHERE tipe_ukur = 'tinggi' ORDER BY jenis_kelamin, tinggi_badan LIMIT 15")->fetchAll();
$jumlahBBTBPanjang = (int) $pdo->query("SELECT COUNT(*) FROM lms_bbtb WHERE tipe_ukur = 'panjang'")->fetchColumn();
$jumlahBBTBTinggi  = (int) $pdo->query("SELECT COUNT(*) FROM lms_bbtb WHERE tipe_ukur = 'tinggi'")->fetchColumn();
$dataIMTU = tampilData($pdo, 'lms_imtu');

$jumlahBBU  = (int) $pdo->query("SELECT COUNT(*) FROM lms_bbu")->fetchColumn();
$jumlahTBU  = (int) $pdo->query("SELECT COUNT(*) FROM lms_tbu")->fetchColumn();
$jumlahIMTU = (int) $pdo->query("SELECT COUNT(*) FROM lms_imtu")->fetchColumn();

$pageTitle = 'Import Referensi LMS WHO';
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/sidebar.php';
?>
<div class="main-content">
  <div class="container-fluid py-4">
    <h4 class="mb-4"><i class="ti ti-table-import me-2"></i>Import Referensi LMS WHO</h4>

    


    <ul class="nav nav-tabs" id="lmsTab" role="tablist">
      <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#bbu">BB/U</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tbu">TB/U</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#bbtb">BB/TB</button></li>
      <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#imtu">IMT/U</button></li>
    </ul>

    <div class="tab-content border border-top-0 p-3 bg-white">

      <div class="tab-pane fade show active" id="bbu">
        <form method="POST" action="proses_import_lms.php" enctype="multipart/form-data" class="row g-2 align-items-end mb-3">
          <input type="hidden" name="tabel" value="lms_bbu">
          <div class="col-auto">
            <label class="form-label small mb-0">Jenis Kelamin</label>
            <select name="jenis_kelamin" class="form-select" required>
              <option value="L">Laki-laki</option>
              <option value="P">Perempuan</option>
            </select>
          </div>
          <div class="col-auto">
            <label class="form-label small mb-0">File Excel (wfa-*-zscore-expanded-tables.xlsx)</label>
            <input type="file" name="file_excel" accept=".xlsx" class="form-control" required>
          </div>
          <div class="col-auto"><button class="btn btn-success"><i class="ti ti-upload me-1"></i>Import BB/U</button></div>
        </form>
        <p class="small text-muted mb-2"><?= $jumlahBBU ?> baris tersimpan (harusnya 3.714 kalau boys+girls, 0-1856 hari, sudah lengkap).</p>
        <table class="table table-sm table-bordered">
          <thead><tr><th>JK</th><th>Usia (hari)</th><th>L</th><th>M</th><th>S</th></tr></thead>
          <tbody>
            <?php foreach ($dataBBU as $r): ?>
            <tr><td><?= $r['jenis_kelamin'] ?></td><td><?= $r['usia_hari'] ?></td><td><?= $r['L'] ?></td><td><?= $r['M'] ?></td><td><?= $r['S'] ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p class="small text-muted">Menampilkan maksimal 15 baris pertama sebagai pratinjau.</p>
      </div>

      <div class="tab-pane fade" id="tbu">
        <form method="POST" action="proses_import_lms.php" enctype="multipart/form-data" class="row g-2 align-items-end mb-3">
          <input type="hidden" name="tabel" value="lms_tbu">
          <div class="col-auto">
            <label class="form-label small mb-0">Jenis Kelamin</label>
            <select name="jenis_kelamin" class="form-select" required>
              <option value="L">Laki-laki</option>
              <option value="P">Perempuan</option>
            </select>
          </div>
          <div class="col-auto">
            <label class="form-label small mb-0">File Excel (lhfa-*-zscore-expanded-tables.xlsx)</label>
            <input type="file" name="file_excel" accept=".xlsx" class="form-control" required>
          </div>
          <div class="col-auto"><button class="btn btn-success"><i class="ti ti-upload me-1"></i>Import TB/U</button></div>
        </form>
        <p class="small text-muted mb-2"><?= $jumlahTBU ?> baris tersimpan (harusnya 3.714 kalau boys+girls, 0-1856 hari, sudah lengkap).</p>
        <table class="table table-sm table-bordered">
          <thead><tr><th>JK</th><th>Usia (hari)</th><th>L</th><th>M</th><th>S</th></tr></thead>
          <tbody>
            <?php foreach ($dataTBU as $r): ?>
            <tr><td><?= $r['jenis_kelamin'] ?></td><td><?= $r['usia_hari'] ?></td><td><?= $r['L'] ?></td><td><?= $r['M'] ?></td><td><?= $r['S'] ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p class="small text-muted">Menampilkan maksimal 15 baris pertama sebagai pratinjau.</p>
      </div>

      <div class="tab-pane fade" id="bbtb">
        <form method="POST" action="proses_import_lms.php" enctype="multipart/form-data" class="row g-2 align-items-end mb-3">
          <input type="hidden" name="tabel" value="lms_bbtb">
          <div class="col-auto">
            <label class="form-label small mb-0">Tipe Tabel</label>
            <select name="tipe_ukur" class="form-select" required>
              <option value="panjang">Weight-for-Length — 0–2 tahun (berbaring)</option>
              <option value="tinggi">Weight-for-Height — 2–5 tahun (berdiri)</option>
            </select>
          </div>
          <div class="col-auto">
            <label class="form-label small mb-0">Jenis Kelamin</label>
            <select name="jenis_kelamin" class="form-select" required>
              <option value="L">Laki-laki</option>
              <option value="P">Perempuan</option>
            </select>
          </div>
          <div class="col-auto">
            <label class="form-label small mb-0">File Excel</label>
            <input type="file" name="file_excel" accept=".xlsx" class="form-control" required>
          </div>
          <div class="col-auto"><button class="btn btn-success"><i class="ti ti-upload me-1"></i>Import BB/TB</button></div>
        </form>
        <p class="small text-muted mb-3">Header kolom pertama pada file boleh <code>Height</code> atau <code>Length</code> (bukan Month).</p>

        <div class="row g-3">
          <div class="col-md-6">
            <h6 class="small text-uppercase text-muted">Weight-for-Length (0–2th) — <?= $jumlahBBTBPanjang ?> baris tersimpan</h6>
            <table class="table table-sm table-bordered">
              <thead><tr><th>JK</th><th>Panjang (cm)</th><th>L</th><th>M</th><th>S</th></tr></thead>
              <tbody>
                <?php foreach ($dataBBTBPanjang as $r): ?>
                <tr><td><?= $r['jenis_kelamin'] ?></td><td><?= $r['tinggi_badan'] ?></td><td><?= $r['L'] ?></td><td><?= $r['M'] ?></td><td><?= $r['S'] ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$dataBBTBPanjang): ?>
                <tr><td colspan="5" class="text-center text-muted">Belum ada data.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="col-md-6">
            <h6 class="small text-uppercase text-muted">Weight-for-Height (2–5th) — <?= $jumlahBBTBTinggi ?> baris tersimpan</h6>
            <table class="table table-sm table-bordered">
              <thead><tr><th>JK</th><th>Tinggi (cm)</th><th>L</th><th>M</th><th>S</th></tr></thead>
              <tbody>
                <?php foreach ($dataBBTBTinggi as $r): ?>
                <tr><td><?= $r['jenis_kelamin'] ?></td><td><?= $r['tinggi_badan'] ?></td><td><?= $r['L'] ?></td><td><?= $r['M'] ?></td><td><?= $r['S'] ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$dataBBTBTinggi): ?>
                <tr><td colspan="5" class="text-center text-muted">Belum ada data.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <p class="small text-muted">Menampilkan maksimal 15 baris pertama per tabel sebagai pratinjau.</p>
      </div>

      <div class="tab-pane fade" id="imtu">
        <form method="POST" action="proses_import_lms.php" enctype="multipart/form-data" class="row g-2 align-items-end mb-3">
          <input type="hidden" name="tabel" value="lms_imtu">
          <div class="col-auto">
            <label class="form-label small mb-0">Jenis Kelamin</label>
            <select name="jenis_kelamin" class="form-select" required>
              <option value="L">Laki-laki</option>
              <option value="P">Perempuan</option>
            </select>
          </div>
          <div class="col-auto">
            <label class="form-label small mb-0">File Excel (bfa-*-zscore-expanded-tables.xlsx)</label>
            <input type="file" name="file_excel" accept=".xlsx" class="form-control" required>
          </div>
          <div class="col-auto"><button class="btn btn-success"><i class="ti ti-upload me-1"></i>Import IMT/U</button></div>
        </form>
        <p class="small text-muted mb-2">Gunakan file resmi WHO BMI-for-age (bfa) expanded table, header kolom pertama <code>Day</code>. <?= $jumlahIMTU ?> baris tersimpan (harusnya 3.714 kalau boys+girls, 0-1856 hari, sudah lengkap).</p>
        <table class="table table-sm table-bordered">
          <thead><tr><th>JK</th><th>Usia (hari)</th><th>L</th><th>M</th><th>S</th></tr></thead>
          <tbody>
            <?php foreach ($dataIMTU as $r): ?>
            <tr><td><?= $r['jenis_kelamin'] ?></td><td><?= $r['usia_hari'] ?></td><td><?= $r['L'] ?></td><td><?= $r['M'] ?></td><td><?= $r['S'] ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <p class="small text-muted">Menampilkan maksimal 15 baris pertama sebagai pratinjau.</p>
      </div>

    </div>
  </div>
</div>

<script>
<?php if (isset($_GET['success'])): ?>
Swal.fire({ icon: 'success', title: 'Import berhasil', text: '<?= (int)($_GET['rows'] ?? 0) ?> baris diproses', timer: 2200, showConfirmButton: false });
<?php endif; ?>
<?php if (!empty($_GET['error'])): ?>
Swal.fire({ icon: 'error', title: 'Import gagal', text: <?= json_encode($_GET['error']) ?> });
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>