<?php
// ============================================================
// FILE: includes/kmeans.php
// Implementasi Algoritma K-Means Clustering
// Untuk Deteksi Anomali Kehadiran Siswa
// [UPDATE] Normalisasi Min-Max, Silhouette Score, Elbow Method
// ============================================================

class KMeans {
    private $k;
    private $maxIter;
    private $centroids;
    private $clusters;
    private $data;

    public function __construct($k = 3, $maxIter = 100) {
        $this->k = $k;
        $this->maxIter = $maxIter;
    }

    /**
     * Menjalankan algoritma K-Means
     * @param array $data - Array of [hadir, izin, sakit, alfa, terlambat] (sudah dinormalisasi)
     * @return array - Hasil cluster untuk setiap data point
     */
    public function fit($data) {
        if (empty($data)) return [];

        $this->data = $data;
        $n = count($data);

        // Inisialisasi centroid menggunakan K-Means++
        $this->initCentroids();

        $prevClusters = [];

        for ($iter = 0; $iter < $this->maxIter; $iter++) {
            // Assignment step: assign each data point to nearest centroid
            $this->clusters = array_fill(0, $n, 0);

            foreach ($data as $i => $point) {
                $minDist = PHP_FLOAT_MAX;
                $bestCluster = 0;

                foreach ($this->centroids as $c => $centroid) {
                    $dist = $this->euclideanDistance($point, $centroid);
                    if ($dist < $minDist) {
                        $minDist = $dist;
                        $bestCluster = $c;
                    }
                }
                $this->clusters[$i] = $bestCluster;
            }

            // Check convergence
            if ($this->clusters === $prevClusters) break;
            $prevClusters = $this->clusters;

            // Update step: recalculate centroids
            $this->updateCentroids();
        }

        // Label cluster berdasarkan karakteristik kehadiran
        $this->assignLabels();

        return $this->clusters;
    }

    /**
     * K-Means++ initialization
     */
    private function initCentroids() {
        $n = count($this->data);
        $indices = range(0, $n - 1);
        shuffle($indices);

        // Pilih centroid pertama secara acak
        $this->centroids = [$this->data[$indices[0]]];

        // Pilih centroid berikutnya berdasarkan probabilitas jarak kuadrat
        for ($c = 1; $c < $this->k; $c++) {
            $distances = [];
            foreach ($this->data as $i => $point) {
                $minDist = PHP_FLOAT_MAX;
                foreach ($this->centroids as $centroid) {
                    $d = $this->euclideanDistance($point, $centroid);
                    if ($d < $minDist) $minDist = $d;
                }
                $distances[$i] = $minDist * $minDist;
            }

            $total = array_sum($distances);
            if ($total == 0) {
                $this->centroids[] = $this->data[array_rand($this->data)];
                continue;
            }

            $rand = (mt_rand() / mt_getrandmax()) * $total;
            $cumSum = 0;
            foreach ($distances as $i => $d) {
                $cumSum += $d;
                if ($cumSum >= $rand) {
                    $this->centroids[] = $this->data[$i];
                    break;
                }
            }
        }
    }

    /**
     * Update posisi centroid
     */
    private function updateCentroids() {
        $dims = count($this->data[0]);
        $newCentroids = array_fill(0, $this->k, array_fill(0, $dims, 0));
        $counts = array_fill(0, $this->k, 0);

        foreach ($this->data as $i => $point) {
            $c = $this->clusters[$i];
            $counts[$c]++;
            foreach ($point as $d => $val) {
                $newCentroids[$c][$d] += $val;
            }
        }

        foreach ($newCentroids as $c => &$centroid) {
            if ($counts[$c] > 0) {
                foreach ($centroid as &$val) {
                    $val /= $counts[$c];
                }
            }
        }

        $this->centroids = $newCentroids;
    }

    /**
     * Jarak Euclidean antara dua titik
     */
    private function euclideanDistance($a, $b) {
        $sum = 0;
        foreach ($a as $i => $val) {
            $sum += pow($val - $b[$i], 2);
        }
        return sqrt($sum);
    }

