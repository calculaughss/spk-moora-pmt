<?php
require_once __DIR__ . '/../config/session.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id = $_POST['id'] ?? '';
$nama = trim($_POST['nama_posyandu'] ?? '');
$alamat = trim($_POST['alamat'] ?? '') ?: null;

if ($nama === '') {
    header('Location: index.php?error=' . urlencode('Nama posyandu wajib diisi.') . ($id ? "&edit=$id" : ''));
    exit;
}

$pdo = getDB();

try {
    if ($id) {
        $stmt = $pdo->prepare('UPDATE posyandu SET nama_posyandu = :nama, alamat = :alamat WHERE id_posyandu = :id');
        $stmt->execute(['nama' => $nama, 'alamat' => $alamat, 'id' => $id]);
    } else {
        $stmt = $pdo->prepare('INSERT INTO posyandu (nama_posyandu, alamat) VALUES (:nama, :alamat)');
        $stmt->execute(['nama' => $nama, 'alamat' => $alamat]);
    }
    header('Location: index.php?success=1');
} catch (PDOException $e) {
    $msg = $e->getCode() === '23000' ? 'Nama posyandu sudah dipakai.' : 'Terjadi kesalahan database.';
    header('Location: index.php?error=' . urlencode($msg) . ($id ? "&edit=$id" : ''));
}
exit;
