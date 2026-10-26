# SPK Penerima PMT — Metode MOORA + Z-score WHO (LMS)

**Status: LENGKAP.** Seluruh 16 tahap sesuai prompt awal sudah diimplementasikan.

## Role Pengguna & Posyandu

Aplikasi punya dua role:

- **Admin** — kelola Kriteria & Bobot, referensi LMS WHO, Data Posyandu, dan Data Pengguna.
  Admin melihat menu **Hasil Seleksi** untuk SELURUH posyandu, dikelompokkan per nama posyandu
  (batch perhitungan MOORA terbaru dari tiap posyandu).
- **Kader** — terhubung ke satu Posyandu tertentu. Kelola Data Balita, Data Penilaian, dan
  jalankan Perhitungan MOORA — semuanya dibatasi hanya untuk balita di posyandu yang ia kelola.
  Menu **Hasil Seleksi** kader hanya menampilkan hasil perhitungan posyandunya sendiri.

Kalau meng-upgrade dari database lama (role `admin`/`operator`, tanpa tabel `posyandu`), jalankan
`database/migrasi_role_posyandu.sql` setelah tabel-tabel lama ada. Untuk instalasi baru cukup
import `database/spk_pmt_moora.sql` (sudah termasuk tabel `posyandu` & akun contoh `kader1`).

Field **Tanggal Lahir** di form Data Balita sekarang menerima format `dd-mm-yyyy` atau
`yyyy-mm-dd`, dengan pemisah `-` maupun `/`.

## Cara Menjalankan (XAMPP / Laragon)

1. Salin folder `spk-pmt-moora` ke `htdocs` (XAMPP) atau `www` (Laragon).
2. **Install dependency PHP** (PhpSpreadsheet & Dompdf) via Composer — jalankan di dalam folder project:
   ```
   composer install
   ```
   Ini akan membuat folder `vendor/` yang dibutuhkan oleh fitur Export PDF dan Export Excel di menu Laporan
   (termasuk fitur Import Excel LMS WHO di menu Pengaturan).
   Jika belum punya Composer: https://getcomposer.org/download/
3. Buka phpMyAdmin, impor `database/spk_pmt_moora.sql`. File ini otomatis membuat database
   `spk_pmt_moora`, seluruh 9 tabel, kriteria default (C1–C7), dan satu akun admin.
3b. **Impor juga `database/seed_lms_who.sql`** (tab SQL phpMyAdmin, setelah tabel dibuat).
   File ini berisi data resmi **WHO Child Growth Standards 2006** siap pakai:
   - BB/U & TB/U: usia 0–60 bulan, laki-laki & perempuan (122 baris masing-masing)
   - BB/TB: tinggi 45.0–120.0 cm dengan step 0.1 cm, laki-laki & perempuan (1.502 baris)
   Dengan file ini **Anda tidak perlu mencari/mengimpor Excel LMS secara manual** — aplikasi
   langsung bisa menghitung Z-score begitu database dibuat. Menu Import Excel di Pengaturan
   tetap tersedia jika suatu saat Anda perlu memperbarui atau mengganti tabel referensi.
4. **Set password admin** (hash di file SQL hanya placeholder, harus digenerate ulang secara lokal):
   - Buka `http://localhost/spk-pmt-moora/auth/generate_hash.php` di browser.
   - Jalankan query `UPDATE users SET password = ...` yang tampil, di tab SQL phpMyAdmin.
   - **Hapus file `generate_hash.php`** setelah selesai.
   - Login dengan username `admin`, password sesuai yang di-set di file tersebut (default `admin123`).
5. Sesuaikan `config/database.php` (user/password MySQL, dan `BASE_URL` jika nama folder project berbeda).
6. **Import data referensi LMS WHO** sebelum menghitung Z-score balita manapun:
   Menu Pengaturan → Import Referensi LMS WHO → upload file Excel (BB/U, TB/U, BB/TB) per jenis kelamin.
   Format kolom dijelaskan di halaman tersebut (A: jenis_kelamin, B: usia_bulan/tinggi_badan, C-E: L, M, S).
   Data LMS resmi bisa diunduh dari situs WHO Child Growth Standards / tabel Kemenkes.
