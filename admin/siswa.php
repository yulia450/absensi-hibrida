<?php
// ============================================================
// FILE: admin/siswa.php - Manajemen Data Siswa
// ============================================================
require_once '../includes/config.php';
requireLogin('admin');

$msg = ''; $error = '';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// --- NONAKTIF / AKTIFKAN SISWA ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_siswa'])) {
    verifyCsrf();
    $toggleId = (int)($_POST['toggle_id'] ?? 0);
    $aksi     = $_POST['aksi_toggle'] ?? '';
    $alasan   = in_array($_POST['alasan_nonaktif'] ?? '', ['lulus','keluar'])
                ? $_POST['alasan_nonaktif'] : null;
    $ket      = sanitize($_POST['keterangan_nonaktif'] ?? '');

    if ($toggleId > 0) {
        if ($aksi === 'nonaktif' && $alasan) {
            $pdo->prepare("
                UPDATE users
                SET status = 'nonaktif',
                    alasan_nonaktif = ?,
                    keterangan_nonaktif = ?
                WHERE id = (SELECT user_id FROM siswa WHERE id = ?)
            ")->execute([$alasan, $ket ?: null, $toggleId]);
            $msg = 'Siswa berhasil dinonaktifkan.';
        } else {
            $pdo->prepare("
                UPDATE users
                SET status = 'aktif',
                    alasan_nonaktif = NULL,
                    keterangan_nonaktif = NULL
                WHERE id = (SELECT user_id FROM siswa WHERE id = ?)
            ")->execute([$toggleId]);
            $msg = 'Siswa berhasil diaktifkan kembali.';
        }
        auditLog($pdo, 'TOGGLE_SISWA', "siswa_id={$toggleId} aksi={$aksi} alasan={$alasan}");
    }
    $action = 'list';
}

// --- HAPUS SISWA ---
// Hapus hanya boleh via POST (bukan GET) agar terlindungi CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_siswa'])) {
    verifyCsrf();
    $hapusId = (int)($_POST['hapus_id'] ?? 0);
    if ($hapusId > 0) {
        try {
            $pdo->prepare("DELETE FROM users WHERE id = (SELECT user_id FROM siswa WHERE id = ?)")->execute([$hapusId]);
            $msg = 'Siswa berhasil dihapus.';
            auditLog($pdo, 'HAPUS_SISWA', 'siswa_id='.$hapusId);
        } catch (Exception $e) {
            $error = 'Gagal menghapus siswa.';
        }
    }
    $action = 'list';
}

