<?php
$role        = $_SESSION['role'] ?? 'siswa';
$nama        = $_SESSION['nama'] ?? 'Pengguna';
$currentPage = basename($_SERVER['PHP_SELF']);

$icons = [
  'dashboard'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>',
  'students'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>',
  'class'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
  'officer'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
  'attendance' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>',
  'report'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>',
  'permit'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
  'analytics'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>',
  'settings'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>',
  'input'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><line x1="15" y1="3" x2="21" y2="3"/><line x1="21" y1="3" x2="21" y2="9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>',
  'calendar'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
  'history'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
  'home'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
];

$navItems = [
  'admin' => [
    ['icon'=>'dashboard', 'label'=>'Dashboard',         'href'=>'dashboard.php'],
    ['icon'=>'students',  'label'=>'Data Siswa',        'href'=>'siswa.php'],
    ['icon'=>'class',     'label'=>'Data Kelas',        'href'=>'kelas.php'],
    ['icon'=>'history',   'label'=>'Data Alumni',       'href'=>'siswa.php?status=nonaktif'],
    ['icon'=>'officer',   'label'=>'Data Petugas',      'href'=>'petugas.php'],
    ['icon'=>'attendance','label'=>'Absensi Hari Ini',  'href'=>'absensi.php'],
    ['icon'=>'report',    'label'=>'Rekap Absensi',     'href'=>'rekap.php'],
    ['icon'=>'permit',    'label'=>'Pengajuan Izin',    'href'=>'izin.php'],
    ['icon'=>'analytics', 'label'=>'Analisis K-Means', 'href'=>'clustering.php'],
    ['icon'=>'calendar',  'label'=>'Hari Libur',        'href'=>'hari_libur.php'],
    ['icon'=>'settings',  'label'=>'Pengaturan',        'href'=>'settings.php'],
  ],
  'petugas' => [
    ['icon'=>'dashboard', 'label'=>'Dashboard',         'href'=>'dashboard.php'],
    ['icon'=>'attendance','label'=>'Absensi Hari Ini',  'href'=>'absensi.php'],
    ['icon'=>'input',     'label'=>'Input Absensi',     'href'=>'input_absensi.php'],
    ['icon'=>'permit',    'label'=>'Pengajuan Izin',    'href'=>'izin.php'],
    ['icon'=>'report',    'label'=>'Rekap Absensi',     'href'=>'rekap.php'],
    ['icon'=>'analytics', 'label'=>'Analisis Kehadiran','href'=>'clustering.php'],
  ],
  'siswa' => [
    ['icon'=>'home',      'label'=>'Beranda',           'href'=>'dashboard.php'],
    ['icon'=>'attendance','label'=>'Absensi',           'href'=>'absensi.php'],
    ['icon'=>'permit',    'label'=>'Ajukan Izin/Sakit', 'href'=>'izin.php'],
    ['icon'=>'history',   'label'=>'Riwayat Absensi',  'href'=>'riwayat.php'],
    ['icon'=>'report',    'label'=>'Rekap Bulanan',     'href'=>'rekap.php'],
    ['icon'=>'calendar',  'label'=>'Riwayat Izin',     'href'=>'riwayat_izin.php'],
  ],
];

$nav     = $navItems[$role] ?? [];
$baseUrl = BASE_URL . $role . '/';

