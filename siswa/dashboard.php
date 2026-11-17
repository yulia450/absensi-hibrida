<?php
require_once '../includes/config.php';
requireLogin('siswa');

$siswaId  = $_SESSION['siswa_id'];
$settings = getSettings($pdo);
$today    = date('Y-m-d');
$bulanIni = date('n');
$tahunIni = date('Y');

$stmtSiswa = $pdo->prepare("SELECT s.*, u.nama, u.email, u.no_hp, k.nama_kelas, k.jurusan FROM siswa s JOIN users u ON s.user_id = u.id LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ?");
$stmtSiswa->execute([$siswaId]);
$siswa = $stmtSiswa->fetch();

$stmtHari = $pdo->prepare("SELECT * FROM absensi WHERE siswa_id = ? AND tanggal = ?");
$stmtHari->execute([$siswaId, $today]);
$absenHari = $stmtHari->fetch();

$stmtMenunggu = $pdo->prepare("SELECT COUNT(*) FROM pengajuan_izin WHERE siswa_id = ? AND status = 'menunggu'");
$stmtMenunggu->execute([$siswaId]);
$jumlahMenunggu = $stmtMenunggu->fetchColumn();

$stmtRekap = $pdo->prepare("SELECT SUM(CASE WHEN status_masuk='hadir' THEN 1 ELSE 0 END) as hadir, SUM(CASE WHEN status_masuk='terlambat' THEN 1 ELSE 0 END) as terlambat, SUM(CASE WHEN status_masuk='izin' THEN 1 ELSE 0 END) as izin, SUM(CASE WHEN status_masuk='sakit' THEN 1 ELSE 0 END) as sakit, SUM(CASE WHEN status_masuk='alfa' THEN 1 ELSE 0 END) as alfa FROM absensi WHERE siswa_id = ? AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?");
$stmtRekap->execute([$siswaId, $bulanIni, $tahunIni]);
$rekapBulan = $stmtRekap->fetch();

$stmtRiwayat = $pdo->prepare("SELECT * FROM absensi WHERE siswa_id = ? ORDER BY tanggal DESC LIMIT 7");
$stmtRiwayat->execute([$siswaId]);
$riwayat = $stmtRiwayat->fetchAll();

$totalHariRekap = array_sum([$rekapBulan['hadir']??0, $rekapBulan['terlambat']??0, $rekapBulan['izin']??0, $rekapBulan['sakit']??0, $rekapBulan['alfa']??0]);
$pctHadir = $totalHariRekap > 0 ? round(($rekapBulan['hadir'] + $rekapBulan['terlambat']) / $totalHariRekap * 100) : 0;

$sudahMasuk  = $absenHari && $absenHari['waktu_masuk'];
$sudahPulang = $absenHari && $absenHari['waktu_pulang'] && $absenHari['status_pulang'] === 'pulang';

$pageTitle = 'Beranda Siswa';
require_once '../includes/header.php';
?>

<!-- Profil Siswa -->
<div class="card mb-3 profile-card">
  <div class="card-body profile-card-body">
    <div class="profile-avatar">
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="rgba(255,255,255,.9)" stroke-width="1.8">
        <path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/>
      </svg>
    </div>
    <div class="profile-info">
      <div class="profile-name"><?= htmlspecialchars($siswa['nama']) ?></div>
      <div class="profile-meta">NIS: <?= $siswa['nis'] ?> &nbsp;&bull;&nbsp; <?= htmlspecialchars($siswa['nama_kelas'] ?? 'Belum ada kelas') ?></div>
      <div class="profile-jurusan"><?= htmlspecialchars($siswa['jurusan'] ?? '') ?></div>
    </div>
    <div class="profile-percent">
      <div class="profile-percent-label">Kehadiran Bulan Ini</div>
      <div class="profile-percent-value"><?= $pctHadir ?>%</div>
    </div>
  </div>
</div>