    /**
     * Assign label ke cluster berdasarkan karakteristik:
     * Cluster 1 = Kehadiran Normal  (hadir tinggi, alfa rendah)
     * Cluster 2 = Perlu Perhatian   (tengah)
     * Cluster 3 = Anomali           (hadir rendah, alfa tinggi)
     */
    private function assignLabels() {
        $clusterStats = [];
        foreach ($this->centroids as $c => $centroid) {
            // indeks: 0=hadir, 1=izin, 2=sakit, 3=alfa, 4=terlambat
            // hadir tinggi = bagus; izin, sakit, alfa, terlambat tinggi = tidak bagus.
            // Semua indikator masalah diikutkan (bukan cuma alfa) supaya urutan label
            // (Normal / Perlu Perhatian / Anomali) konsisten dengan hasil clustering-nya.
            $hadirScore   = $centroid[0];
            $izinScore    = isset($centroid[1]) ? $centroid[1] : 0;
            $sakitScore   = isset($centroid[2]) ? $centroid[2] : 0;
            $alfaScore    = isset($centroid[3]) ? $centroid[3] : 0;
            $terlambatScore = isset($centroid[4]) ? $centroid[4] : 0;
            // dirata-rata (bukan dijumlah) supaya skalanya tetap 0..1, sama seperti hadirScore
            $masalahScore = ($izinScore + $sakitScore + $alfaScore + $terlambatScore) / 4;
            $clusterStats[$c] = $hadirScore - $masalahScore;
        }

        arsort($clusterStats);
        $sortedKeys = array_keys($clusterStats);

        // Remap: skor tertinggi → 1 (Normal), tengah → 2 (Perlu Perhatian), terendah → 3 (Anomali)
        $labelMap = [];
        $labelMap[$sortedKeys[0]] = 1;
        if (count($sortedKeys) > 1) $labelMap[$sortedKeys[1]] = 2;
        if (count($sortedKeys) > 2) $labelMap[$sortedKeys[2]] = 3;

        foreach ($this->clusters as &$c) {
            $c = $labelMap[$c] ?? $c + 1;
        }

        $newCentroids = [];
        foreach ($labelMap as $old => $new) {
            $newCentroids[$new] = $this->centroids[$old];
        }
        ksort($newCentroids);
        $this->centroids = array_values($newCentroids);
    }

    /**
     * Hitung inertia (WCSS — Within-Cluster Sum of Squares)
     * Digunakan untuk Elbow Method dan memilih run terbaik.
     */
    public function getInertia() {
        $inertia = 0;
        foreach ($this->data as $i => $point) {
            $c = $this->clusters[$i] - 1; // label 1-indexed → 0-indexed
            $centroidIdx = ($c >= 0 && isset($this->centroids[$c])) ? $c : 0;
            $inertia += pow($this->euclideanDistance($point, $this->centroids[$centroidIdx]), 2);
        }
        return $inertia;
    }

    /**
     * Hitung Silhouette Score (rata-rata seluruh data)
     * Nilai mendekati 1 = clustering sangat baik
     * Nilai mendekati 0 = overlapping antar cluster
     * Nilai negatif     = data mungkin salah cluster
     */
    public function getSilhouetteScore() {
        $n = count($this->data);
        if ($n < 2 || $this->k < 2) return 0;

        $scores = [];
        foreach ($this->data as $i => $point) {
            $myCluster = $this->clusters[$i];

            // a(i): rata-rata jarak ke semua titik dalam cluster yang sama
            $sameClusterDists = [];
            foreach ($this->data as $j => $other) {
                if ($j !== $i && $this->clusters[$j] === $myCluster) {
                    $sameClusterDists[] = $this->euclideanDistance($point, $other);
                }
            }
            $a = empty($sameClusterDists) ? 0 : array_sum($sameClusterDists) / count($sameClusterDists);

            // b(i): rata-rata jarak minimum ke cluster terdekat lainnya
            $otherClusterAvg = [];
            foreach ($this->data as $j => $other) {
                if ($this->clusters[$j] !== $myCluster) {
                    $otherClusterAvg[$this->clusters[$j]][] = $this->euclideanDistance($point, $other);
                }
            }

            if (empty($otherClusterAvg)) {
                $scores[] = 0;
                continue;
            }

            $b = PHP_FLOAT_MAX;
            foreach ($otherClusterAvg as $clusterDists) {
                $avg = array_sum($clusterDists) / count($clusterDists);
                if ($avg < $b) $b = $avg;
            }

            // s(i) = (b - a) / max(a, b)
            $maxAB = max($a, $b);
            $scores[] = ($maxAB > 0) ? ($b - $a) / $maxAB : 0;
        }

        return round(array_sum($scores) / count($scores), 4);
    }

