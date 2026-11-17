<?php
// ============================================================
// FILE: includes/config.php
// Konfigurasi Database dan Konstanta Sistem
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'db_absensi_smk');
define('DB_CHARSET', 'utf8mb4');

// ============================================================
// BASE URL
// ============================================================

/* sementara untuk ngrok
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    ? 'https'
    : 'http';

    */

$protocol = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
) ? 'https' : 'http';


$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

$docRoot = rtrim(
    str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']),
    '/'
);

$thisDir = rtrim(
    str_replace('\\', '/', dirname(__DIR__)),
    '/'
);

$subDir = str_replace($docRoot, '', $thisDir);
$subDir = '/' . trim($subDir, '/');

if ($subDir === '/') {
    $subDir = '';
}

define('BASE_URL', $protocol . '://' . $host . $subDir . '/');

define('UPLOAD_PATH', __DIR__ . '/../assets/uploads/');
define('UPLOAD_URL', BASE_URL . 'assets/uploads/');

// ============================================================
// TIMEZONE
// ============================================================

date_default_timezone_set('Asia/Jakarta');

// ============================================================
// SESSION
// ============================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================
// ERROR REPORTING
// ============================================================

// Matikan saat production
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// ============================================================
// DATABASE CONNECTION
// ============================================================

try {

    $dsn =
        "mysql:host=" . DB_HOST .
        ";dbname=" . DB_NAME .
        ";charset=" . DB_CHARSET;

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        $options
    );

} catch (PDOException $e) {

    die(
        '<h2 style="font-family:sans-serif;color:#b91c1c;padding:20px">
        Koneksi Database Gagal: ' .
        htmlspecialchars($e->getMessage()) .
        '</h2>'
    );
}

// ============================================================
// FUNCTIONS
// ============================================================

require_once __DIR__ . '/functions.php';

// ============================================================
// REDIRECT
// ============================================================

function redirect($url)
{
    header("Location: " . BASE_URL . $url);
    exit;
}

// ============================================================
// AUTH
// ============================================================

function isLoggedIn()
{
    return isset($_SESSION['user_id']);
}

function getRole()
{
    return $_SESSION['role'] ?? null;
}

function requireLogin($role = null)
{
    if (!isLoggedIn()) {
        redirect('index.php?msg=login_required');
    }

    if (
        $role &&
        getRole() !== $role &&
        !in_array(getRole(), (array)$role)
    ) {
        redirect('index.php?msg=unauthorized');
    }
}

// ============================================================
// SANITIZE
// ============================================================

function sanitize($data)
{
    return htmlspecialchars(
        strip_tags(trim($data))
    );
}

// ============================================================
// CSRF
// ============================================================

function csrfToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] =
            bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfField()
{
    return
        '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars(csrfToken()) .
        '">';
}

function verifyCsrf()
{
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals(csrfToken(), $token)) {

        http_response_code(403);

        die(
            'Request tidak valid (CSRF token tidak cocok).'
        );
    }
}

// ============================================================
// GENERATE PASSWORD
// ============================================================

function generatePassword($length = 10)
{
    $chars =
        'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';

    $password = '';

    $max = strlen($chars) - 1;

    for ($i = 0; $i < $length; $i++) {

        $password .=
            $chars[random_int(0, $max)];
    }

    return $password;
}

// ============================================================
// SETTINGS
// ============================================================

function getSettings(PDO $pdo)
{
    $stmt = $pdo->query(
        "SELECT * FROM settings ORDER BY id ASC LIMIT 1"
    );

    $row = $stmt->fetch();

    if (!$row) {

        return [
            'id'               => 0,
            'sekolah_nama'     => 'SMK AL-MANSHURIYAH',
            'sekolah_alamat'   => '',
            'sekolah_lat'      => -6.3621,
            'sekolah_lng'      => 106.7481,
            'radius_meter'     => 100,
            'jam_masuk'        => '07:00:00',
            'jam_pulang'       => '15:00:00',
            'batas_terlambat'  => '07:15:00',
            'whatsapp_api_url' => '',
            'whatsapp_api_key' => '',
            'kmeans_last_mode' => 'semester',
            'kmeans_last_bulan' => null,
            'kmeans_last_tahun_bulanan' => null,
        ];
    }

    return $row;
}

// ============================================================
// HITUNG JARAK
// ============================================================

