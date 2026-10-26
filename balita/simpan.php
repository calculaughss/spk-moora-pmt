<?php
require_once __DIR__ . '/../config/session.php';
requireKader();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id               = $_POST['id'] ?? '';
$nama_balita      = trim($_POST['nama_balita'] ?? '');
$jenis_kelamin    = $_POST['jenis_kelamin'] ?? 'L';
$tanggal_lahir_raw= $_POST['tanggal_lahir'] ?? '';
$tanggal_lahir    = normalizeTanggal($tanggal_lahir_raw);
$pendapatan_ortu  = $_POST['pendapatan_ortu'] ?? '';
$jumlah_tanggungan= $_POST['jumlah_tanggungan'] ?? '';
$nama_ortu        = trim($_POST['nama_ortu'] ?? '');
$alamat           = trim($_POST['alamat'] ?? '');
$posyandu_id      = currentPosyanduId();

// Validasi wajib isi
$errors = [];
foreach (['nama_balita','pendapatan_ortu','jumlah_tanggungan','nama_ortu','alamat'] as $field) {
    if ($$field === '' || $$field === null) {
        $errors[] = "Field $field wajib diisi.";
    }
}
if ($tanggal_lahir_raw === '') {
    $errors[] = 'Field tanggal_lahir wajib diisi.';
} elseif ($tanggal_lahir === null) {
    $errors[] = 'Format tanggal lahir tidak valid. Gunakan dd-mm-yyyy atau yyyy-mm-dd.';
}
if (!in_array($jenis_kelamin, ['L', 'P'], true)) $errors[] = 'Jenis kelamin tidak valid.';
if (!$posyandu_id) $errors[] = 'Akun Anda belum terhubung ke posyandu manapun. Hubungi admin.';

if ($errors) {
    $target = $id ? "form.php?id=$id" : 'form.php';
    header('Location: ' . $target . '&error=' . urlencode(implode(' ', $errors)));
    exit;
}

$pdo = getDB();

try {
    if ($id) {
        // Batasi update hanya pada balita di posyandu yang dikelola kader ini
        $sql = 'UPDATE balita SET nama_balita=:nama_balita, jenis_kelamin=:jk, tanggal_lahir=:tgl,
                pendapatan_ortu=:pendapatan, jumlah_tanggungan=:tanggungan,
                nama_ortu=:nama_ortu, alamat=:alamat
                WHERE id_balita=:id AND id_posyandu=:posyandu_id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'nama_balita' => $nama_balita, 'jk' => $jenis_kelamin, 'tgl' => $tanggal_lahir,
            'pendapatan' => $pendapatan_ortu,
            'tanggungan' => $jumlah_tanggungan,
            'nama_ortu' => $nama_ortu, 'alamat' => $alamat, 'id' => $id,
            'posyandu_id' => $posyandu_id,
        ]);
    } else {
        $sql = 'INSERT INTO balita (id_posyandu, nama_balita, jenis_kelamin, tanggal_lahir,
                pendapatan_ortu, jumlah_tanggungan, nama_ortu, alamat)
                VALUES (:posyandu_id, :nama_balita, :jk, :tgl, :pendapatan, :tanggungan, :nama_ortu, :alamat)';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'posyandu_id' => $posyandu_id,
            'nama_balita' => $nama_balita, 'jk' => $jenis_kelamin, 'tgl' => $tanggal_lahir,
            'pendapatan' => $pendapatan_ortu,
            'tanggungan' => $jumlah_tanggungan,
            'nama_ortu' => $nama_ortu, 'alamat' => $alamat,
        ]);
    }

    $newId = $id ?: $pdo->lastInsertId();
    header('Location: index.php?success=1' . (!$id ? '&new_id=' . $newId : ''));
    exit;

} catch (PDOException $e) {
    $msg = 'Terjadi kesalahan database.';
    $target = $id ? "form.php?id=$id" : 'form.php';
    header('Location: ' . $target . '&error=' . urlencode($msg));
    exit;
}