// --- SIMPAN SISWA (TAMBAH/EDIT) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan'])) {
    verifyCsrf();
    $nama    = sanitize($_POST['nama']);
    $email   = sanitize($_POST['email']);
    $nis     = sanitize($_POST['nis']);
    $nisn    = sanitize($_POST['nisn']);
    $kelasId = (int)$_POST['kelas_id'];
    $noHp    = sanitize($_POST['no_hp']);
    $namaOrtu= sanitize($_POST['nama_orang_tua']);
    $noHpOrtu= sanitize($_POST['no_hp_ortu']);
    $alamat  = sanitize($_POST['alamat']);
    // [FIX] Validasi jenis_kelamin dan format tanggal_lahir
    $jk      = in_array($_POST['jenis_kelamin'] ?? '', ['L', 'P']) ? $_POST['jenis_kelamin'] : 'L';
    $tglLahir= !empty($_POST['tanggal_lahir']) ? date('Y-m-d', strtotime($_POST['tanggal_lahir'])) : null;
    $editId  = (int)($_POST['edit_id'] ?? 0);

    if (!$editId) {
        // [FIX] Password awal = 4 digit NIS terakhir + @alman (sama seperti format reset password)
        $plainPassword = substr($nis, -4) . '@alman';
        $password = password_hash($plainPassword, PASSWORD_DEFAULT);
        try {
            $pdo->beginTransaction();
            $stmtUser = $pdo->prepare("INSERT INTO users (nama, email, password, role, no_hp) VALUES (?,?,?,'siswa',?)");
            $stmtUser->execute([$nama, $email, $password, $noHp]);
            $userId = $pdo->lastInsertId();
            $stmtSiswa = $pdo->prepare("INSERT INTO siswa (user_id, nis, nisn, kelas_id, nama_orang_tua, no_hp_ortu, alamat, tanggal_lahir, jenis_kelamin) VALUES (?,?,?,?,?,?,?,?,?)");
            $stmtSiswa->execute([$userId, $nis, $nisn, $kelasId, $namaOrtu, $noHpOrtu, $alamat, $tglLahir, $jk]);
            $pdo->commit();
            auditLog($pdo, 'TAMBAH_SISWA', 'nama='.$nama.', nis='.$nis);
            $msg = "Siswa <strong>{$nama}</strong> berhasil ditambahkan. Password: <strong>{$plainPassword}</strong> (format: 4 digit NIS terakhir@alman — bisa direset kapan saja).";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Gagal menambah siswa. Email atau NIS mungkin sudah digunakan.';
        }
    } else {
        try {
            $pdo->beginTransaction();
            $stmtUser = $pdo->prepare("UPDATE users SET nama=?, email=?, no_hp=? WHERE id=(SELECT user_id FROM siswa WHERE id=?)");
            $stmtUser->execute([$nama, $email, $noHp, $editId]);
            $stmtSiswa = $pdo->prepare("UPDATE siswa SET nis=?, nisn=?, kelas_id=?, nama_orang_tua=?, no_hp_ortu=?, alamat=?, tanggal_lahir=?, jenis_kelamin=? WHERE id=?");
            $stmtSiswa->execute([$nis, $nisn, $kelasId, $namaOrtu, $noHpOrtu, $alamat, $tglLahir, $jk, $editId]);
            $pdo->commit();
            auditLog($pdo, 'EDIT_SISWA', 'siswa_id='.$editId.', nama='.$nama); // [FIX] Audit log untuk edit
            $msg = 'Data siswa berhasil diperbarui.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Gagal memperbarui data siswa.';
        }
    }
    $action = 'list';
}

// Ambil data untuk edit (pre-fill modal)
$editData = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT s.*, u.nama, u.email, u.no_hp, u.status FROM siswa s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
    $stmt->execute([$id]);
    $editData = $stmt->fetch();
}

$kelasList = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();

$search      = sanitize($_GET['search'] ?? '');
$kelasFilter = (int)($_GET['kelas'] ?? 0);
$statusFilter = $_GET['status'] ?? ''; // '' = semua, 'aktif', 'nonaktif'
$params = [];
$where  = "WHERE 1=1";

if ($statusFilter === 'aktif' || $statusFilter === 'nonaktif') {
    $where .= " AND u.status = ?";
    $params[] = $statusFilter;
}
if ($search) {
    $where .= " AND (u.nama LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ?)";
    $params = array_merge($params, ["%$search%","%$search%","%$search%"]);
}
if ($kelasFilter) {
    $where .= " AND s.kelas_id = ?";
    $params[] = $kelasFilter;
}

$totalSiswa = $pdo->prepare("SELECT COUNT(*) FROM siswa s JOIN users u ON s.user_id = u.id $where");
$totalSiswa->execute($params);
$total = $totalSiswa->fetchColumn();

$page = max(1, (int)($_GET['page'] ?? 1));

// Jumlah data per halaman bisa dipilih user: 7 / 25 / 50 / 100
$opsiPerPage = [7, 25, 50, 100];
$perPage = (int)($_GET['limit'] ?? 25);
if (!in_array($perPage, $opsiPerPage, true)) $perPage = 25;

$offset = ($page - 1) * $perPage;
$totalPage = ceil($total / $perPage);

