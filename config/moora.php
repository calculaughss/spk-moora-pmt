<?php
/**
 * config/moora.php
 * -----------------------------------------------------------
 * Implementasi rumus MOORA:
 *  1. Normalisasi : xij* = xij / sqrt(sum(xij^2)) per kolom kriteria
 *  2. Terbobot    : vij = xij* * bobot_j
 *  3. Yi          = sum(vij benefit) - sum(vij cost)
 *  4. Ranking     : urutkan Yi terbesar -> terkecil
 * -----------------------------------------------------------
 */

/**
 * @param array $matriks   [ balita_id => ['C1'=>val, 'C2'=>val, ... 'C7'=>val] ]
 * @param array $kriteria  daftar baris tabel kriteria (kode, jenis, bobot)
 * @return array [
 *   balita_id => [
 *      'asli' => [...], 'normalisasi' => [...], 'terbobot' => [...],
 *      'sum_benefit' => float, 'sum_cost' => float, 'yi' => float
 *   ]
 * ]
 */
function hitungMOORA(array $matriks, array $kriteria): array
{
    $kodeList = array_column($kriteria, 'kode');
    $jenisMap = array_combine(array_column($kriteria, 'kode'), array_column($kriteria, 'jenis'));
    $bobotMap = array_combine(array_column($kriteria, 'kode'), array_map('floatval', array_column($kriteria, 'bobot')));

    // 1. Hitung penyebut (akar jumlah kuadrat) per kolom kriteria
    $penyebut = [];
    foreach ($kodeList as $kode) {
        $sumSq = 0.0;
        foreach ($matriks as $row) {
            $sumSq += pow((float) $row[$kode], 2);
        }
        $penyebut[$kode] = sqrt($sumSq) ?: 1.0; // hindari pembagian dengan 0
    }

    $hasil = [];
    foreach ($matriks as $balitaId => $row) {
        $normalisasi = [];
        $terbobot = [];
        $sumBenefit = 0.0;
        $sumCost = 0.0;

        foreach ($kodeList as $kode) {
            $xij = (float) $row[$kode];
            $xNorm = $xij / $penyebut[$kode];
            $vij = $xNorm * $bobotMap[$kode];

            $normalisasi[$kode] = round($xNorm, 5);
            $terbobot[$kode] = round($vij, 5);

            if ($jenisMap[$kode] === 'benefit') {
                $sumBenefit += $vij;
            } else {
                $sumCost += $vij;
            }
        }

        $yi = $sumBenefit - $sumCost;

        $hasil[$balitaId] = [
            'asli'        => $row,
            'normalisasi' => $normalisasi,
            'terbobot'    => $terbobot,
            'sum_benefit' => round($sumBenefit, 5),
            'sum_cost'    => round($sumCost, 5),
            'yi'          => round($yi, 5),
        ];
    }

    // Ranking berdasar Yi terbesar -> terkecil
    uasort($hasil, fn($a, $b) => $b['yi'] <=> $a['yi']);
    $rank = 1;
    foreach ($hasil as $id => &$row) {
        $row['ranking'] = $rank++;
    }
    unset($row);

    return $hasil;
}
