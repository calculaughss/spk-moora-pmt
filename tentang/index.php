<?php
require_once __DIR__ . '/../config/session.php';
requireLogin();

$pageTitle = 'Tentang Metode';
require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/sidebar.php';
?>
<div class="main-content">
  <div class="container-fluid py-4">
    <h4 class="mb-4"><i class="ti ti-info-circle me-2"></i>Tentang Metode</h4>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <h6>1. LMS WHO (Z-score)</h6>
        <p>
          Metode LMS (Lambda-Mu-Sigma) adalah pendekatan yang digunakan WHO untuk menstandarkan
          distribusi ukuran antropometri (berat badan, tinggi badan) anak yang tidak simetris,
          menjadi nilai Z-score yang dapat dibandingkan lintas usia dan jenis kelamin.
          Setiap kombinasi jenis kelamin dan usia (atau tinggi badan untuk indeks BB/TB) memiliki
          tiga parameter referensi: L (skewness/kemiringan), M (median), dan S (koefisien variasi).
        </p>
        <h6 class="mt-3">Rumus Z-score</h6>
        <div class="p-3 bg-light rounded">
          <p class="mb-1">Jika L ≠ 0:</p>
          <p class="mb-3"><code>Z = ((X / M)^L − 1) / (L × S)</code></p>
          <p class="mb-1">Jika L = 0:</p>
          <p class="mb-0"><code>Z = ln(X / M) / S</code></p>
        </div>
        <p class="mt-3 mb-0">Keterangan: X = nilai pengukuran (berat/tinggi badan balita).</p>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-body">
        <h6>2. MOORA (Multi-Objective Optimization on the Basis of Ratio Analysis)</h6>
        <p>
          MOORA adalah metode pengambilan keputusan multi-kriteria yang mengoptimalkan beberapa
          tujuan (kriteria) yang saling bertentangan (benefit vs cost) secara bersamaan melalui
          rasio perbandingan sederhana, sehingga menghasilkan satu nilai indeks (Yi) per alternatif
          yang dapat diranking.
        </p>

        <h6 class="mt-3">Rumus Normalisasi</h6>
        <div class="p-3 bg-light rounded mb-3">
          <code>xij* = Xij / √(Σ Xij²)</code>
          <p class="small text-muted mb-0 mt-1">Setiap nilai kriteria dibagi akar jumlah kuadrat seluruh nilai pada kolom kriteria yang sama.</p>
        </div>

        <h6>Rumus Nilai Optimasi (Yi)</h6>
        <div class="p-3 bg-light rounded">
          <code>Yi = Σ (xij* × bobot)<sub>benefit</sub> − Σ (xij* × bobot)<sub>cost</sub></code>
          <p class="small text-muted mb-0 mt-1">Semakin besar nilai Yi, semakin diprioritaskan alternatif tersebut sebagai penerima PMT.</p>
        </div>
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-body">
        <h6>3. Alur Penelitian</h6>
        <ol>
          <li>Input data balita (identitas, antropometri, sosial-ekonomi).</li>
          <li>Perhitungan Z-score (BB/U, TB/U, BB/TB) menggunakan tabel referensi LMS WHO.</li>
          <li>Klasifikasi status gizi otomatis berdasarkan ambang batas Z-score WHO.</li>
          <li>Penyusunan matriks keputusan dari 7 kriteria (C1–C7).</li>
          <li>Normalisasi matriks keputusan (akar jumlah kuadrat per kolom).</li>
          <li>Pembobotan nilai normalisasi sesuai bobot kriteria yang ditentukan.</li>
          <li>Perhitungan nilai Yi (selisih total benefit dan total cost).</li>
          <li>Perankingan alternatif berdasarkan Yi tertinggi ke terendah.</li>
          <li>Penetapan status kelayakan (Layak/Tidak Layak) berdasarkan kuota penerima PMT.</li>
          <li>Penyusunan laporan hasil seleksi (cetak, PDF, Excel).</li>
        </ol>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../templates/footer.php'; ?>
