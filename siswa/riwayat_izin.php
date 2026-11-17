<?php
// ============================================================
// FILE: siswa/riwayat_izin.php - Riwayat Pengajuan Izin Siswa
// ============================================================
require_once '../includes/config.php';
require_once '../includes/functions.php';
requireLogin('siswa');

$siswaId = $_SESSION['siswa_id'];

$bulan   = (int)($_GET['bulan'] ?? date('n'));
$tahun   = (int)($_GET['tahun'] ?? date('Y'));

// Data siswa
$stmtSiswa = $pdo->prepare("SELECT s.*, u.nama, k.nama_kelas FROM siswa s JOIN users u ON s.user_id = u.id LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ?");
$stmtSiswa->execute([$siswaId]);
$siswa = $stmtSiswa->fetch();

// Pengajuan izin bulan ini
$stmt = $pdo->prepare("
    SELECT p.*, u.nama as petugas_nama
    FROM pengajuan_izin p
    LEFT JOIN users u ON p.diproses_oleh = u.id
    WHERE p.siswa_id = ?
      AND MONTH(p.tanggal) = ?
      AND YEAR(p.tanggal) = ?
    ORDER BY p.tanggal DESC
");
$stmt->execute([$siswaId, $bulan, $tahun]);
$pengajuan = $stmt->fetchAll();

// Hitung ringkasan
$totalMenunggu  = 0; $totalDisetujui = 0; $totalDitolak = 0;
foreach ($pengajuan as $p) {
    if ($p['status'] === 'menunggu')  $totalMenunggu++;
    if ($p['status'] === 'disetujui') $totalDisetujui++;
    if ($p['status'] === 'ditolak')   $totalDitolak++;
}

$pageTitle = 'Riwayat Izin/Sakit';
require_once '../includes/header.php';
?>

<!-- Header info siswa -->
<div class="card mb-3" style="background:linear-gradient(135deg,var(--primary) 0%,var(--primary-light) 100%);color:#fff;border:none">
  <div class="card-body" style="text-align:center">
    <div style="font-size:13px;opacity:.75">Riwayat Pengajuan Izin &amp; Sakit</div>
    <div style="font-size:18px;font-weight:900;margin-top:2px"><?= htmlspecialchars($siswa['nama']) ?></div>
    <div style="font-size:12px;opacity:.75"><?= htmlspecialchars($siswa['nama_kelas'] ?? '-') ?> | NIS: <?= $siswa['nis'] ?></div>
  </div>
</div>

<!-- Filter -->
<div class="card mb-3">
  <div class="card-body">
    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label">Bulan</label>
        <select name="bulan" class="form-control">
          <?php for ($i=1;$i<=12;$i++): ?>
          <option value="<?=$i?>" <?=$i==$bulan?'selected':''?>><?= namaBulan($i) ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="form-group" style="margin-bottom:0">
        <label class="form-label">Tahun</label>
        <select name="tahun" class="form-control">
          <?php for ($y=date('Y');$y>=date('Y')-2;$y--): ?>
          <option value="<?=$y?>" <?=$y==$tahun?'selected':''?>><?=$y?></option>
          <?php endfor; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary">Tampilkan</button>
      <a href="izin.php" class="btn btn-success">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Ajukan Baru
      </a>
    </form>
  </div>
</div>

<!-- Summary Cards -->
<div class="stats-grid mb-3">
  <div class="stat-card yellow">
    <div class="stat-icon yellow">
      <svg viewBox="0 0 24 24" fill="none" stroke="#92540e" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
    </div>
    <div><div class="stat-value"><?= $totalMenunggu ?></div><div class="stat-label">Menunggu</div></div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon green">
      <svg viewBox="0 0 24 24" fill="none" stroke="#1a7a45" stroke-width="1.8"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
    </div>
    <div><div class="stat-value"><?= $totalDisetujui ?></div><div class="stat-label">Disetujui</div></div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon red">
      <svg viewBox="0 0 24 24" fill="none" stroke="#b91c1c" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
    </div>
    <div><div class="stat-value"><?= $totalDitolak ?></div><div class="stat-label">Ditolak</div></div>
  </div>
</div>

<!-- Tabel Riwayat -->
<div class="card">
  <div class="card-header">
    <div class="card-title">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
      Riwayat Pengajuan — <?= namaBulan($bulan) ?> <?= $tahun ?>
    </div>
  </div>
  <div class="table-responsive">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Tanggal</th>
          <th>Jenis</th>
          <th>Keterangan</th>
          <th>Bukti</th>
          <th>Status</th>
          <th>Catatan Petugas</th>
          <th>Diproses Oleh</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($pengajuan)): ?>
        <tr><td colspan="8" class="text-center text-muted" style="padding:32px">Tidak ada pengajuan untuk periode ini</td></tr>
        <?php else: ?>
        <?php foreach ($pengajuan as $i => $p): ?>
        <?php
          $statusBadge = match($p['status']) {
            'menunggu'  => '<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:#fef3c7;color:#92540e">Menunggu</span>',
            'disetujui' => '<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:#d1fae5;color:#1a7a45">Disetujui</span>',
            'ditolak'   => '<span style="display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:#fee2e2;color:#b91c1c">Ditolak</span>',
            default     => '<span style="padding:3px 10px;border-radius:20px;font-size:11px;background:#eee">' . htmlspecialchars($p['status']) . '</span>',
          };
          $jenisBadge = $p['jenis'] === 'sakit'
            ? '<span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:#ffedd5;color:#c2410c">Sakit</span>'
            : '<span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:700;background:#dbeafe;color:#1e40af">Izin</span>';
          $rowBg = $p['status'] === 'ditolak' ? 'background:#fff5f5' : ($p['status'] === 'disetujui' ? 'background:#f0fdf4' : '');
        ?>
        <tr style="<?= $rowBg ?>">
          <td style="color:var(--text-muted);font-size:12px"><?= $i+1 ?></td>
          <td style="font-weight:600"><?= date('d M Y', strtotime($p['tanggal'])) ?></td>
          <td><?= $jenisBadge ?></td>
          <td style="max-width:200px;font-size:13px"><?= htmlspecialchars($p['keterangan']) ?></td>
          <td>
            <?php if ($p['bukti_foto']): ?>
              <a href="<?= UPLOAD_URL . $p['bukti_foto'] ?>" target="_blank"
                 style="font-size:12px;color:var(--primary);text-decoration:underline">
                Lihat Bukti
              </a>
            <?php else: ?>
              <span style="color:var(--text-muted);font-size:12px">—</span>
            <?php endif; ?>
          </td>
          <td><?= $statusBadge ?></td>
          <td style="font-size:12px;color:var(--text-muted);max-width:180px">
            <?= $p['catatan_petugas'] ? htmlspecialchars($p['catatan_petugas']) : '—' ?>
          </td>
          <td style="font-size:12px;color:var(--text-muted)">
            <?= $p['petugas_nama'] ? htmlspecialchars($p['petugas_nama']) : '—' ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal Bukti Foto Riwayat -->
