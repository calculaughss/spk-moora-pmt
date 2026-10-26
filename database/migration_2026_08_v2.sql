-- =================================================================
-- migration_2026_08_v2.sql  (v2 — robust terhadap nama constraint)
-- -----------------------------------------------------------------
-- Jalankan SETELAH pmt.sql DAN migration_2026_08.sql.
--
-- Versi ini TIDAK menebak nama foreign key constraint (mis.
-- `fk_balita_posyandu`) — nama itu bisa berbeda-beda tergantung
-- riwayat instalasi database Anda. Sebagai gantinya, skrip ini
-- mencari sendiri nama constraint yang sebenarnya lewat
-- information_schema, lalu menghapusnya. Skrip ini juga AMAN
-- dijalankan berulang kali (idempotent): kolom yang sudah pernah
-- di-rename tidak akan diproses ulang.
--
-- Isi migrasi (sama seperti v1, hanya cara eksekusinya yang lebih aman):
--   1. Ganti nama seluruh kolom primary key generik `id` menjadi
--      deskriptif per tabel (mis. `kriteria.id` -> `kriteria.id_kriteria`).
--   2. Ganti nama kolom foreign key `*_id` menjadi `id_<tabel>` supaya
--      sama persis dengan nama primary key yang dirujuknya.
--   3. Hapus kolom `penilaian.tanggal_penilaian` yang tidak terpakai.
--
-- CATATAN: MySQL melakukan implicit commit di setiap statement DDL
-- (ALTER/CREATE/DROP), jadi skrip ini TIDAK dibungkus TRANSACTION —
-- itu tidak akan memberi proteksi apa pun untuk DDL. Kalau skrip ini
-- berhenti di tengah jalan karena error, jalankan ulang saja dari
-- awal; bagian yang sudah berhasil akan otomatis dilewati (idempotent).
-- =================================================================

DELIMITER $$

-- Hapus FK constraint pada suatu kolom, apa pun nama constraint-nya.
-- Tidak melakukan apa-apa jika kolom tidak punya FK (aman dipanggil berulang).
DROP PROCEDURE IF EXISTS _drop_fk_by_column$$
CREATE PROCEDURE _drop_fk_by_column(IN tbl VARCHAR(64), IN col VARCHAR(64))
BEGIN
    DECLARE fk_name VARCHAR(128);
    SELECT CONSTRAINT_NAME INTO fk_name
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = tbl
      AND COLUMN_NAME = col
      AND REFERENCED_TABLE_NAME IS NOT NULL
    LIMIT 1;
    IF fk_name IS NOT NULL THEN
        SET @sql = CONCAT('ALTER TABLE `', tbl, '` DROP FOREIGN KEY `', fk_name, '`');
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END$$

-- Ganti nama kolom hanya jika kolom lama masih ada (idempotent).
DROP PROCEDURE IF EXISTS _safe_change_column$$
CREATE PROCEDURE _safe_change_column(IN tbl VARCHAR(64), IN old_col VARCHAR(64), IN change_clause VARCHAR(500))
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = old_col
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', tbl, '` CHANGE `', old_col, '` ', change_clause);
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END$$

-- Hapus kolom hanya jika masih ada (idempotent).
DROP PROCEDURE IF EXISTS _safe_drop_column$$
CREATE PROCEDURE _safe_drop_column(IN tbl VARCHAR(64), IN col VARCHAR(64))
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', tbl, '` DROP COLUMN `', col, '`');
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END$$

-- Tambah index hanya jika belum ada (idempotent).
DROP PROCEDURE IF EXISTS _safe_add_key$$
CREATE PROCEDURE _safe_add_key(IN tbl VARCHAR(64), IN key_name VARCHAR(128), IN cols VARCHAR(200))
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND INDEX_NAME = key_name
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', tbl, '` ADD KEY `', key_name, '` (', cols, ')');
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END$$

-- Tambah FK constraint hanya jika belum ada & kolomnya sudah bernama baru (idempotent).
DROP PROCEDURE IF EXISTS _safe_add_fk$$
CREATE PROCEDURE _safe_add_fk(IN tbl VARCHAR(64), IN col VARCHAR(64), IN fk_name VARCHAR(128), IN ref_clause VARCHAR(300))
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND CONSTRAINT_NAME = fk_name
    ) AND EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = tbl AND COLUMN_NAME = col
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', tbl, '` ADD CONSTRAINT `', fk_name, '` FOREIGN KEY (`', col, '`) ', ref_clause);
        PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

-- -----------------------------------------------------------------
-- Lepas semua FK yang menyentuh kolom yang akan diganti nama.
-- -----------------------------------------------------------------
CALL _drop_fk_by_column('balita', 'posyandu_id');
CALL _drop_fk_by_column('hasil_moora', 'balita_id');
CALL _drop_fk_by_column('hasil_moora', 'penilaian_id');
CALL _drop_fk_by_column('hasil_moora', 'riwayat_id');
CALL _drop_fk_by_column('penilaian', 'balita_id');
CALL _drop_fk_by_column('riwayat_perhitungan', 'dibuat_oleh');
CALL _drop_fk_by_column('users', 'posyandu_id');
CALL _drop_fk_by_column('subkriteria', 'kriteria_id');

