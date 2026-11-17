<?php
// ============================================================
// FILE: admin/hari_libur.php
// Manajemen Hari Libur Nasional (Tanggal Merah) & Libur Manual
// ============================================================
require_once '../includes/config.php';
requireLogin('admin');

$msg = ''; $error = '';
$bulanKini  = (int)($_GET['bulan'] ?? date('n'));
$tahunKini  = (int)($_GET['tahun'] ?? date('Y'));

// ---- HANDLE POST ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $aksi = $_POST['aksi'] ?? '';

    // TAMBAH libur
    if ($aksi === 'tambah') {
        $tanggal   = $_POST['tanggal'] ?? '';
        $keterangan = sanitize($_POST['keterangan'] ?? '');
        $jenis      = in_array($_POST['jenis'], ['nasional','sekolah']) ? $_POST['jenis'] : 'sekolah';

        if (!$tanggal || !$keterangan) {
            $error = 'Tanggal dan keterangan wajib diisi.';
        } else {
            try {
                $cek = $pdo->prepare("SELECT id FROM hari_libur WHERE tanggal = ?");
                $cek->execute([$tanggal]);
                if ($cek->fetch()) {
                    $error = 'Tanggal ' . date('d/m/Y', strtotime($tanggal)) . ' sudah ada di daftar libur.';
                } else {
                    $pdo->prepare("INSERT INTO hari_libur (tanggal, keterangan, jenis) VALUES (?,?,?)")
                        ->execute([$tanggal, $keterangan, $jenis]);
                    auditLog($pdo, 'TAMBAH_HARI_LIBUR', "tanggal=$tanggal keterangan=$keterangan");
                    $msg = 'Hari libur berhasil ditambahkan.';
                    // redirect ke bulan yang sesuai
                    $bulanKini = (int)date('n', strtotime($tanggal));
                    $tahunKini = (int)date('Y', strtotime($tanggal));
                }
            } catch (PDOException $e) {
                $error = 'Gagal menyimpan: ' . $e->getMessage();
            }
        }
    }

    // EDIT libur
    if ($aksi === 'edit') {
        $id         = (int)($_POST['id'] ?? 0);
        $tanggal    = $_POST['tanggal'] ?? '';
        $keterangan = sanitize($_POST['keterangan'] ?? '');
        $jenis      = in_array($_POST['jenis'], ['nasional','sekolah']) ? $_POST['jenis'] : 'sekolah';

        if (!$id || !$tanggal || !$keterangan) {
            $error = 'Data tidak lengkap.';
        } else {
            try {
                $cek = $pdo->prepare("SELECT id FROM hari_libur WHERE tanggal = ? AND id != ?");
                $cek->execute([$tanggal, $id]);
                if ($cek->fetch()) {
                    $error = 'Tanggal ' . date('d/m/Y', strtotime($tanggal)) . ' sudah ada di daftar libur.';
                } else {
                    $pdo->prepare("UPDATE hari_libur SET tanggal=?, keterangan=?, jenis=? WHERE id=?")
                        ->execute([$tanggal, $keterangan, $jenis, $id]);
                    auditLog($pdo, 'EDIT_HARI_LIBUR', "id=$id tanggal=$tanggal");
                    $msg = 'Hari libur berhasil diperbarui.';
                    $bulanKini = (int)date('n', strtotime($tanggal));
                    $tahunKini = (int)date('Y', strtotime($tanggal));
                }
            } catch (PDOException $e) {
                $error = 'Gagal mengubah: ' . $e->getMessage();
            }
        }
    }

    // HAPUS libur
    if ($aksi === 'hapus') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $row = $pdo->prepare("SELECT tanggal FROM hari_libur WHERE id=?");
            $row->execute([$id]);
            $rowData = $row->fetch();
            $pdo->prepare("DELETE FROM hari_libur WHERE id=?")->execute([$id]);
            auditLog($pdo, 'HAPUS_HARI_LIBUR', "id=$id tanggal=" . ($rowData['tanggal'] ?? ''));
            $msg = 'Hari libur berhasil dihapus.';
        }
    }
}

