<?php
// ============================================================
// FILE: petugas/input_absensi.php - Input Absensi Manual
// (untuk siswa tanpa smartphone)
// ============================================================
require_once '../includes/config.php';
requireLogin('petugas');

$settings = getSettings($pdo);
$msg = ''; $error = '';
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $siswaId  = (int)$_POST['siswa_id'];
    $tanggal  = $_POST['tanggal'] ?? $today;
    $jenis    = $_POST['jenis']; // masuk / pulang
    $status   = $_POST['status'];
    $waktu    = $_POST['waktu'] ?? date('H:i:s');
    $inputOleh = $_SESSION['user_id'];

    // [FIX] Blokir input absensi saat hari libur
    $liburCek = isHariLibur($pdo, $tanggal);
    if ($liburCek['libur']) {
        $error = 'Tidak dapat melakukan absensi pada hari libur: ' . $liburCek['keterangan'] . '.';
    } elseif (!$siswaId || !$jenis || !$status) {
        $error = 'Data tidak lengkap.';
    } else {
        // Ambil kelas siswa
        $stmtK = $pdo->prepare("SELECT kelas_id FROM siswa WHERE id = ?");
        $stmtK->execute([$siswaId]);
        $kelas = $stmtK->fetchColumn();
        $namaKelasSnap = ambilKelasSiswaSaatIni($pdo, $siswaId)['nama_kelas'];

        $stmtCek = $pdo->prepare("SELECT id FROM absensi WHERE siswa_id = ? AND tanggal = ?");
        $stmtCek->execute([$siswaId, $tanggal]);
        $existing = $stmtCek->fetch();

        if ($jenis === 'masuk') {
            if ($existing) {
                $pdo->prepare("UPDATE absensi SET waktu_masuk=?, status_masuk=?, input_oleh=? WHERE siswa_id=? AND tanggal=?")
                    ->execute([$waktu, $status, $inputOleh, $siswaId, $tanggal]);
            } else {
                $pdo->prepare("INSERT INTO absensi (siswa_id, kelas_id, nama_kelas_snapshot, tanggal, waktu_masuk, status_masuk, input_oleh) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$siswaId, $kelas, $namaKelasSnap, $tanggal, $waktu, $status, $inputOleh]);
            }

            // [WA] Notifikasi real-time ke ortu setiap kali petugas input absensi masuk
            kirimNotifikasiHarian($pdo, $siswaId, $tanggal, $status, $waktu);
        } else {
            if ($existing) {
                $pdo->prepare("UPDATE absensi SET waktu_pulang=?, status_pulang='pulang', input_oleh=? WHERE siswa_id=? AND tanggal=?")
                    ->execute([$waktu, $inputOleh, $siswaId, $tanggal]);
            } else {
                $pdo->prepare("INSERT INTO absensi (siswa_id, kelas_id, nama_kelas_snapshot, tanggal, waktu_pulang, status_masuk, status_pulang, input_oleh) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$siswaId, $kelas, $namaKelasSnap, $tanggal, $waktu, $status, 'pulang', $inputOleh]);
            }
        }
        $stmtNama = $pdo->prepare("SELECT u.nama FROM siswa s JOIN users u ON s.user_id=u.id WHERE s.id=?");
        $stmtNama->execute([$siswaId]);
        $namaSiswa = $stmtNama->fetchColumn();
        $msg = "Absensi {$jenis} <strong>{$namaSiswa}</strong> berhasil diinput.";
    }
}

// Daftar siswa untuk dropdown
$petugasKelasId = getPetugasKelasId();
if ($petugasKelasId) {
    $kelasList   = $pdo->query("SELECT * FROM kelas WHERE id = $petugasKelasId")->fetchAll();
    $kelasFilter = $petugasKelasId;
    $where  = "WHERE u.status = 'aktif' AND s.kelas_id = $petugasKelasId";
    $params = [];
} else {
    // Petugas belum dapat kelas — data siswa dikosongkan
    $kelasList   = [];
    $kelasFilter = 0;
    $where  = "WHERE 1=0"; // tidak tampilkan siswa apapun
    $params = [];
}

$stmtSiswas = $pdo->prepare("SELECT s.id, u.nama, k.nama_kelas, s.nis FROM siswa s JOIN users u ON s.user_id=u.id LEFT JOIN kelas k ON s.kelas_id=k.id $where ORDER BY k.nama_kelas, u.nama");
$stmtSiswas->execute($params);
$siswas = $stmtSiswas->fetchAll();

// Absensi hari ini
$stmtAbsenHari = $pdo->prepare("
    SELECT a.*, u.nama, k.nama_kelas FROM absensi a
    JOIN siswa s ON a.siswa_id = s.id
    JOIN users u ON s.user_id = u.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    WHERE a.tanggal = ? AND a.input_oleh IS NOT NULL
    ORDER BY a.updated_at DESC
");
$stmtAbsenHari->execute([$today]);
$inputManual = $stmtAbsenHari->fetchAll();

$pageTitle = '️ Input Absensi Manual';
require_once '../includes/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>

<?php if (!$petugasKelasId): ?>
<div class="alert alert-warning">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;flex-shrink:0"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
  Akun Anda belum ditetapkan ke kelas manapun. Hubungi admin untuk mengatur kelas yang Anda pegang.
</div>
<?php else: ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px" class="manual-grid">
  
  <!-- Form Input -->
  <div class="card">
    <div class="card-header"><div class="card-title">Form Input Absensi Manual</div></div>
    <div class="card-body">
      <div class="alert alert-info"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg> Gunakan fitur ini untuk input absensi siswa yang tidak memiliki smartphone.</div>
      
      <form method="POST">
        <?php if ($petugasKelasId): ?>
        <div class="form-group">
          <label class="form-label">Kelas</label>
          <p style="margin:0;font-size:14px;font-weight:600;color:var(--primary);padding:4px 0"><?= htmlspecialchars($kelasList[0]['nama_kelas'] ?? '-') ?></p>
        </div>
        <?php else: ?>
        <div class="form-group">
          <label class="form-label">Filter Kelas</label>
          <select onchange="location.href='input_absensi.php?kelas='+this.value" class="form-control">
            <option value="">Semua Kelas</option>
            <?php foreach ($kelasList as $k): ?>
            <option value="<?=$k['id']?>" <?=$kelasFilter==$k['id']?'selected':''?>><?=$k['nama_kelas']?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div class="form-group">
          <label class="form-label">Pilih Siswa *</label>
          <select name="siswa_id" class="form-control" required>
            <option value="">-- Pilih Siswa --</option>
            <?php foreach ($siswas as $s): ?>
            <option value="<?=$s['id']?>"><?= $s['nama_kelas'].' - '.htmlspecialchars($s['nama']).' ('.$s['nis'].')' ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Tanggal *</label>
            <input type="date" name="tanggal" class="form-control" value="<?=$today?>" max="<?=$today?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Waktu *</label>
            <input type="time" name="waktu" class="form-control" value="<?= date('H:i') ?>" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Jenis Absensi *</label>
          <select name="jenis" class="form-control" required id="jenis-select" onchange="updateStatus()">
            <option value="masuk">Absensi Masuk</option>
            <option value="pulang">Absensi Pulang</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Status *</label>
          <select name="status" class="form-control" id="status-select" required>
            <option value="hadir">Hadir</option>
            <option value="terlambat">Terlambat</option>
            <option value="izin">Izin</option>
            <option value="sakit">Sakit</option>
            <option value="alfa">Alfa</option>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Simpan Absensi</button>
      </form>
    </div>
  </div>

  <!-- Riwayat Input Manual -->
  <div class="card">
    <div class="card-header"><div class="card-title">Input Manual Hari Ini</div></div>
    <div class="table-responsive">
      <table>
        <thead><tr><th>Siswa</th><th>Kelas</th><th>Masuk</th><th>Pulang</th><th>Status</th></tr></thead>
        <tbody>
          <?php if (empty($inputManual)): ?>
          <tr><td colspan="5" class="text-center text-muted" style="padding:24px">Belum ada input manual hari ini</td></tr>
          <?php else: ?>
          <?php foreach ($inputManual as $a): ?>
          <tr>
            <td><strong><?= htmlspecialchars($a['nama']) ?></strong></td>
            <td><?= $a['nama_kelas'] ?></td>
            <td style="font-family:var(--font-mono);font-size:12px"><?= $a['waktu_masuk']??'-' ?></td>
            <td style="font-family:var(--font-mono);font-size:12px"><?= $a['waktu_pulang']??'-' ?></td>
            <td><?= getStatusBadge($a['status_masuk']) ?></td>
          </tr>
          <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php
$extraJs = <<<JS
<script>
function updateStatus() {
  const jenis = document.getElementById('jenis-select').value;
  const statusSel = document.getElementById('status-select');
  if (jenis === 'pulang') {
    statusSel.innerHTML = '<option value="pulang">Pulang</option><option value="alfa">Belum Pulang (Alfa)</option>';
  } else {
    statusSel.innerHTML = '<option value="hadir">Hadir</option><option value="terlambat">Terlambat</option><option value="izin">Izin</option><option value="sakit">Sakit</option><option value="alfa">Alfa</option>';
  }
}
</script>
<style>@media(max-width:768px){.manual-grid{grid-template-columns:1fr!important}}</style>
JS;
require_once '../includes/footer.php';
// close else block for petugasKelasId check
?>
<?php endif; ?>