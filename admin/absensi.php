<?php
// ============================================================
// FILE: admin/absensi.php
// Monitor & Koreksi Absensi Harian — khusus Admin
// Admin bisa: lihat, filter, koreksi status, hapus record
// ============================================================
require_once '../includes/config.php';
requireLogin('admin');

$today    = date('Y-m-d');
$settings = getSettings($pdo);

$kelasList   = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();
$kelasFilter = (int)($_GET['kelas'] ?? 0);
$tglFilter   = $_GET['tanggal'] ?? $today;

$msg = ''; $error = '';

// ---- KOREKSI STATUS (POST) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $aksi = $_POST['aksi'] ?? '';

    // [FIX] Blokir semua input absensi saat hari libur (koreksi & bulk_koreksi)
    if (in_array($aksi, ['koreksi', 'bulk_koreksi', 'auto_alfa'])) {
        $tglCek   = $_POST['tanggal_koreksi'] ?? $_POST['tanggal_alfa'] ?? date('Y-m-d');
        $liburCek = isHariLibur($pdo, $tglCek);
        if ($liburCek['libur']) {
            $error = 'Tidak dapat melakukan absensi pada hari libur: ' . $liburCek['keterangan'] . '.';
            $aksi  = ''; // batalkan aksi
        }
    }

    if ($aksi === 'koreksi') {
        $absensiId    = (int)($_POST['absensi_id'] ?? 0);
        $siswaId      = (int)($_POST['siswa_id'] ?? 0);
        $statusMasuk  = $_POST['status_masuk'] ?? '';
        $statusPulang = $_POST['status_pulang'] ?? '';
        $tglKoreksi   = $_POST['tanggal_koreksi'] ?? $tglFilter;

        // Ambil waktu — kalau diisi pakai yang diinput, kalau kosong pakai NOW() hanya saat INSERT
        $waktuMasukRaw  = trim($_POST['waktu_masuk'] ?? '');
        $waktuPulangRaw = trim($_POST['waktu_pulang'] ?? '');
        // Validasi format HH:MM
        $waktuMasuk  = preg_match('/^\d{2}:\d{2}$/', $waktuMasukRaw)  ? $waktuMasukRaw.':00' : null;
        $waktuPulang = preg_match('/^\d{2}:\d{2}$/', $waktuPulangRaw) ? $waktuPulangRaw.':00' : null;

        // Kalau status hadir, cek waktu vs batas_terlambat — sistem yang tentukan
        if ($statusMasuk === 'hadir') {
            $cekWaktu    = $waktuMasuk ?? date('H:i:s');
            $statusMasuk = ($cekWaktu > $settings['batas_terlambat']) ? 'terlambat' : 'hadir';
        }

        // [FIX] Admin edit → geolokasi otomatis pakai koordinat sekolah
        $latSekolah = (float)$settings['sekolah_lat'];
        $lngSekolah = (float)$settings['sekolah_lng'];
        $isPresent  = in_array($statusMasuk, ['hadir','terlambat']);

        $validStatus = ['hadir','terlambat','izin','sakit','alfa'];

        if (!in_array($statusMasuk, $validStatus)) {
            $error = 'Status masuk tidak valid.';
        } else {
            try {
                if ($absensiId) {
                    // UPDATE — waktu hanya diupdate kalau memang diisi
                    $setParts = ['status_masuk=?', 'status_pulang=?'];
                    $vals     = [$statusMasuk, $statusPulang ?: null];
                    if ($waktuMasuk  !== null) { $setParts[] = 'waktu_masuk=?';  $vals[] = $waktuMasuk; }
                    if ($waktuPulang !== null) { $setParts[] = 'waktu_pulang=?'; $vals[] = $waktuPulang; }
                    // [FIX] Geolokasi sekolah kalau admin set hadir/terlambat
                    if ($isPresent) {
                        $setParts[] = 'lat_masuk=?';  $vals[] = $latSekolah;
                        $setParts[] = 'lng_masuk=?';  $vals[] = $lngSekolah;
                    }
                    $vals[] = $absensiId;
                    $pdo->prepare("UPDATE absensi SET ".implode(',',$setParts)." WHERE id=?")
                        ->execute($vals);
                } else {
                    $cek = $pdo->prepare("SELECT id FROM absensi WHERE siswa_id=? AND tanggal=?");
                    $cek->execute([$siswaId, $tglKoreksi]);
                    if ($cek->fetch()) {
                        $error = 'Record sudah ada, refresh halaman.';
                    } else {
                        // INSERT — kalau waktu kosong default ke jam sekarang untuk hadir/terlambat
                        $needsTime = in_array($statusMasuk, ['hadir','terlambat']);
                        $wm = $waktuMasuk ?? ($needsTime ? date('H:i:s') : null);
                        $wp = $waktuPulang;
                        // [FIX] Geolokasi sekolah saat admin insert hadir/terlambat
                        $latM = $isPresent ? $latSekolah : null;
                        $lngM = $isPresent ? $lngSekolah : null;
                        $kelasSnap = ambilKelasSiswaSaatIni($pdo, $siswaId);
                        $pdo->prepare("INSERT INTO absensi (siswa_id, kelas_id, nama_kelas_snapshot, tanggal, status_masuk, status_pulang, waktu_masuk, waktu_pulang, lat_masuk, lng_masuk) VALUES (?,?,?,?,?,?,?,?,?,?)")
                            ->execute([$siswaId, $kelasSnap['kelas_id'], $kelasSnap['nama_kelas'], $tglKoreksi, $statusMasuk, $statusPulang ?: null, $wm, $wp, $latM, $lngM]);
                    }
                }
                if (!$error) {
                    auditLog($pdo, 'KOREKSI_ABSENSI', "id=$absensiId siswa=$siswaId status=$statusMasuk tgl=$tglKoreksi");
                    // [WA] Notifikasi real-time ke ortu setiap kali admin koreksi status masuk
                    // (waktu null -> otomatis diambil dari waktu_masuk yang barusan disimpan)
                    kirimNotifikasiHarian($pdo, $siswaId, $tglKoreksi, $statusMasuk, $waktuMasuk);
                    $msg = 'Status absensi berhasil dikoreksi.';
                }
            } catch (PDOException $e) { $error = 'Gagal: ' . $e->getMessage(); }
        }
    }

    if ($aksi === 'hapus') {
        $id = (int)($_POST['absensi_id'] ?? 0);
        if ($id) {
            $pdo->prepare("DELETE FROM absensi WHERE id=?")->execute([$id]);
            auditLog($pdo, 'HAPUS_ABSENSI', "id=$id");
            $msg = 'Record absensi dihapus.';
        }
    }

    if ($aksi === 'bulk_koreksi') {
        $siswaIds    = $_POST['siswa_ids'] ?? [];
        $kolom       = $_POST['bulk_kolom'] ?? 'masuk'; // 'masuk' atau 'pulang'
        $statusMasuk = $_POST['bulk_status_masuk'] ?? '';
        $statusPulang= $_POST['bulk_status_pulang'] ?? '';
        $tglKoreksi  = $_POST['tanggal_koreksi'] ?? $tglFilter;
        $validStatus = ['hadir','terlambat','izin','sakit','alfa'];
        $validPulang = ['pulang','belum_pulang',''];
        $waktuSekarang = date('H:i:s'); // jam saat admin klik Terapkan
        // Kalau bulk status masuk = hadir, tentukan hadir/terlambat dari jam sekarang
        if ($kolom === 'masuk' && $statusMasuk === 'hadir') {
            $statusMasuk = ($waktuSekarang > $settings['batas_terlambat']) ? 'terlambat' : 'hadir';
        }

        if (empty($siswaIds)) {
            $error = 'Tidak ada siswa yang dipilih.';
        } elseif ($kolom === 'masuk' && !in_array($statusMasuk, $validStatus)) {
            $error = 'Status masuk tidak valid.';
        } elseif ($kolom === 'pulang' && !in_array($statusPulang, $validPulang)) {
            $error = 'Status pulang tidak valid.';
        } else {
            $berhasil = 0;
            foreach ($siswaIds as $rawId) {
                $sid = (int)$rawId;
                if (!$sid) continue;
                try {
                    $cek = $pdo->prepare("SELECT id, status_masuk FROM absensi WHERE siswa_id=? AND tanggal=?");
                    $cek->execute([$sid, $tglKoreksi]);
                    $existing = $cek->fetch();
                    if ($kolom === 'masuk') {
                        if ($existing) {
                            // [FIX] Jangan timpa jika siswa sudah izin/sakit/alfa
                            $statusAda = $existing['status_masuk'] ?? '';
                            if (in_array($statusAda, ['izin','sakit','alfa'])) {
                                $berhasil++; // hitung tapi tidak ubah
                                continue;
                            }
                            // UPDATE — isi waktu_masuk kalau masih kosong
                            $pdo->prepare("UPDATE absensi SET status_masuk=?, waktu_masuk=COALESCE(waktu_masuk,?) WHERE id=?")
                                ->execute([$statusMasuk, $waktuSekarang, $existing['id']]);
                            // [WA] Notifikasi real-time ke ortu (jam null -> ambil dari DB, karena
                            // waktu_masuk bisa jadi punya nilai lama, bukan $waktuSekarang)
                            kirimNotifikasiHarian($pdo, $sid, $tglKoreksi, $statusMasuk, null);
                        } else {
                            // [FIX] Cek apakah ada izin/sakit disetujui sebelum insert
                            $cekIzin = $pdo->prepare("SELECT id, jenis FROM pengajuan_izin WHERE siswa_id=? AND tanggal=? AND status='disetujui' LIMIT 1");
                            $cekIzin->execute([$sid, $tglKoreksi]);
                            $izinAda = $cekIzin->fetch();
                            if ($izinAda) {
                                // Insert dengan status sesuai jenis izin
                                $stIzin = $izinAda['jenis'] ?? 'izin';
                                $kelasSnap = ambilKelasSiswaSaatIni($pdo, $sid);
                                $pdo->prepare("INSERT INTO absensi (siswa_id, kelas_id, nama_kelas_snapshot, tanggal, status_masuk, status_pulang, waktu_masuk) VALUES (?,?,?,?,?,?,NULL)")
                                    ->execute([$sid, $kelasSnap['kelas_id'], $kelasSnap['nama_kelas'], $tglKoreksi, $stIzin, $stIzin]);
                                // [WA] Notifikasi real-time ke ortu (tanpa jam -- izin/sakit
                                // tidak punya waktu_masuk, jadi jangan tampilkan jam ngawur)
                                kirimNotifikasiHarian($pdo, $sid, $tglKoreksi, $stIzin, '');
                                $berhasil++;
                                continue;
                            }
                            // INSERT — waktu masuk = jam sekarang untuk hadir/terlambat
                            $adaJamMasuk = in_array($statusMasuk, ['hadir','terlambat']);
                            $wm = $adaJamMasuk ? $waktuSekarang : null;
                            $kelasSnap = ambilKelasSiswaSaatIni($pdo, $sid);
                            $pdo->prepare("INSERT INTO absensi (siswa_id, kelas_id, nama_kelas_snapshot, tanggal, status_masuk, waktu_masuk) VALUES (?,?,?,?,?,?)")
                                ->execute([$sid, $kelasSnap['kelas_id'], $kelasSnap['nama_kelas'], $tglKoreksi, $statusMasuk, $wm]);
                            // [WA] Notifikasi real-time ke ortu (jam cuma ditampilkan untuk
                            // hadir/terlambat -- alfa/izin/sakit tidak punya jam masuk beneran)
                            kirimNotifikasiHarian($pdo, $sid, $tglKoreksi, $statusMasuk, $adaJamMasuk ? $waktuSekarang : '');
                        }
                    } else { // pulang — hanya update jika record sudah ada
                        if ($existing) {
                            // [FIX] Jika status masuk adalah alfa/sakit/izin, status pulang ikuti status masuk (tidak bisa diubah)
                            $statusMasukSiswa = $existing['status_masuk'];
                            if (in_array($statusMasukSiswa, ['alfa','sakit','izin'])) {
                                $finalStatusPulang = $statusMasukSiswa; // preserve: alfa=>alfa, sakit=>sakit, izin=>izin
                            } elseif ($statusPulang === '') {
                                $finalStatusPulang = null;
                            } else {
                                $finalStatusPulang = $statusPulang;
                            }
                            $pdo->prepare("UPDATE absensi SET status_pulang=?, waktu_pulang=COALESCE(waktu_pulang,?) WHERE id=?")
                                ->execute([$finalStatusPulang, $waktuSekarang, $existing['id']]);
                        }
                        // kalau belum ada record masuk, skip — pulang tidak bisa tanpa masuk
                    }
                    $berhasil++;
                } catch (PDOException $e) { /* skip */ }
            }
            $kolomLabel = $kolom === 'masuk' ? 'Status Masuk' : 'Status Pulang';
            $nilaiLabel = $kolom === 'masuk' ? strtoupper($statusMasuk) : strtoupper($statusPulang ?: 'kosong');
            auditLog($pdo, 'BULK_KOREKSI_ABSENSI', "kolom=$kolom status=$nilaiLabel tgl=$tglKoreksi jumlah=$berhasil");
            $msg = "Berhasil update $berhasil siswa — $kolomLabel: $nilaiLabel.";
        }
    }

    if ($aksi === 'auto_alfa') {
        $tglAlfa = $_POST['tanggal_alfa'] ?? $today;
        $result  = prosesAutoAlfa($pdo, $tglAlfa);
        $msg   = $result['message'];
        if ($result['status'] !== 'success') $error = $msg;
    }
}

