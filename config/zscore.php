<?php
/**
 * config/zscore.php
 * -----------------------------------------------------------
 * Fungsi:
 * 1. Menghitung umur balita secara presisi berdasarkan tanggal.
 * 2. Mengambil/interpolasi parameter LMS WHO.
 * 3. Menghitung Z-Score WHO untuk:
 *    - BB/U
 *    - TB/U
 *    - BB/TB
 *    - IMT/U
 * 4. Menggunakan extended Z-score untuk nilai di luar ±3 SD.
 * 5. Menghitung IMT.
 * 6. Menentukan status gizi.
 * 7. Menentukan subkriteria untuk proses MOORA.
 * -----------------------------------------------------------
 */


/* =========================================================
 * 1. HITUNG UMUR
 * ========================================================= */

/**
 * Menghitung umur dalam hari.
 *
 * Digunakan sebagai dasar perhitungan umur WHO.
 */
function hitungUsiaHari(
    string $tanggalLahir,
    ?string $tanggalAcuan = null
): int {

    $lahir = new DateTime($tanggalLahir);

    $acuan = $tanggalAcuan
        ? new DateTime($tanggalAcuan)
        : new DateTime('today');

    if ($acuan < $lahir) {
        throw new InvalidArgumentException(
            'Tanggal pengukuran tidak boleh lebih awal dari tanggal lahir.'
        );
    }

    return (int) $lahir->diff($acuan)->days;
}


/**
 * Menghitung umur dalam bulan secara presisi.
 *
 * WHO menggunakan konversi:
 *
 * umur bulan = umur hari / 30.4375
 *
 * Contoh:
 * 429 hari / 30.4375 = 14.095 bulan
 */
function hitungUsiaBulan(
    string $tanggalLahir,
    ?string $tanggalAcuan = null
): float {

    $usiaHari = hitungUsiaHari(
        $tanggalLahir,
        $tanggalAcuan
    );

    return $usiaHari / 30.4375;
}


/**
 * Status kepesertaan posyandu berdasarkan usia.
 *
 * Balita dianggap Lulus jika usia sudah lebih dari 60 bulan.
 */
function statusPosyandu(
    string $tanggalLahir,
    ?string $tanggalAcuan = null
): string {

    $usiaBulan = hitungUsiaBulan(
        $tanggalLahir,
        $tanggalAcuan
    );

    return $usiaBulan > 60
        ? 'Lulus'
        : 'Aktif';
}


/* =========================================================
 * 2. MENGAMBIL DAN INTERPOLASI LMS
 * ========================================================= */

/**
 * Mengambil parameter LMS WHO berdasarkan:
 *
 * - lms_bbu  -> usia_bulan
 * - lms_tbu  -> usia_bulan
 * - lms_imtu -> usia_bulan
 * - lms_bbtb -> tinggi_badan (+ tipe_ukur, lihat catatan di bawah)
 *
 * CATATAN PENTING soal BB/TB:
 * WHO sebenarnya punya DUA tabel referensi berbeda untuk BB/TB,
 * bukan satu:
 *   - Weight-for-Length (0-2 tahun, diukur BERBARING/panjang badan),
 *     rentang 45,0-110,0 cm.
 *   - Weight-for-Height (2-5 tahun, diukur BERDIRI/tinggi badan),
 *     rentang 65,0-120,0 cm.
 * Pada tinggi yang SAMA, nilai L/M/S kedua tabel ini BERBEDA
 * (karena panjang badan berbaring secara sistematis sedikit lebih
 * panjang daripada tinggi badan berdiri pada anak yang sama).
 * Kalau keduanya digabung jadi satu tabel tanpa dibedakan, balita
 * di rentang tumpang-tindih (65-110 cm) berisiko terhitung memakai
 * parameter tabel yang salah, menghasilkan Z-score yang meleset
 * dari WHO Anthro walau rumusnya sudah benar. Karena itu kolom
 * `tipe_ukur` pada tabel lms_bbtb WAJIB diisi ('panjang' untuk
 * Weight-for-Length, 'tinggi' untuk Weight-for-Height), dan dipilih
 * otomatis berdasarkan usia balita oleh hitungSemuaZScore() di
 * bawah — bukan oleh pemanggil getLMS() secara manual.
 *
 * Tabel referensi lms_bbtb pada database ini tersedia pada SETIAP
 * kelipatan 0,1 cm, sama seperti tabel resmi WHO Anthro. Karena
 * resolusinya sudah sehalus itu, WHO Anthro TIDAK melakukan
 * interpolasi untuk BB/TB — tinggi/panjang badan cukup dibulatkan
 * ke 0,1 cm terdekat lalu dicari baris LMS yang PERSIS cocok.
 *
 * Untuk BB/U, TB/U, dan IMT/U tetap dilakukan interpolasi linear
 * antar bulan (karena tabel referensi memang hanya tersedia per
 * bulan bulat, sedangkan usia balita berupa pecahan bulan).
 *
 * @param string|null $tipeUkur  Hanya dipakai jika $tabel = 'lms_bbtb':
 *                                'panjang' (Weight-for-Length, 0-2th)
 *                                atau 'tinggi' (Weight-for-Height, 2-5th).
 */
