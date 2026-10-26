<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/moora.php';
require_once __DIR__ . '/../config/zscore.php';
requireKader();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$kuota = (int) ($_POST['kuota'] ?? 0);
$keterangan = trim($_POST['keterangan'] ?? '') ?: null;
$periode = trim($_POST['periode'] ?? '');

if ($kuota < 1) {
    header('Location: index.php?error=' . urlencode('Kuota harus lebih dari 0.'));
    exit;
}
if (!preg_match('/^\d{4}-\d{2}$/', $periode)) {
    header('Location: index.php?error=' . urlencode('Bulan pengukuran tidak valid. Silakan pilih ulang.'));
    exit;
}

$pdo = getDB();

// Ambil kriteria & pastikan total bobot = 1
$kriteria = $pdo->query('SELECT * FROM kriteria ORDER BY kode ASC')->fetchAll();
$totalBobot = array_sum(array_column($kriteria, 'bobot'));
if (abs($totalBobot - 1.0) > 0.0009) {
    header('Location: index.php?error=' . urlencode('Total bobot kriteria belum 1.000.'));
    exit;
}

// Ambil penilaian HANYA pada bulan (periode) yang dipilih kader — bukan
// "penilaian terbaru" lintas bulan — supaya balita yang tidak diukur pada
// bulan berjalan tidak ikut memakai data pengukuran bulan sebelumnya.
// Jika satu balita punya >1 pengukuran dalam bulan yang sama, dipakai yang
// terakhir diinput (MAX id) pada bulan itu. Dibatasi hanya balita di
// posyandu yang dikelola kader ini.
$sql = "
    SELECT p.*, b.tanggal_lahir
    FROM penilaian p
    INNER JOIN (
        SELECT id_balita, MAX(id_penilaian) AS max_id
        FROM penilaian
        WHERE DATE_FORMAT(tanggal_pengukuran, '%Y-%m') = :periode
        GROUP BY id_balita
    ) latest ON latest.max_id = p.id_penilaian
    JOIN balita b ON b.id_balita = p.id_balita
    WHERE b.id_posyandu = :posyandu_id
";
$stmtRows = $pdo->prepare($sql);
$stmtRows->execute(['periode' => $periode, 'posyandu_id' => currentPosyanduId()]);
$penilaianRowsAll = $stmtRows->fetchAll();

// Balita yang usianya sekarang sudah > 60 bulan (lulus posyandu) tidak diikutkan dalam PMT
$penilaianRows = array_values(array_filter(
    $penilaianRowsAll,
    fn($row) => statusPosyandu($row['tanggal_lahir']) === 'Aktif'
));
$jumlahLulusDikecualikan = count($penilaianRowsAll) - count($penilaianRows);

if (count($penilaianRows) < 1) {
    header('Location: index.php?periode=' . urlencode($periode) . '&error=' . urlencode(
        'Tidak ada data penilaian balita aktif untuk bulan ' . date('F Y', strtotime($periode . '-01')) . '.'
    ));
    exit;
}

if ($jumlahLulusDikecualikan > 0) {
    $catatanLulus = "($jumlahLulusDikecualikan balita dikecualikan karena sudah lulus posyandu)";
    $keterangan = $keterangan ? "$keterangan $catatanLulus" : $catatanLulus;
}

// Susun matriks keputusan Xij per balita. Xij BUKAN nilai mentah (Z-score /
// Rupiah / jumlah tanggungan) melainkan BOBOT SUBKRITERIA hasil klasifikasi
// nilai mentah tsb terhadap tabel `subkriteria` (bisa diubah di menu
// Kriteria > Subkriteria). Nilai mentah tetap disimpan terpisah sbg
// dokumentasi (matriks_asli).
$matriks = [];        // balitaId => [kode => bobot subkriteria]
$matriksAsli = [];    // balitaId => [kode => nilai mentah]
$matriksLabel = [];   // balitaId => [kode => ['nama'=>.., 'bobot'=>.., 'celah'=>bool]]
$penilaianIdMap = [];

$nilaiMentahPerKriteria = function (array $row): array {
    return [
        'C1' => (float) $row['bbu_zscore'],
        'C2' => (float) $row['tbu_zscore'],
        'C3' => (float) $row['bbtb_zscore'],
        'C4' => (float) $row['imtu_zscore'],
        'C5' => (float) $row['pendapatan_ortu'],
        'C6' => (float) $row['jumlah_tanggungan'],
    ];
};

