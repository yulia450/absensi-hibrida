<?php
// TARUH FILE INI DI ROOT PROJECT (sejajar dengan index.php)
// Buka: http://localhost/absensi/debug_login2.php
// Isi form persis kayak kamu isi form login asli, lalu submit.
// HAPUS FILE INI SETELAH SELESAI DEBUG.

require_once __DIR__ . '/includes/config.php';

$result = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailRaw = $_POST['email'] ?? '';
    $passRaw  = $_POST['password'] ?? '';

    $result .= "== RAW INPUT (sebelum sanitize) ==\n";
    $result .= "Email raw  : '" . $emailRaw . "'\n";
    $result .= "Email hex  : " . bin2hex($emailRaw) . "\n";
    $result .= "Email len  : " . strlen($emailRaw) . "\n\n";

    $result .= "Password raw : '" . $passRaw . "'\n";
    $result .= "Password hex : " . bin2hex($passRaw) . "\n";
    $result .= "Password len : " . strlen($passRaw) . "\n\n";

    $email = sanitize($emailRaw);
    $result .= "Email setelah sanitize(): '" . $email . "'\n\n";

    $stmt = $pdo->prepare("SELECT id, email, password, status, role FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        $result .= "❌ Query tidak menemukan user dengan email ini.\n";
    } else {
        $result .= "✅ User ditemukan: ID {$user['id']}, role {$user['role']}, status {$user['status']}\n";
        $result .= "Hash di DB: {$user['password']}\n\n";
        $ok = password_verify($passRaw, $user['password']);
        $result .= "password_verify(): " . ($ok ? "✅ COCOK" : "❌ TIDAK COCOK") . "\n";
        if ($user['status'] !== 'aktif') {
            $result .= "⚠️ PERHATIAN: status akun bukan 'aktif', ini juga bikin login gagal walau password benar!\n";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><title>Debug Login</title></head>
<body style="font-family:sans-serif;max-width:600px;margin:40px auto;">
<h2>Debug Login Tester</h2>
<form method="POST">
  <label>Email:<br><input type="email" name="email" style="width:100%;padding:8px;" value="admin@smkalmanshuriyah.sch.id"></label><br><br>
  <label>Password:<br><input type="text" name="password" style="width:100%;padding:8px;" placeholder="ketik password di sini"></label><br><br>
  <button type="submit" style="padding:10px 20px;">Tes Login</button>
</form>
<?php if ($result): ?>
<pre style="background:#f4f4f4;padding:16px;margin-top:20px;white-space:pre-wrap;"><?= htmlspecialchars($result) ?></pre>
<?php endif; ?>
</body>
</html>