function getLMS(
    PDO $pdo,
    string $tabel,
    string $jenisKelamin,
    float $x,
    ?string $tipeUkur = null
): ?array {

    /*
     * =====================================================
     * BB/TB
     * =====================================================
     *
     * Tabel WHO Weight-for-Length/Height pada database ini
     * sudah tersedia di SETIAP kelipatan 0,1 cm — sama persis
     * dengan resolusi tabel resmi WHO Anthro. Karena itu tidak
     * perlu (dan tidak boleh) diinterpolasi; cukup dicari baris
     * yang nilainya PERSIS sama dengan tinggi/panjang badan
     * (dibulatkan ke 0,1 cm terdekat), DIPERSEMPIT dulu ke
     * tipe_ukur yang sesuai usia balita (lihat catatan di atas).
     */
    if ($tabel === 'lms_bbtb') {

        if ($tipeUkur === null) {
            throw new InvalidArgumentException(
                'getLMS() untuk lms_bbtb wajib menyertakan $tipeUkur ' .
                '("panjang" atau "tinggi") — panggil lewat hitungSemuaZScore(), ' .
                'jangan panggil getLMS() langsung untuk BB/TB.'
            );
        }

        /*
         * Bulatkan ke 0,1 cm terdekat — presisi standar
         * pengukuran panjang/tinggi badan balita, dan sama
         * dengan presisi tabel referensi WHO.
         */
        $xBulat = round($x, 1);

        $sql = "
            SELECT
                L,
                M,
                S,
                tinggi_badan AS x_ref
            FROM lms_bbtb
            WHERE jenis_kelamin = :jk
              AND tipe_ukur = :tipe
              AND ABS(tinggi_badan - :x) < 0.001
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            'jk'   => $jenisKelamin,
            'tipe' => $tipeUkur,
            'x'    => $xBulat
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return [
                'L' => (float) $row['L'],
                'M' => (float) $row['M'],
                'S' => (float) $row['S'],
                'x_ref' => (float) $row['x_ref'],
            ];
        }

        /*
         * Fallback: kalau tinggi/panjang badan berada di luar
         * rentang tabel referensi (mis. di luar 45,0–110,0 cm
         * untuk 'panjang', atau 65,0–120,0 cm untuk 'tinggi'),
         * pakai baris dengan nilai tinggi_badan paling dekat
         * PADA TIPE UKUR YANG SAMA, supaya perhitungan tetap
         * bisa jalan, bukan mengembalikan null begitu saja.
         */
        $sqlTerdekat = "
            SELECT
                L,
                M,
                S,
                tinggi_badan AS x_ref
            FROM lms_bbtb
            WHERE jenis_kelamin = :jk
              AND tipe_ukur = :tipe
            ORDER BY ABS(tinggi_badan - :x) ASC
            LIMIT 1
        ";

        $stmt = $pdo->prepare($sqlTerdekat);

        $stmt->execute([
            'jk'   => $jenisKelamin,
            'tipe' => $tipeUkur,
            'x'    => $xBulat
        ]);

        $terdekat = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$terdekat) {
            return null;
        }

        return [
            'L' => (float) $terdekat['L'],
            'M' => (float) $terdekat['M'],
            'S' => (float) $terdekat['S'],
            'x_ref' => (float) $terdekat['x_ref'],
        ];
    }


    /*
     * =====================================================
     * BB/U, TB/U, dan IMT/U
     * =====================================================
     *
     * Tabel WHO expanded (wfa/lhfa/bfa) tersedia di SETIAP hari
     * usia (0-1856 hari) — sama seperti BB/TB, tidak perlu (dan
     * tidak boleh) diinterpolasi lagi antar bulan. Cukup exact
     * match ke usia_hari yang PERSIS sama dengan usia anak saat
     * pengukuran (dibulatkan ke hari terdekat).
     */
    $xBulat = (int) round($x);

    $sql = "
        SELECT
            L,
            M,
            S,
            usia_hari AS x_ref
        FROM $tabel
        WHERE jenis_kelamin = :jk
          AND usia_hari = :x
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        'jk' => $jenisKelamin,
        'x'  => $xBulat
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        return [
            'L' => (float) $row['L'],
            'M' => (float) $row['M'],
            'S' => (float) $row['S'],
            'x_ref' => (float) $row['x_ref'],
        ];
    }

    /*
     * Fallback: kalau usia anak berada di luar rentang tabel
     * referensi (mis. di luar 0-1856 hari / lebih dari 5 tahun),
     * pakai baris dengan usia_hari paling dekat, supaya
     * perhitungan tetap bisa jalan, bukan mengembalikan null.
     */
    $sqlTerdekat = "
        SELECT
            L,
            M,
            S,
            usia_hari AS x_ref
        FROM $tabel
        WHERE jenis_kelamin = :jk
        ORDER BY ABS(usia_hari - :x) ASC
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sqlTerdekat);

    $stmt->execute([
        'jk' => $jenisKelamin,
        'x'  => $xBulat
    ]);

    $terdekat = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$terdekat) {
        return null;
    }

    return [
        'L' => (float) $terdekat['L'],
        'M' => (float) $terdekat['M'],
        'S' => (float) $terdekat['S'],
        'x_ref' => (float) $terdekat['x_ref'],
    ];
}


