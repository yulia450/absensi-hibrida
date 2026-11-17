<?php
// ============================================================
// FILE: admin/ajax_kirim_wa_batch.php
// Endpoint AJAX untuk kirim notifikasi WA per-batch (15 siswa/panggilan).
// Dipanggil berulang kali oleh JS di clustering.php sampai semua target
// selesai. Tujuannya supaya tiap request PHP singkat (nggak nunggu
// ratusan pesan sekaligus dalam 1 request -> nggak timeout, nggak
// dianggap spam burst oleh provider WA, dan admin bisa lihat progress).
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

require_once '../includes/config.php';
require_once '../includes/functions.php';

function jsonFail($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => false, 'message' => $message]);
    exit;
}

// ── Auth: harus admin yang login ──
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    jsonFail('Sesi habis atau tidak diizinkan. Silakan login ulang.', 401);
}

// ── CSRF ──
$csrfToken = $_POST['csrf_token'] ?? '';
if (!hash_equals(csrfToken(), $csrfToken)) {
    jsonFail('Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.', 403);
}

$settings = getSettings($pdo);

$modePeriode = $_POST['mode_periode'] ?? 'semester';
if (!in_array($modePeriode, ['semester', 'bulanan'])) $modePeriode = 'semester';

if ($modePeriode === 'bulanan') {
    $bPOST = (int)($_POST['bulan'] ?? 0);
    $tPOST = (int)($_POST['tahun'] ?? 0);
    if ($bPOST < 1 || $bPOST > 12 || $tPOST < 2000) {
        jsonFail('Parameter periode tidak lengkap.');
    }
    $rangePOST = getRangeBulanan($bPOST, $tPOST);
} else {
    $taPOST  = $_POST['tahun_ajaran'] ?? '';
    $semPOST = $_POST['semester']     ?? '';
    if (empty($taPOST) || empty($semPOST)) {
        jsonFail('Parameter periode tidak lengkap.');
    }
    $rangePOST = getRangeSemester($taPOST, $semPOST);
}
$bD = $rangePOST['bulan_dari'];
$bS = $rangePOST['bulan_sampai'];
$b  = $bD;
$t  = $rangePOST['tahun_mulai'];

$targetMode  = $_POST['target_mode']  ?? 'semua';
$targetKelas = (int)($_POST['target_kelas'] ?? 0);
$offset      = max(0, (int)($_POST['offset'] ?? 0));
$limit       = 15; // ukuran batch tetap, sengaja kecil biar tiap request ringan

// [FIX] Notifikasi WA HANYA untuk cluster yang perlu peringatan:
// Cluster 2 (Perlu Perhatian) & Cluster 3 (Anomali). Cluster 1 (Normal)
// sengaja tidak dikirimi.
$sqlWA = "
    SELECT hc.*, u.nama, k.nama_kelas, s.no_hp_ortu, s.nama_orang_tua
    FROM hasil_clustering hc
    JOIN siswa s ON hc.siswa_id = s.id
    JOIN users u ON s.user_id = u.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    WHERE hc.bulan >= ? AND hc.bulan <= ? AND hc.tahun = ?
      AND hc.cluster_id IN (2, 3)
";
$paramsWA = [$bD, $bS, $t];

if ($targetMode === 'kelas' && $targetKelas > 0) {
    $sqlWA .= " AND s.kelas_id = ?";
    $paramsWA[] = $targetKelas;
}
$sqlWA .= " ORDER BY hc.cluster_id, u.nama";

$stmt = $pdo->prepare($sqlWA);
$stmt->execute($paramsWA);
$hasilWA = $stmt->fetchAll();

$total = count($hasilWA);
$batch = array_slice($hasilWA, $offset, $limit);

if ($total === 0) {
    echo json_encode([
        'status'  => true,
        'total'   => 0,
        'offset'  => 0,
        'terkirim'=> 0,
        'gagal'   => 0,
        'done'    => true,
        'message' => 'Tidak ada siswa Cluster 2 (Perlu Perhatian) / Cluster 3 (Anomali) untuk target yang dipilih.',
    ]);
    exit;
}

$terkirim = 0;
$gagal    = 0;

foreach ($batch as $siswa) {
    if (empty($siswa['no_hp_ortu'])) { $gagal++; continue; }
    $pesan = buatPesanWA($siswa, $b, $t, $siswa['label_cluster']);
    $res   = kirimWhatsApp($siswa['no_hp_ortu'], $pesan, $settings['whatsapp_api_url'], $settings['whatsapp_api_key']);
    $status = $res['status'] ? 'terkirim' : 'gagal';
    $stmt2 = $pdo->prepare("
        INSERT INTO notifikasi_wa (siswa_id, no_hp, pesan, status, bulan, tahun)
        VALUES (?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE status=VALUES(status), pesan=VALUES(pesan)
    ");
    $stmt2->execute([$siswa['siswa_id'], $siswa['no_hp_ortu'], $pesan, $status, $b, $t]);
    $res['status'] ? $terkirim++ : $gagal++;

    // Delay kecil antar-pesan dalam batch biar nggak dianggap pola spam.
    // Batch dibikin kecil (15) supaya total delay per request tetap singkat.
    usleep(300000); // 0.3 detik
}

$nextOffset = $offset + count($batch);
$done = $nextOffset >= $total;

echo json_encode([
    'status'      => true,
    'total'       => $total,
    'offset'      => $offset,
    'processed'   => count($batch),
    'next_offset' => $nextOffset,
    'terkirim'    => $terkirim,
    'gagal'       => $gagal,
    'done'        => $done,
]);
exit;