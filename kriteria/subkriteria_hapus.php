<?php
require_once __DIR__ . '/../config/session.php';
requireAdmin();

$id = $_GET['id'] ?? null;
$kriteriaId = (int) ($_GET['kriteria_id'] ?? 0);

if ($id) {
    $pdo = getDB();
    $stmt = $pdo->prepare('DELETE FROM subkriteria WHERE id_subkriteria = ? AND id_kriteria = ?');
    $stmt->execute([$id, $kriteriaId]);
}

header('Location: subkriteria.php?kriteria_id=' . $kriteriaId . '&success=1');
exit;
