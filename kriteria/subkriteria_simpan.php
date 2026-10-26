<?php
require_once __DIR__ . '/../config/session.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = $_POST['id'] ?? '';
$kriteriaId = (int) ($_POST['kriteria_id'] ?? 0);
$nama = trim($_POST['nama_subkriteria'] ?? '');
$tipeNilai = $_POST['tipe_nilai'] ?? 'rentang';
$nilaiTunggal = $_POST['nilai_tunggal'] ?? '';
$batasBawah = $_POST['batas_bawah'] ?? '';
$batasBawahInklusif = ($_POST['batas_bawah_inklusif'] ?? '1') === '1' ? 1 : 0;
$batasAtas = $_POST['batas_atas'] ?? '';
$batasAtasInklusif = ($_POST['batas_atas_inklusif'] ?? '1') === '1' ? 1 : 0;
$bobot = $_POST['bobot'] ?? '';
$urutan = (int) ($_POST['urutan'] ?? 0);

$redirectBack = 'subkriteria.php?kriteria_id=' . $kriteriaId;

// Mode "Nilai Tunggal": subkriteria ini hanya cocok kalau nilai mentah PERSIS
// sama dengan satu angka (dipakai utk kriteria yang nilainya bukan rentang,
// mis. kategori/kode angka tunggal) — batas bawah & atas dibuat sama dan
// keduanya inklusif, sehingga field rentang di form diabaikan.
if ($tipeNilai === 'tunggal') {
    if ($nilaiTunggal === '' || !is_numeric($nilaiTunggal)) {
        header('Location: ' . $redirectBack . '&error=' . urlencode('Isi angka nilai tunggal terlebih dahulu.'));
        exit;
    }
    $batasBawah = $nilaiTunggal;
    $batasAtas = $nilaiTunggal;
    $batasBawahInklusif = 1;
    $batasAtasInklusif = 1;
}

if ($kriteriaId < 1 || $nama === '' || !is_numeric($bobot)) {
    header('Location: ' . $redirectBack . '&error=' . urlencode('Nama subkriteria dan bobot wajib diisi.'));
    exit;
}
if ($batasBawah !== '' && !is_numeric($batasBawah)) {
    header('Location: ' . $redirectBack . '&error=' . urlencode('Batas bawah harus berupa angka atau dikosongkan.'));
    exit;
}
if ($batasAtas !== '' && !is_numeric($batasAtas)) {
    header('Location: ' . $redirectBack . '&error=' . urlencode('Batas atas harus berupa angka atau dikosongkan.'));
    exit;
}
if ($batasBawah !== '' && $batasAtas !== '' && (float) $batasBawah > (float) $batasAtas) {
    header('Location: ' . $redirectBack . '&error=' . urlencode('Batas bawah tidak boleh lebih besar dari batas atas.'));
    exit;
}

$pdo = getDB();

// Pastikan kriteria_id valid
$check = $pdo->prepare('SELECT id_kriteria FROM kriteria WHERE id_kriteria = ?');
$check->execute([$kriteriaId]);
if (!$check->fetch()) {
    header('Location: index.php?error=' . urlencode('Kriteria tidak ditemukan.'));
    exit;
}

$params = [
    'kriteria_id' => $kriteriaId,
    'nama'        => $nama,
    'bawah'       => $batasBawah === '' ? null : $batasBawah,
    'bawah_inc'   => $batasBawahInklusif,
    'atas'        => $batasAtas === '' ? null : $batasAtas,
    'atas_inc'    => $batasAtasInklusif,
    'bobot'       => $bobot,
    'urutan'      => $urutan,
];

try {
    if ($id !== '') {
        $params['id'] = $id;
        $stmt = $pdo->prepare('
            UPDATE subkriteria
            SET nama_subkriteria = :nama, batas_bawah = :bawah, batas_bawah_inklusif = :bawah_inc,
                batas_atas = :atas, batas_atas_inklusif = :atas_inc, bobot = :bobot, urutan = :urutan
            WHERE id_subkriteria = :id AND id_kriteria = :kriteria_id
        ');
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO subkriteria (id_kriteria, nama_subkriteria, batas_bawah, batas_bawah_inklusif,
                                      batas_atas, batas_atas_inklusif, bobot, urutan)
            VALUES (:kriteria_id, :nama, :bawah, :bawah_inc, :atas, :atas_inc, :bobot, :urutan)
        ');
    }
    $stmt->execute($params);
    header('Location: ' . $redirectBack . '&success=1');
} catch (PDOException $e) {
    header('Location: ' . $redirectBack . '&error=' . urlencode('Terjadi kesalahan database.'));
}
exit;