// ---- DATA ----
$params     = [$tglFilter];
$kelasWhere = '';
if ($kelasFilter) { $kelasWhere = 'AND s.kelas_id = ?'; $params[] = $kelasFilter; }

$rows = $pdo->prepare("
    SELECT u.nama, k.nama_kelas, a.id as absensi_id,
           a.waktu_masuk, a.waktu_pulang, a.status_masuk, a.status_pulang,
           s.id as siswa_id, s.nis
    FROM siswa s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN absensi a ON s.id = a.siswa_id AND a.tanggal = ?
    WHERE u.status = 'aktif' $kelasWhere
    ORDER BY k.nama_kelas, u.nama
");
$rows->execute($params);
$absensi = $rows->fetchAll();

$stat = ['hadir'=>0,'terlambat'=>0,'izin'=>0,'sakit'=>0,'alfa'=>0,'belum'=>0];
foreach ($absensi as $a) {
    $s = $a['status_masuk'] ?? 'belum';
    if (isset($stat[$s])) $stat[$s]++; else $stat['belum']++;
}
$total      = count($absensi);
$pctHadir   = $total > 0 ? round(($stat['hadir'] + $stat['terlambat']) / $total * 100) : 0;
$sudahAbsen = $total - $stat['belum'];
$liburInfo  = isHariLibur($pdo, $tglFilter);

$pageTitle = 'Monitor & Koreksi Absensi';
require_once '../includes/header.php';
?>

<?php if ($msg && !$error): ?><div class="alert alert-success"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/></svg><?= $msg ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg><?= $error ?></div><?php endif; ?>

<?php if ($liburInfo['libur']): ?>
<div class="alert alert-warning">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
  <strong>Hari Libur:</strong>&nbsp;<?= htmlspecialchars($liburInfo['keterangan']) ?> — Auto-alfa tidak berjalan.
</div>
<?php endif; ?>

<!-- Filter + Aksi Admin -->
<div class="card" style="margin-bottom:16px">
  <div class="card-body" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;justify-content:space-between">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label">Tanggal</label>
        <input type="date" name="tanggal" class="form-control" value="<?= $tglFilter ?>" max="<?= $today ?>">
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label">Kelas</label>
        <select name="kelas" class="form-control">
          <option value="">Semua Kelas</option>
          <?php foreach ($kelasList as $k): ?>
          <option value="<?= $k['id'] ?>" <?= $kelasFilter==$k['id']?'selected':'' ?>><?= $k['nama_kelas'] ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary">Filter</button>
    </form>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <button type="button" onclick="jalanAutoAlfa()" class="btn btn-warning btn-sm">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
        Jalankan Auto-Alfa
      </button>
    </div>
  </div>
</div>

<!-- Progress bar kehadiran -->
<div class="card" style="margin-bottom:16px">
  <div class="card-body">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
      <div style="font-weight:700;font-size:14px">Tingkat Kehadiran — <?= formatTanggal($tglFilter) ?></div>
      <div style="font-size:24px;font-weight:800;color:<?= $pctHadir>=80?'#1a7a45':($pctHadir>=60?'#d97706':'#b91c1c') ?>"><?= $pctHadir ?>%</div>
    </div>
    <div style="background:var(--border-light);border-radius:99px;height:12px;overflow:hidden;margin-bottom:10px">
      <div style="height:100%;width:<?= $pctHadir ?>%;background:<?= $pctHadir>=80?'linear-gradient(90deg,#1a7a45,#22c55e)':($pctHadir>=60?'linear-gradient(90deg,#d97706,#fbbf24)':'linear-gradient(90deg,#b91c1c,#ef4444)') ?>;border-radius:99px;transition:width .5s"></div>
    </div>
    <div style="display:flex;gap:16px;font-size:12px;flex-wrap:wrap">
      <span style="color:var(--text-muted)"><?= $sudahAbsen ?>/<?= $total ?> absen</span>
      <span style="color:#1a7a45">✓ Hadir <?= $stat['hadir'] ?></span>
      <span style="color:#d97706">⧗ Terlambat <?= $stat['terlambat'] ?></span>
      <span style="color:#1555a0">✎ Izin <?= $stat['izin'] ?></span>
      <span style="color:#6b21a8">+ Sakit <?= $stat['sakit'] ?></span>
      <span style="color:#b91c1c">✗ Alfa <?= $stat['alfa'] ?></span>
      <span style="color:#94a3b8">? Belum <?= $stat['belum'] ?></span>
    </div>
  </div>
</div>

<!-- Tabel absensi dengan tombol edit -->
<div class="card">
  <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
    <div class="card-title">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
      Daftar Absensi
    </div>
    <!-- Bulk Action Toolbar (muncul saat ada checkbox dicentang) -->
    <div id="bulk-toolbar" style="display:none;align-items:center;gap:8px;flex-wrap:wrap">
      <span id="bulk-count" style="font-size:13px;font-weight:600;color:var(--text-muted)"></span>
      <select id="bulk-kolom" class="form-control" style="padding:4px 10px;height:auto;font-size:13px;width:auto" onchange="updateOpsiStatus()">
        <option value="masuk">Status Masuk</option>
        <option value="pulang">Status Pulang</option>
      </select>
      <select id="bulk-status" class="form-control" style="padding:4px 10px;height:auto;font-size:13px;width:auto">
        <option value="">— Pilih status —</option>
        <option value="hadir">Hadir</option>
        <option value="terlambat">Terlambat</option>
        <option value="izin">Izin</option>
        <option value="sakit">Sakit</option>
        <option value="alfa">Alfa</option>
      </select>
      <button type="button" onclick="terapkanBulk()" class="btn btn-primary btn-sm" style="font-size:13px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:12px;height:12px"><path d="M9 11l3 3L22 4"/></svg>
        Terapkan
      </button>
      <button type="button" onclick="batalPilihan()" class="btn btn-outline btn-sm" style="font-size:13px">Batal</button>
    </div>
    <span id="hint-edit" style="font-size:12px;color:var(--text-muted)">Centang siswa atau klik "Edit" untuk koreksi</span>
  </div>
  <!-- Search Bar -->
  <div style="padding:10px 16px;border-bottom:1px solid var(--border-light);background:var(--bg-subtle)">
    <div style="position:relative;max-width:320px">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
           style="position:absolute;left:10px;top:50%;transform:translateY(-50%);width:14px;height:14px;color:var(--text-muted);pointer-events:none">
        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
      </svg>
      <input type="text" id="search-siswa" placeholder="Cari nama atau NIS..."
        oninput="filterTabel(this.value)"
        autocomplete="off"
        style="width:100%;padding:7px 10px 7px 32px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:var(--bg);color:var(--text);outline:none"
        onfocus="this._focused=true" onblur="this._focused=false">
    </div>
  </div>
  <div class="table-responsive">
    <table>
      <thead>
        <tr>
          <th style="width:36px;text-align:center">
            <input type="checkbox" id="cb-semua" title="Pilih semua"
              style="width:16px;height:16px;cursor:pointer;accent-color:var(--primary)"
              onchange="toggleSemuaCb(this.checked)">
          </th>
          <th style="width:32px">#</th><th>NIS</th><th>Nama Siswa</th><th>Kelas</th>
          <th>Waktu Masuk</th><th>Waktu Pulang</th>
          <th>Status Masuk</th><th>Status Pulang</th><th>Koreksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($absensi)): ?>
        <tr><td colspan="9" class="text-center text-muted" style="padding:32px">Tidak ada data</td></tr>
        <?php else: ?>
        <?php foreach ($absensi as $i => $a):
          $bg = '';
          if (!isset($a['status_masuk']))           $bg = 'background:#f8fafc';
          elseif ($a['status_masuk']==='alfa')       $bg = 'background:#fff5f5';
          elseif ($a['status_masuk']==='terlambat')  $bg = 'background:#fffbeb';
        ?>
        <tr style="<?= $bg ?>" data-siswa="<?= $a['siswa_id'] ?>">
          <td style="text-align:center">
            <input type="checkbox" class="cb-siswa" value="<?= $a['siswa_id'] ?>"
              style="width:16px;height:16px;cursor:pointer;accent-color:var(--primary)"
              onchange="updateBulkToolbar()">
          </td>
          <td style="color:var(--text-muted);font-size:12px"><?= $i+1 ?></td>
          <td style="font-family:var(--font-mono);font-size:12px"><?= htmlspecialchars($a['nis']) ?></td>
          <td><strong><?= htmlspecialchars($a['nama']) ?></strong></td>
          <td style="font-size:13px"><?= htmlspecialchars($a['nama_kelas'] ?? '—') ?></td>
          <td style="font-family:var(--font-mono);font-size:13px"><?= $a['waktu_masuk'] ?? '<span style="color:#ccc">—</span>' ?></td>
          <td style="font-family:var(--font-mono);font-size:13px"><?= $a['waktu_pulang'] ?? '<span style="color:#ccc">—</span>' ?></td>
          <td>
            <?php if (!isset($a['status_masuk'])): ?>
              <span class="badge" style="background:#f1f5f9;color:#64748b">Belum Absen</span>
            <?php else: echo getStatusBadge($a['status_masuk']); endif; ?>
          </td>
          <td>
            <?php if (!empty($a['status_pulang'])): echo getStatusBadge($a['status_pulang']);
            else: ?><span style="color:#ccc;font-size:12px">—</span><?php endif; ?>
          </td>
          <td>
            <button type="button"
              onclick="bukaModal(<?= (int)($a['absensi_id']??0) ?>,<?= $a['siswa_id'] ?>,'<?= htmlspecialchars(addslashes($a['nama'])) ?>','<?= $a['status_masuk']??'' ?>','<?= $a['status_pulang']??'' ?>','<?= $tglFilter ?>','<?= $a['waktu_masuk']??'' ?>','<?= $a['waktu_pulang']??'' ?>')"
              class="btn btn-outline btn-sm" style="padding:3px 10px;font-size:12px">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:11px;height:11px"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              Edit
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal koreksi -->
<div id="modal-bg" style="display:none;position:fixed;inset:0;z-index:999;background:rgba(0,0,0,.45);align-items:center;justify-content:center">
  <div style="background:var(--bg);border-radius:16px;padding:28px;width:420px;max-width:94vw;box-shadow:0 24px 60px rgba(0,0,0,.22)">
    <div style="font-weight:800;font-size:16px;margin-bottom:2px">Koreksi Absensi</div>
    <div id="m-nama" style="font-size:13px;color:var(--text-muted);margin-bottom:18px"></div>
    <form method="POST" id="form-koreksi">
      <?= csrfField() ?>
      <input type="hidden" name="aksi" value="koreksi">
      <input type="hidden" name="absensi_id" id="m-aid">
      <input type="hidden" name="siswa_id" id="m-sid">
      <input type="hidden" name="tanggal_koreksi" id="m-tgl">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label class="form-label">Status Masuk</label>
          <select name="status_masuk" id="m-masuk" class="form-control">
            <option value="hadir">Hadir</option>
            <option value="izin">Izin</option>
            <option value="sakit">Sakit</option>
            <option value="alfa">Alfa</option>
          </select>
          <div style="font-size:11px;color:var(--text-muted);margin-top:3px">Hadir/terlambat ditentukan otomatis dari waktu</div>
        </div>
        <div class="form-group">
          <label class="form-label">Waktu Masuk</label>
          <input type="time" name="waktu_masuk" id="m-waktu-masuk" class="form-control"
            placeholder="HH:MM" style="font-family:var(--font-mono)">
          <div style="font-size:11px;color:var(--text-muted);margin-top:3px">Kosong = otomatis</div>
        </div>
        <div class="form-group">
          <label class="form-label">Status Pulang</label>
          <select name="status_pulang" id="m-pulang" class="form-control">
            <option value="">Tidak diisi</option>
            <option value="pulang">Pulang Normal</option>
            <option value="belum_pulang">Belum Pulang</option>
            <option value="alfa">Alfa Pulang</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Waktu Pulang</label>
          <input type="time" name="waktu_pulang" id="m-waktu-pulang" class="form-control"
            placeholder="HH:MM" style="font-family:var(--font-mono)">
          <div style="font-size:11px;color:var(--text-muted);margin-top:3px">Kosong = tidak diubah</div>
        </div>
      </div>
      <div style="display:flex;gap:8px;margin-top:18px">
        <button type="submit" class="btn btn-primary" style="flex:1">Simpan</button>
        <button type="button" id="btn-hapus" class="btn btn-sm" style="background:#ef4444;color:#fff;border:none;padding:0 16px;border-radius:8px">Hapus</button>
        <button type="button" onclick="tutupModal()" class="btn btn-outline">Batal</button>
      </div>
    </form>
  </div>