7. Akses `http://localhost/spk-pmt-moora/`.

## Alur Pemakaian End-to-End

1. **Data Balita** → tambah balita (identitas & data sosial-ekonomi saja — **berat/tinggi TIDAK
   diinput di sini** karena berubah tiap bulan).
2. **Pengaturan → Import LMS WHO** → pastikan referensi BB/U, TB/U, BB/TB sudah ter-upload
   (sudah ter-seed otomatis dari WHO Child Growth Standards, lihat bagian di atas).
3. **Data Penilaian → Tambah Pengukuran** → pilih balita, isi tanggal pengukuran + berat + tinggi
   bulan itu. Sistem otomatis mengambil L,M,S dari tabel referensi berdasarkan usia balita **pada
   tanggal pengukuran tersebut** (bukan usia hari ini), menghitung 3 Z-score, mengklasifikasikan
   status gizi, lalu menyimpannya sebagai satu baris riwayat. **Satu balita bisa diinput berkali-kali
   untuk bulan yang berbeda** — setiap balita punya riwayat pertumbuhan sendiri yang bisa dilihat
   di menu Data Balita → ikon grafik (Riwayat Pertumbuhan), lengkap dengan tren berat/tinggi/Z-score
   antar bulan.
4. **Kriteria & Bobot** → pastikan total bobot C1–C7 = 1.000 (bisa diubah sesuai kebutuhan penelitian).
5. **Perhitungan MOORA** → tentukan kuota penerima PMT → jalankan. Sistem otomatis mengambil
   pengukuran **terbaru** tiap balita (bulan paling akhir yang sudah diinput), membangun matriks
   keputusan, menormalisasi, membobot, menghitung Yi, meranking, dan menyimpan sebagai satu batch
   (`riwayat_perhitungan`).
6. **Hasil Seleksi** → tabel ranking (DataTables: cari/filter/pagination) per batch. Klik
   **Detail Perhitungan** untuk melihat seluruh langkah: data balita & tanggal pengukuran yang
   dipakai, L/M/S, langkah Z-score, matriks keputusan seluruh batch, normalisasi & pembobotan per
   kriteria, hingga Yi & ranking akhir — siap dipakai sebagai lampiran skripsi.
7. **Laporan** → pilih batch, lalu Print / Export PDF (Dompdf) / Export Excel (PhpSpreadsheet).
8. **Tentang Metode** → penjelasan rumus LMS WHO, rumus MOORA, dan alur penelitian (dokumentasi metodologi).

## Struktur Proyek

```
config/       koneksi PDO, session/auth guard, library Z-score & MOORA
auth/         login, logout, ganti password, generator hash
templates/    header, sidebar, footer (dipakai semua halaman)
dashboard/    statistik + grafik Chart.js
balita/       CRUD data balita + export PDF/Excel
kriteria/     kelola bobot 7 kriteria MOORA
penilaian/    hitung & simpan Z-score per balita
perhitungan/  jalankan proses MOORA (matriks -> normalisasi -> Yi -> ranking)
hasil/        tabel ranking + halaman Detail Perhitungan lengkap
laporan/      cetak, export PDF, export Excel
pengaturan/   import referensi LMS WHO dari Excel
tentang/      dokumentasi metode untuk skripsi
database/     spk_pmt_moora.sql (skema lengkap 9 tabel + data default)
```

## Catatan Metodologi