// ---- AMBIL DATA ----
// Semua hari libur bulan ini untuk kalender
$stmtKal = $pdo->prepare("SELECT * FROM hari_libur WHERE YEAR(tanggal)=? AND MONTH(tanggal)=? ORDER BY tanggal");
$stmtKal->execute([$tahunKini, $bulanKini]);
$liburBulanIni = [];
foreach ($stmtKal->fetchAll() as $r) {
    $liburBulanIni[$r['tanggal']] = $r;
}

// Semua libur untuk tabel daftar (tahun berjalan)
$stmtList = $pdo->prepare("SELECT * FROM hari_libur WHERE YEAR(tanggal)=? ORDER BY tanggal ASC");
$stmtList->execute([$tahunKini]);
$semuaLibur = $stmtList->fetchAll();

// Navigasi bulan
$namaBulanArr = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
$prevBulan = $bulanKini - 1; $prevTahun = $tahunKini;
if ($prevBulan < 1) { $prevBulan = 12; $prevTahun--; }
$nextBulan = $bulanKini + 1; $nextTahun = $tahunKini;
if ($nextBulan > 12) { $nextBulan = 1; $nextTahun++; }

$pageTitle = 'Manajemen Hari Libur';
require_once '../includes/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
  <?= $msg ?>
</div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
  <?= $error ?>
</div><?php endif; ?>

