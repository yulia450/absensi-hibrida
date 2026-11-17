<?php
// ============================================================
// FILE: admin/rekap.php - Rekap Absensi per Semester
// ============================================================
require_once '../includes/config.php';
require_once '../includes/functions.php';
requireLogin(['admin','petugas']);

// ── Filter Periode (Semester / Bulanan) ────────────────────────
$sf          = parsePeriodeFilter();
$modePeriode = $sf['mode'];
$tahunAjaran = $sf['tahun_ajaran'];
$semester    = $sf['semester'];
$bulanFilter = $sf['bulan'] ?? (int)date('n');
$tahunFilter = $sf['tahun'] ?? (int)date('Y');
$range       = $sf['range'];
$labelPeriode = $sf['label'];

$bulanDari   = $range['bulan_dari'];
$bulanSampai = $range['bulan_sampai'];
$tahunRange  = $range['tahun_mulai'];  // ganjil: tahun mulai; genap: tahun akhir

$kelasId     = (int)($_GET['kelas'] ?? 0);
$kelasList   = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();

// ── Query rekap semester ─────────────────────────────────────
// [FIX] Nama kelas diambil dari snapshot teks yang terkunci di baris
// absensi saat itu (nama_kelas_snapshot) -- jadi tetap akurat sesuai
// waktunya walau nama kelas berubah/di-reset di kemudian hari lewat
// fitur Kenaikan Kelas. Fallback ke kelas siswa saat ini kalau di
// periode itu siswa sama sekali tidak punya baris absensi.
// Siswa yang sudah nonaktif (lulus/keluar) tetap ikut tampil kalau
// dia punya riwayat absensi di periode yang dipilih.
$params = [
    $bulanDari, $bulanSampai, $tahunRange, // subquery kelas historis
    $bulanDari, $bulanSampai, $tahunRange, // join absensi periode
    $bulanDari, $bulanSampai, $tahunRange, // exists cek siswa nonaktif
];
$kelasWhere  = '';
if ($kelasId) { $kelasWhere = 'AND s.kelas_id = ?'; $params[] = $kelasId; }

