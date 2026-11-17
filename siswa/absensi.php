<?php
// ============================================================
// FILE: siswa/absensi.php - Halaman Absensi Siswa
// ============================================================
require_once '../includes/config.php';
requireLogin('siswa');

$siswaId  = $_SESSION['siswa_id'];
$settings = getSettings($pdo);
$today    = date('Y-m-d');

// Data siswa
$stmtSiswa = $pdo->prepare("SELECT s.*, u.nama, k.nama_kelas FROM siswa s JOIN users u ON s.user_id = u.id LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ?");
$stmtSiswa->execute([$siswaId]);
$siswa = $stmtSiswa->fetch();

// Absensi hari ini
$stmtHari = $pdo->prepare("SELECT * FROM absensi WHERE siswa_id = ? AND tanggal = ?");
$stmtHari->execute([$siswaId, $today]);
$absenHari = $stmtHari->fetch();

$sudahMasuk  = $absenHari && $absenHari['waktu_masuk'];
$sudahPulang = $absenHari && $absenHari['waktu_pulang'] && $absenHari['status_pulang'] === 'pulang';

$pageTitle = 'Absensi';
require_once '../includes/header.php';
?>

<div class="card mb-3" style="background:linear-gradient(135deg,var(--primary) 0%,var(--primary-light) 100%);color:#fff;border:none">
  <div class="card-body" style="text-align:center">
    <div style="font-size:14px;opacity:.8"><?= formatTanggal($today) ?></div>
    <div style="font-size:18px;font-weight:900;margin-top:4px"><?= htmlspecialchars($siswa['nama']) ?></div>
    <div style="font-size:13px;opacity:.8"><?= htmlspecialchars($siswa['nama_kelas'] ?? '-') ?> | NIS: <?= $siswa['nis'] ?></div>
  </div>
</div>

<!-- Info Jam Sekolah -->
<div class="card mb-3">
  <div class="card-body" style="display:flex;gap:16px;flex-wrap:wrap;justify-content:center">
    <div style="text-align:center;padding:12px 24px;background:var(--bg);border-radius:10px">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);letter-spacing:1px">Jam Masuk</div>
      <div style="font-family:var(--font-mono);font-size:22px;font-weight:700;color:var(--primary)"><?= $settings['jam_masuk'] ?></div>
    </div>
    <div style="text-align:center;padding:12px 24px;background:var(--bg);border-radius:10px">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);letter-spacing:1px">Batas Terlambat</div>
      <div style="font-family:var(--font-mono);font-size:22px;font-weight:700;color:var(--warning)"><?= $settings['batas_terlambat'] ?></div>
    </div>
    <div style="text-align:center;padding:12px 24px;background:var(--bg);border-radius:10px">
      <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);letter-spacing:1px">Jam Pulang</div>
      <div style="font-family:var(--font-mono);font-size:22px;font-weight:700;color:var(--info)"><?= $settings['jam_pulang'] ?></div>
    </div>
  </div>
</div>