- **Lulus Posyandu otomatis:** balita dianggap "Lulus" begitu usianya lebih dari 5 tahun (60 bulan),
  dihitung otomatis dari `tanggal_lahir` (tidak ada kolom status manual, jadi selalu akurat tanpa
  perlu diupdate). Balita yang sudah lulus: ditandai badge kuning di Data Balita, tombol "Tambah
  Pengukuran" dinonaktifkan, tidak bisa lagi diinput pengukuran baru (dicek berdasarkan usia pada
  tanggal pengukuran, bukan cuma usia hari ini), dan **otomatis dikecualikan** dari batch
  Perhitungan MOORA berikutnya (jumlah yang dikecualikan dicatat di kolom keterangan batch).
- **Import Data Balita:** menu Data Balita → Import Data, upload file `.xlsx` sesuai template
  (tombol "Download Template Excel" tersedia di halaman itu). Kolom dicari berdasarkan nama header
  (bebas urutan). Karena data tidak memakai NIK/ID unik, setiap baris valid **selalu ditambahkan
  sebagai balita baru** (bukan upsert) — mengimport ulang file yang sama akan menggandakan data,
  jadi pastikan file yang diupload benar sebelum menekan Import. Baris dengan data tidak
  lengkap/tidak valid otomatis dilewati dan dihitung di ringkasan hasil import (tidak
  menghentikan keseluruhan proses).
- **Model data pertumbuhan bulanan:** berat & tinggi badan disimpan di tabel `penilaian`
  (bukan di `balita`), karena berubah tiap bulan. Satu balita = banyak baris `penilaian`
  (satu per tanggal pengukuran). Ini memungkinkan pelacakan tren pertumbuhan (grafik di
  menu Data Balita → ikon grafik) dan memastikan MOORA selalu memakai data **terbaru**.
- **Jika database Anda sudah pernah dibuat dari versi lama** (sebelum perubahan ini, di mana
  berat/tinggi masih ada di tabel `balita`), jalankan `database/migrasi_riwayat_bulanan.sql`
  untuk memindahkan data lama ke struktur baru. Untuk instalasi baru, cukup import
  `spk_pmt_moora.sql` (sudah versi terbaru) — tidak perlu file migrasi ini.
- **Status Kelayakan (Layak/Tidak Layak):** ditentukan oleh kuota penerima PMT yang diinput admin
  saat menjalankan perhitungan (balita dengan ranking Yi tertinggi sejumlah kuota = "Layak").
  Anda bisa mengubah logika ini di `perhitungan/proses.php` jika metodologi skripsi Anda memakai
  ambang batas lain (mis. Yi > 0, atau median Yi).
- **Riwayat Perhitungan** menyimpan snapshot bobot kriteria yang dipakai saat itu (`bobot_json`),
  sehingga hasil batch lama tetap bisa ditelusuri walau bobot diubah di kemudian hari.
- Setiap balita menggunakan **pengukuran (Z-score) terbarunya** saat sebuah batch MOORA dijalankan.
- Validasi dasar (field wajib, prepared statement, exception handling, unique constraint per bulan)
  sudah diterapkan di semua form.

## Yang Perlu Anda Siapkan Sendiri

- Menjalankan `composer install` untuk vendor library PhpSpreadsheet & Dompdf (lisensi pihak ketiga,
  tidak boleh di-bundle langsung dalam zip ini).
- Data referensi LMS WHO **sudah disertakan** (`database/seed_lms_who.sql`), jadi tidak perlu dicari manual.
  Jika institusi Anda memakai tabel Kemenkes yang sedikit berbeda dari standar WHO 2006 asli, Anda tetap
  bisa mengganti/menambah data melalui menu Pengaturan → Import Referensi LMS WHO.

## Sumber Data LMS WHO

Nilai L, M, S pada `seed_lms_who.sql` diambil dari WHO Child Growth Standards (2006):
weight-for-age, length/height-for-age, dan weight-for-height/length. Usia dalam tabel resmi WHO
disusun per hari; nilai bulanan pada file ini diambil pada hari ke (bulan × 30.4375), dibulatkan
ke hari terdekat, sesuai konvensi tabel bulanan WHO.
