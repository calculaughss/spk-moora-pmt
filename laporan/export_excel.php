<?php
require_once __DIR__ . '/../config/session.php';
requireLogin();
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$riwayatId = $_GET['riwayat_id'] ?? null;
if (!$riwayatId) {
    header('Location: index.php');
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare('SELECT * FROM riwayat_perhitungan WHERE id_riwayat = ?');
$stmt->execute([$riwayatId]);
$batch = $stmt->fetch();

if (!$batch) {
    header('Location: index.php');
    exit;
}

// Kader hanya boleh export batch milik posyandu yang ia kelola
if (isKader()) {
    $chk = $pdo->prepare('
        SELECT COUNT(*) FROM hasil_moora hm
        JOIN balita b ON b.id_balita = hm.id_balita
        WHERE hm.id_riwayat = ? AND b.id_posyandu = ?
    ');
    $chk->execute([$riwayatId, currentPosyanduId()]);
    if ((int) $chk->fetchColumn() === 0) {
        header('Location: index.php');
        exit;
    }
}

$stmt = $pdo->prepare('
    SELECT h.*, b.nama_balita, b.jenis_kelamin, p.usia_bulan, p.status_bbu, p.status_tbu, p.status_bbtb
    FROM hasil_moora h
    JOIN balita b ON b.id_balita = h.id_balita
    JOIN penilaian p ON p.id_penilaian = h.id_penilaian
    WHERE h.id_riwayat = :id
    ORDER BY h.ranking ASC
');
$stmt->execute(['id' => $riwayatId]);
$hasil = $stmt->fetchAll();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Hasil Seleksi PMT');

$headers = ['Rank', 'Nama Balita', 'JK', 'Usia (bln)', 'Status BB/U', 'Status TB/U', 'Status BB/TB', 'Nilai Yi', 'Status PMT'];
$sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:I1')->getFont()->setBold(true);

$row = 2;
foreach ($hasil as $h) {
    $sheet->fromArray([
        $h['ranking'], $h['nama_balita'], $h['jenis_kelamin'], $h['usia_bulan'],
        $h['status_bbu'], $h['status_tbu'], $h['status_bbtb'], $h['yi_value'], $h['status_kelayakan'],
    ], null, 'A' . $row);
    $row++;
}

foreach (range('A', 'I') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$filename = 'laporan_pmt_batch_' . $batch['id_riwayat'] . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