function hitungJarak(
    $lat1,
    $lng1,
    $lat2,
    $lng2
) {

    $R = 6371000;

    $phi1 = deg2rad($lat1);
    $phi2 = deg2rad($lat2);

    $dphi = deg2rad($lat2 - $lat1);

    $dlambda = deg2rad($lng2 - $lng1);

    $a =
        sin($dphi / 2) * sin($dphi / 2) +
        cos($phi1) *
        cos($phi2) *
        sin($dlambda / 2) *
        sin($dlambda / 2);

    $c = 2 * atan2(
        sqrt($a),
        sqrt(1 - $a)
    );

    return $R * $c;
}

// ============================================================
// STATUS BADGE
// ============================================================

function getStatusBadge($status)
{
    $badges = [

        'hadir' =>
            '<span class="badge badge-hadir">Hadir</span>',

        'terlambat' =>
            '<span class="badge badge-terlambat">Terlambat</span>',

        'izin' =>
            '<span class="badge badge-izin">Izin</span>',

        'sakit' =>
            '<span class="badge badge-sakit">Sakit</span>',

        'alfa' =>
            '<span class="badge badge-alfa">Alfa</span>',

        'pulang' =>
            '<span class="badge badge-hadir">Pulang</span>',

        'belum_pulang' =>
            '<span class="badge badge-terlambat">Belum Pulang</span>',

        'menunggu' =>
            '<span class="badge badge-izin">Menunggu</span>',

        'disetujui' =>
            '<span class="badge badge-hadir">Disetujui</span>',

        'ditolak' =>
            '<span class="badge badge-alfa">Ditolak</span>',
    ];

    return $badges[$status]
        ?? '<span class="badge">' .
        htmlspecialchars($status) .
        '</span>';
}

// ============================================================
// FORMAT TANGGAL
// ============================================================

