<?php
// ============================================================
// FILE: siswa/ubah_password.php - Ubah Password Siswa
// ============================================================
require_once '../includes/config.php';
requireLogin('siswa');

$msg = ''; $error = '';
$userId = $_SESSION['user_id'];

$isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verifikasi CSRF token (lewati jika AJAX dari dashboard — token sudah diverifikasi header)
    if (!$isAjax) {
        verifyCsrf();
    } else {
        // Untuk AJAX: verifikasi token yang dikirim manual
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Request tidak valid (CSRF).']);
            exit;
        }
    }

    $pwLama    = $_POST['password_lama'] ?? '';
    $pwBaru    = $_POST['password_baru'] ?? '';
    $pwKonfirm = $_POST['konfirm_password'] ?? '';

    $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!password_verify($pwLama, $user['password'])) {
        $error = 'Password lama tidak sesuai.';
    } elseif (strlen($pwBaru) < 6) {
        $error = 'Password baru minimal 6 karakter.';
    } elseif ($pwBaru !== $pwKonfirm) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")
            ->execute([password_hash($pwBaru, PASSWORD_DEFAULT), $userId]);
        $msg = 'Password berhasil diubah.';
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        if ($error) echo json_encode(['status'=>'error','message'=>$error]);
        else echo json_encode(['status'=>'success','message'=>$msg]);
        exit;
    }
}

$pageTitle = 'Ubah Password';
require_once '../includes/header.php';
?>
<?php if ($msg): ?><div class="alert alert-success">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/></svg>
  <?= $msg ?>
</div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg>
  <?= $error ?>
</div><?php endif; ?>

<div class="card" style="max-width:440px">
  <div class="card-header">
    <div class="card-title">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
      Ubah Password Akun
    </div>
  </div>
  <div class="card-body">
    <div class="alert alert-info" style="margin-bottom:18px">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
      Gunakan password yang kuat. Minimal 6 karakter.
    </div>
    <form method="POST">
      <?= csrfField() ?>
      <div class="form-group">
        <label class="form-label">Password Lama *</label>
        <input type="password" name="password_lama" class="form-control" placeholder="Masukkan password saat ini" required>
      </div>
      <div class="form-group">
        <label class="form-label">Password Baru *</label>
        <input type="password" name="password_baru" class="form-control" placeholder="Min. 6 karakter" required minlength="6">
      </div>
      <div class="form-group">
        <label class="form-label">Konfirmasi Password Baru *</label>
        <input type="password" name="konfirm_password" class="form-control" placeholder="Ulangi password baru" required minlength="6">
      </div>
      <button type="submit" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
        Simpan Password Baru
      </button>
    </form>
  </div>
</div>
<?php require_once '../includes/footer.php'; ?>