</div>

<form method="POST" id="form-bulk" style="display:none">
  <?= csrfField() ?>
  <input type="hidden" name="aksi" value="bulk_koreksi">
  <input type="hidden" name="bulk_kolom" id="bulk-kolom-hidden">
  <input type="hidden" name="bulk_status_masuk" id="bulk-status-masuk-hidden">
  <input type="hidden" name="bulk_status_pulang" id="bulk-status-pulang-hidden">
  <input type="hidden" name="tanggal_koreksi" value="<?= $tglFilter ?>">
  <div id="bulk-siswa-inputs"></div>
</form>

<form method="POST" id="form-hapus" style="display:none">
  <?= csrfField() ?><input type="hidden" name="aksi" value="hapus"><input type="hidden" name="absensi_id" id="hapus-id">
</form>
<form method="POST" id="form-alfa" style="display:none">
  <?= csrfField() ?><input type="hidden" name="aksi" value="auto_alfa"><input type="hidden" name="tanggal_alfa" value="<?= $tglFilter ?>">
</form>

<script>
// ---- Bulk Checkbox ----
function toggleSemuaCb(checked) {
  document.querySelectorAll('.cb-siswa').forEach(cb => cb.checked = checked);
  updateBulkToolbar();
}

function updateBulkToolbar() {
  const dipilih = [...document.querySelectorAll('.cb-siswa:checked')];
  const toolbar  = document.getElementById('bulk-toolbar');
  const hint     = document.getElementById('hint-edit');
  const cbSemua  = document.getElementById('cb-semua');
  const semua    = document.querySelectorAll('.cb-siswa');

  document.getElementById('bulk-count').textContent = dipilih.length + ' siswa dipilih';
  toolbar.style.display = dipilih.length ? 'flex' : 'none';
  hint.style.display    = dipilih.length ? 'none' : '';

  // state indeterminate untuk "pilih semua"
  cbSemua.indeterminate = dipilih.length > 0 && dipilih.length < semua.length;
  cbSemua.checked = dipilih.length === semua.length && semua.length > 0;
}