<!-- Absensi Hari Ini -->
<div class="card mb-3">
  <div class="card-header">
    <div class="card-title">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
      Absensi Hari Ini — <?= formatTanggal($today) ?>
    </div>
  </div>
  <div class="card-body">
    <?php if ($absenHari && $absenHari['status_masuk'] === 'alfa'): ?>
    <div class="alert alert-danger">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      Anda tercatat <strong>ALFA</strong> pada hari ini.
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px" class="absen-grid">
      <!-- Masuk -->
      <div class="absen-box <?= $sudahMasuk ? 'done-masuk' : '' ?>">
        <div style="width:44px;height:44px;border-radius:10px;background:<?= $sudahMasuk ? '#b7dfcb' : '#e8ecf0' ?>;display:inline-flex;align-items:center;justify-content:center;margin-bottom:10px">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="<?= $sudahMasuk ? '#1a7a45' : '#9ca3af' ?>" stroke-width="1.8">
            <path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>
          </svg>
        </div>
        <div style="font-weight:800;font-size:14px;color:var(--primary);margin-bottom:4px">Absensi Masuk</div>
        <?php if ($sudahMasuk): ?>
          <div style="font-family:var(--font-mono);font-size:20px;font-weight:700;color:var(--success)"><?= $absenHari['waktu_masuk'] ?></div>
          <div style="margin-top:6px"><?= getStatusBadge($absenHari['status_masuk']) ?></div>
        <?php else: ?>
          <div style="color:var(--text-muted);font-size:12.5px;margin-bottom:6px">Jam masuk: <?= $settings['jam_masuk'] ?></div>
          <div style="color:var(--text-muted);font-size:13px;font-weight:600">Belum melakukan absensi masuk</div>
        <?php endif; ?>
      </div>

      <!-- Pulang -->
      <div class="absen-box <?= $sudahPulang ? 'done-pulang' : '' ?>">
        <div style="width:44px;height:44px;border-radius:10px;background:<?= $sudahPulang ? '#93c5fd' : '#e8ecf0' ?>;display:inline-flex;align-items:center;justify-content:center;margin-bottom:10px">
          <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="<?= $sudahPulang ? '#1555a0' : '#9ca3af' ?>" stroke-width="1.8">
            <path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
          </svg>
        </div>
        <div style="font-weight:800;font-size:14px;color:var(--primary);margin-bottom:4px">Absensi Pulang</div>
        <?php if ($sudahPulang): ?>
          <div style="font-family:var(--font-mono);font-size:20px;font-weight:700;color:var(--info)"><?= $absenHari['waktu_pulang'] ?></div>
          <div style="margin-top:6px"><?= getStatusBadge($absenHari['status_pulang']) ?></div>
        <?php elseif (!$sudahMasuk): ?>
          <div style="color:var(--text-muted);font-size:12.5px">Lakukan absensi masuk terlebih dahulu</div>
        <?php else: ?>
          <div style="color:var(--text-muted);font-size:12.5px;margin-bottom:6px">Jam pulang: <?= $settings['jam_pulang'] ?></div>
          <div style="color:var(--text-muted);font-size:13px;font-weight:600">Belum melakukan absensi pulang</div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<!-- Rekap & Pengajuan -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px" class="rekap-grid">
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:15px;height:15px;opacity:.6"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        Rekap <?= namaBulan($bulanIni) ?> <?= $tahunIni ?>
      </div>
      <a href="rekap.php" class="btn btn-sm btn-outline">Detail</a>
    </div>
    <div class="card-body" style="padding:14px 18px">
      <?php
      $rekapItems = [
        ['Hadir',     $rekapBulan['hadir']??0,     'var(--success)'],
        ['Terlambat', $rekapBulan['terlambat']??0,  '#d97706'],
        ['Izin',      $rekapBulan['izin']??0,       'var(--info)'],
        ['Sakit',     $rekapBulan['sakit']??0,      'var(--purple)'],
        ['Alfa',      $rekapBulan['alfa']??0,        'var(--danger)'],
      ];
      foreach ($rekapItems as [$label, $val, $color]): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--divider)">
        <span style="font-size:13.5px;font-weight:600;color:var(--text-secondary)"><?= $label ?></span>
        <strong style="color:<?= $color ?>;font-size:14px;font-variant-numeric:tabular-nums"><?= $val ?> hari</strong>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:15px;height:15px;opacity:.6"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Status Pengajuan
      </div>
      <a href="izin.php" class="btn btn-sm btn-outline">Lihat Semua</a>
    </div>
    <div class="card-body" style="padding:14px 18px">
      <?php if ($jumlahMenunggu > 0): ?>
      <div class="alert alert-warning" style="margin-bottom:12px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <?= $jumlahMenunggu ?> pengajuan sedang menunggu persetujuan
      </div>
      <?php endif; ?>
      <?php
      $stmtPengajuan = $pdo->prepare("SELECT * FROM pengajuan_izin WHERE siswa_id = ? ORDER BY created_at DESC LIMIT 4");
      $stmtPengajuan->execute([$siswaId]);
      $pengajuans = $stmtPengajuan->fetchAll();
      ?>
      <?php if (empty($pengajuans)): ?>
      <div style="text-align:center;color:var(--text-muted);padding:16px;font-size:13px">Belum ada pengajuan</div>
      <?php else: ?>
      <?php foreach ($pengajuans as $p): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--divider)">
        <div>
          <div style="font-weight:700;font-size:13px"><?= date('d/m/Y', strtotime($p['tanggal'])) ?> — <?= ucfirst($p['jenis']) ?></div>
          <div style="font-size:11.5px;color:var(--text-muted)"><?= htmlspecialchars(substr($p['keterangan'],0,42)) ?></div>
        </div>
        <?= getStatusBadge($p['status']) ?>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Riwayat -->
<div class="card">
  <div class="card-header">
    <div class="card-title">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:15px;height:15px;opacity:.6"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
      Riwayat Absensi 7 Hari Terakhir
    </div>
    <a href="riwayat.php" class="btn btn-sm btn-outline">Lihat Semua</a>
  </div>
  <div class="table-responsive">
    <table>
      <thead>
        <tr><th>Tanggal</th><th>Masuk</th><th>Pulang</th><th>Status Masuk</th><th>Status Pulang</th></tr>
      </thead>
      <tbody>
        <?php if (empty($riwayat)): ?>
        <tr class="empty-row"><td colspan="5">Belum ada riwayat absensi</td></tr>
        <?php else: ?>
        <?php foreach ($riwayat as $r): ?>
        <tr>
          <td><?= formatTanggal($r['tanggal']) ?></td>
          <td><span class="mono"><?= $r['waktu_masuk'] ?? '—' ?></span></td>
          <td><span class="mono"><?= $r['waktu_pulang'] ?? '—' ?></span></td>
          <td><?= getStatusBadge($r['status_masuk']) ?></td>
          <td><?= getStatusBadge($r['status_pulang']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
$extraJs = '<style>@media(max-width:600px){.absen-grid,.rekap-grid{grid-template-columns:1fr!important}}</style>';
require_once '../includes/footer.php';
?>