/* =========================================================
 * 3. FUNGSI NILAI X PADA Z TERTENTU
 * ========================================================= */

/**
 * Menghitung nilai X berdasarkan parameter LMS dan Z.
 *
 * Digunakan untuk extended Z-score WHO.
 *
 * Jika L != 0:
 *
 * X = M * (1 + L*S*Z)^(1/L)
 *
 * Jika L = 0:
 *
 * X = M * exp(S*Z)
 */
function nilaiXUntukZ(
    float $L,
    float $M,
    float $S,
    float $Z
): float {

    if (abs($L) > 0.0000001) {

        $dasar = 1 + ($L * $S * $Z);

        /*
         * Hindari pow dengan bilangan negatif
         * akibat parameter LMS yang tidak valid.
         */
        if ($dasar <= 0) {
            return 0.0;
        }

        return $M * pow(
            $dasar,
            1 / $L
        );
    }

    return $M * exp($S * $Z);
}


/* =========================================================
 * 4. HITUNG Z-SCORE WHO
 * ========================================================= */

/**
 * Menghitung Z-score berdasarkan metode LMS WHO.
 *
 * Rumus dasar:
 *
 * Z = ((X/M)^L - 1) / (L*S)
 *
 * jika L != 0
 *
 * Z = ln(X/M) / S
 *
 * jika L = 0
 *
 * Kemudian diterapkan extended Z-score untuk:
 *
 * Z < -3
 * Z > +3
 *
 * CATATAN PENTING (sesuai WHO technical report / de Onis 2007):
 * Restriksi/extended Z-score di atas HANYA berlaku untuk indikator
 * yang distribusinya skewed, yaitu BB/U, BB/TB, dan IMT/U. Untuk
 * TB/U (length/height-for-age) WHO Anthro TIDAK menerapkan restriksi
 * ini sama sekali karena distribusinya sudah mendekati normal —
 * nilai Z mentah dipakai apa adanya walau > +3 atau < -3.
 * Karena itu parameter $terapkanRestriksi WAJIB diisi false khusus
 * saat menghitung TB/U (lihat pemanggilan di hitungSemuaZScore()).
 */
