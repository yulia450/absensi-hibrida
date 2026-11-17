<?php
// ============================================================
// FILE: admin/import_libur_nasional.php
// Import hari libur nasional Indonesia ke tabel hari_libur
// ============================================================
require_once '../includes/config.php';
requireLogin('admin');
header('Content-Type: application/json');

// Validasi token CSRF sederhana via GET
$token = $_GET['token'] ?? '';
if (!hash_equals(csrfToken(), $token)) {
    echo json_encode(['status'=>'error','message'=>'Token tidak valid']);
    exit;
}

$tahun = (int)($_GET['tahun'] ?? date('Y'));
if ($tahun < 2020 || $tahun > 2030) {
    echo json_encode(['status'=>'error','message'=>'Tahun tidak valid']);
    exit;
}

// Daftar hari libur nasional Indonesia (bisa diperluas setiap tahun)
$liburNasional = [
    2025 => [
        ['2025-01-01', 'Tahun Baru Masehi'],
        ['2025-01-27', 'Isra Mi\'raj Nabi Muhammad SAW'],
        ['2025-01-29', 'Tahun Baru Imlek 2576'],
        ['2025-03-29', 'Hari Raya Nyepi (Tahun Baru Saka 1947)'],
        ['2025-03-31', 'Idul Fitri 1446 H'],
        ['2025-04-01', 'Idul Fitri 1446 H'],
        ['2025-04-18', 'Wafat Yesus Kristus'],
        ['2025-04-20', 'Kebangkitan Yesus Kristus'],
        ['2025-05-01', 'Hari Buruh Internasional'],
        ['2025-05-12', 'Hari Raya Waisak 2569'],
        ['2025-05-29', 'Kenaikan Yesus Kristus'],
        ['2025-06-01', 'Hari Lahir Pancasila'],
        ['2025-06-06', 'Idul Adha 1446 H'],
        ['2025-06-27', 'Tahun Baru Islam 1447 H'],
        ['2025-08-17', 'Hari Kemerdekaan Republik Indonesia'],
        ['2025-09-05', 'Maulid Nabi Muhammad SAW'],
        ['2025-12-25', 'Hari Raya Natal'],
        ['2025-12-26', 'Cuti Bersama Natal'],
    ],
    2026 => [
        ['2026-01-01', 'Tahun Baru Masehi'],
        ['2026-01-17', 'Isra Mi\'raj Nabi Muhammad SAW'],
        ['2026-02-17', 'Tahun Baru Imlek 2577'],
        ['2026-03-19', 'Hari Raya Nyepi (Tahun Baru Saka 1948)'],
        ['2026-03-20', 'Idul Fitri 1447 H'],
        ['2026-03-21', 'Idul Fitri 1447 H'],
        ['2026-04-03', 'Wafat Yesus Kristus'],
        ['2026-04-05', 'Kebangkitan Yesus Kristus'],
        ['2026-05-01', 'Hari Buruh Internasional'],
        ['2026-05-14', 'Kenaikan Yesus Kristus'],
        ['2026-05-22', 'Hari Raya Waisak 2570'],
        ['2026-05-27', 'Idul Adha 1447 H'],
        ['2026-06-01', 'Hari Lahir Pancasila'],
        ['2026-06-16', 'Tahun Baru Islam 1448 H'],
        ['2026-08-17', 'Hari Kemerdekaan Republik Indonesia'],
        ['2026-08-25', 'Maulid Nabi Muhammad SAW'],
        ['2026-12-25', 'Hari Raya Natal'],
    ],
    2027 => [
        ['2027-01-01', 'Tahun Baru Masehi'],
        ['2027-01-06', 'Isra Mi\'raj Nabi Muhammad SAW'],
        ['2027-02-06', 'Tahun Baru Imlek 2578'],
        ['2027-03-09', 'Hari Raya Nyepi (Tahun Baru Saka 1949)'],
        ['2027-03-10', 'Idul Fitri 1448 H'],
        ['2027-03-11', 'Idul Fitri 1448 H'],
        ['2027-03-26', 'Wafat Yesus Kristus'],
        ['2027-03-28', 'Kebangkitan Yesus Kristus'],
        ['2027-05-01', 'Hari Buruh Internasional'],
        ['2027-05-06', 'Kenaikan Yesus Kristus'],
        ['2027-05-16', 'Idul Adha 1448 H'],
        ['2027-05-30', 'Hari Raya Waisak 2571'],
        ['2027-06-01', 'Hari Lahir Pancasila'],
        ['2027-06-06', 'Tahun Baru Islam 1449 H'],
        ['2027-08-15', 'Maulid Nabi Muhammad SAW'],
        ['2027-08-17', 'Hari Kemerdekaan Republik Indonesia'],
        ['2027-12-25', 'Hari Raya Natal'],
    ],
];

// Gunakan tahun yang diminta, atau fallback ke 2026
$daftarLibur = $liburNasional[$tahun] ?? [];

if (empty($daftarLibur)) {
    echo json_encode(['status'=>'info','message'=>"Data libur nasional untuk tahun $tahun belum tersedia. Silakan tambah manual."]);
    exit;
}

$ditambah = 0;
$dilewati = 0;

try {
    foreach ($daftarLibur as [$tanggal, $keterangan]) {
        // Cek apakah sudah ada
        $cek = $pdo->prepare("SELECT id FROM hari_libur WHERE tanggal = ?");
        $cek->execute([$tanggal]);
        if ($cek->fetch()) {
            $dilewati++;
            continue;
        }
        $pdo->prepare("INSERT INTO hari_libur (tanggal, keterangan, jenis) VALUES (?, ?, 'nasional')")
            ->execute([$tanggal, $keterangan]);
        $ditambah++;
    }

    auditLog($pdo, 'IMPORT_LIBUR_NASIONAL', "tahun=$tahun ditambah=$ditambah dilewati=$dilewati");

    $pesan = "$ditambah hari libur nasional berhasil ditambahkan";
    if ($dilewati > 0) $pesan .= " ($dilewati sudah ada, dilewati)";
    $pesan .= " untuk tahun $tahun.";

    echo json_encode(['status'=>'success','message'=>$pesan]);
} catch (PDOException $e) {
    echo json_encode(['status'=>'error','message'=>'Error database: ' . $e->getMessage()]);
}
