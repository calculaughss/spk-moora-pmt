<?php
require_once __DIR__ . '/../config/session.php';
requireKader();
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['file_excel']['tmp_name'])) {
    header('Location: import.php');
    exit;
}

/**
 * Parse nilai tanggal dari sel Excel: bisa berupa serial number Excel
 * (jika sel diformat sebagai Date) atau teks biasa (mis. "2021-05-14" / "14/05/2021").
 */
function parseTanggalExcel($value): ?string
{
    if ($value === null || $value === '') return null;

    if (is_numeric($value)) {
        try {
            $dt = ExcelDate::excelToDateTimeObject((float) $value);
            return $dt->format('Y-m-d');
        } catch (Exception $e) {
            // lanjut coba sebagai teks
        }
    }

    $ts = strtotime((string) $value);
    return $ts !== false ? date('Y-m-d', $ts) : null;
}

try {
    $spreadsheet = IOFactory::load($_FILES['file_excel']['tmp_name']);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = $sheet->toArray(null, true, true, false);

    if (count($rows) < 2) {
        throw new RuntimeException('File tidak memiliki data (hanya header atau kosong).');
    }

    $header = array_map(fn($h) => strtolower(trim((string) $h)), $rows[0]);

    $kolomWajib = [
        'nama_balita' => ['nama_balita', 'nama balita', 'nama'],
        'jenis_kelamin' => ['jenis_kelamin', 'jenis kelamin', 'jk'],
        'tanggal_lahir' => ['tanggal_lahir', 'tanggal lahir', 'tgl_lahir', 'tgl lahir'],
        'pendapatan_ortu' => ['pendapatan_ortu', 'pendapatan orang tua', 'pendapatan'],
        'jumlah_tanggungan' => ['jumlah_tanggungan', 'jumlah tanggungan', 'tanggungan'],
        'nama_ortu' => ['nama_ortu', 'nama orang tua', 'nama ortu'],
        'alamat' => ['alamat'],
    ];

    $idx = [];
    foreach ($kolomWajib as $key => $aliases) {
        $found = null;
        foreach ($aliases as $alias) {
            $pos = array_search($alias, $header, true);
            if ($pos !== false) { $found = $pos; break; }
        }
        if ($found === null) {
            throw new RuntimeException("Header kolom '$key' tidak ditemukan di file. Pastikan file sesuai template.");
        }
        $idx[$key] = $found;
    }

    $posyanduId = currentPosyanduId();
    if (!$posyanduId) {
        throw new RuntimeException('Akun Anda belum terhubung ke posyandu manapun. Hubungi admin.');
    }

    $pdo = getDB();
    $stmtInsert = $pdo->prepare('
        INSERT INTO balita (id_posyandu, nama_balita, jenis_kelamin, tanggal_lahir, pendapatan_ortu, jumlah_tanggungan, nama_ortu, alamat)
        VALUES (:posyandu_id, :nama, :jk, :tgl, :pendapatan, :tanggungan, :nama_ortu, :alamat)
    ');

    $inserted = 0;
    $skipped = 0;

    $pdo->beginTransaction();

    for ($i = 1; $i < count($rows); $i++) {
        $row = $rows[$i];

        $nama = trim((string) ($row[$idx['nama_balita']] ?? ''));
        $jk = strtoupper(trim((string) ($row[$idx['jenis_kelamin']] ?? '')));
        $tglLahir = parseTanggalExcel($row[$idx['tanggal_lahir']] ?? null);
        $pendapatan = $row[$idx['pendapatan_ortu']] ?? null;
        $tanggungan = $row[$idx['jumlah_tanggungan']] ?? null;
        $namaOrtu = trim((string) ($row[$idx['nama_ortu']] ?? ''));
        $alamat = trim((string) ($row[$idx['alamat']] ?? ''));

        // Lewati baris kosong total
        if ($nama === '' && $namaOrtu === '') continue;

        // Validasi per baris - lewati baris yang tidak valid, catat jumlahnya
        if ($nama === '' || !in_array($jk, ['L', 'P'], true) || $tglLahir === null
            || !is_numeric($pendapatan) || !is_numeric($tanggungan)
            || $namaOrtu === '' || $alamat === '') {
            $skipped++;
            continue;
        }

        $stmtInsert->execute([
            'posyandu_id' => $posyanduId,
            'nama' => $nama, 'jk' => $jk, 'tgl' => $tglLahir,
            'pendapatan' => (float) $pendapatan, 'tanggungan' => (int) $tanggungan,
            'nama_ortu' => $namaOrtu, 'alamat' => $alamat,
        ]);
        $inserted++;
    }

    if ($inserted === 0) {
        $pdo->rollBack();
        header('Location: import.php?error=' . urlencode('Tidak ada baris valid yang berhasil diimport. Periksa kembali format file.'));
        exit;
    }

    $pdo->commit();
    header('Location: import.php?success=1&inserted=' . $inserted . '&skipped=' . $skipped);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: import.php?error=' . urlencode('Gagal membaca file: ' . $e->getMessage()));
}
exit;