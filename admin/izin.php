<?php
// ============================================================
// FILE: admin/izin.php - Manajemen Pengajuan Izin/Sakit
// ============================================================
require_once '../includes/config.php';
requireLogin(['admin','petugas']);

$msg = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf(); // [FIX] Validasi CSRF
    $pengajuanId = (int)$_POST['pengajuan_id'];
    $aksi        = $_POST['aksi'];
    $catatan     = sanitize($_POST['catatan'] ?? '');
    
    $stmt = $pdo->prepare("SELECT * FROM pengajuan_izin WHERE id = ?");
    $stmt->execute([$pengajuanId]);
    $pengajuan = $stmt->fetch();
    
    if ($pengajuan) {
        if ($aksi === 'setujui') {
            $newStatus = 'disetujui';
            $statusAbsen = $pengajuan['jenis'];
            
            $stmtCek = $pdo->prepare("SELECT id FROM absensi WHERE siswa_id=? AND tanggal=?");
            $stmtCek->execute([$pengajuan['siswa_id'], $pengajuan['tanggal']]);
            $existing = $stmtCek->fetch();
            
            if ($existing) {
                $pdo->prepare("UPDATE absensi SET status_masuk=?, status_pulang=? WHERE siswa_id=? AND tanggal=?")
                    ->execute([$statusAbsen, $statusAbsen === 'izin' || $statusAbsen === 'sakit' ? $statusAbsen : 'pulang', $pengajuan['siswa_id'], $pengajuan['tanggal']]);
            } else {
                $kelasId = $pdo->prepare("SELECT kelas_id FROM siswa WHERE id=?");
                $kelasId->execute([$pengajuan['siswa_id']]);
                $kId = $kelasId->fetchColumn();
                $pdo->prepare("INSERT INTO absensi (siswa_id, kelas_id, tanggal, status_masuk, status_pulang) VALUES (?,?,?,?,?)")
                    ->execute([$pengajuan['siswa_id'], $kId, $pengajuan['tanggal'], $statusAbsen, $statusAbsen]);
            }
            $msg = 'Pengajuan disetujui dan absensi diperbarui.';
        } else {
            $newStatus = 'ditolak';
            $stmtCek = $pdo->prepare("SELECT id FROM absensi WHERE siswa_id=? AND tanggal=?");
            $stmtCek->execute([$pengajuan['siswa_id'], $pengajuan['tanggal']]);
            if (!$stmtCek->fetch()) {
                $kelasId = $pdo->prepare("SELECT kelas_id FROM siswa WHERE id=?");
                $kelasId->execute([$pengajuan['siswa_id']]);
                $kId = $kelasId->fetchColumn();
                $pdo->prepare("INSERT INTO absensi (siswa_id, kelas_id, tanggal, status_masuk, status_pulang) VALUES (?,?,?,'alfa','alfa')")
                    ->execute([$pengajuan['siswa_id'], $kId, $pengajuan['tanggal']]);
            }
            $msg = 'Pengajuan ditolak. Siswa dicatat Alfa.';
        }
        
        $pdo->prepare("UPDATE pengajuan_izin SET status=?, diproses_oleh=?, catatan_petugas=? WHERE id=?")
            ->execute([$newStatus, $_SESSION['user_id'], $catatan, $pengajuanId]);
    }
}

$statusFilter = $_GET['status'] ?? 'menunggu';
$params = [];
$where = '';
$andClauses = [];
if ($statusFilter && $statusFilter !== 'semua') {
    $andClauses[] = 'pi.status = ?';
    $params[] = $statusFilter;
}
// Filter kelas jika dipanggil dari petugas/izin.php
$izinKelasFilter = (int)($_GET['kelas_id_filter'] ?? 0);
if ($izinKelasFilter) {
    $andClauses[] = "s.kelas_id = $izinKelasFilter";
}
if ($andClauses) $where = 'WHERE ' . implode(' AND ', $andClauses);

