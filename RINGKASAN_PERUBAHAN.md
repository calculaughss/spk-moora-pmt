# Ringkasan Perbaikan — PMT MOORA

Semua perubahan sudah diuji dengan `php -l` (tidak ada syntax error) pada seluruh file
proyek. Karena sandbox ini tidak punya server MySQL, perubahan **belum diuji jalan
end-to-end** — mohon jalankan di lokal/XAMPP Anda dan kabari saya kalau ada error.

## Langkah instalasi migrasi

1. **Backup dulu** database `pmt` Anda yang sekarang (mysqldump).
2. Import `database/migration_2026_08.sql` (dijalankan **setelah** `pmt.sql` yang lama
   sudah ada di database Anda). File ini dibungkus `TRANSACTION`.
3. **PENTING**: Migrasi ini **menghapus seluruh riwayat perhitungan MOORA yang lama**
   (`hasil_moora` & `riwayat_perhitungan`), karena hasil lama dihitung dengan 7 kriteria
   & bobot lama yang sudah tidak sesuai skema baru — supaya tidak ada data campuran lama/baru.
4. **PENTING**: Kriteria C4 (IMT/U) butuh tabel referensi LMS WHO (`lms_imtu`) yang **belum
   ada datanya** — silakan download file resmi WHO *BMI-for-age (bfa), 0–60 bulan* per jenis
   kelamin, lalu import lewat menu **Pengaturan → Import Referensi LMS WHO → tab IMT/U**
   (tab baru, mengikuti pola BB/U dan TB/U yang sudah ada). Sebelum data ini diimport,
   perhitungan MOORA & input penilaian baru akan gagal dengan pesan error yang jelas
   (bukan salah/diam-diam, supaya tidak ada perhitungan yang salah tanpa Anda sadari).
5. Setelah `migration_2026_08.sql` berhasil, jalankan juga **`database/migration_2026_08_v2.sql`**
   — ini me-rename semua primary key generik `id` menjadi deskriptif per tabel (lihat bagian 8
   di bawah). Urutannya WAJIB: `pmt.sql` → `migration_2026_08.sql` → `migration_2026_08_v2.sql`.
   File ini **aman dijalankan berulang kali** kalau sebelumnya sempat gagal di tengah jalan —
   ia mencari nama constraint & mengecek keberadaan kolom secara otomatis (tidak menebak nama),
   jadi bagian yang sudah berhasil sebelumnya otomatis dilewati saat dijalankan ulang.

## 1. Kriteria diubah sesuai lampiran Word

Tabel `kriteria` sekarang berisi 6 baris sesuai Tabel 4.5 pada lampiran Anda:

| Kode | Kriteria | Jenis | Bobot |
|---|---|---|---|
| C1 | BB/U | Benefit | 0.20 |
| C2 | TB/U | Benefit | 0.20 |
| C3 | BB/TB | Benefit | 0.20 |
| C4 | IMT/U | Benefit | 0.20 |
| C5 | Pendapatan Orang Tua | Cost | 0.10 |
| C6 | Jumlah Tanggungan Keluarga | Benefit | 0.10 |

Kriteria lama "Usia (bulan)" dan "Riwayat Penyakit" (C7) **dihapus** karena tidak ada di
lampiran Tabel 4.5. Kolom `penilaian.riwayat_penyakit` ikut dihapus (lihat bagian 5).

**Catatan penting soal isi lampiran**: paragraf penjelasan di dokumen Anda menyebut BB/U,
TB/U, BB/TB, IMT/U sebagai atribut **Cost**, tapi Tabel 4.5 menandainya **Benefit**. Saya
mengikuti **Tabel 4.5** (bukan paragrafnya) karena itu tabel operasional yang dipakai
langsung sebagai isi kolom "Atribut" di sistem — dan karena nilai mentah (Z-score) sudah
dikonversi ke bobot subkriteria yang arahnya "semakin besar bobot = semakin butuh
prioritas" (lihat bagian 2), sehingga secara matematis memang harus diperlakukan sebagai
Benefit. Kalau ternyata yang benar adalah paragrafnya (Cost), tinggal ganti `jenis` kriteria
terkait di halaman Kriteria — tidak perlu ubah kode.

