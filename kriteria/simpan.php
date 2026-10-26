<?php
require_once __DIR__ . '/../config/session.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$ids = $_POST['id'] ?? [];
$bobots = $_POST['bobot'] ?? [];

if (count($ids) !== count($bobots) || empty($ids)) {
    header('Location: index.php?error=' . urlencode('Data tidak valid.'));
    exit;
}

$total = array_sum(array_map('floatval', $bobots));
if (abs($total - 1.0) > 0.0009) {
    header('Location: index.php?error=' . urlencode('Total bobot harus tepat 1.000 (saat ini: ' . number_format($total, 3) . ').'));
    exit;
}

foreach ($bobots as $b) {
    if (!is_numeric($b) || $b < 0 || $b > 1) {
        header('Location: index.php?error=' . urlencode('Bobot harus berupa angka antara 0 dan 1.'));
        exit;
    }
}

$pdo = getDB();
$stmt = $pdo->prepare('UPDATE kriteria SET bobot = :bobot WHERE id_kriteria = :id');

try {
    $pdo->beginTransaction();
    foreach ($ids as $i => $id) {
        $stmt->execute(['bobot' => $bobots[$i], 'id' => $id]);
    }
    $pdo->commit();
    header('Location: index.php?success=1');
} catch (PDOException $e) {
    $pdo->rollBack();
    header('Location: index.php?error=' . urlencode('Terjadi kesalahan database.'));
}
exit;
