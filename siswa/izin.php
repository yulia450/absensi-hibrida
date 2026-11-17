<?php
// ============================================================
// FILE: siswa/izin.php - Pengajuan Izin / Sakit
// ============================================================
require_once '../includes/config.php';
require_once '../includes/functions.php';
requireLogin('siswa');

$siswaId = $_SESSION['siswa_id'];
$msg = ''; $error = '';
$tanggalDefault = $_GET['tanggal'] ?? date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tanggal    = $_POST['tanggal'];
    $jenis      = $_POST['jenis']; // izin / sakit
    $keterangan = sanitize($_POST['keterangan']);
    $buktiFile  = null;

    // Validasi dasar
    if (empty($tanggal) || empty($jenis) || empty($keterangan)) {
        $error = 'Tanggal, jenis, dan keterangan wajib diisi.';
    } else {
        $tglLabel = date('d/m/Y', strtotime($tanggal));

        // 1. Cek hari libur
        $liburCek = isHariLibur($pdo, $tanggal);
        if ($liburCek['libur']) {
            $error = 'Tidak dapat mengajukan izin/sakit pada hari libur: ' . $liburCek['keterangan'] . '.';
        } else {
            // 2. Cek duplikat pengajuan izin
            $stmtCekIzin = $pdo->prepare("SELECT id FROM pengajuan_izin WHERE siswa_id = ? AND tanggal = ?");
            $stmtCekIzin->execute([$siswaId, $tanggal]);
            if ($stmtCekIzin->fetch()) {
                $error = 'Sudah ada pengajuan izin/sakit untuk tanggal ' . $tglLabel . '.';
            } else {
                // 3. Cek apakah sudah ada record absensi di tanggal tersebut
                $stmtCekAbsen = $pdo->prepare("SELECT id, status_masuk FROM absensi WHERE siswa_id = ? AND tanggal = ?");
                $stmtCekAbsen->execute([$siswaId, $tanggal]);
                $absenAda = $stmtCekAbsen->fetch();
                if ($absenAda && in_array($absenAda['status_masuk'], ['hadir', 'terlambat'])) {
                    $error = 'Kamu sudah tercatat ' . $absenAda['status_masuk'] . ' pada tanggal ' . $tglLabel . '. Pengajuan izin tidak diperlukan.';
                } elseif ($absenAda && in_array($absenAda['status_masuk'], ['izin', 'sakit'])) {
                    $error = 'Kamu sudah memiliki catatan ' . $absenAda['status_masuk'] . ' pada tanggal ' . $tglLabel . '.';
                } else {
                    // Semua validasi lolos — proses upload & insert
                    if (!empty($_FILES['bukti_foto']['name'])) {
                        $uploadResult = uploadBukti($_FILES['bukti_foto'], 'izin');
                        if (!$uploadResult['status']) {
                            $error = $uploadResult['message'];
                        } else {
                            $buktiFile = $uploadResult['filename'];
                        }
                    }

                    if (!$error) {
                        $stmt = $pdo->prepare("
                            INSERT INTO pengajuan_izin (siswa_id, tanggal, jenis, keterangan, bukti_foto, status)
                            VALUES (?, ?, ?, ?, ?, 'menunggu')
                        ");
                        $stmt->execute([$siswaId, $tanggal, $jenis, $keterangan, $buktiFile]);
                        $msg = 'Pengajuan ' . ($jenis === 'sakit' ? 'sakit' : 'izin') . ' berhasil dikirim! Menunggu persetujuan guru/admin.';
                    }
                }
            }
        }
    }
}

