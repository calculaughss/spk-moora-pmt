-- =================================================================
-- migration_2026_08.sql
-- -----------------------------------------------------------------
-- Jalankan file ini SETELAH database `pmt` (dari pmt.sql) sudah ada.
-- Migrasi ini menerapkan:
--   1. Kriteria baru sesuai Tabel 4.5 skripsi (6 kriteria, C7 riwayat
--      penyakit dihapus dari perhitungan MOORA).
--   2. Tabel `subkriteria` (Tabel 4.6 - 4.11) yang bisa diubah lewat
--      halaman Kriteria di frontend.
--   3. Tabel referensi WHO `lms_imtu` untuk kriteria C4 (IMT/U).
--   4. Kolom `periode` (bulan target, YYYY-MM) pada riwayat_perhitungan
--      supaya perhitungan MOORA hanya memakai data pengukuran bulan
--      yang dipilih (bukan "pengukuran terakhir" lintas bulan).
--   5. Kolom hasil tambahan pada hasil_moora untuk transparansi
--      klasifikasi subkriteria per kriteria.
--   6. Pembersihan kolom yang tidak dipakai aplikasi (updated_at, dst).
-- =================================================================

START TRANSACTION;

-- -----------------------------------------------------------------
-- 1. KRITERIA — ganti isi sesuai Tabel 4.5
-- -----------------------------------------------------------------
ALTER TABLE `kriteria` MODIFY `kode` VARCHAR(5) NOT NULL;

TRUNCATE TABLE `kriteria`;

INSERT INTO `kriteria` (`id`, `kode`, `nama_kriteria`, `jenis`, `bobot`, `keterangan`) VALUES
(1, 'C1', 'Berat Badan Menurut Umur (BB/U)', 'benefit', 0.20, 'Menunjukkan Gizi Kurang, lebih diprioritaskan'),
(2, 'C2', 'Tinggi Badan Menurut Umur (TB/U)', 'benefit', 0.20, 'Menunjukkan Stunting, lebih diprioritaskan'),
(3, 'C3', 'Berat Badan Menurut Tinggi Badan (BB/TB)', 'benefit', 0.20, 'Menunjukkan wasting (BB terlalu rendah dibanding tinggi badan), lebih diprioritaskan'),
(4, 'C4', 'Indeks Massa Tubuh Menurut Umur (IMT/U)', 'benefit', 0.20, 'Balita dengan IMT rendah (kurus) lebih diprioritaskan'),
(5, 'C5', 'Pendapatan Orang Tua', 'cost', 0.10, 'Pendapatan rendah, lebih diprioritaskan'),
(6, 'C6', 'Jumlah Tanggungan Keluarga', 'benefit', 0.10, 'Semakin banyak tanggungan dalam keluarga, lebih diprioritaskan');

ALTER TABLE `kriteria` AUTO_INCREMENT = 7;

-- -----------------------------------------------------------------
-- 2. SUBKRITERIA — Tabel 4.6 s.d 4.11
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `subkriteria` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kriteria_id` int NOT NULL,
  `nama_subkriteria` varchar(50) NOT NULL,
  `batas_bawah` decimal(12,3) DEFAULT NULL COMMENT 'NULL = tidak terbatas ke bawah',
  `batas_bawah_inklusif` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = >=, 0 = >',
  `batas_atas` decimal(12,3) DEFAULT NULL COMMENT 'NULL = tidak terbatas ke atas',
  `batas_atas_inklusif` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 = <=, 0 = <',
  `bobot` decimal(4,2) NOT NULL COMMENT 'nilai yang dipakai sbg Xij pada matriks MOORA',
  `urutan` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_subkriteria_kriteria` (`kriteria_id`),
  CONSTRAINT `fk_subkriteria_kriteria` FOREIGN KEY (`kriteria_id`) REFERENCES `kriteria` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- C1 - BB/U (Tabel 4.6)
INSERT INTO `subkriteria` (`kriteria_id`, `nama_subkriteria`, `batas_bawah`, `batas_bawah_inklusif`, `batas_atas`, `batas_atas_inklusif`, `bobot`, `urutan`) VALUES
(1, 'Gizi Buruk',  NULL, 1, -3, 0, 4, 1),
(1, 'Gizi Kurang', -3,   1, -2, 0, 3, 2),
(1, 'Gizi Baik',   -2,   1,  2, 1, 2, 3),
(1, 'Gizi Lebih',   2,   0, NULL, 1, 1, 4);

