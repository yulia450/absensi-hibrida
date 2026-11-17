<?php
// Mulai session sebelum output apapun
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek login dulu sebelum require config (hindari redirect)
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak.']);
    exit;
}

require_once '../includes/config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method tidak valid.']);
    exit;
}

// Verifikasi CSRF token
$csrfToken = $_POST['csrf_token'] ?? '';
if (!hash_equals(csrfToken(), $csrfToken)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Token tidak valid. Muat ulang halaman dan coba lagi.']);
    exit;
}

$petugasId = isset($_POST['petugas_id']) ? (int) $_POST['petugas_id'] : 0;

if ($petugasId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak valid.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, nama FROM users WHERE id = ? AND role = 'petugas'");
    $stmt->execute([$petugasId]);
    $p = $stmt->fetch();

    if (!$p) {
        echo json_encode(['status' => 'error', 'message' => 'Petugas tidak ditemukan.']);
        exit;
    }

    $pw = buildDefaultPasswordPetugas($p['nama']);

    $stmtUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE id = ? AND role = 'petugas'");
    $stmtUpdate->execute([password_hash($pw, PASSWORD_DEFAULT), $petugasId]);

    echo json_encode([
        'status'  => 'success',
        'message' => "Password {$p['nama']} berhasil direset ke: {$pw}",
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
}

/**
 * Generate password default petugas: alman@namadepan
 * Nama depan diambil dari kata pertama nama lengkap, lowercase, tanpa karakter spesial.
 * Contoh: Ahmad Fauzi → alman@ahmad
 */
function buildDefaultPasswordPetugas(string $nama): string
{
    $parts     = explode(' ', trim($nama));
    $namadepan = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $parts[0]));
    return 'alman@' . $namadepan;
}