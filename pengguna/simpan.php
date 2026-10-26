<?php
require_once __DIR__ . '/../config/session.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$id           = $_POST['id'] ?? '';
$username     = trim($_POST['username'] ?? '');
$nama_lengkap = trim($_POST['nama_lengkap'] ?? '');
$password     = $_POST['password'] ?? '';
$role         = $_POST['role'] ?? 'kader';
$posyandu_id  = $_POST['posyandu_id'] ?? '';
$is_active    = isset($_POST['is_active']) ? 1 : 0;

$errors = [];
if ($username === '') $errors[] = 'Username wajib diisi.';
if ($nama_lengkap === '') $errors[] = 'Nama lengkap wajib diisi.';
if (!$id && $password === '') $errors[] = 'Password wajib diisi untuk pengguna baru.';
if ($password !== '' && strlen($password) < 6) $errors[] = 'Password minimal 6 karakter.';
if (!in_array($role, ['admin', 'kader'], true)) $errors[] = 'Role tidak valid.';

if ($role === 'kader') {
    if ($posyandu_id === '') {
        $errors[] = 'Posyandu wajib dipilih untuk role kader.';
    }
} else {
    $posyandu_id = null; // admin tidak terikat posyandu manapun
}

if ($errors) {
    $target = $id ? "index.php?edit=$id" : 'index.php';
    header('Location: ' . $target . '&error=' . urlencode(implode(' ', $errors)));
    exit;
}

$pdo = getDB();

try {
    if ($id) {
        if ($password !== '') {
            $stmt = $pdo->prepare('
                UPDATE users SET username=:username, nama_lengkap=:nama, password=:password,
                       role=:role, id_posyandu=:posyandu_id, is_active=:aktif
                WHERE id_user=:id
            ');
            $stmt->execute([
                'username' => $username, 'nama' => $nama_lengkap,
                'password' => password_hash($password, PASSWORD_BCRYPT),
                'role' => $role, 'posyandu_id' => $posyandu_id, 'aktif' => $is_active, 'id' => $id,
            ]);
        } else {
            $stmt = $pdo->prepare('
                UPDATE users SET username=:username, nama_lengkap=:nama,
                       role=:role, id_posyandu=:posyandu_id, is_active=:aktif
                WHERE id_user=:id
            ');
            $stmt->execute([
                'username' => $username, 'nama' => $nama_lengkap,
                'role' => $role, 'posyandu_id' => $posyandu_id, 'aktif' => $is_active, 'id' => $id,
            ]);
        }

        // Jika akun yang diedit adalah akun yang sedang login, sinkronkan sesi
        if ((int) $id === (int) $_SESSION['user_id']) {
            $_SESSION['nama_lengkap'] = $nama_lengkap;
            $_SESSION['role'] = $role;
            $_SESSION['posyandu_id'] = $posyandu_id;
        }
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO users (username, password, nama_lengkap, role, id_posyandu, is_active)
            VALUES (:username, :password, :nama, :role, :posyandu_id, :aktif)
        ');
        $stmt->execute([
            'username' => $username,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'nama' => $nama_lengkap, 'role' => $role,
            'posyandu_id' => $posyandu_id, 'aktif' => $is_active,
        ]);
    }

    header('Location: index.php?success=1');
} catch (PDOException $e) {
    $msg = $e->getCode() === '23000' ? 'Username sudah dipakai.' : 'Terjadi kesalahan database.';
    $target = $id ? "index.php?edit=$id" : 'index.php';
    header('Location: ' . $target . '&error=' . urlencode($msg));
}
exit;