$stmt = $pdo->prepare("
    SELECT pi.*, u.nama as nama_siswa, k.nama_kelas, 
           pu.nama as nama_petugas
    FROM pengajuan_izin pi
    JOIN siswa s ON pi.siswa_id = s.id
    JOIN users u ON s.user_id = u.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    LEFT JOIN users pu ON pi.diproses_oleh = pu.id
    $where
    ORDER BY pi.created_at DESC
");
$stmt->execute($params);
$pengajuans = $stmt->fetchAll();

$stmtCount = $pdo->query("SELECT status, COUNT(*) as jml FROM pengajuan_izin GROUP BY status");
$counts = [];
foreach ($stmtCount->fetchAll() as $r) $counts[$r['status']] = $r['jml'];

$pageTitle = 'Manajemen Pengajuan Izin/Sakit';
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

<!-- Filter Tabs -->
<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
  <?php
  $tabs = [
    'menunggu'  => ['label'=>'Menunggu',  'color'=>'warning',
                    'icon'=>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>'],
    'disetujui' => ['label'=>'Disetujui', 'color'=>'success',
                    'icon'=>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>'],
    'ditolak'   => ['label'=>'Ditolak',   'color'=>'danger',
                    'icon'=>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>'],
    'semua'     => ['label'=>'Semua',     'color'=>'info',
                    'icon'=>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>'],
  ];
  foreach ($tabs as $key => $tab):
    $cnt = $counts[$key] ?? '';
    $active = $statusFilter === $key;
  ?>
  <a href="izin.php?status=<?=$key?>" 
     class="btn btn-<?= $tab['color'] ?> <?= !$active?'btn-outline':'' ?>">
    <?= $tab['icon'] ?>
    <?= $tab['label'] ?><?= $cnt?' ('.$cnt.')':'' ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- Tabel Pengajuan -->
<div class="card">
  <div class="card-header">
    <div class="card-title">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
      Daftar Pengajuan — <?= $tabs[$statusFilter]['label'] ?? '' ?> (<?= count($pengajuans) ?>)
    </div>
  </div>
  <div class="table-responsive">
    <table>
      <thead>
        <tr><th>Tgl Diajukan</th><th>Siswa</th><th>Kelas</th><th>Tgl Izin</th><th>Jenis</th><th>Keterangan</th><th>Bukti</th><th>Status</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        <?php if (empty($pengajuans)): ?>
        <tr><td colspan="9" class="text-center text-muted" style="padding:32px">Tidak ada pengajuan</td></tr>
        <?php else: ?>
        <?php foreach ($pengajuans as $p): ?>
        <tr>
          <td style="font-size:12px;white-space:nowrap"><?= date('d/m/Y H:i', strtotime($p['created_at'])) ?></td>
          <td><strong><?= htmlspecialchars($p['nama_siswa']) ?></strong></td>
          <td><?= htmlspecialchars($p['nama_kelas'] ?? '-') ?></td>
          <td style="font-size:12px;white-space:nowrap"><?= date('d/m/Y', strtotime($p['tanggal'])) ?></td>
          <td>
            <?php if ($p['jenis']==='sakit'): ?>
            <span class="badge badge-sakit">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:10px;height:10px"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
              Sakit
            </span>
            <?php else: ?>
            <span class="badge badge-izin">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:10px;height:10px"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
              Izin
            </span>
            <?php endif; ?>
          </td>
          <td style="max-width:200px;font-size:13px"><?= htmlspecialchars($p['keterangan']) ?></td>
          <td>
            <?php if ($p['bukti_foto']): ?>
            <button type="button" onclick="lihatBukti('<?= UPLOAD_URL . $p['bukti_foto'] ?>')" class="btn btn-sm btn-outline">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
              Lihat
            </button>
            <?php else: ?>
            <span class="text-muted" style="font-size:12px">-</span>
            <?php endif; ?>
          </td>
          <td><?= getStatusBadge($p['status']) ?></td>
          <td>
            <?php if ($p['status'] === 'menunggu'): ?>
            <div class="action-dropdown">
              <button class="action-dropdown-btn" onclick="toggleActionDropdown(this)" title="Aksi">•••</button>
              <div class="action-dropdown-menu">
                <button onclick="document.querySelectorAll('.action-dropdown-menu').forEach(m=>m.classList.remove('open'));prosesIzin(<?= $p['id'] ?>, 'setujui')" class="menu-item-success">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><path d="M9 11l3 3L22 4"/></svg>
                  Setujui
                </button>
                <button onclick="document.querySelectorAll('.action-dropdown-menu').forEach(m=>m.classList.remove('open'));prosesIzin(<?= $p['id'] ?>, 'tolak')" class="menu-item-danger">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                  Tolak
                </button>
              </div>
            </div>
            <?php else: ?>
            <span style="font-size:11px;color:var(--text-muted)">
              <?= $p['nama_petugas'] ? 'oleh '.$p['nama_petugas'] : '' ?>
              <?= $p['catatan_petugas'] ? '<br>'.$p['catatan_petugas'] : '' ?>
            </span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Proses Izin -->
<!-- Modal Bukti Foto -->
<div id="modal-bukti-foto" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:10000;align-items:center;justify-content:center;padding:20px">
  <div style="position:relative;max-width:90vw;max-height:90vh">
    <button onclick="document.getElementById('modal-bukti-foto').style.display='none'"
      style="position:absolute;top:-36px;right:0;background:none;border:none;color:#fff;font-size:28px;cursor:pointer;font-weight:bold;line-height:1">&times;</button>
    <img id="bukti-foto-img" src="" alt="Bukti Izin/Sakit"
      style="max-width:100%;max-height:85vh;border-radius:8px;box-shadow:0 8px 40px rgba(0,0,0,.5);display:block">
    <a id="bukti-foto-link" href="#" target="_blank"
      style="display:block;text-align:center;margin-top:10px;color:#93c5fd;font-size:13px;text-decoration:underline">
      Buka di tab baru
    </a>
  </div>
</div>

<div id="modal-izin" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9998;align-items:center;justify-content:center;padding:20px">
  <div style="background:#fff;border-radius:16px;padding:28px;max-width:400px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.3)">
    <h3 style="margin-bottom:16px;color:var(--primary);font-size:16px;display:flex;align-items:center;gap:8px" id="modal-title">
      <span id="modal-icon"></span>
      Proses Pengajuan
    </h3>
    <form method="POST" id="form-izin">
        <?= csrfField() ?>
      <input type="hidden" name="pengajuan_id" id="input-pengajuan-id">
      <input type="hidden" name="aksi" id="input-aksi">
      <div class="form-group">
        <label class="form-label">Catatan (opsional)</label>
        <textarea name="catatan" class="form-control" placeholder="Catatan untuk siswa..."></textarea>
      </div>
      <div style="display:flex;gap:10px">
        <button type="submit" class="btn btn-primary" id="btn-proses-submit">Proses</button>
        <button type="button" class="btn btn-outline" onclick="document.getElementById('modal-izin').style.display='none'">Batal</button>
      </div>
    </form>
  </div>
</div>

<?php
$extraJs = <<<JS
<script>
function prosesIzin(id, aksi) {
  document.getElementById('input-pengajuan-id').value = id;
  document.getElementById('input-aksi').value = aksi;
  const isSetujui = aksi === 'setujui';
  document.getElementById('modal-title').childNodes[1] && (document.getElementById('modal-title').childNodes[1].textContent = (isSetujui ? ' Setujui Pengajuan' : ' Tolak Pengajuan'));
  document.getElementById('btn-proses-submit').textContent = isSetujui ? 'Setujui' : 'Tolak';
  document.getElementById('btn-proses-submit').className = 'btn ' + (isSetujui ? 'btn-success' : 'btn-danger');
  document.getElementById('modal-izin').style.display = 'flex';
}
function lihatBukti(url) {
  const modal = document.getElementById('modal-bukti-foto');
  const img = document.getElementById('bukti-foto-img');
  const link = document.getElementById('bukti-foto-link');
  img.src = url;
  link.href = url;
  modal.style.display = 'flex';
}
document.getElementById('modal-bukti-foto').addEventListener('click', function(e) {
  if (e.target === this) this.style.display = 'none';
});

document.getElementById('modal-izin').addEventListener('click', function(e) {
  if (e.target === this) this.style.display = 'none';
});
</script>
JS;
require_once '../includes/footer.php';
?>
