<?php
// ============================================================
// FILE: admin/clustering.php - Analisis K-Means Clustering
// ============================================================
require_once '../includes/config.php';
require_once '../includes/kmeans.php';
require_once '../includes/functions.php';
requireLogin('admin');

$settings = getSettings($pdo);

// [FIX] Daftar kelas untuk filter target kirim notifikasi WA
$daftarKelasWA = $pdo->query("SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas")->fetchAll();

// ── Filter Periode (Semester / Bulanan) ────────────────────────
// [FIX] Kalau user belum eksplisit pilih filter di URL, pakai filter
// TERAKHIR yang dipakai waktu "Jalankan Clustering" (tersimpan permanen
// di DB), bukan selalu balik ke "semester berjalan". Jadi pas pindah
// halaman lalu balik lagi (bahkan setelah logout-login), hasil yang
// sama tetap kelihatan tanpa perlu klik "Jalankan Clustering" ulang.
if (!isset($_GET['mode_periode']) && !empty($settings['kmeans_last_mode'])) {
    $_GET['mode_periode'] = $settings['kmeans_last_mode'];
    if ($settings['kmeans_last_mode'] === 'bulanan') {
        $_GET['bulan'] = $settings['kmeans_last_bulan'];
        $_GET['tahun'] = $settings['kmeans_last_tahun_bulanan'];
    } else {
        $_GET['tahun_ajaran'] = $settings['kmeans_last_tahun_ajaran'];
        $_GET['semester']     = $settings['kmeans_last_semester'];
    }
}

$sf          = parsePeriodeFilter();
$modePeriode = $sf['mode'];
$tahunAjaran = $sf['tahun_ajaran'];
$semester    = $sf['semester'];
$bulanFilter = $sf['bulan'] ?? (int)date('n');   // default bulan berjalan kalau belum pernah pilih mode bulanan
$tahunFilter = $sf['tahun'] ?? (int)date('Y');    // default tahun berjalan kalau belum pernah pilih mode bulanan
$range       = $sf['range'];
$labelPeriode = $sf['label'];

$bulanDari   = $range['bulan_dari'];
$bulanSampai = $range['bulan_sampai'];
$tahun       = $range['tahun_mulai'];
$bulan       = $bulanDari;

$hasil = null;
$error = '';
$msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'clustering') {
        $modePOST = $_POST['mode_periode'] ?? 'semester';
        if (!in_array($modePOST, ['semester', 'bulanan'])) $modePOST = 'semester';

        if ($modePOST === 'bulanan') {
            $bPOST = (int)($_POST['bulan'] ?? date('n'));
            $tPOST = (int)($_POST['tahun'] ?? date('Y'));
            $rangePOST = getRangeBulanan($bPOST, $tPOST);
            $bD = $rangePOST['bulan_dari'];
            $bS = $rangePOST['bulan_sampai'];
            $t  = $rangePOST['tahun_mulai'];
        } else {
            $taPOST  = $_POST['tahun_ajaran'] ?? $tahunAjaran;
            $semPOST = $_POST['semester']     ?? $semester;
            if (!in_array($semPOST, ['ganjil','genap'])) $semPOST = 'ganjil';
            $rangePOST = getRangeSemester($taPOST, $semPOST);
            $bD = $rangePOST['bulan_dari'];
            $bS = $rangePOST['bulan_sampai'];
            $t  = $rangePOST['tahun_mulai'];
        }

        $hasil = jalankanKMeans($pdo, $bD, $t, $bS);
        if ($hasil['status'] === 'success') {
            $msg = "Analisis selesai. {$hasil['total_siswa']} siswa telah dikelompokkan.";
            $modePeriode = $modePOST;
            $bulanDari = $bD; $bulanSampai = $bS; $tahun = $t; $bulan = $bD;

            if ($modePOST === 'bulanan') {
                $bulanFilter = $bPOST; $tahunFilter = $tPOST;
                $tahunAjaran = ''; $semester = '';
                $labelPeriode = labelBulanan($bPOST, $tPOST);
            } else {
                $tahunAjaran = $taPOST; $semester = $semPOST;
                $bulanFilter = null; $tahunFilter = null;
                $labelPeriode = labelSemester($tahunAjaran, $semester);
            }

            $evalData = [
                'silhouette'  => $hasil['silhouette_score'],
                'inertia'     => $hasil['inertia'],
                'elbow'       => $hasil['elbow'],
                'total_siswa' => $hasil['total_siswa'],
                'min_max'     => $hasil['minMax'],
                'centroids'   => $hasil['centroids'],
                'detail'      => $hasil['hasil'],
            ];

            // [FIX] Simpan permanen ke DB — filter & detail perhitungan terakhir
            // supaya tetap muncul walau pindah halaman / logout-login,
            // sampai "Jalankan Clustering" diklik lagi.
            $pdo->prepare("
                UPDATE settings
                SET kmeans_last_mode = ?, kmeans_last_tahun_ajaran = ?, kmeans_last_semester = ?,
                    kmeans_last_bulan = ?, kmeans_last_tahun_bulanan = ?, kmeans_last_detail = ?
                WHERE id = ?
            ")->execute([
                $modePOST,
                $modePOST === 'semester' ? $taPOST : null,
                $modePOST === 'semester' ? $semPOST : null,
                $modePOST === 'bulanan' ? $bPOST : null,
                $modePOST === 'bulanan' ? $tPOST : null,
                json_encode($evalData),
                $settings['id']
            ]);
        } else {
            $error = $hasil['message'];
        }
    }
    // [FIX] Action 'kirim_wa' lama (sinkron, sekali kirim semua) sudah
    // dipindah ke admin/ajax_kirim_wa_batch.php yang dipanggil per-batch
    // lewat AJAX oleh JS di bawah — supaya tidak timeout & tidak dianggap
    // spam burst saat data banyak.
}

