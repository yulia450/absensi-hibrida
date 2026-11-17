<?php
// ============================================================
// FILE: api/auto_alfa.php
// Endpoint untuk Auto-Alfa (dipanggil via cron job atau manual)
// Jalankan setiap hari setelah jam pulang sekolah
//
// Setup Cron Job di cPanel / server Linux:
// 0 15 * * 1-5 curl -s http://localhost/absensi-smk/api/auto_alfa.php?token=SECRET
//
// Artinya: setiap pukul 15:00, Senin-Jumat
// ============================================================
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Keamanan sederhana: token
$token = $_GET['token'] ?? '';
$validToken = 'smk_islamiyah_auto_alfa_2024'; // Ganti token ini

// Izinkan juga dari command line (CLI)
if (PHP_SAPI !== 'cli' && $token !== $validToken) {
    echo json_encode(['status' => 'error', 'message' => 'Token tidak valid']);
    exit;
}

$tanggal = $_GET['tanggal'] ?? date('Y-m-d');
$result = prosesAutoAlfa($pdo, $tanggal);
echo json_encode($result);