function batalPilihan() {
  document.querySelectorAll('.cb-siswa').forEach(cb => cb.checked = false);
  document.getElementById('cb-semua').checked = false;
  document.getElementById('cb-semua').indeterminate = false;
  updateBulkToolbar();
}

const opsiMasuk  = '<option value="">— Pilih status —</option><option value="hadir">Hadir</option><option value="izin">Izin</option><option value="sakit">Sakit</option><option value="alfa">Alfa</option>';
const opsiPulang = '<option value="">— Pilih status —</option><option value="pulang">Pulang</option>';

function updateOpsiStatus() {
  const kolom = document.getElementById('bulk-kolom').value;
  document.getElementById('bulk-status').innerHTML = kolom === 'masuk' ? opsiMasuk : opsiPulang;
}

function terapkanBulk() {
  const kolom  = document.getElementById('bulk-kolom').value;
  const status = document.getElementById('bulk-status').value;
  if (!status) { showToast('Pilih status dulu!', 'warning'); return; }

  const dipilih = [...document.querySelectorAll('.cb-siswa:checked')].map(cb => cb.value);
  if (!dipilih.length) { showToast('Pilih minimal satu siswa.', 'warning'); return; }

  const kolomLabel = kolom === 'masuk' ? 'Status Masuk' : 'Status Pulang';
  const statusLabel = document.getElementById('bulk-status').options[document.getElementById('bulk-status').selectedIndex].text;
  appConfirm({
    title: 'Konfirmasi Update Massal',
    body: `Update ${dipilih.length} siswa?\n${kolomLabel}: ${statusLabel}`,
    btnLabel: 'Update',
    btnClass: 'btn-primary',
    onConfirm: submitBulk
  });
}