// Badge untuk siswa: hitung izin menunggu
$badgeCounts = [];
if ($role === 'siswa' && isset($_SESSION['siswa_id'])) {
  global $pdo;
  $bStmt = $pdo->prepare("SELECT COUNT(*) FROM pengajuan_izin WHERE siswa_id=? AND status='menunggu'");
  $bStmt->execute([$_SESSION['siswa_id']]);
  $jmlMenunggu = (int)$bStmt->fetchColumn();
  if ($jmlMenunggu > 0) $badgeCounts['riwayat_izin.php'] = $jmlMenunggu;
}
// Badge untuk admin/petugas: hitung izin menunggu
if (in_array($role, ['admin','petugas'])) {
  global $pdo;
  $bStmt = $pdo->query("SELECT COUNT(*) FROM pengajuan_izin WHERE status='menunggu'");
  $jmlMenunggu = (int)$bStmt->fetchColumn();
  if ($jmlMenunggu > 0) $badgeCounts['izin.php'] = $jmlMenunggu;
}
?>
<nav class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="sidebar-brand-icon">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.9)" stroke-width="1.8">
        <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
        <path d="M6 12v5c3 3 9 3 12 0v-5"/>
      </svg>
    </div>
    <div>
      <div class="school-name">SMK AL-MANSHURIYAH</div>
      <div class="system-name">Sistem Absensi Hibrida</div>
    </div>
  </div>
  <div class="sidebar-user sidebar-user-dropdown" id="user-profile-btn" onclick="toggleUserDropdown(event)">
    <div class="sidebar-user-avatar">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.8)" stroke-width="1.8">
        <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>
      </svg>
    </div>
    <div style="flex:1;min-width:0;">
      <div class="user-name"><?= htmlspecialchars($nama) ?></div>
      <div class="user-role"><?= $role === 'admin' ? 'Administrator' : ($role === 'petugas' ? 'Petugas / Guru' : 'Siswa') ?></div>
    </div>
    <svg class="user-dropdown-chevron" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.5)" stroke-width="2.5">
      <polyline points="6 9 12 15 18 9"/>
    </svg>
    <!-- Dropdown Menu -->
    <div class="user-dropdown-menu" id="user-dropdown-menu">
      <button class="user-dropdown-item" onclick="openUbahPasswordModal(event)">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/>
        </svg>
        Ubah Password
      </button>
      <div class="user-dropdown-divider"></div>
      <a class="user-dropdown-item user-dropdown-item--danger" href="<?= BASE_URL ?>logout.php">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
          <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/>
          <polyline points="16 17 21 12 16 7"/>
          <line x1="21" y1="12" x2="9" y2="12"/>
        </svg>
        Keluar
      </a>
    </div>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section-title">Menu Utama</div>
    <?php foreach ($nav as $item): ?>
    <div class="nav-item">
      <a href="<?= $baseUrl . $item['href'] ?>" class="nav-link <?= $currentPage === $item['href'] ? 'active' : '' ?>">
        <span style="width:18px;height:18px;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;">
          <?= $icons[$item['icon']] ?? '' ?>
        </span>
        <?= $item['label'] ?>
        <?php if (!empty($badgeCounts[$item['href']])): ?>
          <span style="margin-left:auto;background:#ef4444;color:#fff;font-size:10px;font-weight:800;padding:1px 6px;border-radius:10px;min-width:18px;text-align:center">
            <?= $badgeCounts[$item['href']] ?>
          </span>
        <?php endif; ?>
      </a>
    </div>
    <?php endforeach; ?>
  </nav>
</nav>
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<!-- Modal Ubah Password -->
<div class="modal-overlay" id="modal-ubah-password" style="display:none;">
  <div class="modal-box" style="max-width:400px;">
    <div class="modal-header">
      <span class="modal-title">Ubah Password</span>
      <button class="modal-close" onclick="closeUbahPasswordModal()">&times;</button>
    </div>
    <div class="modal-body">
      <form id="form-ubah-password" onsubmit="submitUbahPassword(event)">
        <div class="form-group" style="margin-bottom:14px;">
          <label class="form-label">Password Lama</label>
          <div class="pw-input-wrap">
            <input type="password" name="password_lama" id="inp-pw-lama" class="form-control" placeholder="Masukkan password lama" required autocomplete="current-password">
            <button type="button" class="pw-toggle-btn" onclick="togglePwVisibility('inp-pw-lama', this)" tabindex="-1" aria-label="Tampilkan password">
              <svg class="eye-icon eye-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="eye-icon eye-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
        </div>
        <div class="form-group" style="margin-bottom:14px;">
          <label class="form-label">Password Baru</label>
          <div class="pw-input-wrap">
            <input type="password" name="password_baru" id="inp-pw-baru" class="form-control" placeholder="Minimal 6 karakter" required autocomplete="new-password">
            <button type="button" class="pw-toggle-btn" onclick="togglePwVisibility('inp-pw-baru', this)" tabindex="-1" aria-label="Tampilkan password">
              <svg class="eye-icon eye-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="eye-icon eye-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
        </div>
        <div class="form-group" style="margin-bottom:20px;">
          <label class="form-label">Konfirmasi Password Baru</label>
          <div class="pw-input-wrap">
            <input type="password" name="password_konfirmasi" id="inp-pw-konfirmasi" class="form-control" placeholder="Ulangi password baru" required autocomplete="new-password">
            <button type="button" class="pw-toggle-btn" onclick="togglePwVisibility('inp-pw-konfirmasi', this)" tabindex="-1" aria-label="Tampilkan password">
              <svg class="eye-icon eye-show" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
              <svg class="eye-icon eye-hide" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
            </button>
          </div>
        </div>
        <div id="ubah-pw-alert" style="display:none;margin-bottom:12px;"></div>
        <div style="display:flex;gap:10px;justify-content:flex-end;">
          <button type="button" class="btn btn-secondary" onclick="closeUbahPasswordModal()">Batal</button>
          <button type="submit" class="btn btn-primary" id="btn-submit-pw">Simpan</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// --- Password Visibility Toggle ---