function hitungZScore(
    float $x,
    float $L,
    float $M,
    float $S,
    bool $terapkanRestriksi = true
): float {

    /*
     * Validasi dasar.
     */
    if ($x <= 0 || $M <= 0 || $S <= 0) {
        return 0.0;
    }


    /*
     * -----------------------------------------------------
     * HITUNG Z-SCORE DASAR
     * -----------------------------------------------------
     */
    if (abs($L) > 0.0000001) {

        $z =
            (
                pow(
                    $x / $M,
                    $L
                )
                - 1
            )
            /
            ($L * $S);

    } else {

        $z =
            log($x / $M)
            /
            $S;
    }


    /*
     * -----------------------------------------------------
     * EXTENDED Z-SCORE WHO
     * -----------------------------------------------------
     *
     * Jika Z berada di bawah -3 SD,
     * gunakan perluasan berdasarkan jarak antara
     * -2 SD dan -3 SD.
     */
    if ($terapkanRestriksi && $z < -3) {

        $xMinus3 = nilaiXUntukZ(
            $L,
            $M,
            $S,
            -3
        );

        $xMinus2 = nilaiXUntukZ(
            $L,
            $M,
            $S,
            -2
        );


        /*
         * Selisih nilai antara -2 SD dan -3 SD.
         */
        $selisihSD =
            $xMinus2 -
            $xMinus3;


        if ($selisihSD > 0) {

            $z =
                -3
                -
                (
                    ($xMinus3 - $x)
                    /
                    $selisihSD
                );
        }
    }


    /*
     * -----------------------------------------------------
     * EXTENDED Z-SCORE UNTUK > +3 SD
     * -----------------------------------------------------
     */
    elseif ($terapkanRestriksi && $z > 3) {

        $xPlus3 = nilaiXUntukZ(
            $L,
            $M,
            $S,
            3
        );

        $xPlus2 = nilaiXUntukZ(
            $L,
            $M,
            $S,
            2
        );


        /*
         * Selisih nilai antara +2 SD dan +3 SD.
         */
        $selisihSD =
            $xPlus3 -
            $xPlus2;


        if ($selisihSD > 0) {

            $z =
                3
                +
                (
                    ($x - $xPlus3)
                    /
                    $selisihSD
                );
        }
    }


    /*
     * Batasi hasil agar tidak menghasilkan
     * nilai tidak terhingga.
     */
    if (!is_finite($z)) {
        $z = 0.0;
    }


    return round(
        $z,
        3
    );
}


/* =========================================================
 * 5. KLASIFIKASI BB/U
 * ========================================================= */

function klasifikasiBBU(float $z): string
{
    if ($z < -3) {
        return 'Gizi Buruk';
    }

    if ($z < -2) {
        return 'Gizi Kurang';
    }

    if ($z <= 1) {
        return 'Gizi Baik';
    }

    return 'Berisiko Gizi Lebih';
}


/* =========================================================
 * 6. KLASIFIKASI TB/U
 * ========================================================= */