$stmtSiswa = $pdo->prepare("
    SELECT s.*, u.nama, u.email, u.no_hp, u.status, u.alasan_nonaktif, u.keterangan_nonaktif, k.nama_kelas
    FROM siswa s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    $where
    ORDER BY k.nama_kelas, u.nama
    LIMIT ? OFFSET ?
");
$allParams = array_merge($params, [(int)$perPage, (int)$offset]);
$stmtSiswa->execute($allParams);
$siswas = $stmtSiswa->fetchAll();

// Apakah modal edit harus terbuka?
$openEditModal = ($editData !== null);

$pageTitle = 'Manajemen Data Siswa';
require_once '../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-success">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
  <?= $msg ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
  <?= $error ?>
</div>
<?php endif; ?>

<!-- Tabel Siswa -->
<div class="card">
  <div class="card-header">
    <div class="card-title">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
      Daftar Siswa (<?= $total ?> siswa)
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <button class="btn btn-sm btn-primary" onclick="openModal('modal-tambah-siswa')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Tambah Siswa
      </button>
      <a href="export/export_rekap.php?jenis=siswa" class="btn btn-sm btn-success">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Ekspor Excel
      </a>
      <button class="btn btn-sm btn-danger" onclick="resetSemuaPassword()" title="Reset password semua siswa ke 4 digit NIS terakhir@alman">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
        Reset Semua Password Siswa
      </button>
      <button class="btn btn-sm btn-info" onclick="openModal('modal-import-siswa')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Impor Excel
      </button>
    </div>
  </div>
  <div class="card-body" style="padding-bottom:0">
    <form method="GET" id="form-filter-siswa">
      <!-- Search Bar — gaya sama seperti Absensi Hari Ini -->
      <div style="padding:10px 16px;border-bottom:1px solid var(--border-light);background:var(--bg-subtle);margin:0 -24px 14px">
        <div style="position:relative;max-width:320px">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
               style="position:absolute;left:10px;top:50%;transform:translateY(-50%);width:14px;height:14px;color:var(--text-muted);pointer-events:none">
            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
          </svg>
          <input type="text" name="search" id="search-siswa-input"
            placeholder="Cari nama atau NIS..."
            value="<?= htmlspecialchars($search) ?>"
            autocomplete="off"
            style="width:100%;padding:7px 10px 7px 32px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:var(--bg);color:var(--text);outline:none"
            oninput="autoSearchSiswa()"
            onfocus="this._focused=true" onblur="this._focused=false">
        </div>
      </div>
      <!-- Filter row -->
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px">
        <select name="kelas" id="kelas-filter-siswa" class="form-control" style="max-width:180px" onchange="document.getElementById('form-filter-siswa').submit()">
          <option value="">Semua Kelas</option>
          <?php foreach ($kelasList as $k): ?>
          <option value="<?= $k['id'] ?>" <?= $kelasFilter==$k['id']?'selected':'' ?>><?= $k['nama_kelas'] ?></option>
          <?php endforeach; ?>
        </select>
        <select name="status" class="form-control" style="max-width:150px" onchange="document.getElementById('form-filter-siswa').submit()">
          <option value="" <?= $statusFilter===''?'selected':'' ?>>Semua Status</option>
          <option value="aktif" <?= $statusFilter==='aktif'?'selected':'' ?>>Aktif</option>
          <option value="nonaktif" <?= $statusFilter==='nonaktif'?'selected':'' ?>>Nonaktif</option>
        </select>
        <a href="siswa.php" class="btn btn-outline">Reset</a>
      </div>
    </form>
  </div>
  <div class="table-responsive">
    <table>
      <thead>
        <tr><th>#</th><th>NIS</th><th>Nama Siswa</th><th>Kelas</th><th>No. HP Ortu</th><th>Email</th><th>Status</th><th>Aksi</th></tr>
      </thead>
      <tbody>
        <?php if (empty($siswas)): ?>
        <tr><td colspan="8" class="text-center text-muted" style="padding:24px">Tidak ada data siswa</td></tr>
        <?php else: ?>
        <?php foreach ($siswas as $i => $s): ?>
        <tr>
          <td><?= $offset + $i + 1 ?></td>
          <td><span style="font-family:var(--font-mono);font-size:12px"><?= $s['nis'] ?></span></td>
          <td>
            <strong><?= htmlspecialchars($s['nama']) ?></strong>
            <div style="font-size:11px;color:var(--text-muted)"><?= $s['jenis_kelamin']==='L'?'Laki-laki':'Perempuan' ?></div>
          </td>
          <td><?= htmlspecialchars($s['nama_kelas'] ?? '-') ?></td>
          <td><?= htmlspecialchars($s['no_hp_ortu'] ?? '-') ?></td>
          <td style="font-size:12px"><?= htmlspecialchars($s['email']) ?></td>
          <td>
            <?php if ($s['status'] === 'nonaktif'): ?>
              <?php
                $labelAlasan = $s['alasan_nonaktif'] === 'lulus' ? 'Lulus' : ($s['alasan_nonaktif'] === 'keluar' ? 'Keluar' : 'Nonaktif');
                $badgeClass  = $s['alasan_nonaktif'] === 'lulus' ? 'badge-izin' : 'badge-alfa';
                $title       = $s['keterangan_nonaktif'] ? htmlspecialchars($s['keterangan_nonaktif']) : $labelAlasan;
              ?>
              <span class="badge <?= $badgeClass ?>" title="<?= $title ?>"><?= $labelAlasan ?></span>
            <?php endif; ?>
          </td>
          <td>
            <div class="action-dropdown">
              <button class="action-dropdown-btn" onclick="toggleAksi(this)" aria-haspopup="true" aria-expanded="false" title="Aksi">
                <svg viewBox="0 0 24 24" fill="currentColor" stroke="none"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
              </button>
              <div class="action-dropdown-menu dropdown-aksi">
                <button onclick="editSiswa(<?= $s['id'] ?>)" class="menu-item-info">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  Edit Data
                </button>
                <button onclick="resetPasswordSiswa(<?= $s['id'] ?>, '<?= addslashes($s['nama']) ?>', '<?= $s['nis'] ?>', '<?= $s['tanggal_lahir'] ?? '' ?>')" class="menu-item-warning">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                  Reset Password
                </button>
                <?php if ($s['status'] === 'aktif'): ?>
                <button onclick="bukaModalNonaktif(<?= $s['id'] ?>, '<?= addslashes($s['nama']) ?>')" class="menu-item-warning">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><circle cx="12" cy="12" r="10"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>
                  Nonaktifkan
                </button>
                <?php else: ?>
                <button onclick="aktifkanSiswa(<?= $s['id'] ?>, '<?= addslashes($s['nama']) ?>')" class="menu-item-success">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><circle cx="12" cy="12" r="10"/><path d="M9 11l3 3L22 4"/></svg>
                  Aktifkan Kembali
                </button>
                <?php endif; ?>
                <hr class="action-dropdown-divider">
                <button onclick="konfirmasiHapusSiswa(<?= $s['id'] ?>, '<?= addslashes(htmlspecialchars($s['nama'], ENT_QUOTES)) ?>')" class="menu-item-danger">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>
                  Hapus
                </button>
              </div>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php
  // Bikin daftar nomor halaman yang dipadetin (standar: 1 ... 5 6 [7] 8 9 ... 55)
  function buatRangePaginasi($current, $total, $delta = 1) {
      $range = [];
      foreach (range(1, $total) as $i) {
          if ($i === 1 || $i === $total || ($i >= $current - $delta && $i <= $current + $delta)) {
              $range[] = $i;
          }
      }
      $hasil = [];
      $prev = null;
      foreach ($range as $i) {
          if ($prev !== null) {
              if ($i - $prev === 2) {
                  $hasil[] = $prev + 1;
              } elseif ($i - $prev > 2) {
                  $hasil[] = '...';
              }
          }
          $hasil[] = $i;
          $prev = $i;
      }
      return $hasil;
  }
  ?>
  <?php if ($total > 0): ?>
  <div class="card-body" style="padding-top:12px;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px">
    <?php if ($totalPage > 1):
        $urlDasarHal = '?limit='.$perPage.'&search='.urlencode($search).'&kelas='.$kelasFilter.'&status='.urlencode($statusFilter);
    ?>
    <div class="pagination" style="display:flex;align-items:center;gap:4px;flex-wrap:wrap">
      <?php if ($page > 1): ?>
      <a href="<?=$urlDasarHal?>&page=<?=$page-1?>" class="page-btn" title="Sebelumnya">&laquo;</a>
      <?php endif; ?>

      <?php foreach (buatRangePaginasi($page, $totalPage) as $item): ?>
        <?php if ($item === '...'): ?>
        <span class="page-btn" style="border:none;cursor:default;background:transparent">&hellip;</span>
        <?php else: ?>
        <a href="<?=$urlDasarHal?>&page=<?=$item?>" class="page-btn <?= $item==$page?'active':'' ?>"><?=$item?></a>
        <?php endif; ?>
      <?php endforeach; ?>

      <?php if ($page < $totalPage): ?>
      <a href="<?=$urlDasarHal?>&page=<?=$page+1?>" class="page-btn" title="Berikutnya">&raquo;</a>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div></div>
    <?php endif; ?>

    <div style="display:flex;align-items:center;gap:8px;font-size:13px;color:#555">
      <label for="limit-siswa">Tampilkan</label>
      <select id="limit-siswa" onchange="gantiLimitSiswa(this.value)" style="padding:4px 8px;border:1px solid #d1d5db;border-radius:6px">
        <?php foreach ($opsiPerPage as $opsi): ?>
        <option value="<?=$opsi?>" <?= $perPage==$opsi?'selected':'' ?>><?=$opsi?></option>
        <?php endforeach; ?>
      </select>
      <span>data / halaman</span>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
function gantiLimitSiswa(limit) {
  var url = new URL(window.location.href);
  url.searchParams.set('limit', limit);
  url.searchParams.set('page', '1');
  window.location.href = url.toString();
}
</script>

<!-- Form tersembunyi untuk aksi Hapus Siswa via POST (CSRF-safe) -->
<form id="form-hapus-siswa" method="POST" style="display:none">
  <?= csrfField() ?>
  <input type="hidden" name="hapus_siswa" value="1">
  <input type="hidden" name="hapus_id" id="hapus-siswa-id" value="">
</form>

<!-- Form tersembunyi untuk Toggle Status Siswa (POST + CSRF) -->
<form id="form-toggle-siswa" method="POST" style="display:none">
  <?= csrfField() ?>
  <input type="hidden" name="toggle_siswa" value="1">
  <input type="hidden" name="toggle_id" id="toggle-siswa-id" value="">
  <input type="hidden" name="aksi_toggle" id="aksi-toggle-siswa" value="">
  <input type="hidden" name="alasan_nonaktif" id="alasan-nonaktif-siswa" value="">
  <input type="hidden" name="keterangan_nonaktif" id="keterangan-nonaktif-siswa" value="">
</form>

<!-- ============================================================
     MODAL NONAKTIFKAN SISWA
     ============================================================ -->
<div id="modal-nonaktif-siswa" class="modal-overlay">
  <div class="modal-box" style="max-width:440px">
    <div class="modal-header">
      <div class="modal-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px"><circle cx="12" cy="12" r="10"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>
        Nonaktifkan Siswa
      </div>
      <button class="modal-close" onclick="closeModal('modal-nonaktif-siswa')">&times;</button>
    </div>
    <div class="modal-body">
      <p style="margin-bottom:14px;color:var(--text-secondary)">
        Nonaktifkan: <strong id="nonaktif-nama-siswa"></strong>
      </p>
      <div class="form-group">
        <label class="form-label">Alasan Nonaktif <span style="color:var(--danger)">*</span></label>
        <div style="display:flex;flex-direction:column;gap:10px;margin-top:6px">
          <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;padding:10px 12px;border:1px solid var(--border);border-radius:8px;transition:border-color .15s" id="label-alasan-lulus">
            <input type="radio" name="alasan_ui" value="lulus" style="margin-top:2px;accent-color:var(--primary)" onchange="perbaruiAlasan()">
            <div>
              <div style="font-weight:600;font-size:13px">Lulus / Naik Kelas</div>
              <div style="font-size:12px;color:var(--text-muted)">Masih bisa login, tidak bisa absen</div>
            </div>
          </label>
          <label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;padding:10px 12px;border:1px solid var(--border);border-radius:8px;transition:border-color .15s" id="label-alasan-keluar">
            <input type="radio" name="alasan_ui" value="keluar" style="margin-top:2px;accent-color:var(--primary)" onchange="perbaruiAlasan()">
            <div>
              <div style="font-weight:600;font-size:13px">Keluar / Pindah Sekolah</div>
              <div style="font-size:12px;color:var(--text-muted)">Tidak bisa login sama sekali</div>
            </div>
          </label>
        </div>
      </div>
      <div class="form-group" style="margin-top:12px">
        <label class="form-label">Keterangan <span style="color:var(--text-muted);font-weight:400">(opsional)</span></label>
        <input type="text" id="input-keterangan-nonaktif" class="form-control" placeholder="Contoh: Lulus tahun 2025, pindah ke SMAN 1">
      </div>
    </div>
    <div class="modal-footer" style="display:flex;justify-content:flex-end;gap:8px;padding:14px 20px;border-top:1px solid var(--border-light)">
      <button type="button" class="btn btn-outline" onclick="closeModal('modal-nonaktif-siswa')">Batal</button>
      <button type="button" class="btn btn-danger" id="btn-konfirm-nonaktif" onclick="submitNonaktif()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><circle cx="12" cy="12" r="10"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>
        Nonaktifkan
      </button>
    </div>
  </div>
</div>

<!-- ============================================================
     MODAL TAMBAH / EDIT SISWA
     ============================================================ -->
<div id="modal-tambah-siswa" class="modal-overlay <?= $openEditModal ? 'open' : '' ?>">
  <div class="modal-box" style="max-width:720px">
    <div class="modal-header">
      <div class="modal-title" id="modal-siswa-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Tambah Siswa Baru
      </div>
      <button class="modal-close" onclick="closeModal('modal-tambah-siswa');resetFormSiswa()">&times;</button>
    </div>
    <div class="modal-body">
      <form method="POST" id="form-siswa">
        <?= csrfField() ?>
        <input type="hidden" name="edit_id" id="siswa-edit-id" value="<?= $editData['id'] ?? '' ?>">
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px" class="form-grid3">
          <div class="form-group">
            <label class="form-label">Nama Lengkap *</label>
            <input type="text" name="nama" id="s-nama" class="form-control" value="<?= htmlspecialchars($editData['nama'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Email Login *</label>
            <input type="email" name="email" id="s-email" class="form-control" value="<?= htmlspecialchars($editData['email'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">No. HP Siswa</label>
            <input type="text" name="no_hp" id="s-nohp" class="form-control" value="<?= htmlspecialchars($editData['no_hp'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">NIS *</label>
            <input type="text" name="nis" id="s-nis" class="form-control" value="<?= htmlspecialchars($editData['nis'] ?? '') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">NISN</label>
            <input type="text" name="nisn" id="s-nisn" class="form-control" value="<?= htmlspecialchars($editData['nisn'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Kelas *</label>
            <select name="kelas_id" id="s-kelas" class="form-control" required>
              <option value="">-- Pilih Kelas --</option>
              <?php foreach ($kelasList as $k): ?>
              <option value="<?= $k['id'] ?>" <?= ($editData['kelas_id'] ?? '') == $k['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($k['nama_kelas']) ?> - <?= $k['jurusan'] ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Jenis Kelamin</label>
            <select name="jenis_kelamin" id="s-jk" class="form-control">
              <option value="L" <?= ($editData['jenis_kelamin'] ?? '') === 'L' ? 'selected' : '' ?>>Laki-laki</option>
              <option value="P" <?= ($editData['jenis_kelamin'] ?? '') === 'P' ? 'selected' : '' ?>>Perempuan</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Tanggal Lahir</label>
            <input type="date" name="tanggal_lahir" id="s-tgl" class="form-control" value="<?= $editData['tanggal_lahir'] ?? '' ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Nama Orang Tua</label>
            <input type="text" name="nama_orang_tua" id="s-ortu" class="form-control" value="<?= htmlspecialchars($editData['nama_orang_tua'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">No. HP Orang Tua (WA)</label>
            <input type="text" name="no_hp_ortu" id="s-hportu" class="form-control" value="<?= htmlspecialchars($editData['no_hp_ortu'] ?? '') ?>" placeholder="081234567890">
            <div class="form-hint">Digunakan untuk notifikasi WhatsApp</div>
          </div>
          <div class="form-group" style="grid-column:span 2">
            <label class="form-label">Alamat</label>
            <input type="text" name="alamat" id="s-alamat" class="form-control" value="<?= htmlspecialchars($editData['alamat'] ?? '') ?>">
          </div>
        </div>
        <div id="info-tambah" class="alert alert-info" style="<?= $editData ? 'display:none' : '' ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          Password default siswa baru: <strong>4 digit NIS terakhir@alman</strong> (contoh NIS <em>2324012345</em> → <code>2345@alman</code>)
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:4px">
          <button type="button" id="btn-simpan-siswa" class="btn btn-primary" onclick="konfirmasiSimpanSiswa()">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Simpan
          </button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Modal Reset Password Siswa -->
<div id="modal-reset-pw" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">
  <div style="background:var(--card-bg,#fff);border-radius:12px;padding:24px;width:360px;max-width:90vw;box-shadow:0 8px 32px rgba(0,0,0,.2)">
    <h3 style="margin:0 0 4px;font-size:15px">Reset Password Siswa</h3>
    <p id="reset-pw-nama" style="margin:0 0 16px;font-size:13px;color:var(--text-muted)"></p>
    <div style="background:var(--bg-secondary,#f8f9fa);border-radius:8px;padding:12px;margin-bottom:16px;font-size:13px">
      Password baru: <strong id="reset-pw-preview" style="font-family:monospace"></strong><br>
      <span style="font-size:11px;color:var(--text-muted)">Format: 4 digit NIS terakhir@alman</span>
    </div>
    <div style="display:flex;gap:8px;justify-content:flex-end">
      <button class="btn btn-outline" onclick="document.getElementById('modal-reset-pw').style.display='none'">Batal</button>
      <button id="btn-konfirm-reset" class="btn btn-warning">Reset Password</button>
    </div>
  </div>
</div>

<!-- Modal Import Siswa -->
<div id="modal-import-siswa" class="modal-overlay">
  <div class="modal-box" style="max-width:480px">
    <div class="modal-header">
      <div class="modal-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Impor Data Siswa dari Excel
      </div>
      <button class="modal-close" onclick="closeModal('modal-import-siswa')">&times;</button>
    </div>
    <div class="modal-body">
      <div class="alert alert-info" style="margin-bottom:16px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
        Gunakan template resmi agar data terbaca dengan benar.
      </div>
      <div style="margin-bottom:16px">
        <a href="import/generate_template.php?jenis=siswa" class="btn btn-outline btn-sm" style="width:100%;justify-content:center;margin-bottom:10px">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Unduh Template Excel Siswa
        </a>
      </div>
      <div class="form-group">
        <label class="form-label">Upload File Excel (.xlsx)</label>
        <input type="file" id="file-import-siswa" accept=".xlsx" class="form-control" onchange="previewImportFile(this,'siswa')">
        <div class="form-hint">Maks. 2MB. Hanya file .xlsx dari template yang disediakan.</div>
      </div>
      <div id="import-preview-siswa" style="display:none;background:var(--bg);border-radius:8px;padding:12px;margin-bottom:14px;font-size:13px"></div>
      <div id="import-result-siswa" style="display:none;margin-bottom:14px"></div>
      <div style="display:flex;gap:10px">
        <button class="btn btn-primary" id="btn-proses-import-siswa" onclick="prosesImport('siswa')" disabled>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          Proses Import
        </button>
        <button class="btn btn-outline" onclick="closeModal('modal-import-siswa')">Tutup</button>
      </div>
    </div>
  </div>
</div>


<script>
(function(){
  var STORAGE_KEY = 'siswa_search_raw';
  var _t = null;

  window.autoSearchSiswa = function() {
    clearTimeout(_t);
    var inp = document.getElementById('search-siswa-input');
    // Simpan nilai mentah (termasuk spasi trailing) sebelum submit
    if (inp) sessionStorage.setItem(STORAGE_KEY, inp.value);
    _t = setTimeout(function(){
      document.getElementById('form-filter-siswa').submit();
    }, 400);
  };

  // Setelah reload, restore nilai asli + fokus + kursor tepat di posisi terakhir
  document.addEventListener('DOMContentLoaded', function() {
    var inp = document.getElementById('search-siswa-input');
    if (!inp) return;
    var raw = sessionStorage.getItem(STORAGE_KEY);
    if (raw !== null) {
      inp.value = raw; // restore termasuk spasi trailing
      inp.focus();
      inp.setSelectionRange(raw.length, raw.length);
    }
  });
})();
</script>
<?php
// Data PHP yang dibutuhkan siswa.js di-inject sekali di sini,
// sebelum footer.php me-load siswa.js.
// Semua logika JS ada di assets/js/siswa.js — tidak ada duplikasi.
$extraJs = '<style>@media(max-width:768px){.form-grid3{grid-template-columns:1fr!important}}</style>'
         . '<script>'
         . 'window.SISWA_EDIT_DATA = ' . json_encode($editData ?: null) . ';'
         . 'window.CSRF_TOKEN      = ' . json_encode(csrfToken()) . ';'
         . '</script>'
         . '<script src="' . BASE_URL . 'assets/js/siswa.js?v=' . filemtime(__DIR__ . '/../assets/js/siswa.js') . '"></script>';
require_once '../includes/footer.php';
?>
<script>
function konfirmasiSimpanSiswa() {
  var form = document.getElementById('form-siswa');
  if (!form) return;

  if (!form.checkValidity()) {
    form.reportValidity();
    return;
  }

  var lama = document.getElementById('_kf-simpan-siswa');
  if (lama) lama.remove();

  var overlay = document.createElement('div');
  overlay.id = '_kf-simpan-siswa';
  overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.52);z-index:99999;display:flex;align-items:center;justify-content:center;padding:20px';

  overlay.innerHTML =
    '<div style="background:#fff;border-radius:16px;padding:28px 24px 24px;max-width:380px;width:100%;box-shadow:0 24px 64px rgba(0,0,0,.25);text-align:center">' +
      '<div style="width:56px;height:56px;background:#e8f0fb;border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 14px">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="#1e3a5f" stroke-width="2" style="width:26px;height:26px"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>' +
      '</div>' +
      '<div style="font-weight:800;font-size:16px;color:#1e3a5f;margin-bottom:8px">Konfirmasi Penambahan Data</div>' +
      '<div style="color:#6b7280;font-size:13.5px;line-height:1.55;margin-bottom:22px">Apakah Anda yakin ingin menyimpan data siswa ini? Pastikan seluruh informasi yang dimasukkan sudah benar sebelum melanjutkan.</div>' +
      '<div style="display:flex;gap:10px;justify-content:center">' +
        '<button id="_kf-ss-batal" style="padding:9px 22px;border:1.5px solid #d1d9e0;background:#fff;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;color:#374151">Batal</button>' +
        '<button id="_kf-ss-simpan" style="padding:9px 22px;border:none;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;color:#fff;background:#1e3a5f">Simpan Data</button>' +
      '</div>' +
    '</div>';

  document.body.appendChild(overlay);

  var close = function() { overlay.remove(); };
  overlay.querySelector('#_kf-ss-batal').onclick = close;
  overlay.onclick = function(e) { if (e.target === overlay) close(); };
  overlay.querySelector('#_kf-ss-simpan').onclick = function() {
    close();
    var inp = document.createElement('input');
    inp.type  = 'hidden';
    inp.name  = 'simpan';
    inp.value = '1';
    form.appendChild(inp);
    form.submit();
  };
}
</script>