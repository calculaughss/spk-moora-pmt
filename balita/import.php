<?php
require_once __DIR__ . '/../config/session.php';
requireKader();

$pageTitle = 'Import Data Balita';
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/sidebar.php';
?>
<div class="main-content">
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="mb-0"><i class="ti ti-file-import me-2"></i>Import Data Balita</h4>
      <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="ti ti-arrow-left me-1"></i>Kembali</a>
    </div>

    <div class="alert alert-info">
      Format file <strong>.xlsx</strong>, baris pertama adalah header dengan kolom (urutan bebas, dicari
      berdasarkan nama header, tidak case-sensitive):
      <div class="mt-2">
        <code>nama_balita&nbsp;&nbsp;jenis_kelamin&nbsp;&nbsp;tanggal_lahir&nbsp;&nbsp;pendapatan_ortu&nbsp;&nbsp;jumlah_tanggungan&nbsp;&nbsp;riwayat_penyakit&nbsp;&nbsp;nama_ortu&nbsp;&nbsp;alamat</code>
      </div>
      <ul class="mb-0 mt-2">
        <li><strong>jenis_kelamin</strong>: isi <code>L</code> atau <code>P</code>.</li>
        <li><strong>tanggal_lahir</strong>: format tanggal Excel (cell bertipe Date) atau teks <code>YYYY-MM-DD</code>.</li>
        <li>Setiap baris yang valid akan <strong>ditambahkan sebagai balita baru</strong> (tidak ada NIK/ID unik
            di file untuk mencocokkan data yang sudah ada, jadi import ini tidak menimpa data lama —
            jika mengimport ulang file yang sama, data akan tergandakan).</li>
        <li>Berat &amp; tinggi badan <strong>tidak diimport di sini</strong> — catat lewat menu Data Penilaian setelah balita masuk sistem (berubah tiap bulan).</li>
      </ul>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <a href="download_template.php" class="btn btn-outline-success btn-sm mb-3">
          <i class="ti ti-download me-1"></i>Download Template Excel
        </a>
        <form method="POST" action="proses_import.php" enctype="multipart/form-data" class="row g-2 align-items-end">
          <div class="col-auto">
            <label class="form-label small mb-0">File Excel</label>
            <input type="file" name="file_excel" accept=".xlsx" class="form-control" required>
          </div>
          <div class="col-auto">
            <button class="btn btn-success"><i class="ti ti-upload me-1"></i>Import Data Balita</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
<?php if (isset($_GET['success'])): ?>
Swal.fire({
  icon: 'success',
  title: 'Import selesai',
  html: '<?= (int)($_GET['inserted'] ?? 0) ?> balita baru ditambahkan<br><?= (int)($_GET['skipped'] ?? 0) ?> baris dilewati (data tidak lengkap/tidak valid)',
});
<?php endif; ?>
<?php if (!empty($_GET['error'])): ?>
Swal.fire({ icon: 'error', title: 'Import gagal', text: <?= json_encode($_GET['error']) ?> });
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