// Riwayat pengajuan
$stmtRiwayat = $pdo->prepare("
    SELECT * FROM pengajuan_izin WHERE siswa_id = ?
    ORDER BY created_at DESC LIMIT 10
");
$stmtRiwayat->execute([$siswaId]);
$riwayatPengajuan = $stmtRiwayat->fetchAll();

$pageTitle = ' Ajukan Izin / Sakit';
require_once '../includes/header.php';
?>

<?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px" class="izin-grid">

  <!-- Form Pengajuan -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Form Pengajuan Ketidakhadiran</div>
    </div>
    <div class="card-body">
      <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        
        <div class="form-group">
          <label class="form-label">Tanggal Tidak Hadir *</label>
          <input type="date" name="tanggal" class="form-control" 
                 value="<?= $tanggalDefault ?>"
                 max="<?= date('Y-m-d') ?>" required>
        </div>

        <div class="form-group">
          <label class="form-label">Jenis Pengajuan *</label>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
            <label style="border:2px solid var(--border);border-radius:10px;padding:14px;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:10px" id="label-izin">
              <input type="radio" name="jenis" value="izin" style="display:none" onclick="selectJenis('izin')">
              <span style="font-size:24px"></span>
              <div>
                <div style="font-weight:700">Izin</div>
                <div style="font-size:11px;color:var(--text-muted)">Keperluan keluarga, dll</div>
              </div>
            </label>
            <label style="border:2px solid var(--border);border-radius:10px;padding:14px;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:10px" id="label-sakit">
              <input type="radio" name="jenis" value="sakit" style="display:none" onclick="selectJenis('sakit')">
              <span style="font-size:24px"></span>
              <div>
                <div style="font-weight:700">Sakit</div>
                <div style="font-size:11px;color:var(--text-muted)">Dengan surat dokter</div>
              </div>
            </label>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Keterangan *</label>
          <textarea name="keterangan" class="form-control" rows="4" 
                    placeholder="Tuliskan alasan dengan lengkap dan jelas..."
                    required><?= htmlspecialchars($_POST['keterangan'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
          <label class="form-label">Bukti Pendukung</label>
          <div style="border:2px dashed var(--border);border-radius:10px;padding:16px;text-align:center;cursor:pointer;background:var(--bg)" onclick="document.getElementById('bukti-input').click()">
            <div id="preview-foto">
              <div style="font-size:32px;margin-bottom:8px"></div>
              <div style="font-size:13px;color:var(--text-muted)">Ketuk untuk ambil foto / pilih file</div>
              <div style="font-size:11px;color:var(--text-muted);margin-top:4px">JPG, PNG, PDF — maks 5MB</div>
            </div>
          </div>
          <input type="file" id="bukti-input" name="bukti_foto" 
                 accept="image/*,application/pdf" 
                 capture="environment"
                 style="display:none"
                 onchange="previewFoto(this)">
          <div class="form-hint">
            Contoh: surat dokter, surat izin orang tua, foto kondisi sakit.
          </div>
        </div>

        <div class="alert alert-info">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg> Pengajuan akan diproses oleh guru/admin. Status akan berubah setelah disetujui atau ditolak.
        </div>

        <button type="submit" class="btn btn-warning btn-block btn-lg">
          Kirim Pengajuan
        </button>
      </form>
    </div>
  </div>

  <!-- Riwayat Pengajuan -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        Riwayat Pengajuan</div>
    </div>
    <div class="card-body" style="padding:0">
      <?php if (empty($riwayatPengajuan)): ?>
      <div class="text-center text-muted" style="padding:40px">
        <div style="font-size:40px;margin-bottom:12px"></div>
        <div>Belum ada riwayat pengajuan</div>
      </div>
      <?php else: ?>
      <?php foreach ($riwayatPengajuan as $p): ?>
      <div style="padding:14px 20px;border-bottom:1px solid var(--border)">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px">
          <div style="flex:1">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px">
              <strong style="font-size:14px"><?= date('d/m/Y', strtotime($p['tanggal'])) ?></strong>
              <span class="badge <?= $p['jenis']==='sakit'?'badge-sakit':'badge-izin' ?>">
                <?= $p['jenis']==='sakit'?' Sakit':'Izin' ?>
              </span>
            </div>
            <div style="font-size:13px;color:var(--text-muted)"><?= htmlspecialchars(substr($p['keterangan'],0,60)) ?></div>
            <?php if ($p['catatan_petugas']): ?>
            <div style="font-size:12px;color:var(--primary);margin-top:4px;background:var(--bg);padding:4px 8px;border-radius:6px">
              <?= htmlspecialchars($p['catatan_petugas']) ?>
            </div>
            <?php endif; ?>
          </div>
          <div style="text-align:right;flex-shrink:0">
            <?= getStatusBadge($p['status']) ?>
            <?php if ($p['bukti_foto']): ?>
            <div style="margin-top:6px">
              <a href="<?= UPLOAD_URL . $p['bukti_foto'] ?>" target="_blank" class="btn btn-sm btn-outline" style="font-size:11px">Bukti</a>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div>

<?php
$extraJs = <<<JS
<script>
function selectJenis(jenis) {
  document.getElementById('label-izin').style.borderColor = jenis==='izin' ? 'var(--primary)' : 'var(--border)';
  document.getElementById('label-izin').style.background  = jenis==='izin' ? '#eaf4fd' : '';
  document.getElementById('label-sakit').style.borderColor = jenis==='sakit' ? '#9b59b6' : 'var(--border)';
  document.getElementById('label-sakit').style.background  = jenis==='sakit' ? '#f5eef8' : '';
  document.querySelector('[name="jenis"][value="'+jenis+'"]').checked = true;
}
</script>
<style>@media(max-width:768px){.izin-grid{grid-template-columns:1fr!important}}</style>
JS;
require_once '../includes/footer.php';
?>