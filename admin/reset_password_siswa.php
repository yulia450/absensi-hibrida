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

// Verifikasi CSRF token pada setiap aksi sensitif
$csrfToken = $_POST['csrf_token'] ?? '';
if (!hash_equals(csrfToken(), $csrfToken)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Token tidak valid. Muat ulang halaman dan coba lagi.']);
    exit;
}

$resetSemua = isset($_POST['reset_semua']) && $_POST['reset_semua'] === '1';
$siswaId    = isset($_POST['siswa_id']) ? (int) $_POST['siswa_id'] : 0;

if (!$resetSemua && $siswaId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Data tidak valid.']);
    exit;
}

try {
    if ($resetSemua) {
        $stmt = $pdo->query("
            SELECT u.id AS user_id, s.nis
            FROM users u
            JOIN siswa s ON s.user_id = u.id
            WHERE u.role = 'siswa'
        ");
        $siswas = $stmt->fetchAll();

        if (empty($siswas)) {
            echo json_encode(['status' => 'error', 'message' => 'Tidak ada data siswa.']);
            exit;
        }

        $stmtUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $berhasil = 0;
        $gagal    = 0;

        foreach ($siswas as $s) {
            $pw = buildDefaultPassword($s['nis']);
            if ($pw === null) {
                $gagal++;
                continue;
            }
            $stmtUpdate->execute([password_hash($pw, PASSWORD_DEFAULT), $s['user_id']]);
            $berhasil++;
        }

        $msg = "Berhasil reset {$berhasil} siswa ke password 4 digit NIS terakhir@alman.";
        if ($gagal > 0) $msg .= " {$gagal} siswa dilewati (NIS kosong).";
        echo json_encode(['status' => 'success', 'message' => $msg]);

    } else {
        $stmt = $pdo->prepare("
            SELECT u.id AS user_id, s.nis, u.nama
            FROM users u
            JOIN siswa s ON s.user_id = u.id
            WHERE s.id = ? AND u.role = 'siswa'
        ");
        $stmt->execute([$siswaId]);
        $s = $stmt->fetch();

        if (!$s) {
            echo json_encode(['status' => 'error', 'message' => 'Siswa tidak ditemukan.']);
            exit;
        }

        $pw = buildDefaultPassword($s['nis']);
        if ($pw === null) {
            echo json_encode(['status' => 'error', 'message' => 'NIS siswa kosong, tidak bisa generate password.']);
            exit;
        }

        $stmtUpdate = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmtUpdate->execute([password_hash($pw, PASSWORD_DEFAULT), $s['user_id']]);

        echo json_encode([
            'status'  => 'success',
            'message' => "Password {$s['nama']} berhasil direset ke: {$pw}",
        ]);
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan: ' . $e->getMessage()]);
}

/**
 * Generate password default: 4 digit terakhir NIS + @alman
 * Contoh: NIS=2324012345 -> 2345@alman
 */
function buildDefaultPassword(?string $nis): ?string
{
    if (empty($nis)) return null;
    $last4 = substr($nis, -4);
    return $last4 . '@alman';
}