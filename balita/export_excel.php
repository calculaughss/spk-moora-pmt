<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/zscore.php';
requireKader();
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$pdo = getDB();
$stmt = $pdo->prepare("
    SELECT b.*,
           (SELECT COUNT(*) FROM penilaian p WHERE p.id_balita = b.id_balita) AS jumlah_pengukuran,
           (SELECT MAX(tanggal_pengukuran) FROM penilaian p WHERE p.id_balita = b.id_balita) AS pengukuran_terakhir
    FROM balita b
    WHERE b.id_posyandu = :posyandu_id
    ORDER BY b.nama_balita ASC
");
$stmt->execute(['posyandu_id' => currentPosyanduId()]);
$rows = $stmt->fetchAll();

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Data Balita');

$headers = [
    'No', 'Nama Balita', 'JK', 'Tanggal Lahir', 'Usia (bulan)',
    'Pendapatan Ortu', 'Jumlah Tanggungan', 'Riwayat Penyakit',
    'Nama Orang Tua', 'Alamat', 'Jumlah Pengukuran', 'Pengukuran Terakhir',
];
$sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:L1')->getFont()->setBold(true);

$row = 2;
foreach ($rows as $i => $r) {
    $sheet->fromArray([
        $i + 1,
        $r['nama_balita'],
        $r['jenis_kelamin'],
        date('d/m/Y', strtotime($r['tanggal_lahir'])),
        hitungUsiaBulan($r['tanggal_lahir']),
        (float) $r['pendapatan_ortu'],
        (int) $r['jumlah_tanggungan'],
        $r['riwayat_penyakit'],
        $r['nama_ortu'],
        $r['alamat'],
        (int) $r['jumlah_pengukuran'],
        $r['pengukuran_terakhir'] ? date('d/m/Y', strtotime($r['pengukuran_terakhir'])) : '-',
    ], null, 'A' . $row);
    $row++;
}

foreach (range('A', 'L') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

$filename = 'data_balita_' . date('Ymd_His') . '.xlsx';
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
