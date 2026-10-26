<?php
require_once __DIR__ . '/../config/session.php';
requireKader();

$id = $_GET['id'] ?? null;
if ($id) {
    $pdo = getDB();
    $stmt = $pdo->prepare('
        DELETE p FROM penilaian p
        JOIN balita b ON b.id_balita = p.id_balita
        WHERE p.id_penilaian = ? AND b.id_posyandu = ?
    ');
    $stmt->execute([$id, currentPosyanduId()]);
}

header('Location: index.php?deleted=1');
exit;
