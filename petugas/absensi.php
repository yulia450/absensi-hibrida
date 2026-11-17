<?php
// ============================================================
// FILE: petugas/absensi.php - Lihat Absensi Hari Ini (Petugas)
// ============================================================
require_once '../includes/config.php';
requireLogin('petugas');

$today = date('Y-m-d');
$settings = getSettings($pdo);

// Kelas yang dipegang petugas — WAJIB, tidak bisa dipilih bebas
$petugasKelasId = getPetugasKelasId();

if ($petugasKelasId) {
    $kelasFilter = $petugasKelasId;
    $kelasList   = $pdo->query("SELECT * FROM kelas WHERE id = $petugasKelasId")->fetchAll();
} else {
    // Petugas belum dapat kelas — tampilkan warning, data kosong
    $kelasFilter = -1; // angka tidak valid → tidak ada data yang keluar
    $kelasList   = [];
}
$tglFilter   = $_GET['tanggal'] ?? $today;

$params = [$tglFilter];
$kelasWhere = '';
if ($kelasFilter) { $kelasWhere = 'AND s.kelas_id = ?'; $params[] = $kelasFilter; }

$stmtAbsen = $pdo->prepare("
    SELECT u.nama, k.nama_kelas, a.*, s.nis
    FROM siswa s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN absensi a ON s.id = a.siswa_id AND a.tanggal = ?
    WHERE u.status = 'aktif' $kelasWhere
    ORDER BY k.nama_kelas, u.nama
");
$stmtAbsen->execute($params);
$absensi = $stmtAbsen->fetchAll();

// Hitung statistik
$stat = ['hadir'=>0,'terlambat'=>0,'izin'=>0,'sakit'=>0,'alfa'=>0,'belum'=>0];
foreach ($absensi as $a) {
    $s = $a['status_masuk'] ?? 'belum';
    if ($s === 'belum_pulang') $s = 'belum';
    if (isset($stat[$s])) $stat[$s]++;
    else $stat['belum']++;
}

$pageTitle = ' Absensi Siswa';
require_once '../includes/header.php';
?>

<!-- Filter -->
<?php if (!$petugasKelasId): ?>
<div class="alert alert-warning" style="margin-bottom:16px">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;flex-shrink:0"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
  Akun Anda belum ditetapkan ke kelas manapun. Hubungi admin untuk mengatur kelas yang Anda pegang.
</div>
<?php endif; ?>
<div class="card mb-3">
  <div class="card-body">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label">Tanggal</label>
        <input type="date" name="tanggal" class="form-control" value="<?=$tglFilter?>" max="<?=$today?>">
      </div>
      <button type="submit" class="btn btn-primary">Tampilkan</button>
    </form>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid mb-3">
  <div class="stat-card"><div class="stat-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="#1a7a45" stroke-width="1.8"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg></div><div><div class="stat-value"><?=$stat['hadir']?></div><div class="stat-label">Hadir</div></div></div>
  <div class="stat-card"><div class="stat-icon yellow"><svg viewBox="0 0 24 24" fill="none" stroke="#92540e" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div><div><div class="stat-value"><?=$stat['terlambat']?></div><div class="stat-label">Terlambat</div></div></div>
  <div class="stat-card"><div class="stat-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="#1555a0" stroke-width="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg></div><div><div class="stat-value"><?=$stat['izin']?></div><div class="stat-label">Izin</div></div></div>
  <div class="stat-card"><div class="stat-icon orange"><svg viewBox="0 0 24 24" fill="none" stroke="#c2610c" stroke-width="1.8"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div><div><div class="stat-value"><?=$stat['sakit']?></div><div class="stat-label">Sakit</div></div></div>
  <div class="stat-card"><div class="stat-icon red"><svg viewBox="0 0 24 24" fill="none" stroke="#b91c1c" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div><div><div class="stat-value"><?=$stat['alfa']?></div><div class="stat-label">Alfa</div></div></div>
  <div class="stat-card"><div class="stat-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="#1555a0" stroke-width="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg></div><div><div class="stat-value"><?=$stat['belum']?></div><div class="stat-label">Belum Absen</div></div></div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">Data Absensi — <?= formatTanggal($tglFilter) ?></div>
  </div>
  <div class="table-responsive">
    <table>
      <thead>
        <tr><th>#</th><th>NIS</th><th>Nama</th><th>Kelas</th><th>Masuk</th><th>Pulang</th><th>Status Masuk</th><th>Status Pulang</th></tr>
      </thead>
      <tbody>
        <?php if (empty($absensi)): ?>
        <tr><td colspan="8" class="text-center text-muted" style="padding:24px">Tidak ada data</td></tr>
        <?php else: ?>
        <?php foreach ($absensi as $i => $a): ?>
        <tr>
          <td><?=$i+1?></td>
          <td style="font-family:var(--font-mono);font-size:12px"><?=$a['nis']?></td>
          <td><strong><?= htmlspecialchars($a['nama']) ?></strong></td>
          <td><?= $a['nama_kelas']??'-' ?></td>
          <td style="font-family:var(--font-mono);font-size:13px"><?= $a['waktu_masuk']??'-' ?></td>
          <td style="font-family:var(--font-mono);font-size:13px"><?= $a['waktu_pulang']??'-' ?></td>
          <td><?= isset($a['status_masuk']) ? getStatusBadge($a['status_masuk']) : '<span class="badge" style="background:#eee;color:#666">Belum</span>' ?></td>
          <td><?= isset($a['status_pulang']) ? getStatusBadge($a['status_pulang']) : '<span class="badge" style="background:#eee;color:#666">Belum</span>' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