    public function getCentroids() { return $this->centroids; }
    public function getClusters()  { return $this->clusters; }
}

// ============================================================
// NORMALISASI MIN-MAX
// Mengubah semua fitur ke rentang [0, 1] agar K-Means tidak
// bias terhadap fitur dengan nilai absolut besar.
// ============================================================

/**
 * Hitung nilai min dan max setiap fitur dari dataset
 * @param array $dataset - array of [hadir, izin, sakit, alfa, terlambat]
 * @return array - ['min' => [...], 'max' => [...]]
 */
function hitungMinMax($dataset) {
    if (empty($dataset)) return ['min' => [], 'max' => []];
    $dims = count($dataset[0]);
    $minVals = array_fill(0, $dims, PHP_FLOAT_MAX);
    $maxVals = array_fill(0, $dims, -PHP_FLOAT_MAX);

    foreach ($dataset as $point) {
        foreach ($point as $d => $val) {
            if ($val < $minVals[$d]) $minVals[$d] = $val;
            if ($val > $maxVals[$d]) $maxVals[$d] = $val;
        }
    }
    return ['min' => $minVals, 'max' => $maxVals];
}

/**
 * Normalisasi Min-Max seluruh dataset ke rentang [0, 1]
 */
function normalisasiMinMax($dataset, $minMax) {
    $normalized = [];
    foreach ($dataset as $point) {
        $norm = [];
        foreach ($point as $d => $val) {
            $range = $minMax['max'][$d] - $minMax['min'][$d];
            $norm[$d] = ($range > 0) ? ($val - $minMax['min'][$d]) / $range : 0;
        }
        $normalized[] = $norm;
    }
    return $normalized;
}

// ============================================================
// ELBOW METHOD
// Menjalankan K-Means untuk K=2..7, mengembalikan array inertia
// untuk setiap nilai K. Digunakan untuk membuktikan K=3 optimal.
// ============================================================

/**
 * @param array $dataset - data SUDAH dinormalisasi
 * @return array - ['k' => [...], 'inertia' => [...]]
 */
function hitungElbow($dataset) {
    if (empty($dataset)) return ['k' => [], 'inertia' => []];

    $kValues   = [];
    $inertias  = [];
    $maxK = min(7, count($dataset)); // tidak bisa K > jumlah data

    for ($k = 2; $k <= $maxK; $k++) {
        $bestInertia = PHP_FLOAT_MAX;
        for ($run = 0; $run < 5; $run++) {
            $km = new KMeans($k, 100);
            $km->fit($dataset);
            $inertia = $km->getInertia();
            if ($inertia < $bestInertia) $bestInertia = $inertia;
        }
        $kValues[]  = $k;
        $inertias[] = round($bestInertia, 4);
    }
    return ['k' => $kValues, 'inertia' => $inertias];
}

// ============================================================
// FUNGSI UTAMA: jalankanKMeans
// ============================================================