function submitBulk() {
  const kolom  = document.getElementById('bulk-kolom').value;
  const status = document.getElementById('bulk-status').value;
  const dipilih = [...document.querySelectorAll('.cb-siswa:checked')].map(cb => cb.value);

  document.getElementById('bulk-kolom-hidden').value = kolom;
  if (kolom === 'masuk') {
    document.getElementById('bulk-status-masuk-hidden').value = status;
    document.getElementById('bulk-status-pulang-hidden').value = '';
  } else {
    document.getElementById('bulk-status-masuk-hidden').value = '';
    document.getElementById('bulk-status-pulang-hidden').value = status;
  }
  const container = document.getElementById('bulk-siswa-inputs');
  container.innerHTML = '';
  dipilih.forEach(id => {
    const inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'siswa_ids[]'; inp.value = id;
    container.appendChild(inp);
  });
  document.getElementById('form-bulk').submit();
}

// ---- Modal & lainnya ----
function bukaModal(aid, sid, nama, masuk, pulang, tgl, waktuMasuk, waktuPulang) {
  document.getElementById('m-aid').value = aid;
  document.getElementById('m-sid').value = sid;
  document.getElementById('m-tgl').value = tgl;
  document.getElementById('m-nama').textContent = nama + ' · ' + tgl;
  document.getElementById('m-masuk').value = masuk || 'alfa';
  document.getElementById('m-pulang').value = pulang || '';
  // Waktu — strip detik supaya cocok dengan input[type=time] (HH:MM)
  document.getElementById('m-waktu-masuk').value  = waktuMasuk  ? waktuMasuk.substring(0,5)  : new Date().toTimeString().substring(0,5);
  document.getElementById('m-waktu-pulang').value = waktuPulang ? waktuPulang.substring(0,5) : '';
  const btnH = document.getElementById('btn-hapus');
  btnH.style.display = aid ? '' : 'none';
  btnH.onclick = () => {
    appConfirm({
      title: 'Hapus Record Absensi',
      body: 'Hapus record absensi ' + nama + '?\n\nTindakan ini tidak bisa dibatalkan.',
      btnLabel: 'Ya, Hapus',
      btnClass: 'btn-danger',
      onConfirm: () => {
        document.getElementById('hapus-id').value = aid;
        document.getElementById('form-hapus').submit();
      }
    });
  };
  document.getElementById('modal-bg').style.display = 'flex';
}
function tutupModal() { document.getElementById('modal-bg').style.display = 'none'; }
function jalanAutoAlfa() {
  appConfirm({
    title: 'Jalankan Auto-Alfa',
    body: 'Jalankan Auto-Alfa untuk <?= $tglFilter ?>?\nSiswa yang belum absen akan ditandai ALFA.',
    btnLabel: 'Jalankan',
    btnClass: 'btn-warning',
    onConfirm: () => document.getElementById('form-alfa').submit()
  });
}
document.getElementById('modal-bg').addEventListener('click', e => { if (e.target===e.currentTarget) tutupModal(); });

// ---- Search / Filter tabel ----
function filterTabel(q) {
  q = q.toLowerCase().trim();
  let visible = 0;
  document.querySelectorAll('tbody tr[data-siswa]').forEach(tr => {
    const teks = tr.textContent.toLowerCase();
    const show = !q || teks.includes(q);
    tr.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  // Update nomor urut yang terlihat
  let no = 1;
  document.querySelectorAll('tbody tr[data-siswa]').forEach(tr => {
    if (tr.style.display !== 'none') {
      const nomorCell = tr.querySelector('td:nth-child(2)');
      if (nomorCell) nomorCell.textContent = no++;
    }
  });
  // Tampilkan pesan kosong kalau tidak ada hasil
  let emptyRow = document.getElementById('search-empty-row');
  if (!emptyRow) {
    emptyRow = document.createElement('tr');
    emptyRow.id = 'search-empty-row';
    emptyRow.innerHTML = '<td colspan="10" style="text-align:center;padding:24px;color:var(--text-muted)">Tidak ada siswa yang cocok dengan pencarian</td>';
    document.querySelector('tbody').appendChild(emptyRow);
  }
  emptyRow.style.display = (q && visible === 0) ? '' : 'none';
}
</script>

<?php require_once '../includes/footer.php'; ?>