-- -----------------------------------------------------------------
-- Rename kolom PK (`id`) dan FK (`*_id`) per tabel.
-- -----------------------------------------------------------------
CALL _safe_change_column('posyandu', 'id', '`id_posyandu` int NOT NULL AUTO_INCREMENT');

CALL _safe_change_column('users', 'id', '`id_user` int NOT NULL AUTO_INCREMENT');
CALL _safe_change_column('users', 'posyandu_id', '`id_posyandu` int DEFAULT NULL');

CALL _safe_change_column('kriteria', 'id', '`id_kriteria` int NOT NULL AUTO_INCREMENT');

CALL _safe_change_column('subkriteria', 'id', '`id_subkriteria` int NOT NULL AUTO_INCREMENT');
CALL _safe_change_column('subkriteria', 'kriteria_id', '`id_kriteria` int NOT NULL');

CALL _safe_change_column('balita', 'id', '`id_balita` int NOT NULL AUTO_INCREMENT');
CALL _safe_change_column('balita', 'posyandu_id', '`id_posyandu` int NOT NULL');

CALL _safe_change_column('penilaian', 'id', '`id_penilaian` int NOT NULL AUTO_INCREMENT');
CALL _safe_change_column('penilaian', 'balita_id', '`id_balita` int NOT NULL');
CALL _safe_drop_column('penilaian', 'tanggal_penilaian');

CALL _safe_change_column('riwayat_perhitungan', 'id', '`id_riwayat` int NOT NULL AUTO_INCREMENT');

CALL _safe_change_column('hasil_moora', 'id', '`id_hasil` int NOT NULL AUTO_INCREMENT');
CALL _safe_change_column('hasil_moora', 'riwayat_id', '`id_riwayat` int NOT NULL');
CALL _safe_change_column('hasil_moora', 'balita_id', '`id_balita` int NOT NULL');
CALL _safe_change_column('hasil_moora', 'penilaian_id', '`id_penilaian` int NOT NULL');

CALL _safe_change_column('lms_bbu',  'id', '`id_lms_bbu` int NOT NULL AUTO_INCREMENT');
CALL _safe_change_column('lms_tbu',  'id', '`id_lms_tbu` int NOT NULL AUTO_INCREMENT');
CALL _safe_change_column('lms_bbtb', 'id', '`id_lms_bbtb` int NOT NULL AUTO_INCREMENT');
CALL _safe_change_column('lms_imtu', 'id', '`id_lms_imtu` int NOT NULL AUTO_INCREMENT');

-- -----------------------------------------------------------------
-- Pasang kembali index & FK dengan nama kolom baru.
-- -----------------------------------------------------------------
CALL _safe_add_key('balita', 'idx_balita_posyandu', '`id_posyandu`');
CALL _safe_add_fk('balita', 'id_posyandu', 'fk_balita_posyandu', 'REFERENCES `posyandu` (`id_posyandu`)');

CALL _safe_add_key('users', 'fk_users_posyandu', '`id_posyandu`');
CALL _safe_add_fk('users', 'id_posyandu', 'fk_users_posyandu', 'REFERENCES `posyandu` (`id_posyandu`) ON DELETE SET NULL');

CALL _safe_add_key('subkriteria', 'fk_subkriteria_kriteria', '`id_kriteria`');
CALL _safe_add_fk('subkriteria', 'id_kriteria', 'fk_subkriteria_kriteria', 'REFERENCES `kriteria` (`id_kriteria`) ON DELETE CASCADE');

CALL _safe_add_key('penilaian', 'idx_penilaian_balita', '`id_balita`');
CALL _safe_add_fk('penilaian', 'id_balita', 'fk_penilaian_balita', 'REFERENCES `balita` (`id_balita`) ON DELETE CASCADE');

CALL _safe_add_key('riwayat_perhitungan', 'fk_riwayat_user', '`dibuat_oleh`');
CALL _safe_add_fk('riwayat_perhitungan', 'dibuat_oleh', 'fk_riwayat_user', 'REFERENCES `users` (`id_user`) ON DELETE SET NULL');

CALL _safe_add_key('hasil_moora', 'fk_hasil_balita', '`id_balita`');
CALL _safe_add_key('hasil_moora', 'fk_hasil_penilaian', '`id_penilaian`');
CALL _safe_add_key('hasil_moora', 'idx_hasil_riwayat', '`id_riwayat`, `ranking`');
CALL _safe_add_fk('hasil_moora', 'id_balita', 'fk_hasil_balita', 'REFERENCES `balita` (`id_balita`) ON DELETE CASCADE');
CALL _safe_add_fk('hasil_moora', 'id_penilaian', 'fk_hasil_penilaian', 'REFERENCES `penilaian` (`id_penilaian`) ON DELETE CASCADE');
CALL _safe_add_fk('hasil_moora', 'id_riwayat', 'fk_hasil_riwayat', 'REFERENCES `riwayat_perhitungan` (`id_riwayat`) ON DELETE CASCADE');

-- -----------------------------------------------------------------
-- Bersihkan stored procedure sementara.
-- -----------------------------------------------------------------
DROP PROCEDURE IF EXISTS _drop_fk_by_column;
DROP PROCEDURE IF EXISTS _safe_change_column;
DROP PROCEDURE IF EXISTS _safe_drop_column;
DROP PROCEDURE IF EXISTS _safe_add_key;
DROP PROCEDURE IF EXISTS _safe_add_fk;
