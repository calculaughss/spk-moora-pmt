<?php
require_once __DIR__ . '/../config/session.php';
requireAdmin();

$id = $_GET['id'] ?? null;

if ($id && (int) $id !== (int) $_SESSION['user_id']) {
    $pdo = getDB();
    $stmt = $pdo->prepare('DELETE FROM users WHERE id_user = ?');
    $stmt->execute([$id]);
}

header('Location: index.php?deleted=1');
exit;