<!-- INFO BANNER -->
<div class="alert alert-info" style="margin-bottom:20px">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
  <div>
    <strong>Cara kerja hari libur:</strong> Tanggal yang terdaftar di sini <strong>tidak akan diproses auto-alfa</strong>. 
    Gunakan jenis <em>Nasional</em> untuk tanggal merah resmi, dan <em>Libur Sekolah</em> untuk libur tambahan seperti rapat, peringatan sekolah, dll.
    Selain itu dan Sabtu juga otomatis dikecualikan dari auto-alfa sesuai pengaturan sistem.
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px" class="libur-grid">

  <!-- ====== KALENDER ====== -->
  <div class="card">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
      <div class="card-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Kalender <?= $namaBulanArr[$bulanKini] ?> <?= $tahunKini ?>
      </div>
      <div style="display:flex;gap:6px;align-items:center">
        <a href="?bulan=<?= $prevBulan ?>&tahun=<?= $prevTahun ?>" class="btn btn-outline btn-sm">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><polyline points="15 18 9 12 15 6"/></svg>
        </a>
        <select onchange="location.href='?bulan='+this.value.split('-')[0]+'&tahun='+this.value.split('-')[1]" class="form-control" style="padding:4px 8px;height:32px;font-size:13px;width:auto">
          <?php for ($m=1; $m<=12; $m++): ?>
            <option value="<?= $m ?>-<?= $tahunKini ?>" <?= $m==$bulanKini?'selected':'' ?>><?= $namaBulanArr[$m] ?> <?= $tahunKini ?></option>
          <?php endfor; ?>
          <?php for ($m=1; $m<=12; $m++): ?>
            <option value="<?= $m ?>-<?= $tahunKini+1 ?>" <?= ($m==$bulanKini&&$tahunKini+1==$tahunKini)?'selected':'' ?>><?= $namaBulanArr[$m] ?> <?= $tahunKini+1 ?></option>
          <?php endfor; ?>
        </select>
        <a href="?bulan=<?= $nextBulan ?>&tahun=<?= $nextTahun ?>" class="btn btn-outline btn-sm">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><polyline points="9 18 15 12 9 6"/></svg>
        </a>
      </div>
    </div>
    <div class="card-body" style="padding:12px">
      <!-- Legend -->
      <div style="display:flex;gap:12px;font-size:12px;margin-bottom:12px;flex-wrap:wrap">
        <span style="display:flex;align-items:center;gap:4px"><span style="width:12px;height:12px;background:#ef4444;border-radius:3px;display:inline-block"></span> Libur Nasional</span>
        <span style="display:flex;align-items:center;gap:4px"><span style="width:12px;height:12px;background:#8b5cf6;border-radius:3px;display:inline-block"></span> Libur Sekolah</span>
        <span style="display:flex;align-items:center;gap:4px"><span style="width:12px;height:12px;background:#94a3b8;border-radius:3px;display:inline-block"></span> Sabtu</span>*/
        <span style="display:flex;align-items:center;gap:4px"><span style="width:12px;height:12px;background:#2563eb;border-radius:3px;display:inline-block"></span> Hari Ini</span>
      </div>

      <!-- Calendar grid -->
      <div class="kalender-grid">
        <?php
        $hariHeader = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
        foreach ($hariHeader as $h): ?>
          <div class="kal-header"><?= $h ?></div>
        <?php endforeach;

        $firstDay  = mktime(0,0,0,$bulanKini,1,$tahunKini);
        $startDow  = (int)date('w', $firstDay); // 0=Sun
        $totalDays = (int)date('t', $firstDay);
        $today     = date('Y-m-d');

        // blank cells before 1st
        for ($b = 0; $b < $startDow; $b++): ?>
          <div class="kal-cell kal-blank"></div>
        <?php endfor;

        for ($d = 1; $d <= $totalDays; $d++):
          $ts       = mktime(0,0,0,$bulanKini,$d,$tahunKini);
          $dateStr  = date('Y-m-d', $ts);
          $dow      = (int)date('w', $ts); // 0=Sun,5=Fri,6=Sat
          $isWeekend = ($dow === 6); // Sabtu=6
          $isToday  = ($dateStr === $today);
          $liburRow = $liburBulanIni[$dateStr] ?? null;

          $cls = 'kal-cell';
          $tooltip = '';
          $jenisLibur = '';
          if ($isToday) { $cls .= ' kal-today'; }
          if ($liburRow) {
            $cls .= ($liburRow['jenis'] === 'nasional') ? ' kal-nasional' : ' kal-sekolah';
            $tooltip = htmlspecialchars($liburRow['keterangan']);
            $jenisLibur = $liburRow['jenis'];
          } elseif ($isWeekend) {
            $cls .= ' kal-weekend';
          }
        ?>
          <div class="<?= $cls ?>" 
               title="<?= $tooltip ?>"
               onclick="pilihTanggal('<?= $dateStr ?>','<?= $tooltip ?>','<?= $jenisLibur ?>',<?= $liburRow ? $liburRow['id'] : 0 ?>)"
               style="cursor:pointer">
            <?= $d ?>
            <?php if ($liburRow): ?>
              <div class="kal-dot"></div>
            <?php endif; ?>
          </div>
        <?php endfor; ?>
      </div>

      <!-- Keterangan libur bulan ini -->
      <?php if (!empty($liburBulanIni)): ?>
      <div style="margin-top:14px;border-top:1px solid var(--border-light);padding-top:12px">
        <div style="font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:8px">Libur bulan ini:</div>
        <?php foreach ($liburBulanIni as $tgl => $lb): ?>
          <div style="display:flex;align-items:center;gap:8px;font-size:12px;padding:4px 0;border-bottom:1px solid var(--border-light)">
            <span style="min-width:80px;color:var(--text-muted)"><?= date('d M', strtotime($tgl)) ?></span>
            <span style="flex:1"><?= htmlspecialchars($lb['keterangan']) ?></span>
            <span class="badge <?= $lb['jenis']==='nasional' ? 'badge-alfa' : '' ?>" style="<?= $lb['jenis']==='sekolah' ? 'background:#8b5cf6;color:#fff' : '' ?>">
              <?= $lb['jenis']==='nasional' ? 'Nasional' : 'Sekolah' ?>
            </span>
            <button type="button" onclick="editLibur(<?= $lb['id'] ?>,'<?= $tgl ?>','<?= htmlspecialchars(addslashes($lb['keterangan'])) ?>','<?= $lb['jenis'] ?>')" class="btn btn-outline btn-sm" style="padding:2px 6px;font-size:11px">Edit</button>
            <button type="button" onclick="hapusLibur(<?= $lb['id'] ?>,'<?= htmlspecialchars(addslashes($lb['keterangan'])) ?>')" class="btn btn-sm" style="padding:2px 6px;font-size:11px;background:#ef4444;color:#fff;border:none">Hapus</button>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ====== FORM TAMBAH/EDIT ====== -->
  <div>
    <!-- Form -->
    <div class="card" id="card-form">
      <div class="card-header">
        <div class="card-title" id="form-title">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><path d="M12 5v14M5 12h14"/></svg>
          Tambah Hari Libur
        </div>
      </div>
      <div class="card-body">
        <form method="POST" id="form-libur">
          <?= csrfField() ?>
          <input type="hidden" name="aksi" id="input-aksi" value="tambah">
          <input type="hidden" name="id" id="input-id" value="">

          <div class="form-group">
            <label class="form-label">Tanggal <span style="color:#ef4444">*</span></label>
            <input type="date" name="tanggal" id="input-tanggal" class="form-control" required>
            <div class="form-hint">Klik pada kalender untuk mengisi tanggal otomatis</div>
          </div>
          <div class="form-group">
            <label class="form-label">Keterangan <span style="color:#ef4444">*</span></label>
            <input type="text" name="keterangan" id="input-keterangan" class="form-control" placeholder="Contoh: Hari Raya Idul Fitri, Rapat Guru, HUT Sekolah..." required>
          </div>
          <div class="form-group">
            <label class="form-label">Jenis Libur</label>
            <div style="display:flex;gap:12px;margin-top:6px">
              <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:14px">
                <input type="radio" name="jenis" id="jenis-nasional" value="nasional" style="accent-color:#ef4444">
                <span style="color:#ef4444;font-weight:600">Nasional</span>
                <span style="color:var(--text-muted);font-size:12px">(Tanggal Merah)</span>
              </label>
              <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:14px">
                <input type="radio" name="jenis" id="jenis-sekolah" value="sekolah" checked style="accent-color:#8b5cf6">
                <span style="color:#8b5cf6;font-weight:600">Sekolah</span>
                <span style="color:var(--text-muted);font-size:12px">(Rapat, Acara, dll)</span>
              </label>
            </div>
          </div>
          <div style="display:flex;gap:8px">
            <button type="submit" class="btn btn-primary" id="btn-simpan">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
              Simpan
            </button>
            <button type="button" onclick="resetForm()" class="btn btn-outline" id="btn-batal" style="display:none">Batal Edit</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Libur Massal / Import Cepat -->
    <div class="card" style="margin-top:16px">
      <div class="card-header">
        <div class="card-title">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><path d="M17 3a2.828 2.828 0 114 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>
          Libur Nasional Indonesia <?= $tahunKini ?>
        </div>
      </div>
      <div class="card-body">
        <p style="font-size:13px;color:var(--text-muted);margin-bottom:12px">
          Klik tombol di bawah untuk menambahkan hari libur nasional Indonesia <?= $tahunKini ?> secara otomatis (hanya yang belum ada).
        </p>
        <div id="status-import" style="font-size:13px;margin-bottom:10px;display:none"></div>
        <button type="button" onclick="importLiburNasional(<?= $tahunKini ?>)" class="btn btn-primary btn-sm">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><polyline points="8 17 12 21 16 17"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.88 18.09A5 5 0 0018 9h-1.26A8 8 0 103 16.29"/></svg>
          Import Libur Nasional <?= $tahunKini ?>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- ====== TABEL DAFTAR SEMUA LIBUR TAHUN INI ====== -->
