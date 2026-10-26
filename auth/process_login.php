<?php
require_once __DIR__ . '/../config/session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    header('Location: login.php?error=empty');
    exit;
}

try {
    $pdo = getDB();
    $stmt = $pdo->prepare('
        SELECT u.id_user, u.username, u.password, u.nama_lengkap, u.role, u.is_active,
               u.id_posyandu, p.nama_posyandu
        FROM users u
        LEFT JOIN posyandu p ON p.id_posyandu = u.id_posyandu
        WHERE u.username = :username LIMIT 1
    ');
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password']) || (int)$user['is_active'] !== 1) {
        header('Location: login.php?error=invalid');
        exit;
    }

    // Regenerate session id untuk mencegah session fixation
    session_regenerate_id(true);

    $_SESSION['user_id']      = $user['id_user'];
    $_SESSION['username']     = $user['username'];
    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
    $_SESSION['role']         = $user['role'];
    $_SESSION['posyandu_id']  = $user['id_posyandu'];
    $_SESSION['nama_posyandu']= $user['nama_posyandu'];

    header('Location: ' . BASE_URL . 'dashboard/index.php');
    exit;

} catch (PDOException $e) {
    header('Location: login.php?error=db');
    exit;
}
