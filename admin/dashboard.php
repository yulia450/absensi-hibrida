<?php
require_once '../includes/config.php';
requireLogin('admin');

$settings = getSettings($pdo);
$today    = date('Y-m-d');
$bulanIni = date('n');
$tahunIni = date('Y');

// ── 1. Statistik absensi HARI INI ──────────────────────────────
$stmtStat = $pdo->prepare("
    SELECT
        SUM(CASE WHEN status_masuk IN ('hadir', 'terlambat') THEN 1 ELSE 0 END) AS hadir,
        SUM(CASE WHEN status_masuk = 'terlambat' THEN 1 ELSE 0 END) AS terlambat,
        SUM(CASE WHEN status_masuk = 'izin'      THEN 1 ELSE 0 END) AS izin,
        SUM(CASE WHEN status_masuk = 'sakit'     THEN 1 ELSE 0 END) AS sakit,
        SUM(CASE WHEN status_masuk = 'alfa'      THEN 1 ELSE 0 END) AS alfa
    FROM absensi
    WHERE tanggal = ?
");
$stmtStat->execute([$today]);
$row = $stmtStat->fetch(PDO::FETCH_ASSOC);

$totalSiswaAktif = (int)$pdo->query("
    SELECT COUNT(*) FROM siswa s
    JOIN users u ON s.user_id = u.id
    WHERE u.status = 'aktif'
")->fetchColumn();

$statHari = [
    'total_siswa' => $totalSiswaAktif,
    'hadir'       => (int)($row['hadir']     ?? 0),
    'terlambat'   => (int)($row['terlambat'] ?? 0),
    'izin'        => (int)($row['izin']      ?? 0),
    'sakit'       => (int)($row['sakit']     ?? 0),
    'alfa'        => (int)($row['alfa']      ?? 0),
];

// ── 2. Jumlah izin menunggu ────────────────────────────────────
$stmtIzin = $pdo->prepare("SELECT COUNT(*) FROM pengajuan_izin WHERE status = 'menunggu'");
$stmtIzin->execute();
$jumlahMenunggu = $stmtIzin->fetchColumn();

// ── 3. Tren 7 hari terakhir ────────────────────────────────────
$trendData = [];
for ($i = 6; $i >= 0; $i--) {
    $tgl  = date('Y-m-d', strtotime("-$i days"));
    $stmt = $pdo->prepare("
        SELECT
            SUM(CASE WHEN status_masuk='hadir'     THEN 1 ELSE 0 END) AS hadir,
            SUM(CASE WHEN status_masuk='terlambat' THEN 1 ELSE 0 END) AS terlambat,
            SUM(CASE WHEN status_masuk='alfa'      THEN 1 ELSE 0 END) AS alfa
        FROM absensi WHERE tanggal = ?
    ");
    $stmt->execute([$tgl]);
    $r = $stmt->fetch();
    $trendData[] = [
        'tanggal'   => date('d/m', strtotime($tgl)),
        'hadir'     => (int)($r['hadir']     ?? 0),
        'terlambat' => (int)($r['terlambat'] ?? 0),
        'alfa'      => (int)($r['alfa']      ?? 0),
    ];
}

// ── 4. Card entitas ────────────────────────────────────────────
$totalSiswa   = $totalSiswaAktif;
$totalKelas   = (int)$pdo->query("SELECT COUNT(*) FROM kelas")->fetchColumn();
$totalPetugas = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='petugas' AND status='aktif'")->fetchColumn();

// ── 5. Kehadiran per kelas hari ini ───────────────────────────
$stmtKelas = $pdo->prepare("
    SELECT
        k.nama_kelas,
        COUNT(DISTINCT s.id)                                                    AS total_siswa,
        SUM(CASE WHEN a.status_masuk IN ('hadir','terlambat') THEN 1 ELSE 0 END) AS hadir_count,
        SUM(CASE WHEN a.status_masuk = 'alfa'                 THEN 1 ELSE 0 END) AS alfa_count
    FROM kelas k
    JOIN siswa s  ON s.kelas_id = k.id
    JOIN users us ON s.user_id  = us.id AND us.status = 'aktif'
    LEFT JOIN absensi a ON a.siswa_id = s.id AND a.tanggal = ?
    GROUP BY k.id, k.nama_kelas
    ORDER BY k.nama_kelas ASC
");
$stmtKelas->execute([$today]);
$dataKelas = $stmtKelas->fetchAll(PDO::FETCH_ASSOC);

// ── 6. Absensi terkini ─────────────────────────────────────────
$stmtTerkini = $pdo->query("
    SELECT u.nama, k.nama_kelas, a.waktu_masuk, a.status_masuk, a.tanggal
    FROM absensi a
    JOIN siswa s  ON a.siswa_id  = s.id
    JOIN users u  ON s.user_id   = u.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    WHERE a.waktu_masuk IS NOT NULL
    ORDER BY a.created_at DESC LIMIT 8
");
$absensiTerkini = $stmtTerkini->fetchAll();

$pageTitle = 'Dashboard Admin';
require_once '../includes/header.php';
?>

<?php if ($jumlahMenunggu > 0): ?>
<div class="alert alert-warning">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
  Terdapat <strong><?= $jumlahMenunggu ?> pengajuan izin/sakit</strong> yang menunggu persetujuan.
  <a href="izin.php" style="margin-left:8px;font-weight:700;color:inherit;text-decoration:underline">Tinjau sekarang</a>
</div>
<?php endif; ?>

<!-- Statistik Hari Ini -->
<div class="section-header">
  <div class="section-title">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
    Statistik Absensi — <?= formatTanggal($today) ?>
  </div>
</div>

<div class="stats-grid">
  <div class="stat-card blue">
    <div class="stat-icon blue">
      <svg viewBox="0 0 24 24" fill="none" stroke="#1555a0" stroke-width="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
    </div>
    <div class="stat-info">
      <div class="stat-value"><?= $statHari['total_siswa'] ?></div>
      <div class="stat-label">Total Siswa Aktif</div>
    </div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon green">
      <svg viewBox="0 0 24 24" fill="none" stroke="#1a7a45" stroke-width="1.8"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
    </div>
    <div class="stat-info">
      <div class="stat-value"><?= $statHari['hadir'] ?></div>
      <div class="stat-label">Hadir</div>
    </div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon yellow">
      <svg viewBox="0 0 24 24" fill="none" stroke="#92540e" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
    </div>
    <div class="stat-info">
      <div class="stat-value"><?= $statHari['terlambat'] ?></div>
      <div class="stat-label">Terlambat</div>
    </div>
  </div>
  <div class="stat-card blue">
    <div class="stat-icon blue">
      <svg viewBox="0 0 24 24" fill="none" stroke="#1555a0" stroke-width="1.8"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
    </div>
    <div class="stat-info">
      <div class="stat-value"><?= $statHari['izin'] ?></div>
      <div class="stat-label">Izin</div>
    </div>
  </div>
  <div class="stat-card orange">
    <div class="stat-icon orange">
      <svg viewBox="0 0 24 24" fill="none" stroke="#c2610c" stroke-width="1.8"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
    </div>
    <div class="stat-info">
      <div class="stat-value"><?= $statHari['sakit'] ?></div>
      <div class="stat-label">Sakit</div>
    </div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon red">
      <svg viewBox="0 0 24 24" fill="none" stroke="#b91c1c" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
    </div>
    <div class="stat-info">
      <div class="stat-value"><?= $statHari['alfa'] ?></div>
      <div class="stat-label">Alfa</div>
    </div>
  </div>
</div>

<!-- Charts -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:16px" class="chart-grid">
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        Tren Absensi 7 Hari Terakhir
      </div>
    </div>
    <div class="card-body">
      <canvas id="chart-trend" height="190"></canvas>
    </div>
  </div>
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M7 16V8M12 16v-5M17 16v-9"/></svg>
        Kehadiran Per Kelas — <?= date('d/m/Y') ?>
      </div>
    </div>
    <div class="card-body" style="padding:12px 16px;max-height:425px;overflow-y:auto">
      <?php if (empty($dataKelas)): ?>
        <div style="text-align:center;color:var(--text-muted);padding:40px 0;font-size:13px">Belum ada data kelas</div>
      <?php else: ?>
        <?php foreach ($dataKelas as $kls):
          $total   = max((int)$kls['total_siswa'], 1);
          $hadir   = (int)($kls['hadir_count'] ?? 0);
          $alfa    = (int)($kls['alfa_count']  ?? 0);
          $pct     = round($hadir / $total * 100);
          $barColor = $pct >= 80 ? '#1a7a45' : ($pct >= 60 ? '#d97706' : '#b91c1c');
        ?>
        <div style="margin-bottom:10px">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:3px">
            <span style="font-size:12px;font-weight:700;color:var(--text)"><?= htmlspecialchars($kls['nama_kelas']) ?></span>
            <span style="font-size:11px;font-weight:700;color:<?= $barColor ?>"><?= $pct ?>%
              <span style="font-weight:400;color:var(--text-muted)">(<?= $hadir ?>/<?= $total ?>)</span>
            </span>
          </div>
          <div style="height:8px;border-radius:99px;background:var(--border);overflow:hidden">
            <div style="height:100%;width:<?= $pct ?>%;background:<?= $barColor ?>;border-radius:99px;transition:width .4s ease"></div>
          </div>
          <?php if ($alfa > 0): ?>
          <div style="font-size:10px;color:#b91c1c;margin-top:2px"><?= $alfa ?> siswa alfa</div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Entity Info & Recent -->
<div style="display:grid;grid-template-columns:200px 1fr;gap:16px;margin-bottom:16px" class="info-grid">
  <div style="display:flex;flex-direction:column;gap:12px">
    <?php
    $entityCards = [
      [$totalSiswa,   'Siswa Aktif',    '#1555a0', 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M9 7a4 4 0 108 0 4 4 0 00-8 0M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75', 'siswa.php'],
      [$totalKelas,   'Kelas',          '#1a6b63', 'M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2zM9 22V12h6v10', 'kelas.php'],
      [$totalPetugas, 'Petugas Aktif',  '#6b21a8', 'M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2M12 7a4 4 0 100 8 4 4 0 000-8', 'petugas.php'],
    ];
    foreach ($entityCards as [$val, $label, $color, $path, $link]): ?>
    <a href="<?= $link ?>" style="text-decoration:none">
    <div class="card" style="cursor:pointer;transition:box-shadow .2s" onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,.12)'" onmouseout="this.style.boxShadow=''">
      <div class="card-body" style="text-align:center;padding:16px 12px">
        <div style="width:40px;height:40px;border-radius:10px;background:<?= $color ?>18;display:inline-flex;align-items:center;justify-content:center;margin-bottom:8px">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="<?= $color ?>" stroke-width="1.8"><path d="<?= $path ?>"/></svg>
        </div>
        <div style="font-size:28px;font-weight:800;color:var(--text);line-height:1;font-variant-numeric:tabular-nums"><?= $val ?></div>
        <div style="font-size:11px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-top:4px"><?= $label ?></div>
      </div>
    </div>
    </a>
    <?php endforeach; ?>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Absensi Terkini
      </div>
    </div>
    <div class="table-responsive">
      <table>
        <thead>
          <tr><th>Nama Siswa</th><th>Kelas</th><th>Waktu Masuk</th><th>Status</th></tr>
        </thead>
        <tbody>
          <?php if (empty($absensiTerkini)): ?>
          <tr class="empty-row"><td colspan="4">Belum ada data absensi hari ini</td></tr>
          <?php else: ?>
          <?php foreach ($absensiTerkini as $row): ?>
          <tr>
            <td><strong><?= htmlspecialchars($row['nama']) ?></strong></td>
            <td><?= htmlspecialchars($row['nama_kelas'] ?? '—') ?></td>
            <td><span class="mono"><?= $row['waktu_masuk'] ?? '—' ?></span></td>
            <td><?= getStatusBadge($row['status_masuk']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Quick Actions -->
<div class="card">
  <div class="card-header">
    <div class="card-title">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
      Aksi Cepat
    </div>
  </div>
  <div class="card-body">
    <div style="display:flex;flex-wrap:wrap;gap:8px">
      <a href="absensi.php" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
        Absensi Hari Ini
      </a>
      <a href="izin.php" class="btn btn-warning">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Kelola Pengajuan Izin
      </a>
      <a href="clustering.php" class="btn btn-info">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        Analisis K-Means
      </a>
      <a href="rekap.php?download=1&bulan=<?= $bulanIni ?>&tahun=<?= $tahunIni ?>" class="btn btn-success">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Unduh Rekap Bulan Ini
      </a>
      <a href="settings.php" class="btn btn-outline">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
        Pengaturan
      </a>
    </div>
  </div>
</div>

<?php
$trendLabels = json_encode(array_column($trendData, 'tanggal'));
$trendHadir  = json_encode(array_column($trendData, 'hadir'));
$trendAlfa   = json_encode(array_column($trendData, 'alfa'));
// Data untuk chart kehadiran per kelas
$kelasLabels  = json_encode(array_column($dataKelas, 'nama_kelas'));
$kelasHadir   = json_encode(array_column($dataKelas, 'hadir_count'));
$kelasTotal   = json_encode(array_column($dataKelas, 'total_absen'));

$extraJs = <<<JS
<script>
new Chart(document.getElementById('chart-trend'), {
  type: 'line',
  data: {
    labels: $trendLabels,
    datasets: [
      { label: 'Hadir',  data: $trendHadir, borderColor: '#1a7a45', backgroundColor: 'rgba(26,122,69,.08)', tension: 0.4, fill: true, pointRadius: 4, pointBackgroundColor: '#1a7a45', borderWidth: 2 },
      { label: 'Alfa',   data: $trendAlfa,  borderColor: '#b91c1c', backgroundColor: 'rgba(185,28,28,.07)', tension: 0.4, fill: true, pointRadius: 4, pointBackgroundColor: '#b91c1c', borderWidth: 2 },
    ]
  },
  options: {
    responsive: true,
    plugins: { legend: { labels: { font: { family: 'Plus Jakarta Sans', weight: '600', size: 12 }, boxWidth: 12 } } },
    scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.05)' } }, x: { grid: { display: false } } }
  }
});
</script>
<style>
@media(max-width:768px){ .chart-grid,.info-grid{grid-template-columns:1fr!important} }
</style>
JS;
require_once '../includes/footer.php';
?>
