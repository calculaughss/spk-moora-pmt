<?php
require_once __DIR__ . '/../config/session.php';
requireAdmin();

$id = $_GET['id'] ?? null;

if ($id) {
    $pdo = getDB();

    $chk = $pdo->prepare('SELECT
        (SELECT COUNT(*) FROM balita WHERE id_posyandu = ?) AS jml_balita,
        (SELECT COUNT(*) FROM users WHERE id_posyandu = ?) AS jml_kader
    ');
    $chk->execute([$id, $id]);
    $count = $chk->fetch();

    if ((int) $count['jml_balita'] > 0 || (int) $count['jml_kader'] > 0) {
        header('Location: index.php?error=' . urlencode('Posyandu tidak bisa dihapus karena masih punya data balita atau kader.'));
        exit;
    }

    $stmt = $pdo->prepare('DELETE FROM posyandu WHERE id_posyandu = ?');
    $stmt->execute([$id]);
}

header('Location: index.php?deleted=1');
exit;