$errorSubkriteria = null;
foreach ($penilaianRows as $row) {
    $balitaId = $row['id_balita'];
    $penilaianIdMap[$balitaId] = $row['id_penilaian'];
    $nilaiMentah = $nilaiMentahPerKriteria($row);
    $matriksAsli[$balitaId] = $nilaiMentah;

    foreach ($kriteria as $k) {
        $kode = $k['kode'];
        if (!isset($nilaiMentah[$kode])) {
            continue; // kriteria kustom di luar C1-C6 (jika admin menambah kriteria baru) diabaikan di sini
        }
        $sub = cariSubkriteria($pdo, (int) $k['id_kriteria'], $nilaiMentah[$kode]);
        if (!$sub) {
            $errorSubkriteria = "Kriteria {$kode} ({$k['nama_kriteria']}) belum punya data subkriteria. " .
                'Silakan lengkapi dulu di menu Kriteria > Subkriteria.';
            break 2;
        }
        $matriks[$balitaId][$kode] = (float) $sub['bobot'];
        $matriksLabel[$balitaId][$kode] = [
            'nama'  => $sub['nama_subkriteria'],
            'bobot' => (float) $sub['bobot'],
            'celah' => $sub['celah'],
        ];
    }
}

if ($errorSubkriteria) {
    header('Location: index.php?periode=' . urlencode($periode) . '&error=' . urlencode($errorSubkriteria));
    exit;
}

$hasilMoora = hitungMOORA($matriks, $kriteria);

try {
    $pdo->beginTransaction();

    $bobotSnapshot = [];
    foreach ($kriteria as $k) {
        $bobotSnapshot[$k['kode']] = ['jenis' => $k['jenis'], 'bobot' => (float) $k['bobot']];
    }

    $stmtRiwayat = $pdo->prepare('
        INSERT INTO riwayat_perhitungan (periode, jumlah_data, bobot_json, dibuat_oleh, keterangan)
        VALUES (:periode, :jumlah, :bobot, :user, :ket)
    ');
    $stmtRiwayat->execute([
        'periode' => $periode,
        'jumlah' => count($hasilMoora),
        'bobot'  => json_encode($bobotSnapshot),
        'user'   => $_SESSION['user_id'],
        'ket'    => $keterangan,
    ]);
    $riwayatId = $pdo->lastInsertId();

    $stmtHasil = $pdo->prepare('
        INSERT INTO hasil_moora
        (id_riwayat, id_balita, id_penilaian, matriks_asli, matriks_subkriteria, matriks_normalisasi, matriks_terbobot,
         sum_benefit, sum_cost, yi_value, ranking, status_kelayakan)
        VALUES (:riwayat_id, :balita_id, :penilaian_id, :asli, :sub, :normalisasi, :terbobot,
                :sum_benefit, :sum_cost, :yi, :ranking, :status)
    ');

    foreach ($hasilMoora as $balitaId => $r) {
        $status = $r['ranking'] <= $kuota ? 'Layak' : 'Tidak Layak';
        $stmtHasil->execute([
            'riwayat_id'   => $riwayatId,
            'balita_id'    => $balitaId,
            'penilaian_id' => $penilaianIdMap[$balitaId],
            'asli'         => json_encode($matriksAsli[$balitaId]),
            'sub'          => json_encode($matriksLabel[$balitaId]),
            'normalisasi'  => json_encode($r['normalisasi']),
            'terbobot'     => json_encode($r['terbobot']),
            'sum_benefit'  => $r['sum_benefit'],
            'sum_cost'     => $r['sum_cost'],
            'yi'           => $r['yi'],
            'ranking'      => $r['ranking'],
            'status'       => $status,
        ]);
    }

    $pdo->commit();
    header('Location: ../hasil/index.php?riwayat_id=' . $riwayatId . '&success=1');

} catch (PDOException $e) {
    $pdo->rollBack();
    header('Location: index.php?periode=' . urlencode($periode) . '&error=' . urlencode('Terjadi kesalahan saat menyimpan hasil perhitungan.'));
}
exit;
