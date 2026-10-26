<?php
require_once __DIR__ . '/../config/session.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = $_POST['id'] ?? '';
$namaKriteria = trim($_POST['nama_kriteria'] ?? '');
$jenis = $_POST['jenis'] ?? '';
$bobot = $_POST['bobot'] ?? '';
$keterangan = trim($_POST['keterangan'] ?? '');

if ($id === '' || $namaKriteria === '') {
    header('Location: index.php?error=' . urlencode('Nama kriteria wajib diisi.'));
    exit;
}
if (!in_array($jenis, ['benefit', 'cost'], true)) {
    header('Location: index.php?error=' . urlencode('Jenis kriteria tidak valid.'));
    exit;
}
if (!is_numeric($bobot) || $bobot < 0 || $bobot > 1) {
    header('Location: index.php?error=' . urlencode('Bobot harus berupa angka antara 0 dan 1.'));
    exit;
}

$pdo = getDB();

// kode SENGAJA tidak bisa diubah dari sini — kode C1..C6 dipakai sebagai
// acuan tetap pada perhitungan MOORA (config/zscore.php & perhitungan/proses.php),
// mengubahnya lewat form akan membuat kriteria itu diam-diam tidak terhitung.
$stmt = $pdo->prepare('
    UPDATE kriteria
    SET nama_kriteria = :nama, jenis = :jenis, bobot = :bobot, keterangan = :keterangan
    WHERE id_kriteria = :id
');

try {
    $stmt->execute([
        'nama'       => $namaKriteria,
        'jenis'      => $jenis,
        'bobot'      => $bobot,
        'keterangan' => $keterangan,
        'id'         => $id,
    ]);
    header('Location: index.php?edited=1');
} catch (PDOException $e) {
    header('Location: index.php?error=' . urlencode('Terjadi kesalahan database.'));
}
exit;