$stmt = $pdo->prepare("
    SELECT
        s.nis, u.nama,
        COALESCE(
            (SELECT a2.nama_kelas_snapshot FROM absensi a2
             WHERE a2.siswa_id = s.id AND MONTH(a2.tanggal) BETWEEN ? AND ? AND YEAR(a2.tanggal) = ?
             AND a2.nama_kelas_snapshot IS NOT NULL
             ORDER BY a2.tanggal DESC LIMIT 1),
            k.nama_kelas
        ) AS nama_kelas,
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
    WHERE (u.status = 'aktif' OR EXISTS (
        SELECT 1 FROM absensi a3 WHERE a3.siswa_id = s.id
        AND MONTH(a3.tanggal) BETWEEN ? AND ? AND YEAR(a3.tanggal) = ?
    )) $kelasWhere
    GROUP BY s.id, s.nis, u.nama, nama_kelas
    ORDER BY nama_kelas, u.nama
");
$stmt->execute($params);
$rekap = $stmt->fetchAll();

$totals = ['hadir'=>0,'terlambat'=>0,'izin'=>0,'sakit'=>0,'alfa'=>0];
foreach ($rekap as $r) {
    foreach ($totals as $k => $_) $totals[$k] += $r[$k];
}

$pageTitle = 'Rekap Absensi';
require_once '../includes/header.php';
?>

<!-- Filter -->
<div class="card mb-3">
  <div class="card-body">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label">Mode Periode</label>
        <select name="mode_periode" id="rk-mode-periode" class="form-control">
          <option value="semester" <?=$modePeriode==='semester'?'selected':''?>>Per Semester</option>
          <option value="bulanan"  <?=$modePeriode==='bulanan' ?'selected':''?>>Per Bulan</option>
        </select>
      </div>
      <div id="rk-wrap-semester" style="display:flex;gap:10px;flex-wrap:wrap">
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
      </div>
      <div id="rk-wrap-bulanan" style="display:none;gap:10px;flex-wrap:wrap">
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Bulan</label>
          <select name="bulan" class="form-control">
            <?php for ($bm = 1; $bm <= 12; $bm++): ?>
            <option value="<?=$bm?>" <?=$bm===(int)$bulanFilter?'selected':''?>><?=namaBulan($bm)?></option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">Tahun</label>
          <select name="tahun" class="form-control">
            <?php $thnSkrg = (int)date('Y'); for ($ty = $thnSkrg - 3; $ty <= $thnSkrg + 1; $ty++): ?>
            <option value="<?=$ty?>" <?=$ty===(int)$tahunFilter?'selected':''?>><?=$ty?></option>
            <?php endfor; ?>
          </select>
        </div>
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label">Kelas</label>
        <select name="kelas" id="filterKelas" class="form-control">
          <option value="">Semua Kelas</option>
          <?php foreach ($kelasList as $k): ?>
          <option value="<?=$k['id']?>" <?=$kelasId==$k['id']?'selected':''?>><?=$k['nama_kelas']?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        Tampilkan
      </button>

      <?php
        // Siapkan nilai awal untuk tombol utama (server-side)
        $exportQS = 'mode_periode=' . urlencode($modePeriode)
                  . '&tahun_ajaran=' . urlencode($tahunAjaran)
                  . '&semester=' . urlencode($semester)
                  . '&bulan=' . urlencode((string)$bulanFilter)
                  . '&tahun=' . urlencode((string)$tahunFilter);
        $exportUrl     = 'export/export_rekap.php?' . $exportQS . '&kelas=' . $kelasId;
        $btnUnduhLabel = $kelasId
            ? 'Unduh Excel ' . htmlspecialchars($kelasList[array_search($kelasId, array_column($kelasList,'id'))]['nama_kelas'] ?? 'Kelas Ini')
            : 'Unduh Excel Semua Kelas';
      ?>

      <!-- Tombol unduh utama (selalu tampil) -->
      <a id="btnUnduhUtama"
         href="<?= $exportUrl ?>"
         class="btn btn-success">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        <span id="btnUnduhUtamaLabel"><?= $btnUnduhLabel ?></span>
      </a>

      <!-- Tombol multi-sheet (hanya tampil saat "Semua Kelas") -->
      <a id="btnMultiSheet"
         href="export/export_rekap.php?jenis=perkelas&<?= $exportQS ?>"
         class="btn btn-outline"
         <?= $kelasId ? 'style="display:none"' : '' ?>>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg>
        Unduh Per Kelas
      </a>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  function toggleRkModePeriode() {
    var m = document.getElementById('rk-mode-periode').value;
    document.getElementById('rk-wrap-semester').style.display = m === 'semester' ? 'flex' : 'none';
    document.getElementById('rk-wrap-bulanan').style.display  = m === 'bulanan'  ? 'flex' : 'none';
  }
  document.getElementById('rk-mode-periode').addEventListener('change', toggleRkModePeriode);
  toggleRkModePeriode(); // set tampilan awal sesuai mode yang sudah terpilih
});
</script>

