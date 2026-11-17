<?php
// ============================================================
// FILE: api/ubah_password.php - API Ubah Password (semua role)
// ============================================================
require_once '../includes/config.php';

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();

// Harus login
if (empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Sesi tidak valid. Silakan login ulang.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method tidak diizinkan.']);
    exit;
}

$userId      = (int)$_SESSION['user_id'];
$pwLama      = $_POST['password_lama']    ?? '';
$pwBaru      = $_POST['password_baru']    ?? '';
$pwKonfirmasi = $_POST['password_konfirmasi'] ?? '';

if (empty($pwLama) || empty($pwBaru) || empty($pwKonfirmasi)) {
    echo json_encode(['success' => false, 'message' => 'Semua field wajib diisi.']);
    exit;
}

if (strlen($pwBaru) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password baru minimal 6 karakter.']);
    exit;
}

if ($pwBaru !== $pwKonfirmasi) {
    echo json_encode(['success' => false, 'message' => 'Konfirmasi password tidak cocok.']);
    exit;
}

$stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user || !password_verify($pwLama, $user['password'])) {
    echo json_encode(['success' => false, 'message' => 'Password lama tidak sesuai.']);
    exit;
}

$pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
    ->execute([password_hash($pwBaru, PASSWORD_DEFAULT), $userId]);

echo json_encode(['success' => true, 'message' => 'Password berhasil diubah.']);