<div class="card" style="margin-top:20px">
  <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
    <div class="card-title">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/></svg>
      Semua Hari Libur Tahun <?= $tahunKini ?>
    </div>
    <div style="display:flex;gap:6px">
      <a href="?tahun=<?= $tahunKini-1 ?>&bulan=<?= $bulanKini ?>" class="btn btn-outline btn-sm"><?= $tahunKini-1 ?></a>
      <span class="btn btn-primary btn-sm" style="pointer-events:none"><?= $tahunKini ?></span>
      <a href="?tahun=<?= $tahunKini+1 ?>&bulan=<?= $bulanKini ?>" class="btn btn-outline btn-sm"><?= $tahunKini+1 ?></a>
    </div>
  </div>
  <div class="card-body" style="padding:0">
    <?php if (empty($semuaLibur)): ?>
      <div style="text-align:center;padding:40px;color:var(--text-muted)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:40px;height:40px;opacity:.3;margin-bottom:8px"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        <div>Belum ada hari libur terdaftar untuk tahun <?= $tahunKini ?>.</div>
        <div style="font-size:13px;margin-top:4px">Tambah lewat form di atas atau import libur nasional.</div>
      </div>
    <?php else: ?>
      <div class="table-responsive">
      <table class="table" style="margin:0">
        <thead>
          <tr>
            <th>No</th>
            <th>Tanggal</th>
            <th>Hari</th>
            <th>Keterangan</th>
            <th>Jenis</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php $no=1; foreach ($semuaLibur as $lb): 
            $hariNama = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][date('w', strtotime($lb['tanggal']))];
          ?>
          <tr>
            <td><?= $no++ ?></td>
            <td style="white-space:nowrap"><?= date('d M Y', strtotime($lb['tanggal'])) ?></td>
            <td><?= $hariNama ?></td>
            <td><?= htmlspecialchars($lb['keterangan']) ?></td>
            <td>
              <?php if ($lb['jenis'] === 'nasional'): ?>
                <span class="badge badge-alfa">Nasional</span>
              <?php else: ?>
                <span class="badge" style="background:#8b5cf6;color:#fff">Sekolah</span>
              <?php endif; ?>
            </td>
            <td style="white-space:nowrap">
              <button type="button" onclick="editLibur(<?= $lb['id'] ?>,'<?= $lb['tanggal'] ?>','<?= htmlspecialchars(addslashes($lb['keterangan'])) ?>','<?= $lb['jenis'] ?>')" class="btn btn-outline btn-sm" style="padding:3px 8px;font-size:12px">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:11px;height:11px"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit
              </button>
              <button type="button" onclick="hapusLibur(<?= $lb['id'] ?>,'<?= htmlspecialchars(addslashes($lb['keterangan'])) ?>')" class="btn btn-sm" style="padding:3px 8px;font-size:12px;background:#ef4444;color:#fff;border:none">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:11px;height:11px"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                Hapus
              </button>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal Hapus -->