## 2. Fitur Subkriteria (bisa diubah di frontend)

Menu baru: **Kriteria → klik jumlah baris di kolom "Subkriteria"** pada tiap kriteria.
Halaman ini (`kriteria/subkriteria.php`) menyediakan CRUD penuh (tambah/edit/hapus) untuk
rentang nilai & bobot tiap subkriteria (Tabel 4.6–4.11 di lampiran Anda), termasuk:

- Rentang nilai mentah (Z-score untuk C1–C4, Rupiah untuk C5, jumlah tanggungan untuk C6)
  dengan batas bawah/atas yang bisa dikosongkan (artinya tak terbatas) dan operator ≥/> serta ≤/<.
- Bobot yang menjadi nilai **Xij** pada matriks keputusan MOORA.
- Nilai-nilai awal sudah di-seed persis sesuai Tabel 4.6–4.11 lampiran Anda — **kecuali**
  rentang Pendapatan (Tabel 4.10) yang punya celah (gap) antara Rp2.000.000 dan Rp2.100.000
  pada lampiran aslinya (kemungkinan salah ketik di skripsi). Saya implementasikan literal
  sesuai lampiran; sistem punya fallback "nilai terdekat" kalau ada balita dengan pendapatan
  di celah itu (ditandai badge "celah" di halaman detail hasil), tapi sebaiknya dikonsultasikan
  ke pembimbing untuk memastikan batas yang benar.

## 3. Perhitungan MOORA sekarang per bulan (fix ambiguitas)

Sebelumnya: `perhitungan/proses.php` selalu memakai **penilaian TERAKHIR per balita**,
tanpa peduli bulan — jadi kalau bulan ini dihitung tapi seorang balita belum diukur bulan
ini, data pengukuran bulan lalu tetap ikut dipakai seolah-olah data bulan ini. Ini yang
bikin tidak adil terhadap balita yang benar-benar hadir di penimbangan bulan berjalan.

Sekarang: halaman **Perhitungan MOORA** mewajibkan kader **memilih bulan pengukuran**
(dropdown otomatis berisi semua bulan yang punya data penilaian). Hanya balita dengan data
penilaian pada bulan yang dipilih itu yang ikut dihitung — balita tanpa pengukuran di bulan
tsb otomatis tidak diikutkan.

## 4. Hasil MOORA per bulan — kader & admin

- **Kader** (`hasil/index.php`): dropdown untuk memilih bulan/batch mana pun yang pernah
  dijalankan di posyandunya (bukan cuma yang terbaru), lengkap dengan label bulan.
- **Admin** (`hasil/index.php` mode admin — otomatis terdeteksi dari role): sekarang
  menampilkan **seluruh posyandu, seluruh bulan** yang pernah dihitung (bisa difilter per
  posyandu), dikelompokkan per posyandu, dengan tombol "Lihat" untuk membuka detail hasil
  bulan tsb. Sebelumnya hanya menampilkan batch terakhir tiap posyandu.
- `hasil/detail.php` diperbarui: menambah baris IMT/U pada langkah Z-score, dan matriks
  keputusan/normalisasi sekarang menampilkan **bobot subkriteria** (Xij yang benar-benar
  dipakai MOORA) berdampingan dengan nilai mentahnya, supaya transparan untuk laporan skripsi.

## 5. Pembersihan database

Kolom yang dihapus (dicek dulu satu per satu lewat pencarian ke seluruh kode — hanya yang
benar-benar tidak pernah dipakai di aplikasi yang dihapus):

- `balita.updated_at`, `kriteria.updated_at`, `posyandu.updated_at`, `posyandu.created_at`,
  `users.updated_at`, `users.created_at` — tidak pernah di-SELECT/ditampilkan di mana pun.
- `penilaian.riwayat_penyakit` — hanya dipakai untuk C7 yang sekarang dihapus.

**Yang SENGAJA tidak dihapus** karena masih aktif dipakai:
- `balita.riwayat_penyakit` — masih dipakai sebagai data biodata balita (form, import/export,
  daftar balita), terlepas dari sudah tidak jadi kriteria MOORA.
