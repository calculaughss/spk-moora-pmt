<?php
require_once __DIR__ . '/../config/session.php';
requireAdmin();
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['file_excel']['tmp_name'])) {
    header('Location: import_lms.php');
    exit;
}

$tabel = $_POST['tabel'] ?? '';
$tabelValid = ['lms_bbu', 'lms_tbu', 'lms_bbtb', 'lms_imtu'];
$jenisKelamin = strtoupper(trim($_POST['jenis_kelamin'] ?? ''));
$tipeUkur = trim($_POST['tipe_ukur'] ?? '');

if (!in_array($tabel, $tabelValid, true)) {
    header('Location: import_lms.php?error=' . urlencode('Tabel tujuan tidak valid.'));
    exit;
}
if (!in_array($jenisKelamin, ['L', 'P'], true)) {
    header('Location: import_lms.php?error=' . urlencode('Jenis kelamin tidak valid.'));
    exit;
}
// BB/TB WAJIB menyertakan tipe_ukur ('panjang' = Weight-for-Length 0-2th,
// 'tinggi' = Weight-for-Height 2-5th) — WHO punya dua tabel berbeda untuk
// BB/TB, keduanya tidak boleh disatukan (lihat catatan di halaman import).
if ($tabel === 'lms_bbtb' && !in_array($tipeUkur, ['panjang', 'tinggi'], true)) {
    header('Location: import_lms.php?error=' . urlencode('Pilih dulu Tipe Tabel (Weight-for-Length atau Weight-for-Height) untuk import BB/TB.'));
    exit;
}

$kolomX = $tabel === 'lms_bbtb' ? 'tinggi_badan' : 'usia_hari';
// Nama header di file Excel yang dianggap sebagai nilai X (boleh salah satu dari ini).
// Untuk BB/U, TB/U, IMT/U: WAJIB pakai file "expanded tables" resmi WHO
// (wfa/lhfa/bfa-*-zscore-expanded-tables.xlsx), yang header kolom pertamanya
// "Day" (usia dalam HARI, 0-1856), BUKAN file lama yang headernya "Month".
// Ini supaya look-up LMS exact-match per hari (tanpa interpolasi), sama
// presisinya dengan tabel BB/TB.
$namaKolomXValid = $tabel === 'lms_bbtb' ? ['height', 'length'] : ['day', 'usia_hari'];

try {
    $spreadsheet = IOFactory::load($_FILES['file_excel']['tmp_name']);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, false);

    if (count($rows) < 2) {
        throw new RuntimeException('File tidak memiliki data (hanya header atau kosong).');
    }

    $header = array_map(fn($h) => strtolower(trim((string) $h)), $rows[0]);

    $idxX = null;
    foreach ($namaKolomXValid as $nama) {
        $pos = array_search($nama, $header, true);
        if ($pos !== false) { $idxX = $pos; break; }
    }
    $idxL = array_search('l', $header, true);
    $idxM = array_search('m', $header, true);
    $idxS = array_search('s', $header, true);

    if ($idxX === null || $idxL === false || $idxM === false || $idxS === false) {
        throw new RuntimeException(
            'Header kolom tidak dikenali. Pastikan file memiliki kolom ' .
            ($tabel === 'lms_bbtb' ? 'Height/Length' : 'Day') . ', L, M, S. ' .
            ($tabel === 'lms_bbtb' ? '' : 'Gunakan file "expanded tables" resmi WHO (bukan file lama berheader Month).')
        );
    }

    $pdo = getDB();

    if ($tabel === 'lms_bbtb') {
        $sql = "INSERT INTO lms_bbtb (jenis_kelamin, tipe_ukur, tinggi_badan, L, M, S) VALUES (:jk, :tipe, :x, :l, :m, :s)
                ON DUPLICATE KEY UPDATE L = VALUES(L), M = VALUES(M), S = VALUES(S)";
    } else {
        $sql = "INSERT INTO $tabel (jenis_kelamin, $kolomX, L, M, S) VALUES (:jk, :x, :l, :m, :s)
                ON DUPLICATE KEY UPDATE L = VALUES(L), M = VALUES(M), S = VALUES(S)";
    }
    $stmt = $pdo->prepare($sql);

    $count = 0;
    $pdo->beginTransaction();

    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];
        $x = isset($row[$idxX]) ? trim((string) $row[$idxX]) : null;
        $l = isset($row[$idxL]) ? trim((string) $row[$idxL]) : null;
        $m = isset($row[$idxM]) ? trim((string) $row[$idxM]) : null;
        $s = isset($row[$idxS]) ? trim((string) $row[$idxS]) : null;

        if ($x === null || $x === '' || $l === null || $l === '' || $m === null || $m === '' || $s === null || $s === '') {
            continue;
        }
        if (!is_numeric($x) || !is_numeric($l) || !is_numeric($m) || !is_numeric($s)) {
            continue;
        }

        $params = ['jk' => $jenisKelamin, 'x' => $tabel === 'lms_bbtb' ? (float) $x : (int) round((float) $x), 'l' => (float) $l, 'm' => (float) $m, 's' => (float) $s];
        if ($tabel === 'lms_bbtb') {
            $params['tipe'] = $tipeUkur;
        }
        $stmt->execute($params);
        $count++;
    }

    if ($count === 0) {
        $pdo->rollBack();
        header('Location: import_lms.php?error=' . urlencode('Tidak ada baris valid yang berhasil dibaca dari file.'));
        exit;
    }

    $pdo->commit();
    header('Location: import_lms.php?success=1&rows=' . $count);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: import_lms.php?error=' . urlencode('Gagal membaca file: ' . $e->getMessage()));
}
exit;