<?php
// ============================================================
// FILE: siswa/rekap.php - Rekap Semester Siswa
// ============================================================
require_once '../includes/config.php';
requireLogin('siswa');

$siswaId = $_SESSION['siswa_id'];

// ── Filter Semester ──────────────────────────────────────────
$sf          = parseSemesterFilter();
$tahunAjaran = $sf['tahun_ajaran'];
$semester    = $sf['semester'];
$range       = $sf['range'];
$labelPeriode = $sf['label'];

$bulanDari   = $range['bulan_dari'];
$bulanSampai = $range['bulan_sampai'];
$tahunRange  = $range['tahun_mulai'];

// Data siswa
$stmtSiswa = $pdo->prepare("SELECT s.*, u.nama, k.nama_kelas FROM siswa s JOIN users u ON s.user_id = u.id LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ?");
$stmtSiswa->execute([$siswaId]);
$siswa = $stmtSiswa->fetch();

// Rekap per bulan dalam semester ini
$rekapBulanan = [];
for ($b = $bulanDari; $b <= $bulanSampai; $b++) {
    $stmt = $pdo->prepare("
        SELECT
            SUM(CASE WHEN status_masuk='hadir'     THEN 1 ELSE 0 END) AS hadir,
            SUM(CASE WHEN status_masuk='terlambat' THEN 1 ELSE 0 END) AS terlambat,
            SUM(CASE WHEN status_masuk='izin'      THEN 1 ELSE 0 END) AS izin,
            SUM(CASE WHEN status_masuk='sakit'     THEN 1 ELSE 0 END) AS sakit,
            SUM(CASE WHEN status_masuk='alfa'      THEN 1 ELSE 0 END) AS alfa
        FROM absensi WHERE siswa_id = ? AND MONTH(tanggal) = ? AND YEAR(tanggal) = ?
    ");
    $stmt->execute([$siswaId, $b, $tahunRange]);
    $r = $stmt->fetch();
    $total = array_sum($r);
    $rekapBulanan[$b] = array_merge($r, [
        'total' => $total,
        'pct'   => $total > 0 ? round(($r['hadir'] + $r['terlambat']) / $total * 100) : 0
    ]);
}

// Rekap akumulasi semester
$totalSemester = ['hadir'=>0,'terlambat'=>0,'izin'=>0,'sakit'=>0,'alfa'=>0];
foreach ($rekapBulanan as $rb) {
    foreach ($totalSemester as $k => $_) $totalSemester[$k] += $rb[$k];
}
$totalHariSem = array_sum($totalSemester);
$pctSemester  = $totalHariSem > 0 ? round(($totalSemester['hadir'] + $totalSemester['terlambat']) / $totalHariSem * 100) : 0;

// Hasil clustering semester ini (ambil bulan terakhir tersedia)
$stmtCluster = $pdo->prepare("
    SELECT * FROM hasil_clustering
    WHERE siswa_id = ? AND bulan BETWEEN ? AND ? AND tahun = ?
    ORDER BY bulan DESC LIMIT 1
");
$stmtCluster->execute([$siswaId, $bulanDari, $bulanSampai, $tahunRange]);
$clusterSemester = $stmtCluster->fetch();

$pageTitle = 'Rekap Semester';
require_once '../includes/header.php';
?>

<!-- Header Profil -->
<div class="card mb-3" style="background:linear-gradient(135deg,var(--primary-dark),var(--primary-light));color:#fff;border:none">
  <div class="card-body" style="text-align:center">
    <div style="font-size:18px;font-weight:900"><?= htmlspecialchars($siswa['nama']) ?></div>
    <div style="opacity:.8;font-size:13px">NIS: <?= $siswa['nis'] ?> | <?= htmlspecialchars($siswa['nama_kelas'] ?? '-') ?></div>
  </div>
</div>

<!-- Filter Semester -->
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
      <button type="submit" class="btn btn-primary">Tampilkan</button>
    </form>
  </div>
</div>

<!-- Ringkasan Semester -->
<div class="stats-grid mb-3">
  <?php
  $pctColor = $pctSemester >= 80 ? 'var(--success)' : ($pctSemester >= 60 ? 'var(--warning)' : 'var(--danger)');
  $smCards = [
    ['label'=>'Hadir',     'val'=>$totalSemester['hadir'],     'color'=>'green'],
    ['label'=>'Terlambat', 'val'=>$totalSemester['terlambat'], 'color'=>'yellow'],
    ['label'=>'Izin',      'val'=>$totalSemester['izin'],      'color'=>'blue'],
    ['label'=>'Sakit',     'val'=>$totalSemester['sakit'],     'color'=>'orange'],
    ['label'=>'Alfa',      'val'=>$totalSemester['alfa'],      'color'=>'red'],
  ];
  foreach ($smCards as $c): ?>
  <div class="stat-card <?=$c['color']?>">
    <div><div class="stat-value"><?=$c['val']?></div><div class="stat-label"><?=$c['label']?></div></div>
  </div>
  <?php endforeach; ?>
  <div class="stat-card blue">
    <div><div class="stat-value" style="color:<?=$pctColor?>"><?=$pctSemester?>%</div><div class="stat-label">Kehadiran</div></div>
  </div>
</div>

<!-- Chart Semester -->
<div class="card mb-3">
  <div class="card-header"><div class="card-title">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
    Grafik Kehadiran — <?= htmlspecialchars($labelPeriode) ?>
  </div></div>
  <div class="card-body"><div style="position:relative;height:220px;width:100%;max-width:100%;overflow:hidden"><canvas id="chart-semester"></canvas></div></div>
</div>

<!-- Tabel Rekap Per Bulan -->
<div class="card">
  <div class="card-header"><div class="card-title">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
    Rekap Per Bulan — <?= htmlspecialchars($labelPeriode) ?>
  </div></div>
  <div class="table-responsive">
    <table>
      <thead>
        <tr><th>Bulan</th><th>Hadir</th><th>Terlambat</th><th>Izin</th><th>Sakit</th><th>Alfa</th><th>% Hadir</th></tr>
      </thead>
      <tbody>
        <?php
        $bulanSekarang = (int)date('n');
        $tahunSekarang = (int)date('Y');
        for ($b = $bulanDari; $b <= $bulanSampai; $b++):
          $r = $rekapBulanan[$b];
          $pct = $r['pct'];
          $pctC = $pct >= 80 ? 'var(--success)' : ($pct >= 60 ? 'var(--warning)' : 'var(--danger)');
          $isNow = ($b === $bulanSekarang && $tahunRange === $tahunSekarang);
        ?>
        <tr <?= $isNow ? 'style="background:#eaf4fd"' : '' ?>>
          <td>
            <strong><?= namaBulan($b) ?></strong>
            <?php if ($isNow): ?>
            <span style="font-size:10px;background:var(--primary);color:#fff;padding:2px 6px;border-radius:4px;margin-left:4px">Sekarang</span>
            <?php endif; ?>
          </td>
          <td style="color:var(--success);font-weight:700"><?= $r['hadir'] ?></td>
          <td style="color:var(--warning);font-weight:700"><?= $r['terlambat'] ?></td>
          <td><?= $r['izin'] ?></td>
          <td><?= $r['sakit'] ?></td>
          <td style="color:var(--danger);font-weight:700"><?= $r['alfa'] ?></td>
          <td>
            <?php if ($r['total'] > 0): ?>
            <div style="display:flex;align-items:center;gap:6px">
              <div style="width:60px;height:6px;background:#eee;border-radius:3px;overflow:hidden">
                <div style="height:100%;width:<?=$pct?>%;background:<?=$pctC?>"></div>
              </div>
              <strong style="color:<?=$pctC?>;font-size:13px"><?=$pct?>%</strong>
            </div>
            <?php else: ?>
            <span style="color:var(--text-muted);font-size:12px">-</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endfor; ?>
      </tbody>
      <tfoot>
        <tr style="background:#f0f4f8;font-weight:800">
          <td>TOTAL SEMESTER</td>
          <td style="color:var(--success)"><?= $totalSemester['hadir'] ?></td>
          <td style="color:var(--warning)"><?= $totalSemester['terlambat'] ?></td>
          <td><?= $totalSemester['izin'] ?></td>
          <td><?= $totalSemester['sakit'] ?></td>
          <td style="color:var(--danger)"><?= $totalSemester['alfa'] ?></td>
          <td><strong style="color:<?=$pctColor?>"><?=$pctSemester?>%</strong></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<?php
$labels    = json_encode(array_map(fn($b) => namaBulan($b), range($bulanDari, $bulanSampai)));
$hadirData = json_encode(array_map(fn($b) => $rekapBulanan[$b]['hadir'] + $rekapBulanan[$b]['terlambat'], range($bulanDari, $bulanSampai)));
$alfaData  = json_encode(array_map(fn($b) => $rekapBulanan[$b]['alfa'], range($bulanDari, $bulanSampai)));

$extraJs = <<<JS
<script>
new Chart(document.getElementById('chart-semester'), {
  type: 'bar',
  data: {
    labels: $labels,
    datasets: [
      { label: 'Hadir+Terlambat', data: $hadirData, backgroundColor: 'rgba(39,174,96,.7)', borderRadius: 6 },
      { label: 'Alfa', data: $alfaData, backgroundColor: 'rgba(231,76,60,.7)', borderRadius: 6 }
    ]
  },
  options: {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { legend: { labels: { font: { family: 'Nunito', weight: '700' }, boxWidth: 14, padding: 10 } } },
    scales: { y: { beginAtZero: true, ticks: { font: { size: 11 } } }, x: { ticks: { font: { size: 11 } } } }
  }
});
</script>
JS;
require_once '../includes/footer.php';
?>