- `balita.created_at` — dipakai untuk urutan "balita terbaru" di halaman Data Balita.

## 6. Fitur Edit & Hapus Kriteria (tambahan setelah rilis awal)

Di halaman **Kriteria** (`kriteria/index.php`) sekarang ada tombol **Edit** dan **Hapus** per baris:

- **Edit** (`kriteria/edit_simpan.php`) — modal untuk ubah `nama_kriteria`, `jenis` (benefit/cost),
  `bobot`, dan `keterangan`. **Kode (C1–C6) sengaja tidak bisa diubah** dari sini, karena kode
  tersebut dipakai sebagai acuan tetap di `config/zscore.php` (pemetaan hasil Z-score/pendapatan/
  tanggungan) dan `perhitungan/proses.php` — mengganti kode lewat form akan membuat kriteria itu
  diam-diam tidak ikut terhitung di MOORA tanpa ada error yang muncul.
- **Hapus** (`kriteria/hapus.php`) — menghapus kriteria beserta seluruh subkriteria di dalamnya
  (cascade otomatis via foreign key). Ada dialog konfirmasi yang menjelaskan dampaknya. Riwayat
  hasil perhitungan yang sudah tersimpan (`hasil_moora`) **tidak terpengaruh** karena datanya
  sudah berupa salinan (JSON), tidak bergantung ke baris kriteria yang dihapus.
- Setelah edit/hapus, jangan lupa cek **Total Bobot** di bawah tabel tetap **1.000** — kalau
  tidak, tombol "Jalankan Perhitungan" di menu Perhitungan MOORA otomatis nonaktif sampai
  diperbaiki (safety net yang sudah ada sejak awal).

File baru: `kriteria/edit_simpan.php`, `kriteria/hapus.php`.


**Baru:**
- `database/migration_2026_08.sql`
- `kriteria/subkriteria.php`, `kriteria/subkriteria_simpan.php`, `kriteria/subkriteria_hapus.php`

**Diubah:**
- `config/zscore.php` — tambah hitung IMT & Z-score IMT/U, tambah `cariSubkriteria()`
- `penilaian/simpan.php`, `penilaian/tambah.php`, `penilaian/index.php`
- `perhitungan/index.php`, `perhitungan/proses.php`
- `kriteria/index.php`
- `hasil/index.php`, `hasil/detail.php`
- `pengaturan/import_lms.php`, `pengaturan/proses_import_lms.php`
- `dashboard/index.php` (tambah chart distribusi IMT/U untuk konsistensi)

## 7. Form Subkriteria — dukungan "Nilai Tunggal" (tambahan)

Sebelumnya form subkriteria selalu meminta batas bawah & batas atas (rentang), padahal kalau
suatu saat Anda menambah kriteria baru yang nilainya bukan rentang (mis. hanya cocok kalau
nilainya persis sama dengan satu angka tertentu), harus diakali dengan mengisi batas bawah =
batas atas secara manual.

Sekarang di menu **Kriteria → Subkriteria** ada pilihan **Tipe Nilai**: "Rentang" (perilaku
lama) atau **"Nilai Tunggal"** — kalau dipilih "Nilai Tunggal", cukup isi satu kolom "Nilai
Persis" tanpa perlu mengatur batas atas/bawah dan operator sama sekali (sistem otomatis
menyimpannya sebagai batas bawah = batas atas = nilai tsb, keduanya inklusif). Tabel daftar
subkriteria juga otomatis menampilkan `x = <angka>` untuk baris bertipe ini, dan menampilkan
"semua nilai (tanpa batas)" untuk baris yang batas bawah & atasnya sama-sama dikosongkan.

## 8. Rename primary key + pembersihan lanjutan (tambahan)

Semua kolom primary key generik `id` diganti menjadi deskriptif per tabel, dan kolom foreign
key yang sebelumnya berakhiran `_id` diganti jadi `id_<tabel>` supaya sama persis dengan nama
primary key yang dirujuknya — jalankan `database/migration_2026_08_v2.sql` **setelah**
`migration_2026_08.sql`.

