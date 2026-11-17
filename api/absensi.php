<?php
// ============================================================
// FILE: api/absensi.php - API Endpoint Absensi Geolokasi
// [FIX] Kode duplikat dihapus, rate limiting ditambahkan,
//       validasi GPS diperkuat
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile on line $errline");
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan server.']);
    exit;
});

$configPath = dirname(__DIR__) . '/includes/config.php';
if (!file_exists($configPath)) {
    echo json_encode(['status' => 'error', 'message' => 'Konfigurasi tidak ditemukan.']);
    exit;
}
require_once $configPath;

// Cek login
if (!isset($_SESSION['siswa_id']) || !isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesi habis atau tidak valid. Silakan logout lalu login ulang.']);
    exit;
}

if ($_SESSION['role'] !== 'siswa') {
    echo json_encode(['status' => 'error', 'message' => 'Hanya siswa yang dapat melakukan absensi.']);
    exit;
}


// Cek status akun siswa:
// - 'keluar'  → tidak bisa login sama sekali (sudah diblokir di index.php)
// - 'lulus'   → bisa login, TAPI tidak bisa absen
$stmtStatusAkun = $pdo->prepare("
    SELECT u.status, u.alasan_nonaktif
    FROM users u
    JOIN siswa s ON s.user_id = u.id
    WHERE s.id = ?
");
$stmtStatusAkun->execute([$_SESSION['siswa_id']]);
$statusAkun = $stmtStatusAkun->fetch();
 
if ($statusAkun && $statusAkun['status'] === 'nonaktif') {
    $pesanBlokir = $statusAkun['alasan_nonaktif'] === 'lulus'
        ? 'Akun Anda berstatus Lulus. Absensi tidak dapat dilakukan.'
        : 'Akun Anda tidak aktif. Silakan hubungi administrator.';
    echo json_encode(['status' => 'error', 'message' => $pesanBlokir]);
    exit;
}


// [FIX] Rate limiting sederhana — maksimal 5 request per menit per siswa
$siswaIdStr = (string)$_SESSION['siswa_id'];
$rateLimitKey = 'absensi_rate_' . $siswaIdStr;
$now = time();
if (!isset($_SESSION[$rateLimitKey])) {
    $_SESSION[$rateLimitKey] = ['count' => 0, 'window_start' => $now];
}
$rl = &$_SESSION[$rateLimitKey];
if ($now - $rl['window_start'] > 60) {
    $rl = ['count' => 0, 'window_start' => $now];
}
$rl['count']++;
if ($rl['count'] > 5) {
    echo json_encode(['status' => 'error', 'message' => 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.']);
    exit;
}

// Ambil input JSON
$raw   = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!$input) {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak valid.']);
    exit;
}

$jenis = $input['jenis'] ?? '';
$lat   = isset($input['lat']) ? (float)$input['lat'] : null;
$lng   = isset($input['lng']) ? (float)$input['lng'] : null;

if (!in_array($jenis, ['masuk', 'pulang'])) {
    echo json_encode(['status' => 'error', 'message' => 'Jenis absensi tidak valid: ' . $jenis]);
    exit;
}

// [FIX] Validasi GPS lebih ketat — pastikan koordinat dalam rentang valid
if ($lat === null || $lng === null) {
    echo json_encode(['status' => 'error', 'message' => 'Koordinat GPS tidak diterima.']);
    exit;
}
if ($lat == 0.0 && $lng == 0.0) {
    echo json_encode(['status' => 'error', 'message' => 'Koordinat GPS tidak valid (0,0).']);
    exit;
}
// Validasi rentang koordinat Indonesia
if ($lat < -11.0 || $lat > 6.0 || $lng < 94.0 || $lng > 142.0) {
    echo json_encode(['status' => 'error', 'message' => 'Koordinat GPS di luar jangkauan wilayah yang valid.']);
    exit;
}

$settings = getSettings($pdo);
$siswaId  = (int)$_SESSION['siswa_id'];
$today    = date('Y-m-d');
$nowTime  = date('H:i:s');

// Hitung jarak ke sekolah
$latSekolah = (float)$settings['sekolah_lat'];
$lngSekolah = (float)$settings['sekolah_lng'];
$radius     = (int)$settings['radius_meter'];
$jarak      = hitungJarak($lat, $lng, $latSekolah, $lngSekolah);

// [TEMP-TESTING] Cek hari libur DINONAKTIFKAN SEMENTARA untuk keperluan testing.
// TODO: hapus komentar di bawah & aktifkan lagi block-nya setelah selesai testing!
/*
$liburCek = isHariLibur($pdo, $today);
if ($liburCek['libur']) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Hari ini adalah hari libur (' . $liburCek['keterangan'] . '). Absensi tidak bisa dilakukan.',
    ]);
    exit;
}
*/

if ($jarak > $radius) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Anda berada di luar area sekolah. Jarak Anda: ' . round($jarak) . 'm dari sekolah. Batas radius: ' . $radius . 'm.',
        'jarak'   => round($jarak),
        'radius'  => $radius
    ]);
    exit;
}

// Ambil kelas siswa
$stmtSiswa = $pdo->prepare("SELECT kelas_id FROM siswa WHERE id = ?");
$stmtSiswa->execute([$siswaId]);
$siswaData = $stmtSiswa->fetch();

if (!$siswaData) {
    echo json_encode(['status' => 'error', 'message' => 'Data siswa tidak ditemukan.']);
    exit;
}
$kelasId = $siswaData['kelas_id'];

