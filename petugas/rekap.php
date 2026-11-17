<?php
// FILE: petugas/rekap.php — Rekap absensi per semester, kelas petugas
require_once '../includes/config.php';
requireLogin('petugas');

// ── Filter Semester ──────────────────────────────────────────
$sf          = parseSemesterFilter();
$tahunAjaran = $sf['tahun_ajaran'];
$semester    = $sf['semester'];
$range       = $sf['range'];
$labelPeriode = $sf['label'];

$bulanDari   = $range['bulan_dari'];
$bulanSampai = $range['bulan_sampai'];
$tahunRange  = $range['tahun_mulai'];

// Paksa kelas sesuai yang dipegang petugas
$petugasKelasId = getPetugasKelasId();

$kelasInfo = null;
if ($petugasKelasId) {
    $stmtKI = $pdo->prepare("SELECT * FROM kelas WHERE id = ?");
    $stmtKI->execute([$petugasKelasId]);
    $kelasInfo = $stmtKI->fetch();
}

$rekap  = [];
$totals = ['hadir'=>0,'terlambat'=>0,'izin'=>0,'sakit'=>0,'alfa'=>0];

if ($petugasKelasId) {
    $stmt = $pdo->prepare("
        SELECT
            s.nis, u.nama, k.nama_kelas,
            SUM(CASE WHEN a.status_masuk = 'hadir'     THEN 1 ELSE 0 END) AS hadir,
            SUM(CASE WHEN a.status_masuk = 'terlambat' THEN 1 ELSE 0 END) AS terlambat,
            SUM(CASE WHEN a.status_masuk = 'izin'      THEN 1 ELSE 0 END) AS izin,
            SUM(CASE WHEN a.status_masuk = 'sakit'     THEN 1 ELSE 0 END) AS sakit,
            SUM(CASE WHEN a.status_masuk = 'alfa'      THEN 1 ELSE 0 END) AS alfa
        FROM siswa s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN kelas k ON s.kelas_id = k.id
        LEFT JOIN absensi a ON s.id = a.siswa_id
            AND MONTH(a.tanggal) BETWEEN ? AND ?
            AND YEAR(a.tanggal) = ?
        WHERE u.status = 'aktif' AND s.kelas_id = ?
        GROUP BY s.id, s.nis, u.nama, k.nama_kelas
        ORDER BY u.nama
    ");
    $stmt->execute([$bulanDari, $bulanSampai, $tahunRange, $petugasKelasId]);
    $rekap = $stmt->fetchAll();

    foreach ($rekap as $r) {
        foreach ($totals as $k => $_) $totals[$k] += $r[$k];
    }
}

$pageTitle = 'Rekap Absensi Semester';
require_once '../includes/header.php';
?>

<?php if (!$petugasKelasId): ?>
<div class="alert alert-warning" style="margin-bottom:20px">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;flex-shrink:0"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
  Akun Anda belum ditetapkan ke kelas manapun. Hubungi admin untuk mengatur kelas yang Anda pegang.
</div>
<?php else: ?>

<!-- Filter -->
<div class="card mb-3">
  <div class="card-body">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label">Tahun Ajaran</label>
        <select name="tahun_ajaran" class="form-control">
          <?php foreach (daftarTahunAjaran(6) as $ta): ?>
          <option value="<?=$ta?>" <?=$ta===$tahunAjaran?'selected':''?>><?=$ta?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label">Semester</label>
        <select name="semester" class="form-control">
          <option value="ganjil" <?=$semester==='ganjil'?'selected':''?>>Ganjil (Jul–Des)</option>
          <option value="genap"  <?=$semester==='genap' ?'selected':''?>>Genap (Jan–Jun)</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        Tampilkan
      </button>
      <a href="../admin/export/export_rekap.php?tahun_ajaran=<?=urlencode($tahunAjaran)?>&semester=<?=$semester?>&kelas=<?=$petugasKelasId?>" class="btn btn-success">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Unduh Excel
      </a>
    </form>
  </div>
</div>

<!-- Summary Cards -->
<div class="stats-grid mb-3">
  <?php
  $statCards = [
    ['label'=>'Siswa',       'val'=>count($rekap),        'color'=>'blue'],
    ['label'=>'Total Hadir', 'val'=>$totals['hadir'],     'color'=>'green'],
    ['label'=>'Terlambat',   'val'=>$totals['terlambat'], 'color'=>'yellow'],
    ['label'=>'Izin',        'val'=>$totals['izin'],      'color'=>'blue'],
    ['label'=>'Sakit',       'val'=>$totals['sakit'],     'color'=>'orange'],
    ['label'=>'Alfa',        'val'=>$totals['alfa'],      'color'=>'red'],
  ];
  $statSvg = [
    'blue'   => '<svg viewBox="0 0 24 24" fill="none" stroke="#1555a0" stroke-width="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>',
    'green'  => '<svg viewBox="0 0 24 24" fill="none" stroke="#1a7a45" stroke-width="1.8"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>',
    'yellow' => '<svg viewBox="0 0 24 24" fill="none" stroke="#92540e" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
    'orange' => '<svg viewBox="0 0 24 24" fill="none" stroke="#c2610c" stroke-width="1.8"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>',
    'red'    => '<svg viewBox="0 0 24 24" fill="none" stroke="#b91c1c" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
  ];
  foreach ($statCards as $c): ?>
  <div class="stat-card">
    <div class="stat-icon <?=$c['color']?>"><?= $statSvg[$c['color']] ?? '' ?></div>
    <div><div class="stat-value"><?=$c['val']?></div><div class="stat-label"><?=$c['label']?></div></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Tabel Rekap -->
<div class="card">
  <div class="card-header">
    <div class="card-title">Rekap Absensi — <?= htmlspecialchars($labelPeriode) ?>
      <span style="font-size:12px;font-weight:400;color:var(--text-muted);margin-left:8px"><?= htmlspecialchars($kelasInfo['nama_kelas'] ?? '') ?></span>
    </div>
  </div>
  <div class="table-responsive">
    <table>
      <thead>
        <tr>
          <th>#</th><th>NIS</th><th>Nama Siswa</th><th>Kelas</th>
          <th class="text-center">Hadir</th><th class="text-center">Terlambat</th>
          <th class="text-center">Izin</th><th class="text-center">Sakit</th>
          <th class="text-center">Alfa</th><th class="text-center">% Kehadiran</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rekap)): ?>
        <tr><td colspan="10" class="text-center text-muted" style="padding:32px">Tidak ada data absensi untuk periode ini</td></tr>
        <?php else: ?>
        <?php foreach ($rekap as $i => $r):
            $totalHari = $r['hadir'] + $r['terlambat'] + $r['izin'] + $r['sakit'] + $r['alfa'];
            $pctHadir  = $totalHari > 0 ? round(($r['hadir'] + $r['terlambat']) / $totalHari * 100) : 0;
            $pctColor  = $pctHadir >= 80 ? 'var(--success)' : ($pctHadir >= 60 ? 'var(--warning)' : 'var(--danger)');
        ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td style="font-family:var(--font-mono);font-size:12px"><?= htmlspecialchars($r['nis']) ?></td>
          <td><strong><?= htmlspecialchars($r['nama']) ?></strong></td>
          <td><?= htmlspecialchars($r['nama_kelas'] ?? '-') ?></td>
          <td class="text-center"><strong style="color:var(--success)"><?= $r['hadir'] ?></strong></td>
          <td class="text-center"><strong style="color:var(--warning)"><?= $r['terlambat'] ?></strong></td>
          <td class="text-center"><?= $r['izin'] ?></td>
          <td class="text-center"><?= $r['sakit'] ?></td>
          <td class="text-center"><strong style="color:var(--danger)"><?= $r['alfa'] ?></strong></td>
          <td class="text-center">
            <div style="display:flex;align-items:center;gap:8px">
              <div style="flex:1;height:8px;background:#eee;border-radius:4px;overflow:hidden">
                <div style="height:100%;width:<?=$pctHadir?>%;background:<?=$pctColor?>;border-radius:4px"></div>
              </div>
              <strong style="color:<?=$pctColor?>;min-width:35px;font-size:13px"><?=$pctHadir?>%</strong>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <?php if (!empty($rekap)): ?>
      <tfoot>
        <tr style="background:#f0f4f8;font-weight:800">
          <td colspan="4">TOTAL</td>
          <td class="text-center"><?= $totals['hadir'] ?></td>
          <td class="text-center"><?= $totals['terlambat'] ?></td>
          <td class="text-center"><?= $totals['izin'] ?></td>
          <td class="text-center"><?= $totals['sakit'] ?></td>
          <td class="text-center"><?= $totals['alfa'] ?></td>
          <td></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
