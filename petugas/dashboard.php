<?php
// ============================================================
// FILE: petugas/dashboard.php - Dashboard Petugas/Guru
// ============================================================
require_once '../includes/config.php';
requireLogin('petugas');

$today    = date('Y-m-d');
$settings = getSettings($pdo);

// Kelas yang dipegang petugas ini
$petugasKelasId = getPetugasKelasId();
$kelasNama = '';
if ($petugasKelasId) {
    $stmtK = $pdo->prepare("SELECT nama_kelas FROM kelas WHERE id = ?");
    $stmtK->execute([$petugasKelasId]);
    $kelasNama = $stmtK->fetchColumn() ?: '';
}
$kelasFilterDash = $petugasKelasId ? "AND s.kelas_id = $petugasKelasId" : '';

// Statistik hari ini
$stmt = $pdo->query("
    SELECT
      (SELECT COUNT(*) FROM siswa s JOIN users u ON s.user_id=u.id WHERE u.status='aktif' $kelasFilterDash) as total,
      SUM(CASE WHEN a.status_masuk='hadir' THEN 1 ELSE 0 END) as hadir,
      SUM(CASE WHEN a.status_masuk='terlambat' THEN 1 ELSE 0 END) as terlambat,
      SUM(CASE WHEN a.status_masuk='izin' THEN 1 ELSE 0 END) as izin,
      SUM(CASE WHEN a.status_masuk='sakit' THEN 1 ELSE 0 END) as sakit,
      SUM(CASE WHEN a.status_masuk='alfa' THEN 1 ELSE 0 END) as alfa
    FROM absensi a
    JOIN siswa s ON a.siswa_id = s.id
    WHERE a.tanggal = '$today' $kelasFilterDash
");
$stat = $stmt->fetch();

$totalSiswa = (int)$stat['total'];
$sudahAbsen = (int)$stat['hadir'] + (int)$stat['terlambat'] + (int)$stat['izin'] + (int)$stat['sakit'] + (int)$stat['alfa'];
$belumAbsen = max(0, $totalSiswa - $sudahAbsen);
$pctAbsen   = $totalSiswa > 0 ? round(($sudahAbsen / $totalSiswa) * 100) : 0;

// Pengajuan menunggu (filter by kelas petugas)
$izinKelasFilter = $petugasKelasId ? "AND s.kelas_id = $petugasKelasId" : '';
$jumlahMenunggu = $pdo->query("SELECT COUNT(*) FROM pengajuan_izin pi JOIN siswa s ON pi.siswa_id = s.id WHERE pi.status='menunggu' $izinKelasFilter")->fetchColumn();

// Siswa belum absen (maks 8)
$stmtBelum = $pdo->query("
    SELECT u.nama, k.nama_kelas
    FROM siswa s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN absensi a ON s.id = a.siswa_id AND a.tanggal = '$today'
    WHERE u.status = 'aktif' AND a.id IS NULL $kelasFilterDash
    ORDER BY k.nama_kelas, u.nama
    LIMIT 8
");
$belumAbsenList = $stmtBelum->fetchAll();

// Siswa terlambat hari ini (maks 5)
$stmtLambat = $pdo->query("
    SELECT u.nama, k.nama_kelas, a.waktu_masuk
    FROM absensi a
    JOIN siswa s ON a.siswa_id = s.id
    JOIN users u ON s.user_id = u.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    WHERE a.tanggal = '$today' AND a.status_masuk = 'terlambat' $kelasFilterDash
    ORDER BY a.waktu_masuk DESC
    LIMIT 5
");
$terlambatList = $stmtLambat->fetchAll();

$jamMasuk       = $settings['jam_masuk']        ?? '07:00';
$jamPulang      = $settings['jam_pulang']        ?? '15:00';
$batasTerlambat = $settings['batas_terlambat']   ?? '07:15';

$pageTitle = 'Dashboard Petugas';
require_once '../includes/header.php';
?>

<?php if ($jumlahMenunggu > 0): ?>
<div class="alert alert-warning">Ada <strong><?=$jumlahMenunggu?> pengajuan izin/sakit</strong> menunggu persetujuan. <a href="izin.php">Proses sekarang &rarr;</a></div>
<?php endif; ?>

<!-- Sub-header -->
<div class="section-header">
    <div class="section-title">
        <span>
            Pantau kehadiran siswa hari ini
            <?= $kelasNama ? ' — Kelas <strong>' . $kelasNama . '</strong>' : '' ?>
        </span>
    </div>
</div>

<!-- Stat Cards -->
<div class="stats-grid mb-3">
  <div class="stat-card">
    <div class="stat-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="#1555a0" stroke-width="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg></div>
    <div><div class="stat-value"><?=$totalSiswa?></div><div class="stat-label">Total Siswa Aktif</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="#1a7a45" stroke-width="1.8"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg></div>
    <div><div class="stat-value"><?=$stat['hadir']?></div><div class="stat-label">Hadir</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon yellow"><svg viewBox="0 0 24 24" fill="none" stroke="#92540e" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
    <div><div class="stat-value"><?=$stat['terlambat']?></div><div class="stat-label">Terlambat</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red"><svg viewBox="0 0 24 24" fill="none" stroke="#b91c1c" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
    <div><div class="stat-value"><?=$stat['alfa']?></div><div class="stat-label">Alfa</div></div>
  </div>
</div>

<!-- Main Grid: 3 kolom sejajar -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px;margin-bottom:18px" class="dash-grid">

  <!-- Kolom 1: Progress Kehadiran -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Progress Absensi Hari Ini</div>
      <span style="font-size:12px;color:var(--text-muted)"><?=$sudahAbsen?> / <?=$totalSiswa?> siswa</span>
    </div>
    <div class="card-body">
      <div style="margin-bottom:16px">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px">
          <span style="font-size:13px;font-weight:600">Sudah Absen</span>
          <span style="font-size:13px;font-weight:700;color:var(--primary)"><?=$pctAbsen?>%</span>
        </div>
        <div style="background:#e5e7eb;border-radius:99px;height:10px;overflow:hidden">
          <div style="width:<?=$pctAbsen?>%;background:var(--primary);height:100%;border-radius:99px"></div>
        </div>
      </div>
      <div style="display:flex;flex-direction:column;gap:8px">
        <?php
        $breakdown = [
          ['label'=>'Hadir',       'val'=>$stat['hadir'],     'color'=>'#16a34a'],
          ['label'=>'Terlambat',   'val'=>$stat['terlambat'], 'color'=>'#d97706'],
          ['label'=>'Izin',        'val'=>$stat['izin'],      'color'=>'#2563eb'],
          ['label'=>'Sakit',       'val'=>$stat['sakit'],     'color'=>'#ea580c'],
          ['label'=>'Alfa',        'val'=>$stat['alfa'],      'color'=>'#dc2626'],
          ['label'=>'Belum Absen', 'val'=>$belumAbsen,        'color'=>'#9ca3af'],
        ];
        foreach ($breakdown as $b):
          $pct = $totalSiswa > 0 ? round(($b['val'] / $totalSiswa) * 100) : 0;
        ?>
        <div style="display:flex;align-items:center;gap:10px">
          <div style="width:3px;height:32px;border-radius:2px;background:<?=$b['color']?>;flex-shrink:0"></div>
          <div style="flex:1;min-width:0">
            <div style="font-size:12px;color:var(--text-muted)"><?=$b['label']?></div>
            <div style="background:#f0f0f0;border-radius:4px;height:5px;margin-top:3px;overflow:hidden">
              <div style="width:<?=$pct?>%;height:100%;background:<?=$b['color']?>;border-radius:4px"></div>
            </div>
          </div>
          <div style="font-size:15px;font-weight:800;color:<?=$b['color']?>;min-width:24px;text-align:right"><?=$b['val']?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Kolom 2: Belum Absen -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Belum Absen (<?=$belumAbsen?>)</div>
      <?php if ($belumAbsen > 8): ?>
      <a href="absensi.php" class="btn btn-sm btn-outline" style="font-size:11px">Lihat Semua</a>
      <?php endif; ?>
    </div>
    <?php if (empty($belumAbsenList)): ?>
    <div class="card-body" style="text-align:center;padding:24px">
      <div style="font-weight:600;color:var(--success)">Semua siswa sudah absen!</div>
      <div class="text-muted" style="font-size:13px">Tidak ada siswa yang belum tercatat.</div>
    </div>
    <?php else: ?>
    <div>
      <?php foreach ($belumAbsenList as $s): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:9px 16px;border-bottom:1px solid var(--border)">
        <div>
          <div style="font-size:13px;font-weight:600"><?=htmlspecialchars($s['nama'])?></div>
          <div style="font-size:12px;color:var(--text-muted)"><?=$s['nama_kelas']??'-'?></div>
        </div>
        <span class="badge" style="background:#fee2e2;color:#b91c1c;font-size:11px">Belum</span>
      </div>
      <?php endforeach; ?>
      <?php if ($belumAbsen > 8): ?>
      <div style="padding:10px 16px;text-align:center">
        <a href="absensi.php" style="font-size:12px;color:var(--primary)">+ <?=$belumAbsen-8?> siswa lainnya &rarr;</a>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Kolom 3: Terlambat -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Terlambat Hari Ini</div>
    </div>
    <?php if (empty($terlambatList)): ?>
    <div class="card-body" style="text-align:center;padding:24px">
      <div class="text-muted" style="font-size:13px">Tidak ada siswa terlambat.</div>
    </div>
    <?php else: ?>
    <div>
      <?php foreach ($terlambatList as $t): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:9px 16px;border-bottom:1px solid var(--border)">
        <div>
          <div style="font-size:13px;font-weight:600"><?=htmlspecialchars($t['nama'])?></div>
          <div style="font-size:12px;color:var(--text-muted)"><?=$t['nama_kelas']??'-'?></div>
        </div>
        <span style="font-family:var(--font-mono);font-size:12px;color:#d97706;font-weight:600"><?=$t['waktu_masuk']??'-'?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div style="padding:10px 16px;background:#f9fafb;border-top:1px solid var(--border);border-radius:0 0 10px 10px">
      <div style="font-size:11px;color:var(--text-muted);display:flex;gap:12px;flex-wrap:wrap">
        <span>Masuk: <strong><?=$jamMasuk?></strong></span>
        <span>Batas: <strong><?=$batasTerlambat?></strong></span>
        <span>Pulang: <strong><?=$jamPulang?></strong></span>
      </div>
    </div>
  </div>

</div>

<!-- Aksi Cepat: full width -->
<div class="card">
  <div class="card-header">
    <div class="card-title">Aksi Cepat</div>
  </div>
  <div class="card-body" style="display:grid;grid-template-columns:repeat(4,1fr);gap:10px" class="aksi-grid">
    <a href="input_absensi.php" style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:#eff6ff;border-radius:8px;text-decoration:none;border:1px solid #bfdbfe">
      <div><div style="font-size:13px;font-weight:700;color:#1d4ed8">Input Manual</div><div style="font-size:11px;color:#6b7280">Siswa tanpa HP</div></div>
    </a>
    <a href="izin.php" style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:#fffbeb;border-radius:8px;text-decoration:none;border:1px solid #fde68a">
      <div><div style="font-size:13px;font-weight:700;color:#92400e">Proses Izin</div><div style="font-size:11px;color:#6b7280"><?=$jumlahMenunggu?> menunggu</div></div>
    </a>
    <a href="absensi.php" style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:#f0fdf4;border-radius:8px;text-decoration:none;border:1px solid #bbf7d0">
      <div><div style="font-size:13px;font-weight:700;color:#15803d">Detail Absensi</div><div style="font-size:11px;color:#6b7280">Filter per kelas/tgl</div></div>
    </a>
    <a href="rekap.php" style="display:flex;align-items:center;gap:10px;padding:12px 14px;background:#faf5ff;border-radius:8px;text-decoration:none;border:1px solid #e9d5ff">
      <div><div style="font-size:13px;font-weight:700;color:#7c3aed">Rekap Absensi</div><div style="font-size:11px;color:#6b7280">Laporan semester</div></div>
    </a>
  </div>
</div>

<?php
$extraJs = '<style>
@media(max-width:900px){.dash-grid{grid-template-columns:1fr 1fr!important}}
@media(max-width:600px){.dash-grid{grid-template-columns:1fr!important}.aksi-grid{grid-template-columns:1fr 1fr!important}}
</style>';
require_once '../includes/footer.php';
?>