<div id="modal-bukti-riwayat" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:10000;align-items:center;justify-content:center;padding:20px">
  <div style="position:relative;max-width:90vw;max-height:90vh">
    <button onclick="document.getElementById('modal-bukti-riwayat').style.display='none'"
      style="position:absolute;top:-36px;right:0;background:none;border:none;color:#fff;font-size:28px;cursor:pointer;font-weight:bold;line-height:1">&times;</button>
    <img id="bukti-riwayat-img" src="" alt="Bukti" style="max-width:100%;max-height:85vh;border-radius:8px;box-shadow:0 8px 40px rgba(0,0,0,.5);display:block">
    <a id="bukti-riwayat-link" href="#" target="_blank" style="display:block;text-align:center;margin-top:10px;color:#93c5fd;font-size:13px;text-decoration:underline">Buka di tab baru</a>
  </div>
</div>
<script>
function lihatBuktiRiwayat(url) {
  document.getElementById('bukti-riwayat-img').src = url;
  document.getElementById('bukti-riwayat-link').href = url;
  document.getElementById('modal-bukti-riwayat').style.display = 'flex';
}
document.getElementById('modal-bukti-riwayat').addEventListener('click', function(e) {
  if (e.target === this) this.style.display = 'none';
});
</script>
<?php require_once '../includes/footer.php'; ?>