| Tabel | Kolom PK lama | Kolom PK baru |
|---|---|---|
| posyandu | `id` | `id_posyandu` |
| users | `id`, `posyandu_id` | `id_user`, `id_posyandu` |
| kriteria | `id` | `id_kriteria` |
| subkriteria | `id`, `kriteria_id` | `id_subkriteria`, `id_kriteria` |
| balita | `id`, `posyandu_id` | `id_balita`, `id_posyandu` |
| penilaian | `id`, `balita_id` | `id_penilaian`, `id_balita` |
| riwayat_perhitungan | `id` | `id_riwayat` |
| hasil_moora | `id`, `riwayat_id`, `balita_id`, `penilaian_id` | `id_hasil`, `id_riwayat`, `id_balita`, `id_penilaian` |
| lms_bbu / lms_tbu / lms_bbtb / lms_imtu | `id` | `id_lms_bbu` / `id_lms_tbu` / `id_lms_bbtb` / `id_lms_imtu` |

Seluruh kode PHP (kurang lebih 40 file) sudah disesuaikan: semua query SQL, akses array hasil
`fetch()`, dan foreign key JOIN memakai nama kolom baru. **Yang SENGAJA tidak diubah** karena
memang bukan nama kolom database — parameter URL (`?id=`, `?riwayat_id=`), nama field form
HTML (`name="id"`), named bind parameter PDO (`:posyandu_id`), dan `$_SESSION['posyandu_id']`
— semuanya tetap dengan nama lama karena itu murni penamaan internal aplikasi, bukan skema tabel.

Sekaligus dihapus kolom `penilaian.tanggal_penilaian` yang ternyata tidak pernah dibaca di
halaman manapun (hanya auto-timestamp input, beda dengan `tanggal_pengukuran` yang memang
ditampilkan).

**Update**: versi pertama migration v2 sempat gagal di sebagian instalasi dengan error
`Can't DROP 'fk_xxx'; check that column/key exists` — karena nama foreign key constraint
di database masing-masing instalasi ternyata bisa berbeda dari yang tertulis di `pmt.sql`.
Migration v2 sudah diperbaiki: sekarang **mencari sendiri** nama constraint yang sebenarnya
lewat `information_schema` (tidak menebak nama), dan **aman dijalankan berulang kali** —
kolom yang sudah pernah di-rename otomatis dilewati saat dijalankan ulang. Kalau Anda sempat
menjalankan versi lama migration v2 dan gagal, tidak masalah — cukup jalankan file
`migration_2026_08_v2.sql` yang baru ini, tidak perlu rollback apa pun dulu.

⚠️ **PENTING — verifikasi manual diperlukan**: karena sandbox saya tidak memiliki server
MySQL, seluruh perubahan ini **hanya divalidasi lewat `php -l` (cek sintaks) dan tinjauan
manual per query**, BUKAN uji coba langsung terhadap database sungguhan. Mengingat skala
perubahannya (menyentuh hampir seluruh query di aplikasi), sangat disarankan:
1. Jalankan dulu di database development/staging, bukan langsung produksi.
2. Backup database sebelum migrasi (sama seperti sebelumnya).
3. Coba alur inti satu per satu setelah migrasi: login → tambah balita → tambah penilaian →
   jalankan perhitungan MOORA → lihat hasil → laporan PDF/Excel → kelola kriteria/subkriteria.
4. Kalau ada error `Column not found` di suatu halaman, itu tandanya ada 1 referensi kolom lama
   yang terlewat — beri tahu saya pesan errornya (biasanya menyebutkan nama file & kolom) supaya
   bisa langsung saya perbaiki.

## Yang perlu Anda lakukan setelah import migrasi

1. Import file WHO IMT/U LMS lewat Pengaturan → Import LMS → tab IMT/U.
2. Cek ulang rentang Subkriteria C5 (Pendapatan) di menu Kriteria → Subkriteria karena ada
   celah di lampiran aslinya (lihat bagian 2 di atas).
3. Coba tambah 1 data penilaian baru untuk memastikan IMT/U terhitung dengan benar.
4. Jalankan 1x perhitungan MOORA di menu Perhitungan untuk memastikan alur bulan berjalan
   sesuai harapan.