function klasifikasiTBU(float $z): string
{
    if ($z < -3) {
        return 'Sangat Pendek';
    }

    if ($z < -2) {
        return 'Pendek';
    }

    if ($z <= 3) {
        return 'Normal';
    }

    return 'Tinggi';
}


/* =========================================================
 * 7. KLASIFIKASI BB/TB
 * ========================================================= */

function klasifikasiBBTB(float $z): string
{
    if ($z < -3) {
        return 'Sangat Kurus';
    }

    if ($z < -2) {
        return 'Kurus';
    }

    if ($z <= 1) {
        return 'Normal';
    }

    if ($z <= 2) {
        return 'Risiko Gizi Lebih';
    }

    if ($z <= 3) {
        return 'Gizi Lebih';
    }

    return 'Obesitas';
}


/* =========================================================
 * 8. KLASIFIKASI IMT/U
 * ========================================================= */

function klasifikasiIMTU(float $z): string
{
    if ($z < -3) {
        return 'Gizi Buruk';
    }

    if ($z < -2) {
        return 'Gizi Kurang';
    }

    if ($z <= 1) {
        return 'Gizi Baik';
    }

    if ($z <= 2) {
        return 'Berisiko Gizi Lebih';
    }

    if ($z <= 3) {
        return 'Gizi Lebih';
    }

    return 'Obesitas';
}


/* =========================================================
 * 9. HITUNG IMT
 * ========================================================= */

/**
 * IMT = berat badan / tinggi badan(m)^2
 *
 * TIDAK dibulatkan di sini — pembulatan ditunda ke titik
 * tampilan saja (lihat pemanggilan di hitungSemuaZScore()),
 * supaya nilai presisi penuh yang dipakai untuk hitung Z-score
 * IMT/U, bukan versi yang sudah dibulatkan 2 desimal. Membulatkan
 * dulu sebelum hitung Z-score menambah selisih kecil (~0,01-0,03)
 * dibanding WHO Anthro yang memakai IMT presisi penuh.
 */
function hitungIMT(
    float $beratBadan,
    float $tinggiBadanCm
): float {

    $tinggiM =
        $tinggiBadanCm / 100;


    if ($tinggiM <= 0) {
        return 0.0;
    }


    return
        $beratBadan /
        ($tinggiM * $tinggiM);
}


/* =========================================================
 * 10. HITUNG SEMUA Z-SCORE
 * ========================================================= */

/**
 * Menghitung:
 *
 * BB/U
 * TB/U
 * BB/TB
 * IMT/U
 *
 * untuk satu kali pengukuran.
 *
 * Catatan:
 * Untuk anak < 24 bulan, nilai tinggi_badan yang diberikan
 * diasumsikan merupakan panjang badan/recumbent length,
 * sesuai dengan pengukuran WHO Anthro pada screenshot.
 *
 * KOREKSI POSISI UKUR (WHO Anthro):
 * WHO membedakan dua hal yang independen satu sama lain:
 *  - USIA menentukan tabel referensi mana yang dipakai
 *    (Weight-for-Length utk <24 bln, Weight-for-Height utk >=24 bln).
 *  - POSISI PENGUKURAN AKTUAL (berbaring/telentang vs berdiri) adalah
 *    posisi yang BENAR-BENAR dipakai petugas saat mengukur di lapangan,
 *    yang kadang berbeda dari posisi "seharusnya" sesuai usia (mis.
 *    balita >24 bulan belum bisa berdiri tegak sehingga tetap diukur
 *    berbaring, atau sebaliknya).
 * Kalau posisi aktual BEDA dari posisi yang seharusnya sesuai usia,
 * WHO Anthro WAJIB melakukan konversi 0,7 cm sebelum look-up ke tabel
 * LMS (diukur berdiri padahal seharusnya berbaring -> tambah 0,7 cm;
 * diukur berbaring padahal seharusnya berdiri -> kurangi 0,7 cm),
 * karena panjang badan berbaring secara sistematis sedikit lebih
 * panjang daripada tinggi badan berdiri pada anak yang sama.
 * Kalau posisi ukur tidak diketahui/tidak dicatat ($posisiUkur = null),
 * fungsi ini tetap berjalan seperti sebelumnya (asumsi posisi selalu
 * sesuai usia, tanpa koreksi) — supaya backward compatible dengan
 * pemanggil lama yang belum mengirim parameter ini.
 *
 * @param string|null $posisiUkur 'berbaring' atau 'berdiri' (posisi
 *                                  aktual saat pengukuran dilakukan),
 *                                  atau null jika tidak diketahui.
 */