// Ambil hasil clustering untuk range bulan
$stmtHasil = $pdo->prepare("
    SELECT hc.siswa_id, hc.cluster_id, hc.label_cluster,
           MAX(hc.jumlah_hadir) as jumlah_hadir,
           MAX(hc.jumlah_terlambat) as jumlah_terlambat,
           MAX(hc.jumlah_izin) as jumlah_izin,
           MAX(hc.jumlah_sakit) as jumlah_sakit,
           MAX(hc.jumlah_alfa) as jumlah_alfa,
           u.nama, k.nama_kelas, s.nis, s.no_hp_ortu
    FROM hasil_clustering hc
    JOIN siswa s ON hc.siswa_id = s.id
    JOIN users u ON s.user_id = u.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    WHERE hc.bulan >= ? AND hc.bulan <= ? AND hc.tahun = ?
    GROUP BY hc.siswa_id, hc.cluster_id, hc.label_cluster, u.nama, k.nama_kelas, s.nis, s.no_hp_ortu
    ORDER BY hc.cluster_id, u.nama
");
$stmtHasil->execute([$bulanDari, $bulanSampai, $tahun]);
$hasilClustering = $stmtHasil->fetchAll();

$statCluster = ['1' => 0, '2' => 0, '3' => 0];
foreach ($hasilClustering as $h) {
    if (isset($statCluster[$h['cluster_id']])) $statCluster[$h['cluster_id']]++;
}

// [FIX] $evalData sudah di-set langsung dari hasil run kalau baru saja
// klik "Jalankan Clustering" (di atas). Untuk kunjungan halaman biasa
// (GET, atau habis kirim WA, dll), ambil dari DB (permanen) bukan session.
if (!isset($evalData)) {
    $evalData = !empty($settings['kmeans_last_detail']) ? json_decode($settings['kmeans_last_detail'], true) : null;
}

$pageTitle = 'Analisis K-Means';
require_once '../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-success">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
  <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
  <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<!-- ── PANEL ATAS: Form + Keterangan Cluster ──────────────────────── -->
<div class="km-top-grid">

  <!-- Form Analisis -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:15px;height:15px"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
        Jalankan Analisis
      </div>
    </div>
    <div class="card-body">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="clustering">
        <div class="form-group">
          <label class="form-label">Mode Periode</label>
          <select name="mode_periode" id="km-mode-periode" class="form-control">
            <option value="semester" <?=$modePeriode==='semester'?'selected':''?>>Per Semester</option>
            <option value="bulanan"  <?=$modePeriode==='bulanan' ?'selected':''?>>Per Bulan</option>
          </select>
        </div>
        <div id="km-wrap-semester">
          <div class="form-group">
            <label class="form-label">Tahun Ajaran</label>
            <select name="tahun_ajaran" class="form-control">
              <?php foreach (daftarTahunAjaran(6) as $ta): ?>
              <option value="<?=$ta?>" <?=$ta===$tahunAjaran?'selected':''?>><?=$ta?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Semester</label>
            <select name="semester" class="form-control">
              <option value="ganjil" <?=$semester==='ganjil'?'selected':''?>>Ganjil (Jul–Des)</option>
              <option value="genap"  <?=$semester==='genap' ?'selected':''?>>Genap (Jan–Jun)</option>
            </select>
          </div>
        </div>
        <div id="km-wrap-bulanan" style="display:none">
          <div class="form-group">
            <label class="form-label">Bulan</label>
            <select name="bulan" class="form-control">
              <?php for ($bm = 1; $bm <= 12; $bm++): ?>
              <option value="<?=$bm?>" <?=$bm===(int)$bulanFilter?'selected':''?>><?=namaBulan($bm)?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Tahun</label>
            <select name="tahun" class="form-control">
              <?php $thnSkrg = (int)date('Y'); for ($ty = $thnSkrg - 3; $ty <= $thnSkrg + 1; $ty++): ?>
              <option value="<?=$ty?>" <?=$ty===(int)$tahunFilter?'selected':''?>><?=$ty?></option>
              <?php endfor; ?>
            </select>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block" onclick="showLoading('Menjalankan K-Means...')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
          Jalankan Clustering
        </button>
      </form>
      <script>
      function toggleKmModePeriode() {
        var m = document.getElementById('km-mode-periode').value;
        document.getElementById('km-wrap-semester').style.display = m === 'semester' ? 'block' : 'none';
        document.getElementById('km-wrap-bulanan').style.display  = m === 'bulanan'  ? 'block' : 'none';
      }
      document.getElementById('km-mode-periode').addEventListener('change', toggleKmModePeriode);
      toggleKmModePeriode(); // set tampilan awal sesuai mode yang sudah terpilih
      </script>
      <?php if ($evalData && !empty($evalData['centroids']) && !empty($evalData['detail'])): ?>
      <button type="button" class="btn btn-outline btn-block" style="margin-top:8px" onclick="openModal('modal-detail-kmeans')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:14px;height:14px"><path d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18M3 9v10a2 2 0 002 2h4M21 9v10a2 2 0 01-2 2h-4"/></svg>
        Lihat Perhitungan
      </button>
      <?php else: ?>
      <button type="button" class="btn btn-outline btn-block" style="margin-top:8px" disabled title="Jalankan Clustering terlebih dahulu">
        Lihat Perhitungan
      </button>
      <?php endif; ?>
    </div>
  </div>

  <!-- Cluster Legend Cards -->
  <div class="km-cluster-legend">
    <div class="km-legend-item km-legend-green">
      <div class="km-legend-dot"></div>
      <div>
        <div class="km-legend-title">Cluster 1 — Normal</div>
        <div class="km-legend-desc">Hadir tinggi, alfa rendah. Tidak perlu intervensi.</div>
      </div>
    </div>
    <div class="km-legend-item km-legend-yellow">
      <div class="km-legend-dot"></div>
      <div>
        <div class="km-legend-title">Cluster 2 — Perlu Perhatian</div>
        <div class="km-legend-desc">Kehadiran mulai menurun. Perlu monitoring berkala.</div>
      </div>
    </div>
    <div class="km-legend-item km-legend-red">
      <div class="km-legend-dot"></div>
      <div>
        <div class="km-legend-title">Cluster 3 — Anomali</div>
        <div class="km-legend-desc">Alfa tinggi, hadir rendah. Butuh intervensi segera.</div>
      </div>
    </div>
    <!-- Kirim WA -->
    <form id="form-kirim-wa" style="margin-top:4px" onsubmit="return false;">
      <input type="hidden" id="wa-csrf" value="<?= htmlspecialchars(csrfToken()) ?>">
      <input type="hidden" id="wa-mode-periode" value="<?= htmlspecialchars($modePeriode) ?>">
      <input type="hidden" id="wa-tahun-ajaran" value="<?= htmlspecialchars($tahunAjaran) ?>">
      <input type="hidden" id="wa-semester" value="<?= htmlspecialchars($semester) ?>">
      <input type="hidden" id="wa-bulan" value="<?= htmlspecialchars((string)$bulanFilter) ?>">
      <input type="hidden" id="wa-tahun" value="<?= htmlspecialchars((string)$tahunFilter) ?>">

      <div class="form-group" style="margin-bottom:8px">
        <label class="form-label" style="font-size:11.5px">Target Penerima</label>
        <select name="target_mode" id="wa-target-mode" class="form-control" style="font-size:12.5px;padding:6px 10px;height:32px">
          <option value="kelas">Per Kelas</option>
          <option value="semua">Semua Siswa</option>
        </select>
      </div>
      <div class="form-group" id="wa-kelas-wrap" style="margin-bottom:8px;display:none">
        <label class="form-label" style="font-size:11.5px">Pilih Kelas</label>
        <select name="target_kelas" id="wa-target-kelas" class="form-control" style="font-size:12.5px;padding:6px 10px;height:32px">
          <?php foreach ($daftarKelasWA as $kw): ?>
          <option value="<?= $kw['id'] ?>"><?= htmlspecialchars($kw['nama_kelas']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <button type="button" id="wa-submit-btn" class="btn btn-wa btn-block"
              <?= empty($hasilClustering) ? 'disabled' : '' ?>
              onclick="mulaiKirimWA()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.5a19.79 19.79 0 01-3.07-8.67A2 2 0 012 .84h3a2 2 0 012 1.72c.13.98.36 1.94.7 2.81a2 2 0 01-.45 2.11L6.09 8.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.87.34 1.83.57 2.81.7A2 2 0 0122 16.92z"/></svg>
        Kirim Notifikasi WhatsApp
      </button>

      <!-- Progress bar (disembunyikan sampai proses kirim mulai) -->
      <div id="wa-progress-wrap" style="display:none;margin-top:10px">
        <div style="height:8px;border-radius:4px;background:#e5e7eb;overflow:hidden">
          <div id="wa-progress-bar" style="height:100%;width:0%;background:#16a34a;transition:width .25s"></div>
        </div>
        <div id="wa-progress-text" style="font-size:11px;color:var(--text-muted);margin-top:6px;text-align:center">
          Menyiapkan...
        </div>
      </div>

      <div id="wa-hint" style="font-size:11px;color:var(--text-muted);margin-top:6px;text-align:center">
        Hanya dikirim ke siswa Cluster 2 (Perlu Perhatian) &amp; Cluster 3 (Anomali). Cluster 1 (Normal) tidak dikirimi peringatan.<br>
        Dikirim bertahap (15 siswa/batch) supaya tidak timeout &amp; tidak dianggap spam.
      </div>
    </form>
    <script>
    function toggleWaKelasWrap() {
      document.getElementById('wa-kelas-wrap').style.display =
        document.getElementById('wa-target-mode').value === 'kelas' ? 'block' : 'none';
    }
    document.getElementById('wa-target-mode').addEventListener('change', toggleWaKelasWrap);
    toggleWaKelasWrap(); // set tampilan awal sesuai opsi yang sudah terpilih saat load

    function mulaiKirimWA() {
      var mode = document.getElementById('wa-target-mode').value;
      var teks = mode === 'kelas'
          ? 'Kirim notifikasi WA ke siswa Cluster Perlu Perhatian / Anomali di kelas terpilih?'
          : 'Kirim notifikasi WA ke SEMUA siswa Cluster Perlu Perhatian / Anomali? Proses berjalan bertahap di background.';
      appConfirm({
        title: 'Konfirmasi Kirim Notifikasi WA',
        body: teks,
        btnLabel: 'Ya, Kirim',
        btnClass: 'btn-primary',
        onConfirm: () => jalankanKirimWA(mode)
      });
    }

    async function jalankanKirimWA(mode) {
      var btn = document.getElementById('wa-submit-btn');
      var wrap = document.getElementById('wa-progress-wrap');
      var bar = document.getElementById('wa-progress-bar');
      var text = document.getElementById('wa-progress-text');
      var hint = document.getElementById('wa-hint');

      btn.disabled = true;
      hint.style.display = 'none';
      wrap.style.display = 'block';
      bar.style.width = '0%';
      text.textContent = 'Menyiapkan...';

      var offset = 0;
      var totalTerkirim = 0;
      var totalGagal = 0;
      var total = null;

      try {
        while (true) {
          var fd = new FormData();
          fd.append('csrf_token', document.getElementById('wa-csrf').value);
          fd.append('mode_periode', document.getElementById('wa-mode-periode').value);
          fd.append('tahun_ajaran', document.getElementById('wa-tahun-ajaran').value);
          fd.append('semester', document.getElementById('wa-semester').value);
          fd.append('bulan', document.getElementById('wa-bulan').value);
          fd.append('tahun', document.getElementById('wa-tahun').value);
          fd.append('target_mode', mode);
          if (mode === 'kelas') {
            fd.append('target_kelas', document.getElementById('wa-target-kelas').value);
          }
          fd.append('offset', offset);

          var resp = await fetch('ajax_kirim_wa_batch.php', { method: 'POST', body: fd });
          var data = await resp.json();

          if (!data.status) {
            text.textContent = 'Gagal: ' + (data.message || 'Terjadi kesalahan.');
            break;
          }
          if (data.total === 0) {
            text.textContent = data.message || 'Tidak ada siswa target.';
            break;
          }

          total = data.total;
          totalTerkirim += data.terkirim;
          totalGagal += data.gagal;
          offset = data.next_offset;

          var pct = Math.round((offset / total) * 100);
          bar.style.width = pct + '%';
          text.textContent = 'Mengirim... ' + offset + '/' + total + ' (terkirim ' + totalTerkirim + ', gagal ' + totalGagal + ')';

          if (data.done) {
            bar.style.width = '100%';
            bar.style.background = totalGagal > 0 ? '#f59e0b' : '#16a34a';
            text.textContent = 'Selesai. Terkirim: ' + totalTerkirim + ', gagal: ' + totalGagal + ' dari ' + total + ' siswa.';
            break;
          }
        }
      } catch (e) {
        text.textContent = 'Koneksi terputus. Coba klik kirim lagi (siswa yang sudah terkirim tidak akan dikirim ulang secara berlebihan).';
      } finally {
        btn.disabled = false;
      }
    }
    </script>
  </div>
</div>


<?php if (!empty($hasilClustering)): ?>

<!-- ── STAT CARDS ─────────────────────────────────────────────────── -->
<div class="stats-grid" style="margin-bottom:18px">
  <div class="stat-card blue">
    <div class="stat-icon blue">
      <svg viewBox="0 0 24 24" fill="none" stroke="#1555a0" stroke-width="1.8"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
    </div>
    <div>
      <div class="stat-value"><?= count($hasilClustering) ?></div>
      <div class="stat-label">Total Siswa</div>
    </div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon green">
      <svg viewBox="0 0 24 24" fill="none" stroke="#1a7a45" stroke-width="1.8"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
    </div>
    <div>
      <div class="stat-value"><?= $statCluster['1'] ?></div>
      <div class="stat-label">Kehadiran Normal</div>
    </div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon yellow">
      <svg viewBox="0 0 24 24" fill="none" stroke="#92540e" stroke-width="1.8"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
    </div>
    <div>
      <div class="stat-value"><?= $statCluster['2'] ?></div>
      <div class="stat-label">Perlu Perhatian</div>
    </div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon red">
      <svg viewBox="0 0 24 24" fill="none" stroke="#b91c1c" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    </div>
    <div>
      <div class="stat-value"><?= $statCluster['3'] ?></div>
      <div class="stat-label">Anomali</div>
    </div>
  </div>
</div>

<!-- ── CHART ROW 1: Pie + Elbow ───────────────────────────────────── -->
<div class="km-chart-row km-chart-row-top">

  <!-- Distribusi Cluster (Donut) -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Distribusi Cluster</div>
    </div>
    <div class="card-body km-chart-body">
      <canvas id="chart-donut"></canvas>
      <div id="donut-center-label" class="km-donut-center">
        <div class="km-donut-total"><?= count($hasilClustering) ?></div>
        <div class="km-donut-sub">Siswa</div>
      </div>
    </div>
  </div>

  <?php if ($evalData && !empty($evalData['elbow']['k'])): ?>
  <!-- Elbow Method -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">Elbow Method — K Optimal</div>
    </div>
    <div class="card-body km-chart-body">
      <canvas id="chart-elbow"></canvas>
    </div>
  </div>
  <?php endif; ?>

  <!-- Evaluasi (Silhouette + Inertia) -->
  <?php if ($evalData): ?>
  <div class="km-eval-col">
    <?php
      $ss = (float)$evalData['silhouette'];
      $ssColor = $ss >= 0.5 ? '#1a7a45' : ($ss >= 0.25 ? '#92540e' : '#b91c1c');
      $ssLabel = $ss >= 0.5 ? 'Kuat' : ($ss >= 0.25 ? 'Sedang' : 'Lemah');
    ?>
    <div class="card km-eval-card">
      <div class="card-body" style="text-align:center;padding:18px 14px">
        <div class="km-eval-label">Silhouette Score</div>
        <div class="km-eval-value" style="color:<?= $ssColor ?>"><?= number_format($ss, 4) ?></div>
        <div class="km-eval-badge" style="background:<?= $ssColor ?>22;color:<?= $ssColor ?>"><?= $ssLabel ?></div>
        <div class="km-eval-note">Mendekati 1.0 = cluster makin terpisah</div>
      </div>
    </div>
    <div class="card km-eval-card">
      <div class="card-body" style="text-align:center;padding:18px 14px">
        <div class="km-eval-label">Inertia (WCSS)</div>
        <div class="km-eval-value" style="color:var(--accent)"><?= number_format($evalData['inertia'], 3) ?></div>
        <div class="km-eval-badge" style="background:#1a6b6322;color:var(--accent)">K = 3</div>
        <div class="km-eval-note"><?= $evalData['total_siswa'] ?> siswa · 5 fitur ternormalisasi</div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>



<!-- ── SCATTER PLOT ────────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:18px">
  <div class="card-header">
    <div class="card-title">
      Peta Kehadiran vs Alfa
      <span class="km-hint-badge">Hover/sentuh titik → nama siswa muncul di panel kanan</span>
    </div>
    <div style="display:flex;gap:6px;align-items:center">
      <select id="scatter-filter" class="form-control" style="width:auto;font-size:12px;padding:4px 8px;height:30px">
        <option value="0">Semua Cluster</option>
        <option value="1">Cluster 1 — Normal</option>
        <option value="2">Cluster 2 — Perhatian</option>
        <option value="3">Cluster 3 — Anomali</option>
      </select>
    </div>
  </div>
  <div class="card-body km-scatter-body">
    <!-- Chart canvas -->
    <div class="km-scatter-canvas-wrap">
      <canvas id="chart-scatter"></canvas>
      <div id="km-tooltip" class="km-tooltip" role="tooltip" aria-live="polite"></div>
    </div>
    <!-- Sidebar: daftar semua siswa -->
    <div class="km-scatter-sidebar" id="scatter-sidebar">
      <div class="km-sidebar-header">
        <span id="sidebar-title">Semua Siswa</span>
        <span id="sidebar-count" class="km-sidebar-count"></span>
      </div>
      <div class="km-sidebar-search">
        <input type="text" id="sidebar-search" placeholder="Cari nama..." class="form-control" style="font-size:12px;padding:5px 9px;height:30px">
      </div>
      <div class="km-sidebar-list" id="sidebar-list">
        <!-- filled by JS -->
      </div>
    </div>
  </div>
</div>

<?php if ($evalData && !empty($evalData['centroids']) && !empty($evalData['detail'])): ?>
<!-- ── DETAIL PERHITUNGAN K-MEANS (MODAL) ──────────────────────────── -->
<div id="modal-detail-kmeans" class="modal-overlay">
  <div class="modal-box" style="max-width:920px;width:95%">
    <div class="modal-header">
      <div class="modal-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><path d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18M3 9v10a2 2 0 002 2h4M21 9v10a2 2 0 01-2 2h-4"/></svg>
        Detail Perhitungan K-Means <span style="font-weight:400;color:var(--text-muted);font-size:12px">(bukti tahapan algoritma)</span>
      </div>
      <button class="modal-close" onclick="closeModal('modal-detail-kmeans')">&times;</button>
    </div>
    <div class="modal-body" id="km-detail-body" style="max-height:70vh;overflow-y:auto">

    <!-- 1. Normalisasi Min-Max -->
    <div style="margin-bottom:20px">
      <div style="font-weight:800;font-size:13px;margin-bottom:6px">1. Normalisasi Min-Max</div>
      <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px">
        Setiap fitur dinormalisasi ke rentang [0,1] dengan rumus: <code>x' = (x - min) / (max - min)</code>, supaya fitur dengan skala besar tidak mendominasi perhitungan jarak.
      </div>
      <div class="table-responsive">
        <table style="font-size:12px">
          <thead><tr><th>Fitur</th><th style="text-align:center">Min</th><th style="text-align:center">Max</th></tr></thead>
          <tbody>
            <?php
            $fiturLabel = ['Hadir (+Terlambat)', 'Izin', 'Sakit', 'Alfa', 'Terlambat'];
            foreach ($fiturLabel as $fi => $fl):
            ?>
            <tr>
              <td><?= $fl ?></td>
              <td style="text-align:center"><?= number_format($evalData['min_max']['min'][$fi] ?? 0, 0) ?></td>
              <td style="text-align:center"><?= number_format($evalData['min_max']['max'][$fi] ?? 0, 0) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 2. Centroid Final -->
    <div style="margin-bottom:20px">
      <div style="font-weight:800;font-size:13px;margin-bottom:6px">2. Titik Pusat (Centroid) Final — Hasil Konvergensi</div>
      <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px">
        Nilai centroid dalam bentuk ternormalisasi [0,1] setelah iterasi K-Means konvergen (posisi centroid tidak berubah lagi).
      </div>
      <div class="table-responsive">
        <table style="font-size:12px">
          <thead>
            <tr><th>Cluster</th><th style="text-align:center">Hadir</th><th style="text-align:center">Izin</th><th style="text-align:center">Sakit</th><th style="text-align:center">Alfa</th><th style="text-align:center">Terlambat</th></tr>
          </thead>
          <tbody>
            <?php
            $namaCluster = [1=>'Cluster 1 — Normal', 2=>'Cluster 2 — Perhatian', 3=>'Cluster 3 — Anomali'];
            foreach ($evalData['centroids'] as $ci => $centroid):
            ?>
            <tr>
              <td><strong><?= $namaCluster[$ci+1] ?? ('Cluster '.($ci+1)) ?></strong></td>
              <?php foreach ($centroid as $val): ?>
              <td style="text-align:center;font-family:var(--font-mono)"><?= number_format($val, 4) ?></td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- 3. Jarak Euclidean tiap siswa ke semua centroid -->
    <div>
      <div style="font-weight:800;font-size:13px;margin-bottom:6px">3. Jarak Euclidean ke Setiap Centroid (contoh perhitungan per siswa)</div>
      <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px">
        Rumus: <code>d = √Σ(xᵢ - cᵢ)²</code> — siswa masuk ke cluster dengan jarak <strong>terkecil</strong> (ditandai hijau). Menampilkan <span id="km-detail-count">10</span> dari <?= count($evalData['detail']) ?> siswa.
      </div>
      <div class="table-responsive">
        <table style="font-size:11.5px" id="km-detail-table">
          <thead>
            <tr>
              <th>Nama</th>
              <th style="text-align:center">Data Ternormalisasi</th>
              <th style="text-align:center">Jarak ke C1</th>
              <th style="text-align:center">Jarak ke C2</th>
              <th style="text-align:center">Jarak ke C3</th>
              <th style="text-align:center">Masuk Cluster</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach (array_slice($evalData['detail'], 0, 10) as $d): ?>
            <tr>
              <td><?= htmlspecialchars($d['nama']) ?></td>
              <td style="text-align:center;font-family:var(--font-mono);font-size:10.5px">[<?= implode(', ', $d['normalized']) ?>]</td>
              <?php for ($c = 1; $c <= 3; $c++):
                $isMin = $c == $d['cluster_id']; ?>
              <td style="text-align:center;font-family:var(--font-mono);<?= $isMin ? 'background:#e8f7ee;color:var(--success);font-weight:800' : '' ?>">
                <?= number_format($d['jarak_ke_semua'][$c] ?? 0, 4) ?>
              </td>
              <?php endfor; ?>
              <td style="text-align:center"><strong><?= $namaCluster[$d['cluster_id']] ?? '-' ?></strong></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if (count($evalData['detail']) > 10): ?>
      <button type="button" class="btn btn-sm btn-outline" style="margin-top:8px" onclick="tampilkanSemuaDetailKMeans()">
        Tampilkan semua <?= count($evalData['detail']) ?> siswa
      </button>
      <?php endif; ?>
    </div>

    </div>
    <div class="modal-footer" style="display:flex;justify-content:flex-end;gap:8px;padding:14px 20px;border-top:1px solid var(--border-light)">
      <button type="button" class="btn btn-secondary" onclick="closeModal('modal-detail-kmeans')">Kembali</button>
      <a href="export/export_kmeans_detail.php" target="_blank" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
        Ekspor Excel
      </a>
    </div>
  </div>
</div>
<script>
var kmDetailFull = <?= json_encode(array_map(function($d) use ($namaCluster) {
    return [
        'nama' => $d['nama'],
        'norm' => $d['normalized'],
        'j1' => $d['jarak_ke_semua'][1] ?? 0,
        'j2' => $d['jarak_ke_semua'][2] ?? 0,
        'j3' => $d['jarak_ke_semua'][3] ?? 0,
        'cluster' => $d['cluster_id'],
        'clusterLabel' => $namaCluster[$d['cluster_id']] ?? '-',
    ];
}, $evalData['detail'])) ?>;
function tampilkanSemuaDetailKMeans() {
    var tbody = document.querySelector('#km-detail-table tbody');
    var rows = kmDetailFull.map(function(d) {
        function cell(c) {
            var isMin = c === d.cluster;
            var val = c===1?d.j1:(c===2?d.j2:d.j3);
            return '<td style="text-align:center;font-family:var(--font-mono);' + (isMin?'background:#e8f7ee;color:var(--success);font-weight:800':'') + '">' + val.toFixed(4) + '</td>';
        }
        return '<tr><td>' + d.nama + '</td>' +
               '<td style="text-align:center;font-family:var(--font-mono);font-size:10.5px">[' + d.norm.join(', ') + ']</td>' +
               cell(1) + cell(2) + cell(3) +
               '<td style="text-align:center"><strong>' + d.clusterLabel + '</strong></td></tr>';
    }).join('');
    tbody.innerHTML = rows;
    document.getElementById('km-detail-count').textContent = kmDetailFull.length;
    event.target.style.display = 'none';
}
</script>
<?php endif; ?>

<!-- ── TABEL HASIL ─────────────────────────────────────────────────── -->
<div class="card">
  <div class="card-header">
    <div class="card-title">
      Detail Hasil — <?= htmlspecialchars($labelPeriode) ?>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <!-- Filter cluster -->
      <select id="filter-cluster" class="form-control" style="width:auto;min-width:140px;font-size:12.5px;padding:5px 10px;height:32px">
        <option value="all">Semua Cluster</option>
        <option value="1">Cluster 1 — Normal</option>
        <option value="2">Cluster 2 — Perhatian</option>
        <option value="3">Cluster 3 — Anomali</option>
      </select>
      <a href="export/export_clustering.php?jenis=clustering&bulan=<?= $bulan ?>&tahun=<?= $tahun ?>" class="btn btn-sm btn-success">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Unduh Excel
      </a>
    </div>
  </div>
  <div class="table-responsive">
    <table id="tabel-hasil">
      <thead>
        <tr>
          <th>NIS</th>
          <th>Nama Siswa</th>
          <th>Kelas</th>
          <th style="text-align:center">Hadir</th>
          <th style="text-align:center">Terlambat</th>
          <th style="text-align:center">Izin</th>
          <th style="text-align:center">Sakit</th>
          <th style="text-align:center">Alfa</th>
          <th style="text-align:center">Cluster</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($hasilClustering as $h): ?>
        <tr data-cluster="<?= $h['cluster_id'] ?>">
          <td><span style="font-family:var(--font-mono);font-size:12px"><?= htmlspecialchars($h['nis']) ?></span></td>
          <td><strong><?= htmlspecialchars($h['nama']) ?></strong></td>
          <td><?= htmlspecialchars($h['nama_kelas'] ?? '-') ?></td>
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

<?php
/* ── Prepare JSON for charts ────────────────────────────── */
$scatterData = [];
foreach ($hasilClustering as $h) {
    $scatterData[] = [
        'x'      => (int)$h['jumlah_hadir'],
        'y'      => (int)$h['jumlah_alfa'],
        'nama'   => $h['nama'],
        'kelas'  => $h['nama_kelas'] ?? '-',
        'hadir'  => (int)$h['jumlah_hadir'],
        'alfa'   => (int)$h['jumlah_alfa'],
        'late'   => (int)$h['jumlah_terlambat'],
        'cluster'=> (int)$h['cluster_id'],
    ];
}

$avgPerCluster = [];
for ($c = 1; $c <= 3; $c++) {
    $items = array_filter($hasilClustering, fn($h) => $h['cluster_id'] == $c);
    if (empty($items)) { $avgPerCluster[$c] = [0, 0, 0, 0, 0]; continue; }
    $n = count($items);
    $avgPerCluster[$c] = [
        round(array_sum(array_column($items, 'jumlah_hadir'))    / $n, 1),
        round(array_sum(array_column($items, 'jumlah_terlambat'))/ $n, 1),
        round(array_sum(array_column($items, 'jumlah_izin'))     / $n, 1),
        round(array_sum(array_column($items, 'jumlah_sakit'))    / $n, 1),
        round(array_sum(array_column($items, 'jumlah_alfa'))     / $n, 1),
    ];
}

$pieJson    = json_encode([$statCluster['1'], $statCluster['2'], $statCluster['3']]);
$scatterJson = json_encode($scatterData);
$barC1      = json_encode($avgPerCluster[1] ?? [0,0,0,0,0]);
$barC2      = json_encode($avgPerCluster[2] ?? [0,0,0,0,0]);
$barC3      = json_encode($avgPerCluster[3] ?? [0,0,0,0,0]);

$elbowK       = isset($evalData['elbow']['k'])      ? json_encode($evalData['elbow']['k'])      : '[]';
$elbowInertia = isset($evalData['elbow']['inertia']) ? json_encode($evalData['elbow']['inertia']) : '[]';
?>

<?php endif; /* end hasilClustering */ ?>

<?php
$extraJs = '<style>
/* ── K-Means page styles ──────────────────────────── */
.km-top-grid{display:grid;grid-template-columns:340px 1fr;gap:18px;margin-bottom:18px;align-items:start}
.km-cluster-legend{display:flex;flex-direction:column;gap:10px}
.km-legend-item{display:flex;align-items:flex-start;gap:12px;padding:13px 16px;border-radius:10px;border:1px solid var(--border)}
.km-legend-dot{width:13px;height:13px;border-radius:50%;flex-shrink:0;margin-top:3px}
.km-legend-green{background:var(--success-bg);border-color:#b2d8c0}
.km-legend-green .km-legend-dot{background:var(--success)}
.km-legend-yellow{background:var(--warning-bg);border-color:#f5d08a}
.km-legend-yellow .km-legend-dot{background:#d97706}
.km-legend-red{background:var(--danger-bg);border-color:#f5b3b3}
.km-legend-red .km-legend-dot{background:var(--danger)}
.km-legend-title{font-size:13px;font-weight:700;color:var(--text);margin-bottom:2px}
.km-legend-desc{font-size:12px;color:var(--text-muted);line-height:1.5}
.km-wa-badge{display:inline-flex;align-items:center;justify-content:center;background:rgba(255,255,255,.25);border-radius:20px;min-width:22px;height:18px;font-size:11px;font-weight:700;padding:0 5px;margin-left:4px}
/* chart rows */
.km-chart-row{display:grid;gap:18px;margin-bottom:18px}
.km-chart-row-top{grid-template-columns:1fr 1.6fr auto}
.km-chart-body{position:relative;display:flex;align-items:center;justify-content:center}
.km-chart-body canvas{max-height:220px}
/* donut center */
.km-donut-center{position:absolute;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none}
.km-donut-total{font-size:26px;font-weight:900;color:var(--text);font-family:var(--font-mono);line-height:1}
.km-donut-sub{font-size:11px;color:var(--text-muted);font-weight:600;text-transform:uppercase;letter-spacing:.6px;margin-top:2px}
/* eval cards */
.km-eval-col{display:flex;flex-direction:column;gap:12px}
.km-eval-card .card-body{padding:16px 14px!important}
.km-eval-label{font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:1.2px;color:var(--text-muted);margin-bottom:6px}
.km-eval-value{font-family:var(--font-mono);font-size:26px;font-weight:900;line-height:1.1;margin-bottom:6px}
.km-eval-badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;margin-bottom:5px}
.km-eval-note{font-size:11px;color:var(--text-muted)}
/* hint */
.km-hint-badge{font-size:11px;font-weight:500;color:var(--text-muted);background:var(--bg);padding:3px 8px;border-radius:20px;border:1px solid var(--border);margin-left:6px}
/* tooltip */
.km-tooltip{display:none;position:fixed;background:rgba(15,23,42,.9);backdrop-filter:blur(6px);color:#fff;padding:9px 13px;border-radius:9px;font-size:12.5px;pointer-events:none;z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,.3);border:1px solid rgba(255,255,255,.1);line-height:1.6;max-width:210px}
.km-tooltip strong{display:block;font-size:13px;font-weight:800;margin-bottom:2px}
.km-tooltip .km-tip-row{display:flex;justify-content:space-between;gap:12px;font-size:11.5px;opacity:.85}
/* scatter layout */
.km-scatter-body{display:grid;grid-template-columns:1fr 220px;gap:0;padding:0!important;align-items:stretch}
.km-scatter-canvas-wrap{position:relative;padding:16px 16px 16px 16px;border-right:1px solid var(--border)}
/* sidebar */
.km-scatter-sidebar{display:flex;flex-direction:column;min-height:0}
.km-sidebar-header{display:flex;align-items:center;justify-content:space-between;padding:10px 12px 6px;border-bottom:1px solid var(--border-light)}
.km-sidebar-header span:first-child{font-size:12px;font-weight:700;color:var(--text)}
.km-sidebar-count{font-size:11px;color:var(--text-muted);background:var(--bg-subtle);padding:2px 7px;border-radius:10px}
.km-sidebar-search{padding:8px 10px;border-bottom:1px solid var(--border-light)}
.km-sidebar-search input{width:100%}
.km-sidebar-list{overflow-y:auto;flex:1;max-height:340px;padding:4px 0}
.km-sidebar-item{display:flex;align-items:center;gap:0;padding:6px 10px;cursor:pointer;transition:background .12s;font-size:12px;border-radius:0}
.km-sidebar-item:hover,.km-si-active{background:var(--primary-pale)!important}
.km-si-text{flex:1;min-width:0}
.km-si-nama{font-weight:600;color:var(--text);font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.km-si-meta{font-size:10.5px;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.km-si-badge{font-size:10px;font-weight:700;padding:2px 5px;border-radius:4px;flex-shrink:0;margin-left:4px}
.km-badge-c1{background:#e6f4ec;color:#1a7a45}
.km-badge-c2{background:#fef3e2;color:#92540e}
.km-badge-c3{background:#fce9e9;color:#b91c1c}
/* responsive */
@media(max-width:900px){
  .km-top-grid{grid-template-columns:1fr}
  .km-chart-row-top{grid-template-columns:1fr 1fr}
  .km-eval-col{flex-direction:row}
}
@media(max-width:640px){
  .km-chart-row-top{grid-template-columns:1fr}
  .km-eval-col{flex-direction:row}
  .km-hint-badge{display:none}
  .km-scatter-body{grid-template-columns:1fr}
  .km-scatter-canvas-wrap{border-right:none;border-bottom:1px solid var(--border)}
  .km-sidebar-list{max-height:200px}
}
</style>';

if (!empty($hasilClustering)):
$extraJs .= <<<JS
<script>
(function(){

// ── 1. DONUT CHART ────────────────────────────────────────
buatChartDonut('chart-donut',
  ['Normal','Perlu Perhatian','Anomali'],
  {$pieJson},
  ['#1a7a45','#d97706','#b91c1c']
);

// ── 2. ELBOW METHOD ───────────────────────────────────────
(function(){
  var kV = {$elbowK};
  var iV = {$elbowInertia};
  if (!kV.length) return;
  var ctx = document.getElementById('chart-elbow');
  if (!ctx) return;
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: kV.map(function(k){ return 'K='+k; }),
      datasets:[{
        label: 'Inertia',
        data: iV,
        borderColor: '#1e3a5f',
        backgroundColor: 'rgba(30,58,95,.06)',
        borderWidth: 2.5,
        pointBackgroundColor: kV.map(function(k){ return k===3?'#b91c1c':'#1e3a5f'; }),
        pointBorderColor: kV.map(function(k){ return k===3?'#b91c1c':'#1e3a5f'; }),
        pointRadius: kV.map(function(k){ return k===3?8:4; }),
        tension: 0.35, fill: true
      }]
    },
    options:{
      responsive:true,
      plugins:{
        legend:{display:false},
        tooltip:{
          callbacks:{
            label:function(c){ return 'Inertia: '+c.parsed.y.toFixed(3); },
            afterLabel:function(c){ return kV[c.dataIndex]===3?'← K optimal (Elbow)':''; }
          }
        }
      },
      scales:{
        x:{title:{display:true,text:'Jumlah Cluster (K)',font:{size:11}}},
        y:{title:{display:true,text:'Inertia',font:{size:11}},beginAtZero:false}
      }
    }
  });
})();


// ── 4. SCATTER PLOT — semua siswa, sidebar nama ────────────
(function(){
  var raw    = {$scatterJson};
  var colors = {1:'#1a7a45', 2:'#d97706', 3:'#b91c1c'};
  var bgs    = {1:'#1a7a4522', 2:'#d9770622', 3:'#b91c1c22'};
  var labels = {1:'Normal', 2:'Perlu Perhatian', 3:'Anomali'};

  // Sedikit jitter agar titik yang overlap tidak persis menimpa
  function jitter(v, mag){ return v + (Math.random()-0.5)*mag; }

  function buildDatasets(filterCluster){
    return [1,2,3].map(function(c){
      if (filterCluster && filterCluster!==c) {
        return {
          label:'Cluster '+c+' — '+labels[c],
          data:[], backgroundColor:colors[c]+'99', borderColor:colors[c],
          pointRadius:0
        };
      }
      var pts = raw.filter(function(d){ return d.cluster===c; })
                   .map(function(d){
                     return {
                       x:jitter(d.hadir,0.4), y:jitter(d.alfa,0.4),
                       nama:d.nama, kelas:d.kelas,
                       hadir:d.hadir, alfa:d.alfa, late:d.late,
                       cluster:c
                     };
                   });
      return {
        label:'Cluster '+c+' — '+labels[c],
        data: pts,
        backgroundColor: colors[c]+'99',
        borderColor:     colors[c],
        pointRadius: 4,
        pointHoverRadius: 8,
        pointHoverBorderWidth: 2,
        pointHoverBorderColor: '#fff',
        pointHoverBackgroundColor: colors[c]
      };
    });
  }

  var scatter = new Chart(document.getElementById('chart-scatter'),{
    type:'scatter',
    data:{datasets: buildDatasets(0)},
    options:{
      responsive:true,
      animation:{duration:300},
      plugins:{
        tooltip:{enabled:false},
        legend:{
          labels:{font:{family:'Plus Jakarta Sans',weight:'600',size:12},
                  usePointStyle:true, pointStyleWidth:10}
        }
      },
      scales:{
        x:{title:{display:true,text:'Jumlah Hadir',font:{size:12,weight:'600'}},
           beginAtZero:true, grid:{color:'rgba(0,0,0,.04)'},
           ticks:{font:{size:11}}},
        y:{title:{display:true,text:'Jumlah Alfa',font:{size:12,weight:'600'}},
           beginAtZero:true, grid:{color:'rgba(0,0,0,.04)'},
           ticks:{font:{size:11}}}
      }
    }
  });

  // ── Sidebar: daftar semua siswa ─────────────────────────
  var list       = document.getElementById('sidebar-list');
  var sideTitle  = document.getElementById('sidebar-title');
  var sideCount  = document.getElementById('sidebar-count');
  var searchBox  = document.getElementById('sidebar-search');
  var activeFilter = 0;
  var activeItem   = null;

  function sortedByCluster(arr){
    return arr.slice().sort(function(a,b){
      if (a.cluster!==b.cluster) return a.cluster-b.cluster;
      return a.nama.localeCompare(b.nama);
    });
  }

  function buildSidebar(filterCluster, searchTerm){
    list.innerHTML = '';
    var shown = raw;
    if (filterCluster) shown = shown.filter(function(d){ return d.cluster===filterCluster; });
    if (searchTerm)    shown = shown.filter(function(d){ return d.nama.toLowerCase().indexOf(searchTerm)!==-1 || d.kelas.toLowerCase().indexOf(searchTerm)!==-1; });
    shown = sortedByCluster(shown);

    sideCount.textContent = shown.length + ' siswa';

    shown.forEach(function(d, idx){
      var dot = '<span style="width:8px;height:8px;border-radius:50%;background:'+colors[d.cluster]+';display:inline-block;margin-right:7px;flex-shrink:0"></span>';
      var row = document.createElement('div');
      row.className = 'km-sidebar-item';
      row.dataset.nama    = d.nama;
      row.dataset.cluster = d.cluster;
      row.innerHTML =
        dot+
        '<div class="km-si-text">'+
          '<div class="km-si-nama">'+d.nama+'</div>'+
          '<div class="km-si-meta">'+d.kelas+' · H:'+d.hadir+' A:'+d.alfa+'</div>'+
        '</div>'+
        '<span class="km-si-badge km-badge-c'+d.cluster+'">C'+d.cluster+'</span>';

      // Hover on sidebar item → highlight point on chart
      row.addEventListener('mouseenter', function(){
        highlightPoint(d.nama, row);
      });
      row.addEventListener('mouseleave', function(){
        clearHighlight();
      });
      // Click on mobile to toggle highlight
      row.addEventListener('click', function(){
        if (activeItem===row) { clearHighlight(); activeItem=null; }
        else { highlightPoint(d.nama, row); activeItem=row; }
      });

      list.appendChild(row);
    });
  }

  function highlightPoint(nama, rowEl){
    // Highlight all matching rows
    document.querySelectorAll('.km-sidebar-item').forEach(function(r){
      r.classList.toggle('km-si-active', r===rowEl);
    });

    // Find the point index and visually highlight it
    scatter.data.datasets.forEach(function(ds, di){
      ds.data.forEach(function(pt, pi){
        if (pt.nama === nama) {
          // Show tooltip-like info in the fixed tooltip div
          showInfoBox(pt);
        }
      });
    });

    // Draw a highlight ring using Chart.js active elements
    var activeEls = [];
    scatter.data.datasets.forEach(function(ds, di){
      ds.data.forEach(function(pt, pi){
        if (pt.nama===nama) activeEls.push({datasetIndex:di, index:pi});
      });
    });
    scatter.setActiveElements(activeEls);
    scatter.update('none');
  }

  function clearHighlight(){
    document.querySelectorAll('.km-sidebar-item').forEach(function(r){
      r.classList.remove('km-si-active');
    });
    scatter.setActiveElements([]);
    scatter.update('none');
    var tip = document.getElementById('km-tooltip');
    tip.style.display='none';
  }

  function showInfoBox(ds){
    var tip = document.getElementById('km-tooltip');
    var c   = ds.cluster;
    var dot = '<span style="display:inline-block;width:9px;height:9px;border-radius:50%;background:'+colors[c]+';margin-right:5px;vertical-align:middle"></span>';
    tip.innerHTML =
      '<strong>'+ds.nama+'</strong>'+
      '<div style="font-size:11px;opacity:.72;margin-bottom:4px">'+ds.kelas+'</div>'+
      '<div class="km-tip-row"><span>Hadir</span><strong>'+ds.hadir+'</strong></div>'+
      '<div class="km-tip-row"><span>Alfa</span><strong>'+ds.alfa+'</strong></div>'+
      '<div class="km-tip-row"><span>Terlambat</span><strong>'+ds.late+'</strong></div>'+
      '<div style="margin-top:5px;font-size:11px">'+dot+'Cluster '+c+' — '+labels[c]+'</div>';
    // Pin tooltip near top-right of chart
    var cnvRect = document.getElementById('chart-scatter').getBoundingClientRect();
    tip.style.left    = (cnvRect.right - 220)+'px';
    tip.style.top     = (cnvRect.top + 10)+'px';
    tip.style.display = 'block';
  }

  // Initial sidebar build
  buildSidebar(0, '');

  // ── Hover on chart → scroll + highlight sidebar item ───
  var cnv = document.getElementById('chart-scatter');
  var tip = document.getElementById('km-tooltip');

  function handleChartHover(e){
    var clientX = e.clientX || (e.touches&&e.touches[0].clientX) || 0;
    var clientY = e.clientY || (e.touches&&e.touches[0].clientY) || 0;
    var pts = scatter.getElementsAtEventForMode(e,'nearest',{intersect:false},false);

    if (!pts.length){ return; }
    var pt  = pts[0];
    var ds  = scatter.data.datasets[pt.datasetIndex].data[pt.index];
    if (!ds) return;

    // Highlight matching sidebar row & scroll to it
    var rows = document.querySelectorAll('.km-sidebar-item');
    rows.forEach(function(r){ r.classList.remove('km-si-active'); });
    rows.forEach(function(r){
      if (r.dataset.nama === ds.nama){
        r.classList.add('km-si-active');
        r.scrollIntoView({block:'nearest', behavior:'smooth'});
      }
    });

    showInfoBox(ds);
    // Position tooltip near cursor
    var x = clientX + 16;
    var y = clientY - 20;
    if (x + 200 > window.innerWidth) x = clientX - 215;
    tip.style.left = x+'px';
    tip.style.top  = y+'px';
    cnv.style.cursor = 'pointer';
  }

  cnv.addEventListener('mousemove', function(e){
    var pts = scatter.getElementsAtEventForMode(e,'nearest',{intersect:false},false);
    if (pts.length) handleChartHover(e);
    else {
      tip.style.display='none';
      cnv.style.cursor='default';
      if (!activeItem) {
        document.querySelectorAll('.km-sidebar-item').forEach(function(r){ r.classList.remove('km-si-active'); });
        scatter.setActiveElements([]);
        scatter.update('none');
      }
    }
  });
  cnv.addEventListener('mouseleave', function(){
    tip.style.display='none';
    cnv.style.cursor='default';
    if (!activeItem){
      document.querySelectorAll('.km-sidebar-item').forEach(function(r){ r.classList.remove('km-si-active'); });
      scatter.setActiveElements([]);
      scatter.update('none');
    }
  });

  // Touch
  cnv.addEventListener('touchstart',function(e){
    handleChartHover(e);
    if (scatter.getElementsAtEventForMode(e,'nearest',{intersect:false},false).length)
      e.preventDefault();
  },{passive:false});

  // ── Filter dropdown ──────────────────────────────────────
  document.getElementById('scatter-filter').addEventListener('change',function(){
    activeFilter = parseInt(this.value)||0;
    activeItem = null;
    tip.style.display='none';
    scatter.data.datasets = buildDatasets(activeFilter);
    scatter.update();
    var lbl = activeFilter ? 'Cluster '+activeFilter+' — '+labels[activeFilter] : 'Semua Siswa';
    sideTitle.textContent = lbl;
    buildSidebar(activeFilter, searchBox.value.trim().toLowerCase());
  });

  // ── Search box ────────────────────────────────────────────
  searchBox.addEventListener('input', function(){
    buildSidebar(activeFilter, this.value.trim().toLowerCase());
  });

})();

// ── 5. TABLE FILTER ───────────────────────────────────────
document.getElementById('filter-cluster').addEventListener('change',function(){
  var val = this.value;
  document.querySelectorAll('#tabel-hasil tbody tr').forEach(function(tr){
    tr.style.display = (val==='all' || tr.dataset.cluster===val) ? '' : 'none';
  });
});

})();
</script>
JS;
endif;

require_once '../includes/footer.php';
?>