// Cek absensi hari ini
$stmtCek = $pdo->prepare("SELECT * FROM absensi WHERE siswa_id = ? AND tanggal = ?");
$stmtCek->execute([$siswaId, $today]);
$existing = $stmtCek->fetch();

if ($jenis === 'masuk') {
    if ($existing && $existing['waktu_masuk']) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Anda sudah absensi masuk hari ini pukul ' . $existing['waktu_masuk'] . '.'
        ]);
        exit;
    }

    // [FIX] Cek jika sudah ada status izin/sakit/alfa — tidak bisa absen
    if ($existing && in_array($existing['status_masuk'], ['izin','sakit','alfa'])) {
        $labelStatus = ucfirst($existing['status_masuk']);
        echo json_encode([
            'status'  => 'error',
            'message' => "Status Anda hari ini sudah tercatat: {$labelStatus}. Tidak bisa melakukan absensi.",
        ]);
        exit;
    }

    // [FIX] Cek pengajuan izin/sakit yang sudah disetujui — blokir absensi
    $cekIzin = $pdo->prepare("SELECT jenis FROM pengajuan_izin WHERE siswa_id=? AND tanggal=? AND status='disetujui' LIMIT 1");
    $cekIzin->execute([$siswaId, $today]);
    $izinDisetujui = $cekIzin->fetch();
    if ($izinDisetujui) {
        $jenisIzin = ucfirst($izinDisetujui['jenis']);
        echo json_encode([
            'status'  => 'error',
            'message' => "Anda sudah mengajukan {$jenisIzin} yang telah disetujui. Absensi tidak diperlukan.",
        ]);
        exit;
    }

    $status = ($nowTime > $settings['batas_terlambat']) ? 'terlambat' : 'hadir';

    try {
        if ($existing) {
            $pdo->prepare("UPDATE absensi SET waktu_masuk=?, lat_masuk=?, lng_masuk=?, status_masuk=? WHERE siswa_id=? AND tanggal=?")
                ->execute([$nowTime, $lat, $lng, $status, $siswaId, $today]);
        } else {
            $namaKelasSnap = ambilKelasSiswaSaatIni($pdo, $siswaId)['nama_kelas'];
            $pdo->prepare("INSERT INTO absensi (siswa_id, kelas_id, nama_kelas_snapshot, tanggal, waktu_masuk, lat_masuk, lng_masuk, status_masuk, status_pulang) VALUES (?,?,?,?,?,?,?,?,'belum_pulang')")
                ->execute([$siswaId, $kelasId, $namaKelasSnap, $today, $nowTime, $lat, $lng, $status]);
        }
    } catch (PDOException $e) {
        error_log('Absensi masuk error: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data. Coba lagi.']);
        exit;
    }

    // [WA] Notifikasi real-time ke ortu: konfirmasi hadir / info terlambat
    kirimNotifikasiHarian($pdo, (int)$siswaId, $today, $status, $nowTime);

    $pesan = $status === 'terlambat'
        ? "Absensi masuk tercatat TERLAMBAT pukul {$nowTime}."
        : "Absensi masuk berhasil dicatat pukul {$nowTime}.";

    echo json_encode(['status' => 'success', 'message' => $pesan, 'status_absen' => $status, 'jarak' => round($jarak)]);

} elseif ($jenis === 'pulang') {
    if (!$existing || !$existing['waktu_masuk']) {
        echo json_encode(['status' => 'error', 'message' => 'Anda belum melakukan absensi masuk hari ini.']);
        exit;
    }

    // [FIX] Kalau masuk statusnya izin/sakit/alfa, pulang otomatis sama — tidak perlu absen pulang
    if (in_array($existing['status_masuk'], ['izin','sakit','alfa'])) {
        $labelStatus = ucfirst($existing['status_masuk']);
        echo json_encode([
            'status'  => 'error',
            'message' => "Status masuk Anda adalah {$labelStatus}. Absensi pulang tidak diperlukan.",
        ]);
        exit;
    }

    if ($existing['status_pulang'] === 'pulang' && $existing['waktu_pulang']) {
        echo json_encode([
            'status'  => 'error',
            'message' => 'Anda sudah absensi pulang hari ini pukul ' . $existing['waktu_pulang'] . '.'
        ]);
        exit;
    }

    if ($nowTime < $settings['jam_pulang']) {
        $selisih   = strtotime($settings['jam_pulang']) - strtotime($nowTime);
        $menitLagi = ceil($selisih / 60);
        echo json_encode([
            'status'  => 'error',
            'message' => "Belum waktunya pulang. Jam pulang: {$settings['jam_pulang']} ({$menitLagi} menit lagi)."
        ]);
        exit;
    }

    try {
        $pdo->prepare("UPDATE absensi SET waktu_pulang=?, lat_pulang=?, lng_pulang=?, status_pulang='pulang' WHERE siswa_id=? AND tanggal=?")
            ->execute([$nowTime, $lat, $lng, $siswaId, $today]);
    } catch (PDOException $e) {
        error_log('Absensi pulang error: ' . $e->getMessage());
        echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan data. Coba lagi.']);
        exit;
    }

    echo json_encode([
        'status'  => 'success',
        'message' => "Absensi pulang berhasil dicatat pukul {$nowTime}. Hati-hati di jalan!",
        'jarak'   => round($jarak)
    ]);
}