function togglePwVisibility(inputId, btn) {
  var input   = document.getElementById(inputId);
  var eyeShow = btn.querySelector('.eye-show');
  var eyeHide = btn.querySelector('.eye-hide');
  if (input.type === 'password') {
    input.type = 'text';
    eyeShow.style.display = 'none';
    eyeHide.style.display = 'inline';
    btn.setAttribute('aria-label', 'Sembunyikan password');
  } else {
    input.type = 'password';
    eyeShow.style.display = 'inline';
    eyeHide.style.display = 'none';
    btn.setAttribute('aria-label', 'Tampilkan password');
  }
}

// --- User Profile Dropdown ---
function toggleUserDropdown(e) {
  e.stopPropagation();
  var menu = document.getElementById('user-dropdown-menu');
  var btn  = document.getElementById('user-profile-btn');
  var open = menu.classList.contains('open');
  closeUserDropdown();
  if (!open) {
    menu.classList.add('open');
    btn.classList.add('active');
  }
}
function closeUserDropdown() {
  var menu = document.getElementById('user-dropdown-menu');
  var btn  = document.getElementById('user-profile-btn');
  if (menu) { menu.classList.remove('open'); }
  if (btn)  { btn.classList.remove('active'); }
}
document.addEventListener('click', function() { closeUserDropdown(); });

// --- Modal Ubah Password ---
function openUbahPasswordModal(e) {
  e.stopPropagation();
  closeUserDropdown();
  document.getElementById('form-ubah-password').reset();
  var alert = document.getElementById('ubah-pw-alert');
  alert.style.display = 'none';
  alert.className = '';
  alert.textContent = '';
  document.getElementById('modal-ubah-password').style.display = 'flex';
}
function closeUbahPasswordModal() {
  document.getElementById('modal-ubah-password').style.display = 'none';
}
document.getElementById('modal-ubah-password').addEventListener('click', function(e) {
  if (e.target === this) closeUbahPasswordModal();
});

async function submitUbahPassword(e) {
  e.preventDefault();
  var lama       = document.getElementById('inp-pw-lama').value;
  var baru       = document.getElementById('inp-pw-baru').value;
  var konfirmasi = document.getElementById('inp-pw-konfirmasi').value;
  var alertBox   = document.getElementById('ubah-pw-alert');
  var btn        = document.getElementById('btn-submit-pw');

  function showAlert(msg, type) {
    alertBox.style.display = 'block';
    alertBox.className = 'alert alert-' + type;
    alertBox.textContent = msg;
  }

  if (baru.length < 6) { showAlert('Password baru minimal 6 karakter.', 'danger'); return; }
  if (baru !== konfirmasi) { showAlert('Konfirmasi password tidak cocok.', 'danger'); return; }

  btn.disabled = true;
  btn.textContent = 'Menyimpan...';

  try {
    var formData = new FormData();
    formData.append('action', 'ubah_password');
    formData.append('password_lama', lama);
    formData.append('password_baru', baru);
    formData.append('password_konfirmasi', konfirmasi);
    var res  = await fetch(window.BASE_URL + 'api/ubah_password.php', { method: 'POST', body: formData });
    var data = await res.json();
    if (data.success) {
      showAlert(data.message || 'Password berhasil diubah.', 'success');
      document.getElementById('form-ubah-password').reset();
      setTimeout(closeUbahPasswordModal, 1800);
    } else {
      showAlert(data.message || 'Gagal mengubah password.', 'danger');
    }
  } catch(err) {
    showAlert('Terjadi kesalahan. Silakan coba lagi.', 'danger');
  }

  btn.disabled = false;
  btn.textContent = 'Simpan';
}
</script>