-- C2 - TB/U (Tabel 4.7)
INSERT INTO `subkriteria` (`kriteria_id`, `nama_subkriteria`, `batas_bawah`, `batas_bawah_inklusif`, `batas_atas`, `batas_atas_inklusif`, `bobot`, `urutan`) VALUES
(2, 'Sangat Pendek', NULL, 1, -3, 0, 3, 1),
(2, 'Pendek',        -3,   1, -2, 0, 2, 2),
(2, 'Normal',         -2,  1, NULL, 1, 1, 3);

-- C3 - BB/TB (Tabel 4.8)
INSERT INTO `subkriteria` (`kriteria_id`, `nama_subkriteria`, `batas_bawah`, `batas_bawah_inklusif`, `batas_atas`, `batas_atas_inklusif`, `bobot`, `urutan`) VALUES
(3, 'Sangat Kurus', NULL, 1, -3, 0, 4, 1),
(3, 'Kurus',        -3,   1, -2, 0, 3, 2),
(3, 'Normal',       -2,   1,  2, 1, 2, 3),
(3, 'Gemuk',         2,   0, NULL, 1, 1, 4);

-- C4 - IMT/U (Tabel 4.9)
INSERT INTO `subkriteria` (`kriteria_id`, `nama_subkriteria`, `batas_bawah`, `batas_bawah_inklusif`, `batas_atas`, `batas_atas_inklusif`, `bobot`, `urutan`) VALUES
(4, 'Gizi Buruk',            NULL, 1, -3, 0, 6, 1),
(4, 'Gizi Kurang',           -3,   1, -2, 0, 5, 2),
(4, 'Gizi Baik',             -2,   1,  1, 1, 4, 3),
(4, 'Berisiko Gizi Lebih',    1,   0,  2, 1, 3, 4),
(4, 'Gizi Lebih',             2,   0,  3, 1, 2, 5),
(4, 'Obesitas',               3,   0, NULL, 1, 1, 6);

-- C5 - Pendapatan Orang Tua per bulan, Rupiah (Tabel 4.10, sesuai lampiran apa adanya)
INSERT INTO `subkriteria` (`kriteria_id`, `nama_subkriteria`, `batas_bawah`, `batas_bawah_inklusif`, `batas_atas`, `batas_atas_inklusif`, `bobot`, `urutan`) VALUES
(5, 'Sangat Rendah', NULL,     1, 600000,   0, 5, 1),
(5, 'Rendah',        600000,   0, 1200000,  1, 4, 2),
(5, 'Sedang',        1200000,  1, 2000000,  1, 3, 3),
(5, 'Tinggi',        2100000,  1, 2500000,  1, 2, 4),
(5, 'Sangat Tinggi', 2500000,  0, NULL,     1, 1, 5);

-- C6 - Jumlah Tanggungan Keluarga (Tabel 4.11)
INSERT INTO `subkriteria` (`kriteria_id`, `nama_subkriteria`, `batas_bawah`, `batas_bawah_inklusif`, `batas_atas`, `batas_atas_inklusif`, `bobot`, `urutan`) VALUES
(6, 'Sangat Sedikit', NULL, 1, 1, 1, 1, 1),
(6, 'Sedikit',        2,    1, 2, 1, 2, 2),
(6, 'Sedang',         3,    1, 3, 1, 3, 3),
(6, 'Banyak',         4,    1, 4, 1, 4, 4),
(6, 'Sangat Banyak',  5,    1, NULL, 1, 5, 5);

