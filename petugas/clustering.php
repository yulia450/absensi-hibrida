<?php
// ============================================================
// FILE: petugas/clustering.php - Lihat Hasil Analisis K-Means
// Petugas hanya bisa MELIHAT hasil, tidak bisa menjalankan analisis
// ============================================================
require_once '../includes/config.php';
requireLogin('petugas');

// ── Filter Semester ──────────────────────────────────────────
$sf           = parseSemesterFilter();
$tahunAjaran  = $sf['tahun_ajaran'];
$semester     = $sf['semester'];
$range        = $sf['range'];
$labelPeriode = $sf['label'];

$bulanDari   = $range['bulan_dari'];
$bulanSampai = $range['bulan_sampai'];
$tahunRange  = $range['tahun_mulai'];

// Kelas petugas (wajib)
$petugasKelasId = getPetugasKelasId();

$kelasInfo = null;
if ($petugasKelasId) {
    $stmtKI = $pdo->prepare("SELECT * FROM kelas WHERE id = ?");
    $stmtKI->execute([$petugasKelasId]);
    $kelasInfo = $stmtKI->fetch();
}

// ── Ambil hasil clustering dari DB (hanya kelas petugas) ─────
$hasilClustering = [];
$statCluster     = ['1' => 0, '2' => 0, '3' => 0];