function hitungSemuaZScore(
    PDO $pdo,
    string $jenisKelamin,
    string $tanggalLahir,
    string $tanggalPengukuran,
    float $beratBadan,
    float $tinggiBadan,
    ?string $posisiUkur = null
): array {


    /*
     * -----------------------------------------------------
     * UMUR
     * -----------------------------------------------------
     */
    $usiaHari =
        hitungUsiaHari(
            $tanggalLahir,
            $tanggalPengukuran
        );


    $usiaBulan =
        hitungUsiaBulan(
            $tanggalLahir,
            $tanggalPengukuran
        );


    /*
     * -----------------------------------------------------
     * IMT
     * -----------------------------------------------------
     */
    $imt =
        hitungIMT(
            $beratBadan,
            $tinggiBadan
        );


    /*
     * -----------------------------------------------------
     * AMBIL LMS
     * -----------------------------------------------------
     *
     * BB/U  -> umur hari (exact match, tabel WHO expanded 0-1856 hari)
     * TB/U  -> umur hari (exact match, tabel WHO expanded 0-1856 hari)
     * BB/TB -> tinggi/panjang badan
     * IMT/U -> umur hari (exact match, tabel WHO expanded 0-1856 hari)
     */
    $lmsBBU =
        getLMS(
            $pdo,
            'lms_bbu',
            $jenisKelamin,
            $usiaHari
        );


    $lmsTBU =
        getLMS(
            $pdo,
            'lms_tbu',
            $jenisKelamin,
            $usiaHari
        );


    /*
     * Pemilihan tabel BB/TB berdasarkan usia — sesuai standar WHO:
     * - < 24 bulan  -> Weight-for-Length (diukur berbaring/panjang badan)
     * - >= 24 bulan -> Weight-for-Height (diukur berdiri/tinggi badan)
     * Batas 24 bulan dihitung dalam hari sebagai 24 * 30,4375 = 730,5 hari
     * (bukan dibulatkan ke 730), supaya konsisten dengan cara WHO Anthro
     * mengonversi bulan <-> hari untuk menentukan cutoff usia.
     */
    $tipeUkurBBTB = $usiaHari < (24 * 30.4375) ? 'panjang' : 'tinggi';

    /*
     * -----------------------------------------------------
     * KOREKSI POSISI UKUR (lihat catatan di docblock atas)
     * -----------------------------------------------------
     * Cuma dipakai untuk MENENTUKAN BARIS LMS mana yang
     * dicari (look-up ke tabel lms_bbtb). Nilai tinggiBadan
     * ASLI tetap dipakai untuk TB/U, IMT, dan ditampilkan
     * apa adanya ke pengguna — tidak diubah.
     */
    $tinggiUntukLMS = $tinggiBadan;

    if ($posisiUkur !== null) {

        $posisiSeharusnya = $tipeUkurBBTB === 'panjang' ? 'berbaring' : 'berdiri';

        if ($posisiUkur === 'berdiri' && $posisiSeharusnya === 'berbaring') {
            // Diukur berdiri padahal seharusnya berbaring (usia < 24 bln)
            // -> konversi ke setara panjang badan berbaring: tambah 0,7 cm.
            $tinggiUntukLMS = $tinggiBadan + 0.7;
        } elseif ($posisiUkur === 'berbaring' && $posisiSeharusnya === 'berdiri') {
            // Diukur berbaring padahal seharusnya berdiri (usia >= 24 bln)
            // -> konversi ke setara tinggi badan berdiri: kurangi 0,7 cm.
            $tinggiUntukLMS = $tinggiBadan - 0.7;
        }
    }

    $lmsBBTB =
        getLMS(
            $pdo,
            'lms_bbtb',
            $jenisKelamin,
            $tinggiUntukLMS,
            $tipeUkurBBTB
        );



    $lmsIMTU =
        getLMS(
            $pdo,
            'lms_imtu',
            $jenisKelamin,
            $usiaHari
        );


    /*
     * -----------------------------------------------------
     * VALIDASI LMS
     * -----------------------------------------------------
     */
    if (
        !$lmsBBU ||
        !$lmsTBU ||
        !$lmsBBTB ||
        !$lmsIMTU
    ) {

        throw new RuntimeException(
            'Data referensi LMS WHO belum lengkap untuk ' .
            'jenis kelamin, usia, atau tinggi badan balita ini ' .
            '(BB/TB butuh tabel "' .
            ($tipeUkurBBTB === 'panjang' ? 'Weight-for-Length 0-2 tahun' : 'Weight-for-Height 2-5 tahun') .
            '"). Silakan import tabel LMS WHO terlebih dahulu.'
        );
    }


    /*
     * -----------------------------------------------------
     * HITUNG Z-SCORE
     * -----------------------------------------------------
     */
    $zBBU =
        hitungZScore(
            $beratBadan,
            (float) $lmsBBU['L'],
            (float) $lmsBBU['M'],
            (float) $lmsBBU['S']
        );


    $zTBU =
        hitungZScore(
            $tinggiBadan,
            (float) $lmsTBU['L'],
            (float) $lmsTBU['M'],
            (float) $lmsTBU['S'],
            false // TB/U tidak memakai restriksi ±3 SD (WHO: distribusi sudah normal, bukan skewed)
        );


    $zBBTB =
        hitungZScore(
            $beratBadan,
            (float) $lmsBBTB['L'],
            (float) $lmsBBTB['M'],
            (float) $lmsBBTB['S']
        );


    $zIMTU =
        hitungZScore(
            $imt,
            (float) $lmsIMTU['L'],
            (float) $lmsIMTU['M'],
            (float) $lmsIMTU['S']
        );


    /*
     * -----------------------------------------------------
     * RETURN HASIL
     * -----------------------------------------------------
     */
    return [

        /*
         * Informasi umur.
         */
        'usia_hari'   => $usiaHari,

        'usia_bulan'  => round(
            $usiaBulan,
            3
        ),


        /*
         * Data pengukuran.
         */
        'berat_badan' =>
            $beratBadan,

        'tinggi_badan' =>
            $tinggiBadan,

        'imt' =>
            round($imt, 2),


        /*
         * Z-score.
         */
        'bbu_zscore' =>
            $zBBU,

        'tbu_zscore' =>
            $zTBU,

        'bbtb_zscore' =>
            $zBBTB,

        'imtu_zscore' =>
            $zIMTU,


        /*
         * Status gizi.
         */
        'status_bbu' =>
            klasifikasiBBU(
                $zBBU
            ),

        'status_tbu' =>
            klasifikasiTBU(
                $zTBU
            ),

        'status_bbtb' =>
            klasifikasiBBTB(
                $zBBTB
            ),

        'status_imtu' =>
            klasifikasiIMTU(
                $zIMTU
            ),


        /*
         * LMS yang digunakan.
         *
         * Tetap dikembalikan agar dapat digunakan
         * untuk debugging atau penyimpanan hasil.
         */
        'lms_bbu' =>
            $lmsBBU,

        'lms_tbu' =>
            $lmsTBU,

        'lms_bbtb' =>
            $lmsBBTB,

        'lms_imtu' =>
            $lmsIMTU,

        /*
         * Tipe tabel BB/TB yang dipakai (untuk transparansi/debug,
         * mis. ditampilkan di halaman detail hasil MOORA).
         */
        'tipe_ukur_bbtb' =>
            $tipeUkurBBTB,

        /*
         * Info posisi ukur & tinggi yang benar-benar dipakai untuk
         * look-up LMS BB/TB (setelah koreksi 0,7 cm jika ada) —
         * untuk transparansi/debug juga.
         */
        'posisi_ukur' =>
            $posisiUkur,

        'tinggi_untuk_lms_bbtb' =>
            $tinggiUntukLMS
    ];
}