<?php
// Bangun map id→nama_kelas untuk dioper ke JavaScript
$kelasMapJs = [];
foreach ($kelasList as $k) {
    $kelasMapJs[(int)$k['id']] = $k['nama_kelas'];
}
?>
<script>
(function () {
  // Data kelas dari PHP (id => nama_kelas)
  const kelasMap = <?= json_encode($kelasMapJs, JSON_UNESCAPED_UNICODE) ?>;

  // Parameter periode yang sedang aktif (agar URL tombol selalu sinkron)
  const modePeriode = <?= json_encode($modePeriode) ?>;
  const tahunAjaran = <?= json_encode($tahunAjaran) ?>;
  const semester    = <?= json_encode($semester) ?>;
  const bulanFilter = <?= json_encode($bulanFilter) ?>;
  const tahunFilter = <?= json_encode($tahunFilter) ?>;

  const selectKelas      = document.getElementById('filterKelas');
  const btnUnduhUtama    = document.getElementById('btnUnduhUtama');
  const btnUnduhLabel    = document.getElementById('btnUnduhUtamaLabel');
  const btnMultiSheet    = document.getElementById('btnMultiSheet');

  function updateButtons() {
    const kelasId   = selectKelas.value;          // "" = Semua Kelas
    const namaKelas = kelasMap[kelasId] ?? null;

    // ── URL tombol unduh utama ───────────────────────────────
    const baseExport = 'export/export_rekap.php'
      + '?mode_periode=' + encodeURIComponent(modePeriode)
      + '&tahun_ajaran=' + encodeURIComponent(tahunAjaran)
      + '&semester='     + encodeURIComponent(semester)
      + '&bulan='        + encodeURIComponent(bulanFilter)
      + '&tahun='        + encodeURIComponent(tahunFilter)
      + '&kelas='        + encodeURIComponent(kelasId);

    btnUnduhUtama.href = baseExport;

    if (namaKelas) {
      // ── KONDISI 2: kelas spesifik ────────────────────────
      btnUnduhLabel.textContent = 'Unduh Excel ' + namaKelas;
      btnMultiSheet.style.display = 'none';
    } else {
      // ── KONDISI 1: semua kelas ───────────────────────────
      btnUnduhLabel.textContent = 'Unduh Excel Semua Kelas';
      btnMultiSheet.style.display = '';
    }
  }

  // Jalankan saat dropdown berubah
  selectKelas.addEventListener('change', updateButtons);

  // Jalankan sekali saat halaman dimuat agar state awal konsisten
  updateButtons();
})();
</script>

<!-- Summary Cards -->
<div class="stats-grid mb-3">
  <?php
  $svgSiswa  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>';
  $svgHadir  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>';
  $svgLambat = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
  $svgIzin   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>';
  $svgSakit  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>';
  $svgAlfa   = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';
  $cards = [
    ['label'=>'Siswa',       'val'=>count($rekap),        'svg'=>$svgSiswa,  'color'=>'blue',   'stroke'=>'#1555a0'],
    ['label'=>'Total Hadir', 'val'=>$totals['hadir'],     'svg'=>$svgHadir,  'color'=>'green',  'stroke'=>'#1a7a45'],
    ['label'=>'Terlambat',   'val'=>$totals['terlambat'], 'svg'=>$svgLambat, 'color'=>'yellow', 'stroke'=>'#92540e'],
    ['label'=>'Izin',        'val'=>$totals['izin'],      'svg'=>$svgIzin,   'color'=>'blue',   'stroke'=>'#1555a0'],
    ['label'=>'Sakit',       'val'=>$totals['sakit'],     'svg'=>$svgSakit,  'color'=>'orange', 'stroke'=>'#c2610c'],
    ['label'=>'Alfa',        'val'=>$totals['alfa'],      'svg'=>$svgAlfa,   'color'=>'red',    'stroke'=>'#b91c1c'],
  ];
  foreach ($cards as $c): ?>
  <div class="stat-card <?= $c['color'] ?>">
    <div class="stat-icon <?= $c['color'] ?>">
      <svg viewBox="0 0 24 24" fill="none" stroke="<?= $c['stroke'] ?>" stroke-width="1.8"><?= trim(preg_replace('/<svg[^>]*>|<\/svg>/','',$c['svg'])) ?></svg>
    </div>
    <div><div class="stat-value"><?=$c['val']?></div><div class="stat-label"><?=$c['label']?></div></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Tabel Rekap -->
<div class="card">
  <div class="card-header">
    <div class="card-title">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
      Rekap Absensi — <?= htmlspecialchars($labelPeriode) ?>
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
          <td style="font-family:var(--font-mono);font-size:12px"><?= $r['nis'] ?></td>
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

<?php require_once '../includes/footer.php'; ?>