-- -----------------------------------------------------------------
-- 3. LMS_IMTU — referensi WHO untuk Z-score IMT/U (struktur = lms_bbu/lms_tbu)
--    Silakan import data resminya lewat menu Pengaturan > Import LMS WHO
--    (tab baru "IMT/U") sesuai jenis kelamin, karena data ini BELUM
--    ter-seed otomatis (lampiran tidak menyertakan file referensinya).
-- -----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lms_imtu` (
  `id` int NOT NULL AUTO_INCREMENT,
  `jenis_kelamin` enum('L','P') NOT NULL,
  `usia_bulan` int NOT NULL,
  `L` decimal(10,6) NOT NULL,
  `M` decimal(10,6) NOT NULL,
  `S` decimal(10,6) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_imtu` (`jenis_kelamin`, `usia_bulan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- -----------------------------------------------------------------
-- 4. PENILAIAN — tambah kolom IMT/U, hapus riwayat_penyakit (sudah
--    tidak dipakai sejak C7 dihapus dari kriteria MOORA)
-- -----------------------------------------------------------------
ALTER TABLE `penilaian`
  ADD COLUMN `imt` decimal(6,2) NOT NULL DEFAULT 0 COMMENT 'kg/m^2' AFTER `bbtb_zscore`,
  ADD COLUMN `imtu_zscore` decimal(6,3) NOT NULL DEFAULT 0 AFTER `imt`,
  ADD COLUMN `status_imtu` varchar(30) NOT NULL DEFAULT '' AFTER `status_bbtb`;

ALTER TABLE `penilaian` DROP COLUMN `riwayat_penyakit`;

-- -----------------------------------------------------------------
-- 5. RIWAYAT_PERHITUNGAN — tambah kolom `periode` (bulan target
--    perhitungan, format YYYY-MM), supaya perhitungan MOORA hanya
--    memakai balita yang benar-benar diukur pada bulan tersebut.
-- -----------------------------------------------------------------
ALTER TABLE `riwayat_perhitungan`
  ADD COLUMN `periode` char(7) DEFAULT NULL COMMENT 'YYYY-MM, bulan pengukuran yang dihitung' AFTER `jumlah_data`;

-- Isi periode utk data lama (baris riwayat lama) dari keterangannya /
-- fallback ke bulan tanggal_perhitungan supaya kolom tidak NULL.
UPDATE `riwayat_perhitungan` SET `periode` = DATE_FORMAT(`tanggal_perhitungan`, '%Y-%m') WHERE `periode` IS NULL;

ALTER TABLE `riwayat_perhitungan` MODIFY `periode` char(7) NOT NULL;
ALTER TABLE `riwayat_perhitungan` ADD KEY `idx_riwayat_periode` (`periode`);

-- -----------------------------------------------------------------
-- 6. HASIL_MOORA — tambah kolom transparansi subkriteria (nama +
--    bobot per kriteria) yang menjadi Xij pada matriks MOORA.
--    matriks_asli tetap dipakai untuk menyimpan nilai mentah
--    (Z-score / Rupiah / jumlah tanggungan) sebagai dokumentasi.
-- -----------------------------------------------------------------
ALTER TABLE `hasil_moora`
  ADD COLUMN `matriks_subkriteria` json DEFAULT NULL COMMENT '{C1..C6: {nama, bobot}}' AFTER `matriks_asli`;

-- -----------------------------------------------------------------
-- 7. Hapus kolom yang tidak pernah dipakai aplikasi (dicek lewat
--    pencarian di seluruh kode: tidak ada SELECT/tampilan yang
--    memakainya). Kolom yang MASIH dipakai (mis. balita.created_at
--    untuk urutan data terbaru, balita.riwayat_penyakit sbg biodata)
--    TIDAK dihapus.
-- -----------------------------------------------------------------
ALTER TABLE `balita`      DROP COLUMN `updated_at`;
ALTER TABLE `kriteria`    DROP COLUMN `updated_at`;
ALTER TABLE `posyandu`    DROP COLUMN `updated_at`;
ALTER TABLE `posyandu`    DROP COLUMN `created_at`;
ALTER TABLE `users`       DROP COLUMN `updated_at`;
ALTER TABLE `users`       DROP COLUMN `created_at`;

-- -----------------------------------------------------------------
-- 8. Hasil MOORA lama (dihitung dengan 7 kriteria & bobot lama)
--    sudah tidak konsisten dengan skema kriteria baru. Dihapus
--    supaya tidak menampilkan data campuran skema lama/baru.
--    (riwayat_perhitungan ikut terhapus via ON DELETE CASCADE.)
-- -----------------------------------------------------------------
DELETE FROM `hasil_moora`;
DELETE FROM `riwayat_perhitungan`;

COMMIT;
