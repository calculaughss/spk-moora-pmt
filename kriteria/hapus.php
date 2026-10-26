<?php
require_once __DIR__ . '/../config/session.php';
requireAdmin();

$id = $_GET['id'] ?? null;

if (!$id) {
    header('Location: index.php');
    exit;
}

$pdo = getDB();

try {
    // Subkriteria milik kriteria ini ikut terhapus otomatis lewat
    // FK ON DELETE CASCADE (lihat database/migration_2026_08.sql).
    // Riwayat hasil_moora yang sudah tersimpan TIDAK dihapus — datanya
    // menyimpan salinan (JSON) sehingga tidak bergantung pada baris kriteria ini.
    $stmt = $pdo->prepare('DELETE FROM kriteria WHERE id_kriteria = ?');
    $stmt->execute([$id]);
    header('Location: index.php?deleted=1');
} catch (PDOException $e) {
    header('Location: index.php?error=' . urlencode('Terjadi kesalahan database saat menghapus kriteria.'));
}
exit;