<!-- Absensi Cards -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px" class="absen-main-grid">

  <!-- Card Masuk -->
  <div class="card" style="border-top:4px solid <?= $sudahMasuk ? 'var(--success)' : 'var(--border)' ?>">
    <div class="card-header">
      <div class="card-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
        Absensi Masuk
      </div>
    </div>
    <div class="card-body" style="text-align:center">
      <?php if ($sudahMasuk): ?>
        <div style="width:64px;height:64px;background:var(--success-bg);border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="1.8"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
        </div>
        <div style="font-family:var(--font-mono);font-size:32px;font-weight:900;color:var(--success)"><?= $absenHari['waktu_masuk'] ?></div>
        <div style="margin:8px 0"><?= getStatusBadge($absenHari['status_masuk']) ?></div>
        <?php if ($absenHari['lat_masuk']): ?>
        <div style="font-size:11px;color:var(--text-muted);font-family:var(--font-mono);background:var(--bg);padding:6px;border-radius:6px;margin-top:8px">
          <?= $absenHari['lat_masuk'] ?>, <?= $absenHari['lng_masuk'] ?>
        </div>
        <?php endif; ?>
        <div style="margin-top:12px;font-size:13px;color:var(--success);font-weight:600">Absensi masuk sudah dicatat</div>
      <?php else: ?>
        <div style="width:64px;height:64px;background:var(--success-bg);border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--success)" stroke-width="1.8"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
        </div>
        <div style="color:var(--text-muted);font-size:14px;margin-bottom:20px">
          Tekan tombol di bawah untuk absensi masuk.<br>
          Pastikan Anda berada di area sekolah.
        </div>
        <button class="btn btn-success btn-block btn-lg" id="btn-absen-masuk" onclick="prosesAbsensi('masuk')">
          Absensi Masuk Sekarang
        </button>
      <?php endif; ?>
    </div>
  </div>

  <!-- Card Pulang -->
  <div class="card" style="border-top:4px solid <?= $sudahPulang ? 'var(--info)' : 'var(--border)' ?>">
    <div class="card-header">
      <div class="card-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Absensi Pulang
      </div>
    </div>
    <div class="card-body" style="text-align:center">
      <?php if ($sudahPulang): ?>
        <div style="width:64px;height:64px;background:var(--info-bg);border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--info)" stroke-width="1.8"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </div>
        <div style="font-family:var(--font-mono);font-size:32px;font-weight:900;color:var(--info)"><?= $absenHari['waktu_pulang'] ?></div>
        <div style="margin:8px 0"><?= getStatusBadge($absenHari['status_pulang']) ?></div>
        <?php if ($absenHari['lat_pulang']): ?>
        <div style="font-size:11px;color:var(--text-muted);font-family:var(--font-mono);background:var(--bg);padding:6px;border-radius:6px;margin-top:8px">
          <?= $absenHari['lat_pulang'] ?>, <?= $absenHari['lng_pulang'] ?>
        </div>
        <?php endif; ?>
        <div style="margin-top:12px;font-size:13px;color:var(--info);font-weight:600">Absensi pulang sudah dicatat</div>
      <?php elseif (!$sudahMasuk): ?>
        <div style="width:64px;height:64px;background:var(--bg-subtle);border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div style="color:var(--text-muted);font-size:14px">
          Lakukan absensi masuk terlebih dahulu sebelum absensi pulang.
        </div>
      <?php else: ?>
        <div style="width:64px;height:64px;background:var(--info-bg);border-radius:16px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--info)" stroke-width="1.8"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        </div>
        <div style="color:var(--text-muted);font-size:14px;margin-bottom:20px">
          Tekan tombol saat jam pulang tiba.<br>
          Jam pulang: <strong><?= $settings['jam_pulang'] ?></strong>
        </div>
        <button class="btn btn-info btn-block btn-lg" id="btn-absen-pulang" onclick="prosesAbsensi('pulang')">
          Absensi Pulang Sekarang
        </button>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- Geo Status Panel -->
<?php if (!($sudahMasuk && $sudahPulang)): ?>
<div class="geo-card mb-3">
  <div style="font-size:13px;font-weight:700;color:var(--primary);margin-bottom:10px">Status Geolokasi</div>
  <div class="geo-status detecting" id="geo-status">GPS siap — tekan tombol absensi untuk mendeteksi lokasi</div>
  <div class="geo-coords" id="geo-coords">Koordinat belum terdeteksi</div>
  <div style="font-size:12px;color:var(--text-muted);margin-top:8px">
    Sekolah: <?= $settings['sekolah_lat'] ?>, <?= $settings['sekolah_lng'] ?> | 
    Radius: <?= $settings['radius_meter'] ?> meter
  </div>
</div>
<?php endif; ?>

<!-- URL API disuntikkan langsung dari PHP — 100% benar tanpa perlu hitung path di JS -->
<script>
  window.ABSENSI_API_URL = '<?= BASE_URL ?>api/absensi.php';
</script>

<!-- Tidak Hadir -->
<?php if (!$sudahMasuk): ?>
<div class="card">
  <div class="card-body" style="text-align:center">
    <div style="width:52px;height:52px;background:var(--warning-bg);border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 12px">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="1.8"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
    </div>
    <div style="font-weight:700;margin-bottom:4px">Tidak bisa hadir hari ini?</div>
    <div style="color:var(--text-muted);font-size:13px;margin-bottom:16px">Ajukan izin atau sakit dengan bukti yang valid</div>
    <a href="izin.php?tanggal=<?= $today ?>" class="btn btn-warning">Ajukan Izin / Sakit</a>
  </div>
</div>
<?php endif; ?>

<?php
$extraJs = '<style>@media(max-width:600px){.absen-main-grid{grid-template-columns:1fr!important}}</style>';
require_once '../includes/footer.php';
?>