/* =========================================================
 * 11. CARI SUBKRITERIA
 * ========================================================= */

/**
 * Mencari subkriteria berdasarkan nilai mentah.
 *
 * Nilai bobot subkriteria digunakan sebagai Xij
 * pada matriks keputusan MOORA.
 */
function cariSubkriteria(
    PDO $pdo,
    int $kriteriaId,
    float $nilai
): ?array {


    /*
     * Ambil seluruh subkriteria.
     *
     * id_subkriteria digunakan karena sesuai
     * dengan struktur tabel subkriteria.
     */
    $stmt = $pdo->prepare(
        '
        SELECT *
        FROM subkriteria
        WHERE id_kriteria = :kid
        ORDER BY urutan ASC, id_subkriteria ASC
        '
    );


    $stmt->execute([
        'kid' => $kriteriaId
    ]);


    $rows =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );


    if (!$rows) {
        return null;
    }


    /*
     * -----------------------------------------------------
     * CARI RENTANG YANG SESUAI
     * -----------------------------------------------------
     */
    foreach ($rows as $r) {

        $min =
            $r['batas_bawah'] === null
            ? null
            : (float) $r['batas_bawah'];


        $max =
            $r['batas_atas'] === null
            ? null
            : (float) $r['batas_atas'];


        /*
         * Batas bawah.
         */
        $okMin =
            ($min === null)
            ||
            (
                (int) $r['batas_bawah_inklusif'] === 1
                ? $nilai >= $min
                : $nilai > $min
            );


        /*
         * Batas atas.
         */
        $okMax =
            ($max === null)
            ||
            (
                (int) $r['batas_atas_inklusif'] === 1
                ? $nilai <= $max
                : $nilai < $max
            );


        /*
         * Jika nilai berada dalam rentang.
         */
        if ($okMin && $okMax) {

            $r['celah'] = false;

            return $r;
        }
    }


    /*
     * -----------------------------------------------------
     * JIKA NILAI BERADA DI LUAR RENTANG
     * -----------------------------------------------------
     *
     * Cari subkriteria dengan batas terdekat.
     */
    $terdekat = null;

    $jarakTerdekat = null;


    foreach ($rows as $r) {

        $min =
            $r['batas_bawah'] === null
            ? null
            : (float) $r['batas_bawah'];


        $max =
            $r['batas_atas'] === null
            ? null
            : (float) $r['batas_atas'];


        $jarak = 0.0;


        /*
         * Nilai di bawah batas minimum.
         */
        if (
            $min !== null &&
            $nilai < $min
        ) {

            $jarak =
                $min -
                $nilai;
        }


        /*
         * Nilai di atas batas maksimum.
         */
        if (
            $max !== null &&
            $nilai > $max
        ) {

            $jarak =
                min(
                    $jarak ?: PHP_FLOAT_MAX,
                    $nilai - $max
                );
        }


        /*
         * Simpan yang paling dekat.
         */
        if (
            $jarakTerdekat === null ||
            $jarak < $jarakTerdekat
        ) {

            $jarakTerdekat =
                $jarak;

            $terdekat =
                $r;
        }
    }


    if ($terdekat) {

        $terdekat['celah'] =
            true;

        return $terdekat;
    }


    return null;
}