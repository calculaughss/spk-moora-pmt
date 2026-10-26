<?php
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/zscore.php';
requireKader();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['balita_id'])) {
    header('Location: index.php');
    exit;
}

$balitaId = $_POST['balita_id'];
$tanggalPengukuran = $_POST['tanggal_pengukuran'] ?? '';
$beratBadan = $_POST['berat_badan'] ?? '';
$tinggiBadan = $_POST['tinggi_badan'] ?? '';

if ($tanggalPengukuran === '' || !is_numeric($beratBadan) || !is_numeric($tinggiBadan)) {
    header('Location: tambah.php?balita_id=' . $balitaId . '&error=' . urlencode('Data pengukuran tidak lengkap/valid.'));
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare('SELECT * FROM balita WHERE id_balita = ? AND id_posyandu = ?');
$stmt->execute([$balitaId, currentPosyanduId()]);
$balita = $stmt->fetch();

if (!$balita) {
    header('Location: index.php?error=' . urlencode('Balita tidak ditemukan.'));
    exit;
}

if (hitungUsiaBulan($balita['tanggal_lahir'], $tanggalPengukuran) > 60) {
    header('Location: tambah.php?balita_id=' . $balita['id_balita'] . '&error=' . urlencode(
        'Usia balita pada tanggal pengukuran ini sudah lebih dari 5 tahun (60 bulan) — balita sudah lulus dari Posyandu.'
    ));
    exit;
}

try {
    $z = hitungSemuaZScore(
        $pdo, $balita['jenis_kelamin'], $balita['tanggal_lahir'],
        $tanggalPengukuran, (float) $beratBadan, (float) $tinggiBadan
    );

    $sql = 'INSERT INTO penilaian
            (id_balita, tanggal_pengukuran, berat_badan, tinggi_badan, usia_bulan,
             bbu_zscore, tbu_zscore, bbtb_zscore, imt, imtu_zscore,
             status_bbu, status_tbu, status_bbtb, status_imtu,
             pendapatan_ortu, jumlah_tanggungan)
            VALUES (:balita_id, :tgl_ukur, :bb, :tb, :usia, :zbbu, :ztbu, :zbbtb, :imt, :zimtu,
                    :sbbu, :stbu, :sbbtb, :simtu, :pendapatan, :tanggungan)';
    $insert = $pdo->prepare($sql);
    $insert->execute([
        'balita_id'  => $balita['id_balita'],
        'tgl_ukur'   => $tanggalPengukuran,
        'bb'         => $z['berat_badan'],
        'tb'         => $z['tinggi_badan'],
        'usia'       => $z['usia_bulan'],
        'zbbu'       => $z['bbu_zscore'],
        'ztbu'       => $z['tbu_zscore'],
        'zbbtb'      => $z['bbtb_zscore'],
        'imt'        => $z['imt'],
        'zimtu'      => $z['imtu_zscore'],
        'sbbu'       => $z['status_bbu'],
        'stbu'       => $z['status_tbu'],
        'sbbtb'      => $z['status_bbtb'],
        'simtu'      => $z['status_imtu'],
        'pendapatan' => $balita['pendapatan_ortu'],
        'tanggungan' => $balita['jumlah_tanggungan'],
    ]);

    header('Location: index.php?success=1');
} catch (RuntimeException $e) {
    header('Location: tambah.php?balita_id=' . $balita['id_balita'] . '&error=' . urlencode($e->getMessage()));
} catch (PDOException $e) {
    $msg = $e->getCode() === '23000'
        ? 'Balita ini sudah punya pengukuran pada tanggal tersebut. Pilih tanggal lain atau edit data yang ada.'
        : 'Terjadi kesalahan database.';
    header('Location: tambah.php?balita_id=' . $balita['id_balita'] . '&error=' . urlencode($msg));
}
exit;
