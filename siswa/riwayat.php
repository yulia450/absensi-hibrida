<?php
// ============================================================
// FILE: siswa/riwayat.php - Riwayat Absensi Siswa
// ============================================================
require_once '../includes/config.php';
requireLogin('siswa');

$siswaId = $_SESSION['siswa_id'];

$bulan = (int)($_GET['bulan'] ?? date('n'));
$tahun = (int)($_GET['tahun'] ?? date('Y'));

$stmt = $pdo->prepare("
    SELECT * FROM absensi
    WHERE siswa_id = ?
    AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?
    ORDER BY tanggal DESC
");
$stmt->execute([$siswaId, $bulan, $tahun]);
$absensi = $stmt->fetchAll();

// Hitung rekap
$rekap = ['hadir'=>0,'terlambat'=>0,'izin'=>0,'sakit'=>0,'alfa'=>0];
foreach ($absensi as $a) {
    if (isset($rekap[$a['status_masuk']])) $rekap[$a['status_masuk']]++;
}
$totalHari = array_sum($rekap);
$pct = $totalHari > 0 ? round(($rekap['hadir']+$rekap['terlambat'])/$totalHari*100) : 0;

$pageTitle = 'Riwayat Absensi';
require_once '../includes/header.php';
?>

<!-- Filter -->
<div class="card mb-3">
  <div class="card-body">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label">Bulan</label>
        <select name="bulan" class="form-control">
          <?php for ($i=1;$i<=12;$i++): ?>
          <option value="<?=$i?>" <?=$i==$bulan?'selected':''?>><?= namaBulan($i) ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label">Tahun</label>
        <select name="tahun" class="form-control">
          <?php for ($y=date('Y');$y>=date('Y')-2;$y--): ?>
          <option value="<?=$y?>" <?=$y==$tahun?'selected':''?>><?=$y?></option>
          <?php endfor; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary">Tampilkan</button>
    </form>
  </div>
</div>

<!-- Rekap Bulan -->
<div class="stats-grid mb-3">
  <div class="stat-card"><div class="stat-icon green"><svg viewBox="0 0 24 24" fill="none" stroke="#1a7a45" stroke-width="1.8"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg></div><div><div class="stat-value"><?= $rekap['hadir'] ?></div><div class="stat-label">Hadir</div></div></div>
  <div class="stat-card"><div class="stat-icon yellow"><svg viewBox="0 0 24 24" fill="none" stroke="#92540e" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div><div><div class="stat-value"><?= $rekap['terlambat'] ?></div><div class="stat-label">Terlambat</div></div></div>
  <div class="stat-card"><div class="stat-icon blue"><svg viewBox="0 0 24 24" fill="none" stroke="#1555a0" stroke-width="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg></div><div><div class="stat-value"><?= $rekap['izin'] ?></div><div class="stat-label">Izin</div></div></div>
  <div class="stat-card"><div class="stat-icon orange"><svg viewBox="0 0 24 24" fill="none" stroke="#c2610c" stroke-width="1.8"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div><div><div class="stat-value"><?= $rekap['sakit'] ?></div><div class="stat-label">Sakit</div></div></div>
  <div class="stat-card"><div class="stat-icon red"><svg viewBox="0 0 24 24" fill="none" stroke="#b91c1c" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div><div><div class="stat-value"><?= $rekap['alfa'] ?></div><div class="stat-label">Alfa</div></div></div>
</div>

<!-- Progress Kehadiran -->
<?php if ($totalHari > 0): ?>
<div class="card mb-3">
  <div class="card-body">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
      <div style="font-weight:700">Persentase Kehadiran <?= namaBulan($bulan) ?> <?= $tahun ?></div>
      <strong style="font-size:20px;color:<?= $pct>=80?'var(--success)':($pct>=60?'var(--warning)':'var(--danger)') ?>"><?= $pct ?>%</strong>
    </div>
    <div style="height:12px;background:#e0e0e0;border-radius:6px;overflow:hidden">
      <div style="height:100%;width:<?=$pct?>%;background:<?= $pct>=80?'var(--success)':($pct>=60?'var(--warning)':'var(--danger)') ?>;border-radius:6px;transition:width .6s ease"></div>
    </div>
    <div style="font-size:12px;color:var(--text-muted);margin-top:6px">
      Hadir <?= $rekap['hadir']+$rekap['terlambat'] ?> dari <?= $totalHari ?> hari
      <?php if ($pct < 75): ?><span style="color:var(--danger);font-weight:700"> Di bawah 75% — perlu perhatian!</span><?php endif; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Tabel Detail -->
<div class="card">
  <div class="card-header">
    <div class="card-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        Detail Absensi — <?= namaBulan($bulan) ?> <?= $tahun ?></div>
  </div>
  <div class="table-responsive">
    <table>
      <thead>
        <tr><th>Tanggal</th><th>Waktu Masuk</th><th>Waktu Pulang</th><th>Status Masuk</th><th>Status Pulang</th><th>Lokasi</th></tr>
      </thead>
      <tbody>
        <?php if (empty($absensi)): ?>
        <tr><td colspan="6" class="text-center text-muted" style="padding:32px">
          <div style="font-size:32px;margin-bottom:8px"></div>
          Tidak ada data absensi untuk <?= namaBulan($bulan) ?> <?= $tahun ?>
        </td></tr>
        <?php else: ?>
        <?php foreach ($absensi as $a): ?>
        <tr>
          <td style="white-space:nowrap"><?= formatTanggal($a['tanggal']) ?></td>
          <td><span style="font-family:var(--font-mono);font-size:14px"><?= $a['waktu_masuk'] ?? '-' ?></span></td>
          <td><span style="font-family:var(--font-mono);font-size:14px"><?= $a['waktu_pulang'] ?? '-' ?></span></td>
          <td><?= getStatusBadge($a['status_masuk']) ?></td>
          <td><?= getStatusBadge($a['status_pulang']) ?></td>
          <td>
            <?php if ($a['lat_masuk']): ?>
            <a href="https://maps.google.com/?q=<?=$a['lat_masuk']?>,<?=$a['lng_masuk']?>" target="_blank" class="btn btn-sm btn-outline" style="font-size:11px">Lihat</a>
            <?php else: ?>
            <span style="color:var(--text-muted);font-size:12px">-</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
