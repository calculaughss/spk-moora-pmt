<?php
require_once __DIR__ . '/../config/session.php';
requireKader();

$id = $_GET['id'] ?? null;
if ($id) {
    $pdo = getDB();
    $stmt = $pdo->prepare('DELETE FROM balita WHERE id_balita = ? AND id_posyandu = ?');
    $stmt->execute([$id, currentPosyanduId()]);
}

header('Location: index.php?deleted=1');
exit;