<form method="POST" id="form-hapus" style="display:none">
  <?= csrfField() ?>
  <input type="hidden" name="aksi" value="hapus">
  <input type="hidden" name="id" id="hapus-id">
</form>

<!-- STYLE -->
<style>
.kalender-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
}
.kal-header {
  text-align: center;
  font-size: 11px;
  font-weight: 700;
  color: var(--text-muted);
  padding: 4px 2px;
  text-transform: uppercase;
  letter-spacing: .5px;
}
.kal-cell {
  aspect-ratio: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  border-radius: 8px;
  border: 1.5px solid transparent;
  transition: all .15s;
  position: relative;
  user-select: none;
}
.kal-cell:hover:not(.kal-blank) {
  border-color: var(--primary);
  background: var(--primary-pale);
}
.kal-blank { cursor: default !important; }
.kal-today { background: #2563eb; color: #fff; font-weight: 700; }
.kal-today:hover { background: #1d4ed8 !important; }
.kal-nasional { background: #fef2f2; color: #ef4444; font-weight: 700; border-color: #fca5a5; }
.kal-sekolah  { background: #f5f3ff; color: #8b5cf6; font-weight: 700; border-color: #c4b5fd; }
.kal-weekend  { background: var(--bg-secondary, #f8fafc); color: var(--text-muted); }
.kal-dot {
  width: 5px; height: 5px;
  border-radius: 50%;
  background: currentColor;
  opacity: .7;
  margin-top: 2px;
}
@media(max-width:768px) { .libur-grid { grid-template-columns: 1fr !important; } }
</style>

<script>
// Pilih tanggal dari kalender
function pilihTanggal(tanggal, keterangan, jenis, id) {
  document.getElementById('input-tanggal').value = tanggal;
  if (keterangan) {
    document.getElementById('input-keterangan').value = keterangan;
    document.querySelector('#jenis-' + (jenis||'sekolah')).checked = true;
    if (id) {
      document.getElementById('input-aksi').value = 'edit';
      document.getElementById('input-id').value = id;
      document.getElementById('form-title').innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Edit Hari Libur';
      document.getElementById('btn-batal').style.display = '';
    }
  }
  document.getElementById('input-keterangan').focus();
  document.getElementById('card-form').scrollIntoView({behavior:'smooth', block:'center'});
}

// Edit dari tabel/list
function editLibur(id, tanggal, keterangan, jenis) {
  document.getElementById('input-aksi').value = 'edit';
  document.getElementById('input-id').value = id;
  document.getElementById('input-tanggal').value = tanggal;
  document.getElementById('input-keterangan').value = keterangan;
  document.querySelector('#jenis-' + (jenis||'sekolah')).checked = true;
  document.getElementById('form-title').innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Edit Hari Libur';
  document.getElementById('btn-batal').style.display = '';
  document.getElementById('card-form').scrollIntoView({behavior:'smooth', block:'center'});
  document.getElementById('input-keterangan').focus();
}

function resetForm() {
  document.getElementById('input-aksi').value = 'tambah';
  document.getElementById('input-id').value = '';
  document.getElementById('input-tanggal').value = '';
  document.getElementById('input-keterangan').value = '';
  document.getElementById('jenis-sekolah').checked = true;
  document.getElementById('form-title').innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><path d="M12 5v14M5 12h14"/></svg> Tambah Hari Libur';
  document.getElementById('btn-batal').style.display = 'none';
}

function hapusLibur(id, keterangan) {
  appConfirm({
    title: 'Hapus Hari Libur',
    body: 'Hapus hari libur:\n"' + keterangan + '"?\n\nTindakan ini tidak bisa dibatalkan.',
    btnLabel: 'Ya, Hapus',
    btnClass: 'btn-danger',
    onConfirm: () => {
      document.getElementById('hapus-id').value = id;
      document.getElementById('form-hapus').submit();
    }
  });
}

// Import libur nasional via AJAX
function importLiburNasional(tahun) {
  const status = document.getElementById('status-import');
  status.style.display = 'block';
  status.innerHTML = '<span style="color:var(--warning)">Mengimpor libur nasional ' + tahun + '...</span>';

  fetch('import_libur_nasional.php?tahun=' + tahun + '&token=<?= csrfToken() ?>')
    .then(r => r.json())
    .then(data => {
      if (data.status === 'success') {
        status.innerHTML = '<span style="color:var(--success)">' + data.message + '</span>';
        setTimeout(() => location.reload(), 1500);
      } else {
        status.innerHTML = '<span style="color:#ef4444">' + (data.message || 'Gagal import') + '</span>';
      }
    })
    .catch(() => {
      status.innerHTML = '<span style="color:#ef4444">Terjadi kesalahan. Coba lagi.</span>';
    });
}
</script>

<?php require_once '../includes/footer.php'; ?>