if ($petugasKelasId) {
    $stmtHasil = $pdo->prepare("
        SELECT hc.siswa_id, hc.cluster_id, hc.label_cluster,
               MAX(hc.jumlah_hadir)     AS jumlah_hadir,
               MAX(hc.jumlah_terlambat) AS jumlah_terlambat,
               MAX(hc.jumlah_izin)      AS jumlah_izin,
               MAX(hc.jumlah_sakit)     AS jumlah_sakit,
               MAX(hc.jumlah_alfa)      AS jumlah_alfa,
               u.nama, k.nama_kelas, s.nis
        FROM hasil_clustering hc
        JOIN siswa s  ON hc.siswa_id  = s.id
        JOIN users u  ON s.user_id    = u.id
        LEFT JOIN kelas k ON s.kelas_id = k.id
        WHERE hc.bulan >= ? AND hc.bulan <= ? AND hc.tahun = ?
          AND s.kelas_id = ?
        GROUP BY hc.siswa_id, hc.cluster_id, hc.label_cluster,
                 u.nama, k.nama_kelas, s.nis
        ORDER BY hc.cluster_id, u.nama
    ");
    $stmtHasil->execute([$bulanDari, $bulanSampai, $tahunRange, $petugasKelasId]);
    $hasilClustering = $stmtHasil->fetchAll();

    foreach ($hasilClustering as $h) {
        if (isset($statCluster[$h['cluster_id']])) $statCluster[$h['cluster_id']]++;
    }
}

$pageTitle = 'Hasil Analisis Kehadiran';
require_once '../includes/header.php';
?>

<?php if (!$petugasKelasId): ?>
<div class="alert alert-warning">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;flex-shrink:0"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
  Akun Anda belum ditetapkan ke kelas manapun. Hubungi admin.
</div>
<?php else: ?>

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
      <button type="submit" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        Tampilkan
      </button>
    </form>
  </div>
</div>

<!-- Info Banner -->
<div class="alert" style="background:var(--primary-pale);border:1px solid var(--primary-light);color:var(--primary-dark);margin-bottom:16px;display:flex;gap:10px;align-items:flex-start">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="8.01"/><line x1="12" y1="12" x2="12" y2="16"/></svg>
  <span>Menampilkan hasil analisis kelas <strong><?= htmlspecialchars($kelasInfo['nama_kelas'] ?? '-') ?></strong> untuk <strong><?= htmlspecialchars($labelPeriode) ?></strong>. Analisis dijalankan oleh Admin.</span>
</div>

<?php if (empty($hasilClustering)): ?>
<!-- Belum ada data -->
<div class="card">
  <div class="card-body" style="text-align:center;padding:48px 24px;color:var(--text-muted)">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:48px;height:48px;opacity:.3;margin-bottom:12px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    <div style="font-size:15px;font-weight:600;margin-bottom:6px">Belum Ada Hasil Analisis</div>
    <div style="font-size:13px">Admin belum menjalankan analisis clustering untuk periode ini.</div>
  </div>
</div>

<?php else: ?>

<!-- Summary Cards -->
<div class="stats-grid mb-3">
  <div class="stat-card blue">
    <div class="stat-icon blue">
      <svg viewBox="0 0 24 24" fill="none" stroke="#1555a0" stroke-width="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
    </div>
    <div><div class="stat-value"><?= count($hasilClustering) ?></div><div class="stat-label">Total Siswa</div></div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon green">
      <svg viewBox="0 0 24 24" fill="none" stroke="#1a7a45" stroke-width="1.8"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
    </div>
    <div><div class="stat-value"><?= $statCluster['1'] ?></div><div class="stat-label">Kehadiran Normal</div></div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon yellow">
      <svg viewBox="0 0 24 24" fill="none" stroke="#92540e" stroke-width="1.8"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    </div>
    <div><div class="stat-value"><?= $statCluster['2'] ?></div><div class="stat-label">Perlu Perhatian</div></div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon red">
      <svg viewBox="0 0 24 24" fill="none" stroke="#b91c1c" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    </div>
    <div><div class="stat-value"><?= $statCluster['3'] ?></div><div class="stat-label">Anomali</div></div>
  </div>
</div>

<!-- Keterangan Cluster -->
<div class="card mb-3">
  <div class="card-header"><div class="card-title">Keterangan Pengelompokan</div></div>
  <div class="card-body" style="display:flex;flex-direction:column;gap:10px">
    <div style="display:flex;align-items:flex-start;gap:12px;padding:13px 16px;border-radius:10px;background:var(--success-bg);border:1px solid #b2d8c0">
      <div style="width:13px;height:13px;border-radius:50%;background:var(--success);flex-shrink:0;margin-top:3px"></div>
      <div><div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:2px">Cluster 1 — Normal</div>
      <div style="font-size:12px;color:var(--text-muted)">Hadir tinggi, alfa rendah. Kehadiran dalam batas normal.</div></div>
    </div>
    <div style="display:flex;align-items:flex-start;gap:12px;padding:13px 16px;border-radius:10px;background:var(--warning-bg);border:1px solid #f5d08a">
      <div style="width:13px;height:13px;border-radius:50%;background:#d97706;flex-shrink:0;margin-top:3px"></div>
      <div><div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:2px">Cluster 2 — Perlu Perhatian</div>
      <div style="font-size:12px;color:var(--text-muted)">Kehadiran mulai menurun. Perlu monitoring dan motivasi lebih.</div></div>
    </div>
    <div style="display:flex;align-items:flex-start;gap:12px;padding:13px 16px;border-radius:10px;background:var(--danger-bg);border:1px solid #f5b3b3">
      <div style="width:13px;height:13px;border-radius:50%;background:var(--danger);flex-shrink:0;margin-top:3px"></div>
      <div><div style="font-size:13px;font-weight:700;color:var(--text);margin-bottom:2px">Cluster 3 — Anomali</div>
      <div style="font-size:12px;color:var(--text-muted)">Alfa tinggi, hadir rendah. Butuh perhatian dan tindak lanjut segera.</div></div>
    </div>
  </div>
</div>

<!-- Tabel Hasil -->
<div class="card">
  <div class="card-header">
    <div class="card-title">
      Detail Hasil — <?= htmlspecialchars($labelPeriode) ?>
      <span style="font-size:12px;font-weight:400;color:var(--text-muted);margin-left:6px"><?= htmlspecialchars($kelasInfo['nama_kelas'] ?? '') ?></span>
    </div>
    <select id="filter-cluster" class="form-control" style="width:auto;min-width:150px;font-size:12.5px;padding:5px 10px;height:32px">
      <option value="all">Semua Cluster</option>
      <option value="1">Cluster 1 — Normal</option>
      <option value="2">Cluster 2 — Perhatian</option>
      <option value="3">Cluster 3 — Anomali</option>
    </select>
  </div>
  <div class="table-responsive">
    <table id="tabel-hasil">
      <thead>
        <tr>
          <th>#</th>
          <th>NIS</th>
          <th>Nama Siswa</th>
          <th style="text-align:center">Hadir</th>
          <th style="text-align:center">Terlambat</th>
          <th style="text-align:center">Izin</th>
          <th style="text-align:center">Sakit</th>
          <th style="text-align:center">Alfa</th>
          <th style="text-align:center">Cluster</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($hasilClustering as $i => $h): ?>
        <tr data-cluster="<?= $h['cluster_id'] ?>">
          <td><?= $i + 1 ?></td>
          <td><span style="font-family:var(--font-mono);font-size:12px"><?= htmlspecialchars($h['nis']) ?></span></td>
          <td><strong><?= htmlspecialchars($h['nama']) ?></strong></td>
          <td style="text-align:center;font-weight:700;color:var(--success)"><?= $h['jumlah_hadir'] ?></td>
          <td style="text-align:center;font-weight:600;color:var(--warning)"><?= $h['jumlah_terlambat'] ?></td>
          <td style="text-align:center"><?= $h['jumlah_izin'] ?></td>
          <td style="text-align:center"><?= $h['jumlah_sakit'] ?></td>
          <td style="text-align:center;font-weight:700;color:var(--danger)"><?= $h['jumlah_alfa'] ?></td>
          <td style="text-align:center">
            <?php if ($h['cluster_id'] == 1): ?>
              <span class="badge cluster-1">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:10px;height:10px"><path d="M9 11l3 3L22 4"/></svg>
                Normal
              </span>
            <?php elseif ($h['cluster_id'] == 2): ?>
              <span class="badge cluster-2">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:10px;height:10px"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                Perhatian
              </span>
            <?php else: ?>
              <span class="badge cluster-3">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:10px;height:10px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/></svg>
                Anomali
              </span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php endif; /* hasilClustering */ ?>
<?php endif; /* petugasKelasId */ ?>

<?php
$extraJs = '<script>
var filterSel = document.getElementById("filter-cluster");
if (filterSel) {
  filterSel.addEventListener("change", function() {
    var val = this.value;
    document.querySelectorAll("#tabel-hasil tbody tr").forEach(function(row) {
      row.style.display = (val === "all" || row.dataset.cluster === val) ? "" : "none";
    });
  });
}
</script>';
require_once '../includes/footer.php';
?>