function formatTanggal($tanggal)
{
    $hari = [
        'Minggu',
        'Senin',
        'Selasa',
        'Rabu',
        'Kamis',
        'Jumat',
        'Sabtu'
    ];

    $bulan = [
        '',
        'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];

    $ts = strtotime($tanggal);

    return
        $hari[(int)date('w', $ts)] . ', ' .
        date('d', $ts) . ' ' .
        $bulan[(int)date('m', $ts)] . ' ' .
        date('Y', $ts);
}

// ============================================================
// NAMA BULAN
// ============================================================

function namaBulan($bulan)
{
    $bulanArr = [
        '',
        'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];

    return $bulanArr[(int)$bulan] ?? '';
}

// ============================================================
// TAHUN AJARAN & SEMESTER
// ============================================================

/**
 * Hitung tahun ajaran dari bulan & tahun kalender.
 * Ganjil : Juli(7)–Desember(12)  → "2024/2025"  (tahun Y/Y+1)
 * Genap  : Januari(1)–Juni(6)    → "2024/2025"  (tahun Y-1/Y)
 * Return: ['tahun_ajaran'=>'2024/2025','semester'=>'ganjil'|'genap',
 *          'tahun_mulai'=>2024,'tahun_akhir'=>2025]
 */
function getTahunAjaranDariTanggal($bulan, $tahun)
{
    $bulan = (int)$bulan;
    $tahun = (int)$tahun;

    if ($bulan >= 7) {
        return [
            'tahun_ajaran' => $tahun . '/' . ($tahun + 1),
            'semester'     => 'ganjil',
            'tahun_mulai'  => $tahun,
            'tahun_akhir'  => $tahun + 1,
        ];
    } else {
        return [
            'tahun_ajaran' => ($tahun - 1) . '/' . $tahun,
            'semester'     => 'genap',
            'tahun_mulai'  => $tahun - 1,
            'tahun_akhir'  => $tahun,
        ];
    }
}

/**
 * Dari string tahun_ajaran (misal "2024/2025") + semester,
 * kembalikan array ['bulan_dari'=>int, 'bulan_sampai'=>int, 'tahun_mulai'=>int, 'tahun_akhir'=>int]
 * Ganjil  → bulan 7–12, tahun = tahun_mulai
 * Genap   → bulan 1–6,  tahun = tahun_akhir
 */
function getRangeSemester($tahunAjaran, $semester)
{
    $parts = explode('/', $tahunAjaran);
    $tahunMulai  = (int)($parts[0] ?? date('Y'));
    $tahunAkhir  = (int)($parts[1] ?? ($tahunMulai + 1));

    if ($semester === 'ganjil') {
        return [
            'bulan_dari'   => 7,
            'bulan_sampai' => 12,
            'tahun_mulai'  => $tahunMulai,
            'tahun_akhir'  => $tahunMulai,   // semua bulan 7-12 ada di tahun mulai
        ];
    } else {
        return [
            'bulan_dari'   => 1,
            'bulan_sampai' => 6,
            'tahun_mulai'  => $tahunAkhir,
            'tahun_akhir'  => $tahunAkhir,   // semua bulan 1-6 ada di tahun akhir
        ];
    }
}

/**
 * Label tampilan: "Semester Ganjil 2024/2025"
 */
function labelSemester($tahunAjaran, $semester)
{
    $s = $semester === 'ganjil' ? 'Ganjil' : 'Genap';
    return 'Semester ' . $s . ' ' . $tahunAjaran;
}

/**
 * Daftar tahun ajaran untuk dropdown (N tahun ke belakang + 1 ke depan)
 */
function daftarTahunAjaran($jumlah = 5)
{
    $list = [];
    $ta = getTahunAjaranDariTanggal((int)date('n'), (int)date('Y'));
    $tMulaiSekarang = $ta['tahun_mulai'];

    for ($i = 1; $i >= -($jumlah - 1); $i--) {
        $y = $tMulaiSekarang + $i;
        $list[] = $y . '/' . ($y + 1);
    }
    return $list;
}

/**
 * Range 1 bulan spesifik (mode "Bulanan"), bentuknya disamakan dengan
 * getRangeSemester() supaya bisa dipakai langsung oleh jalankanKMeans()
 * dan query hasil_clustering tanpa perubahan lain.
 */
function getRangeBulanan($bulan, $tahun)
{
    $bulan = max(1, min(12, (int)$bulan));
    $tahun = (int)$tahun;
    return [
        'bulan_dari'   => $bulan,
        'bulan_sampai' => $bulan,
        'tahun_mulai'  => $tahun,
        'tahun_akhir'  => $tahun,
    ];
}

/**
 * Label tampilan mode bulanan: "Bulan Juli 2026"
 */
function labelBulanan($bulan, $tahun)
{
    return 'Bulan ' . namaBulan($bulan) . ' ' . (int)$tahun;
}

/**
 * Parse mode periode dari $_GET/$_POST: 'semester' (default) atau 'bulanan'.
 * Return array yang formatnya sama dengan parseSemesterFilter(), plus
 * key 'mode', 'bulan', 'tahun' supaya form bisa menampilkan ulang pilihan.
 */
function parsePeriodeFilter($source = null)
{
    $src  = $source ?? $_GET;
    $mode = $src['mode_periode'] ?? 'semester';
    if (!in_array($mode, ['semester', 'bulanan'])) $mode = 'semester';

    if ($mode === 'bulanan') {
        $bulan = (int)($src['bulan'] ?? date('n'));
        $tahun = (int)($src['tahun'] ?? date('Y'));
        if ($bulan < 1 || $bulan > 12) $bulan = (int)date('n');
        if ($tahun < 2000 || $tahun > 2100) $tahun = (int)date('Y');

        return [
            'mode'         => 'bulanan',
            'tahun_ajaran' => '',
            'semester'     => '',
            'bulan'        => $bulan,
            'tahun'        => $tahun,
            'range'        => getRangeBulanan($bulan, $tahun),
            'label'        => labelBulanan($bulan, $tahun),
        ];
    }

    $sf = parseSemesterFilter();
    $sf['mode']  = 'semester';
    $sf['bulan'] = null;
    $sf['tahun'] = null;
    return $sf;
}


/**
 * Parse tahun_ajaran dan semester dari $_GET, dengan default ke periode sekarang.
 * Return ['tahun_ajaran'=>string, 'semester'=>string, 'range'=>array]
 */
function parseSemesterFilter()
{
    $taNow = getTahunAjaranDariTanggal((int)date('n'), (int)date('Y'));

    $tahunAjaran = $_GET['tahun_ajaran'] ?? $taNow['tahun_ajaran'];
    $semester    = $_GET['semester']    ?? $taNow['semester'];
    if (!in_array($semester, ['ganjil', 'genap'])) $semester = 'ganjil';

    // Validasi format tahun_ajaran
    if (!preg_match('/^\d{4}\/\d{4}$/', $tahunAjaran)) {
        $tahunAjaran = $taNow['tahun_ajaran'];
    }

    $range = getRangeSemester($tahunAjaran, $semester);

    return [
        'tahun_ajaran' => $tahunAjaran,
        'semester'     => $semester,
        'range'        => $range,
        'label'        => labelSemester($tahunAjaran, $semester),
    ];
}

// ============================================================
// AUTO CREATE UPLOAD FOLDER
// ============================================================

if (!is_dir(UPLOAD_PATH)) {

    mkdir(
        UPLOAD_PATH,
        0755,
        true
    );
}