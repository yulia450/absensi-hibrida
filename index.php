<?php
require_once 'includes/config.php';
if (isLoggedIn()) {
    $role = getRole();
    redirect($role . '/dashboard.php');
}
$error = '';
$msg   = $_GET['msg'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    if (empty($email) || empty($password)) {
        $error = 'Email dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare("SELECT u.*, s.id as siswa_id FROM users u LEFT JOIN siswa s ON u.id = s.user_id WHERE u.email = ? AND u.status = 'aktif'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true); // [FIX] Cegah session fixation attack
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama']    = $user['nama'];
            $_SESSION['email']   = $user['email'];
            $_SESSION['role']    = $user['role'];
            $_SESSION['foto']    = $user['foto'];
            if ($user['role'] === 'siswa') $_SESSION['siswa_id'] = $user['siswa_id'];
            if ($user['role'] === 'petugas') {
                $stmtKelas = $pdo->prepare("SELECT kelas_id FROM users WHERE id = ?");
                $stmtKelas->execute([$user['id']]);
                $_SESSION['kelas_id'] = $stmtKelas->fetchColumn() ?: null;
            }
            redirect($user['role'] . '/dashboard.php');
        } else {
            $error = 'Email atau password salah, atau akun tidak aktif.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Masuk — Sistem Absensi SMK AL-MANSHURIYAH</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
@keyframes fadeUp { from{opacity:0;transform:translateY(24px)} to{opacity:1;transform:translateY(0)} }
.login-panel-right { animation: fadeUp .5s ease; }
</style>
</head>
<body>
<div class="login-wrapper">
  <!-- Left panel: branding -->
  <div class="login-panel-left">
    <div class="login-brand">
      <div class="login-brand-icon">
        <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.9)" stroke-width="1.6">
          <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
          <path d="M6 12v5c3 3 9 3 12 0v-5"/>
        </svg>
      </div>
      <h1>SMK AL-MANSHURIYAH</h1>
      <p>Sistem Informasi Absensi Hibrida dengan Geolokasi dan Analisis K-Means</p>
      <div class="login-features">
        <div class="login-feature">
          <div class="login-feature-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.8)" stroke-width="1.8">
              <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
            </svg>
          </div>
          <div>
            <strong>Absensi Real-Time</strong>
            <span>Rekam kehadiran secara langsung</span>
          </div>
        </div>
        <div class="login-feature">
          <div class="login-feature-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.8)" stroke-width="1.8">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/>
            </svg>
          </div>
          <div>
            <strong>Verifikasi Geolokasi</strong>
            <span>Validasi lokasi otomatis</span>
          </div>
        </div>
        <div class="login-feature">
          <div class="login-feature-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.8)" stroke-width="1.8">
              <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
            </svg>
          </div>
          <div>
            <strong>Analisis K-Means</strong>
            <span>Klasterisasi pola kehadiran</span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Right panel: form -->
  <div class="login-panel-right">
    <div class="login-form-wrap">
      <h2>Masuk ke Sistem</h2>
      <p class="login-sub">Silakan masukkan kredensial akun Anda</p>

      <?php if ($error): ?>
      <div class="alert alert-danger">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>
      <?php if ($msg === 'login_required'): ?>
      <div class="alert alert-warning">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        Silakan login terlebih dahulu untuk mengakses halaman tersebut.
      </div>
      <?php elseif ($msg === 'unauthorized'): ?>
      <div class="alert alert-danger">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Akses tidak diizinkan untuk akun Anda.
      </div>
      <?php elseif ($msg === 'logout'): ?>
      <div class="alert alert-success">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
        Berhasil keluar dari sistem.
      </div>
      <?php endif; ?>

      <form method="POST" action="">
        <div class="form-group">
          <label class="form-label">Alamat Email</label>
          <input type="email" name="email" class="form-control"
                 placeholder="nama@domain.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                 required autofocus>
        </div>
        <div class="form-group">
          <label class="form-label">Kata Sandi</label>
          <div class="password-toggle">
            <input type="password" name="password" id="password-input" class="form-control"
                   placeholder="Masukkan kata sandi" required>
            <button type="button" class="toggle-btn" onclick="togglePassword()" title="Tampilkan/sembunyikan">
              <svg id="eye-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top:4px">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
          Masuk ke Sistem
        </button>
      </form>
      </div>
  </div>
</div>
<script>
function togglePassword() {
  const inp = document.getElementById('password-input');
  inp.type = inp.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