function jalankanKMeans(PDO $pdo, $bulan, $tahun, $bulanSampai = null) {
    if ($bulanSampai === null) $bulanSampai = $bulan;

    // Ambil semua siswa aktif
    $stmtSiswa = $pdo->prepare("
        SELECT s.id, u.nama, k.nama_kelas, s.no_hp_ortu, s.nama_orang_tua
        FROM siswa s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN kelas k ON s.kelas_id = k.id
        WHERE u.status = 'aktif'
    ");
    $stmtSiswa->execute();
    $siswas = $stmtSiswa->fetchAll();

    if (empty($siswas)) {
        return ['status' => 'error', 'message' => 'Tidak ada data siswa aktif'];
    }

    // Hitung hari efektif sekolah dalam rentang bulan
    $jumlahHariEfektif = 0;
    for ($bx = $bulan; $bx <= $bulanSampai; $bx++) {
        $jumlahHariEfektif += hitungHariEfektif($bx, $tahun);
    }

    // Kumpulkan data absensi per siswa
    $dataset    = [];
    $siswaMeta  = [];

    foreach ($siswas as $siswa) {
        $stmt = $pdo->prepare("
            SELECT
                SUM(CASE WHEN status_masuk = 'hadir'    THEN 1 ELSE 0 END) as hadir,
                SUM(CASE WHEN status_masuk = 'izin'     THEN 1 ELSE 0 END) as izin,
                SUM(CASE WHEN status_masuk = 'sakit'    THEN 1 ELSE 0 END) as sakit,
                SUM(CASE WHEN status_masuk = 'alfa'     THEN 1 ELSE 0 END) as alfa,
                SUM(CASE WHEN status_masuk = 'terlambat'THEN 1 ELSE 0 END) as terlambat
            FROM absensi
            WHERE siswa_id = ?
            AND MONTH(tanggal) >= ?
            AND MONTH(tanggal) <= ?
            AND YEAR(tanggal) = ?
        ");
        $stmt->execute([$siswa['id'], $bulan, $bulanSampai, $tahun]);
        $rekap = $stmt->fetch();

        $hadir     = (int)($rekap['hadir']     ?? 0);
        $terlambat = (int)($rekap['terlambat'] ?? 0);
        $izin      = (int)($rekap['izin']      ?? 0);
        $sakit     = (int)($rekap['sakit']     ?? 0);
        $alfa      = (int)($rekap['alfa']      ?? 0);

        // [FIX] Hadir TIDAK lagi digabung dengan Terlambat.
        // Terlambat tetap dihitung "hadir secara fisik" untuk keperluan rekap kehadiran,
        // tapi untuk clustering, Hadir murni (tepat waktu) dan Terlambat dipisah supaya
        // siswa yang sering terlambat tidak mendongkrak skor "Hadir"-nya.
        $hadirMurni = $hadir; // hanya status 'hadir' (tepat waktu), tidak termasuk terlambat

        $dataset[]   = [$hadirMurni, $izin, $sakit, $alfa, $terlambat];
        $siswaMeta[] = [
            'id'          => $siswa['id'],
            'nama'        => $siswa['nama'],
            'kelas'       => $siswa['nama_kelas'],
            'no_hp_ortu'  => $siswa['no_hp_ortu'],
            'nama_ortu'   => $siswa['nama_orang_tua'],
            'hadir'       => $hadirMurni + $terlambat, // untuk tampilan rekap: tetap dihitung hadir fisik
            'izin'        => $izin,
            'sakit'       => $sakit,
            'alfa'        => $alfa,
            'terlambat'   => $terlambat,
            'hari_efektif'=> $jumlahHariEfektif,
        ];
    }

    // -------------------------------------------------------
    // NORMALISASI MIN-MAX sebelum clustering
    // -------------------------------------------------------
    $minMax           = hitungMinMax($dataset);
    $datasetNormalized = normalisasiMinMax($dataset, $minMax);

    // -------------------------------------------------------
    // [BOBOT FITUR] Terlambat diberi bobot lebih besar (2x) sebelum
    // dihitung jaraknya di K-Means, supaya siswa yang sering terlambat
    // benar-benar menjauh dari cluster "Kehadiran Normal", bukan malah
    // ikut tertarik ke sana karena kehadiran fisiknya tetap tercatat hadir.
    // Urutan fitur: [hadir, izin, sakit, alfa, terlambat]
    // Bobot ini didokumentasikan di BAB III sebagai penyesuaian metodologi.
    // -------------------------------------------------------
    $bobotFitur = [1.0, 1.0, 1.0, 1.0, 2.0];
    foreach ($datasetNormalized as &$point) {
        foreach ($point as $d => &$val) {
            $val *= $bobotFitur[$d];
        }
    }
    unset($point, $val);

    // -------------------------------------------------------
    // ELBOW METHOD (K=2..7) — untuk grafik pembuktian K optimal
    // -------------------------------------------------------
    $elbowData = hitungElbow($datasetNormalized);

    // -------------------------------------------------------
    // JALANKAN K-MEANS (K=3) — 10 run, ambil inertia terkecil
    // -------------------------------------------------------
    $bestInertia   = PHP_FLOAT_MAX;
    $clusterResult = [];
    $centroids     = [];

    for ($run = 0; $run < 10; $run++) {
        $kmeans  = new KMeans(3, 100);
        $result  = $kmeans->fit($datasetNormalized);
        $inertia = $kmeans->getInertia();
        if ($inertia < $bestInertia) {
            $bestInertia   = $inertia;
            $clusterResult = $result;
            $centroids     = $kmeans->getCentroids();
            $bestKMeans    = $kmeans;
        }
    }

    // Silhouette Score dari run terbaik
    $silhouetteScore = $bestKMeans->getSilhouetteScore();

    // -------------------------------------------------------
    // LABEL CLUSTER
    // -------------------------------------------------------
    $labelCluster = [
        1 => 'Kehadiran Normal',
        2 => 'Perlu Perhatian',
        3 => 'Anomali',
    ];

    // Hapus hasil lama, simpan baru
    for ($bx = $bulan; $bx <= $bulanSampai; $bx++) {
        $pdo->prepare("DELETE FROM hasil_clustering WHERE bulan = ? AND tahun = ?")->execute([$bx, $tahun]);
    }

    // Simpan hasil clustering ke database
    $hasil = [];
    foreach ($siswaMeta as $i => $meta) {
        $clusterId = $clusterResult[$i];
        $label     = $labelCluster[$clusterId] ?? 'Tidak Diketahui';

        // [FIX] Hitung jarak Euclidean ke SEMUA centroid (bukan cuma centroid sendiri)
        // supaya bisa ditunjukkan proses perbandingannya di halaman perhitungan
        $normPoint     = $datasetNormalized[$i];
        $jarakSemua    = [];
        foreach ($centroids as $cIdx => $centroid) {
            $sum = 0;
            foreach ($normPoint as $d => $val) {
                $sum += pow($val - $centroid[$d], 2);
            }
            $jarakSemua[$cIdx + 1] = round(sqrt($sum), 4);
        }
        $jarakCentroid = $jarakSemua[$clusterId] ?? 0;

        $stmt = $pdo->prepare("
            INSERT INTO hasil_clustering
            (bulan, tahun, siswa_id, jumlah_hadir, jumlah_izin, jumlah_sakit, jumlah_alfa, jumlah_terlambat, cluster_id, label_cluster)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $bulan, $tahun, $meta['id'],
            $meta['hadir'], $meta['izin'], $meta['sakit'], $meta['alfa'], $meta['terlambat'],
            $clusterId, $label
        ]);

        $hasil[] = array_merge($meta, [
            'cluster_id'      => $clusterId,
            'label_cluster'   => $label,
            'jarak_centroid'  => $jarakCentroid,
            'normalized'      => array_map(fn($v) => round($v, 4), $normPoint),
            'jarak_ke_semua'  => $jarakSemua,
        ]);
    }

    // Statistik per cluster
    $statCluster = [1 => 0, 2 => 0, 3 => 0];
    foreach ($clusterResult as $c) {
        if (isset($statCluster[$c])) $statCluster[$c]++;
    }

    return [
        'status'           => 'success',
        'bulan'            => $bulan,
        'bulan_sampai'     => $bulanSampai,
        'tahun'            => $tahun,
        'total_siswa'      => count($siswaMeta),
        'hasil'            => $hasil,
        'centroids'        => $centroids,
        'minMax'           => $minMax,
        'elbow'            => $elbowData,
        'silhouette_score' => $silhouetteScore,
        'inertia'          => round($bestInertia, 4),
        'statistik'        => [
            'kehadiran_normal' => $statCluster[1],
            'perlu_perhatian'  => $statCluster[2],
            'anomali'          => $statCluster[3],
        ],
    ];
}

/**
 * Hitung hari efektif (Senin-Jumat) dalam bulan tertentu
 */
function hitungHariEfektif($bulan, $tahun) {
    $hari = 0;
    $jumlahHari = cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);
    for ($d = 1; $d <= $jumlahHari; $d++) {
        $dayOfWeek = date('N', mktime(0, 0, 0, $bulan, $d, $tahun));
        if ($dayOfWeek < 6) $hari++;
    }
    return $hari;
}