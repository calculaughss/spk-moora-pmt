<?php
require_once __DIR__ . '/../config/session.php';
requireKader();
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('Template Balita');

$headers = [
    'nama_balita', 'jenis_kelamin', 'tanggal_lahir',
    'pendapatan_ortu', 'jumlah_tanggungan', 'nama_ortu', 'alamat',
];
$sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:G1')->getFont()->setBold(true);

// Baris contoh
$sheet->fromArray([
    'Contoh Nama Balita', 'L', '2022-03-15',
    2500000, 3, 'Contoh Nama Orang Tua', 'Contoh Alamat Lengkap',
], null, 'A2');

$sheet->getStyle('C2')->getNumberFormat()->setFormatCode('yyyy-mm-dd');

foreach (range('A', 'G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="template_import_balita.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;