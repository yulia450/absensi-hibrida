<?php
// ============================================================
// FILE: admin/kelas.php - Manajemen Kelas
// ============================================================
require_once '../includes/config.php';
requireLogin('admin');

$msg = ''; $error = '';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// --- HAPUS KELAS (POST, bukan GET) ---
// Tangkap pesan dari redirect
if (isset($_GET['msg']) && $_GET['msg'] === 'hapus_ok') $msg = 'Kelas berhasil dihapus.';
if (isset($_GET['err']) && $_GET['err'] === 'gagal_hapus') $error = 'Gagal menghapus kelas.';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_kelas'])) {
    // [FIX] Blok hapus terpisah — verifyCsrf hanya dipanggil sekali
    verifyCsrf();
    $hapusId = (int)($_POST['hapus_id'] ?? 0);
    if ($hapusId > 0) {
        try {
            // Lepas kelas_id dari petugas yang memegang kelas ini
            $pdo->prepare("UPDATE users SET kelas_id = NULL WHERE kelas_id = ?")->execute([$hapusId]);
            $pdo->prepare("DELETE FROM kelas WHERE id = ?")->execute([$hapusId]);
            header('Location: kelas.php?msg=hapus_ok');
            exit;
        } catch (Exception $e) {
            header('Location: kelas.php?err=gagal_hapus');
            exit;
        }
    }
    $action = 'list';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // [FIX] elseif agar tidak masuk blok ini setelah hapus_kelas (hindari double verifyCsrf)
    verifyCsrf();
    $namaKelas  = sanitize($_POST['nama_kelas']);
    $jurusan    = sanitize($_POST['jurusan']);
    // [FIX] Validasi nilai tingkat agar tidak menerima input sembarang
    $tingkat    = in_array($_POST['tingkat'] ?? '', ['X', 'XI', 'XII']) ? $_POST['tingkat'] : 'X';
    $waliKelas  = (int)$_POST['wali_kelas_id'] ?: null;
    $editId     = (int)($_POST['edit_id'] ?? 0);

    if (!$editId) {
        $pdo->prepare("INSERT INTO kelas (nama_kelas, jurusan, tingkat, wali_kelas_id) VALUES (?,?,?,?)")
            ->execute([$namaKelas, $jurusan, $tingkat, $waliKelas]);
        $newKelasId = (int)$pdo->lastInsertId();
        // Sync: set kelas_id di users untuk wali kelas baru
        if ($waliKelas) {
            // Lepas kelas lama petugas ini dulu (jika sebelumnya pegang kelas lain)
            $pdo->prepare("UPDATE users SET kelas_id = NULL WHERE kelas_id = ? AND id != ?")->execute([$newKelasId, $waliKelas]);
            // Lepas wali lama dari kelas lain yang mungkin punya petugas ini
            $pdo->prepare("UPDATE kelas SET wali_kelas_id = NULL WHERE wali_kelas_id = ? AND id != ?")->execute([$waliKelas, $newKelasId]);
            $pdo->prepare("UPDATE users SET kelas_id = ? WHERE id = ?")->execute([$newKelasId, $waliKelas]);
        }
        $msg = "Kelas <strong>{$namaKelas}</strong> berhasil ditambahkan.";
    } else {
        // Ambil wali kelas lama sebelum diupdate
        $waliLama = (int)$pdo->query("SELECT wali_kelas_id FROM kelas WHERE id = $editId")->fetchColumn();

        $pdo->prepare("UPDATE kelas SET nama_kelas=?, jurusan=?, tingkat=?, wali_kelas_id=? WHERE id=?")
            ->execute([$namaKelas, $jurusan, $tingkat, $waliKelas, $editId]);

        // Sync dua arah ke tabel users
        // 1. Lepas kelas dari wali lama (jika wali berubah)
        if ($waliLama && $waliLama !== $waliKelas) {
            $pdo->prepare("UPDATE users SET kelas_id = NULL WHERE id = ?")->execute([$waliLama]);
        }
        // 2. Set kelas_id ke wali baru
        if ($waliKelas) {
            // Lepas wali baru dari kelas lain yang mungkin sudah dia pegang
            $pdo->prepare("UPDATE kelas SET wali_kelas_id = NULL WHERE wali_kelas_id = ? AND id != ?")->execute([$waliKelas, $editId]);
            $pdo->prepare("UPDATE users SET kelas_id = ? WHERE id = ?")->execute([$editId, $waliKelas]);
        } else {
            // Wali dikosongkan — clear kelas_id petugas yang sebelumnya pegang kelas ini
            if ($waliLama) {
                $pdo->prepare("UPDATE users SET kelas_id = NULL WHERE id = ? AND kelas_id = ?")->execute([$waliLama, $editId]);
            }
        }

        $msg = 'Kelas berhasil diperbarui.';
    }
    $action = 'list';
}

$editData = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM kelas WHERE id = ?");
    $stmt->execute([$id]);
    $editData = $stmt->fetch();
}

$petugasList = $pdo->query("SELECT id, nama FROM users WHERE role IN ('petugas','admin') AND status='aktif' ORDER BY nama")->fetchAll();

$stmtKelas = $pdo->query("
    SELECT k.*, u.nama as wali_nama,
    (SELECT COUNT(*) FROM siswa s2 WHERE s2.kelas_id = k.id) as jumlah_siswa
    FROM kelas k LEFT JOIN users u ON k.wali_kelas_id = u.id
    ORDER BY k.tingkat, k.nama_kelas
");
$kelasList = $stmtKelas->fetchAll();

$pageTitle = 'Manajemen Kelas';
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

<div style="display:grid;grid-template-columns:1fr 2fr;gap:12px" class="kelas-grid">
  
  <!-- Form -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <?php if ($editData): ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Edit Kelas
        <?php else: ?>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Tambah Kelas
        <?php endif; ?>
      </div>
      <?php if ($editData): ?>
      <a href="kelas.php" class="btn btn-sm btn-outline">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
        Batal
      </a>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="edit_id" value="<?= $editData['id'] ?? '' ?>">
        <div class="form-group">
          <label class="form-label">Nama Kelas *</label>
          <input type="text" name="nama_kelas" class="form-control" value="<?= htmlspecialchars($editData['nama_kelas'] ?? '') ?>" placeholder="Contoh: X-TKJ-1" required>
        </div>
        <div class="form-group">
          <label class="form-label">Tingkat *</label>
          <select name="tingkat" class="form-control" required>
            <?php foreach (['X','XI','XII'] as $t): ?>
            <option value="<?=$t?>" <?= ($editData['tingkat']??'') === $t ? 'selected' : '' ?>><?=$t?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Jurusan</label>
          <input type="text" name="jurusan" class="form-control" value="<?= htmlspecialchars($editData['jurusan'] ?? '') ?>" placeholder="Teknik Komputer dan Jaringan">
        </div>
        <div class="form-group">
          <label class="form-label">Wali Kelas</label>
          <select name="wali_kelas_id" class="form-control">
            <option value="">-- Pilih Wali Kelas --</option>
            <?php foreach ($petugasList as $p): ?>
            <option value="<?=$p['id']?>" <?= ($editData['wali_kelas_id']??'') == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nama']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn btn-primary btn-block">
          <?php if ($editData): ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          Perbarui
          <?php else: ?>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
          Tambah Kelas
          <?php endif; ?>
        </button>
      </form>
    </div>
  </div>

  <!-- Daftar Kelas -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Daftar Kelas (<?= count($kelasList) ?>)
      </div>
      <button class="btn btn-sm btn-info" onclick="openModal('modal-import-kelas')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Impor Excel
      </button>
    </div>
    <div class="table-responsive">
      <table>
        <thead><tr><th>#</th><th>Nama Kelas</th><th>Tingkat</th><th>Jurusan</th><th>Wali Kelas</th><th>Siswa</th><th>Aksi</th></tr></thead>
        <tbody>
          <?php if (empty($kelasList)): ?>
          <tr><td colspan="7" class="text-center text-muted" style="padding:24px">Belum ada kelas</td></tr>
          <?php else: ?>
          <?php foreach ($kelasList as $i => $k): ?>
          <tr>
            <td><?=$i+1?></td>
            <td><strong><?= htmlspecialchars($k['nama_kelas']) ?></strong></td>
            <td><span class="badge badge-hadir"><?=$k['tingkat']?></span></td>
            <td style="font-size:13px"><?= htmlspecialchars($k['jurusan'] ?? '-') ?></td>
            <td><?= htmlspecialchars($k['wali_nama'] ?? '-') ?></td>
            <td><strong><?=$k['jumlah_siswa']?></strong> siswa</td>
            <td>
              <div class="action-dropdown">
                <button class="action-dropdown-btn" onclick="toggleActionDropdown(this)" aria-haspopup="true" aria-expanded="false" title="Aksi">
                    <svg viewBox="0 0 24 24" fill="currentColor" stroke="none"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
                  </button>
                <div class="action-dropdown-menu">
                  <a href="kelas.php?action=edit&id=<?=$k['id']?>" class="menu-item-info">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Edit Kelas
                  </a>
                  <hr class="action-dropdown-divider">
                  <button onclick="closeAllDropdowns();konfirmasiHapusKelas(<?=$k['id']?>, '<?= addslashes(htmlspecialchars($k['nama_kelas'], ENT_QUOTES)) ?>')" class="menu-item-danger">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/></svg>
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
  </div>
</div>


<!-- Modal Import Kelas -->
<div id="modal-import-kelas" class="modal-overlay">
  <div style="background:#fff;border-radius:16px;padding:28px;max-width:460px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.3)">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px">
      <h3 style="font-size:16px;font-weight:800;color:var(--primary);display:flex;align-items:center;gap:8px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Impor Data Kelas dari Excel
      </h3>
      <button onclick="closeModal('modal-import-kelas')" style="background:none;border:none;cursor:pointer;color:var(--text-muted);font-size:20px;padding:0;line-height:1">&times;</button>
    </div>
    <div style="margin-bottom:16px">
      <a href="import/generate_template.php?jenis=kelas" class="btn btn-outline btn-sm" style="width:100%;justify-content:center;margin-bottom:10px">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Unduh Template Excel Kelas
      </a>
    </div>
    <div class="form-group">
      <label class="form-label">Upload File Excel (.xlsx)</label>
      <input type="file" id="file-import-kelas" accept=".xlsx" class="form-control" onchange="previewImportFile(this,'kelas')">
    </div>
    <div id="import-preview-kelas" style="display:none;background:var(--bg);border-radius:8px;padding:12px;margin-bottom:14px;font-size:13px"></div>
    <div id="import-result-kelas" style="display:none;margin-bottom:14px"></div>
    <div style="display:flex;gap:10px">
      <button class="btn btn-primary" id="btn-proses-import-kelas" onclick="prosesImport('kelas')" disabled>
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Proses Import
      </button>
      <button class="btn btn-outline" onclick="closeModal('modal-import-kelas')">Tutup</button>
    </div>
  </div>
</div>
<!-- Form tersembunyi untuk hapus kelas via POST + CSRF -->
<form id="form-hapus-kelas" method="POST" style="display:none">
  <?= csrfField() ?>
  <input type="hidden" name="hapus_kelas" value="1">
  <input type="hidden" name="hapus_id" id="hapus-kelas-id" value="">
</form>

<?php
$csrfTokenKelas = json_encode(csrfToken());
$extraJs = <<<JS
<style>@media(max-width:768px){.kelas-grid{grid-template-columns:1fr!important}}</style>
<script>
window.CSRF_TOKEN_KELAS = {$csrfTokenKelas};

if (typeof closeAllDropdowns === "undefined") {
  function closeAllDropdowns() {
    document.querySelectorAll(".action-dropdown-menu.open").forEach(function(m){ m.classList.remove("open"); });
  }
}

function konfirmasiHapusKelas(id, nama) {
  konfirmasi("Hapus kelas " + nama + "? Tindakan ini tidak dapat dibatalkan.", function() {
    document.getElementById("hapus-kelas-id").value = id;
    document.getElementById("form-hapus-kelas").submit();
  });
}

function previewImportFile(input, jenis) {
  const file = input.files[0];
  const btn  = document.getElementById('btn-proses-import-' + jenis);
  const prev = document.getElementById('import-preview-' + jenis);
  if (!file) { btn.disabled = true; prev.style.display='none'; return; }
  prev.style.display = 'block';
  prev.innerHTML = '<strong>File:</strong> ' + file.name + ' (' + (file.size/1024).toFixed(1) + ' KB)';
  btn.disabled = false;
}
function prosesImport(jenis) {
  const input = document.getElementById('file-import-' + jenis);
  const result = document.getElementById('import-result-' + jenis);
  const btn = document.getElementById('btn-proses-import-' + jenis);
  if (!input.files[0]) return;
  btn.disabled = true; btn.textContent = 'Memproses...';
  const fd = new FormData();
  fd.append('jenis', jenis);
  fd.append('file_import', input.files[0]);
  fetch('import/proses_import.php', { method:'POST', body:fd, credentials:'include' })
    .then(r => r.json())
    .then(data => {
      result.style.display = 'block';
      const cls = data.status==='success'?'alert-success':(data.status==='partial'?'alert-warning':'alert-danger');
      let html = '<div class="alert '+cls+'">'+data.message+'</div>';
      if (data.errors && data.errors.length) {
        html += '<div style="border:1px solid #f5b8b8;background:var(--danger-bg);border-radius:var(--radius-sm);padding:10px 12px;margin-top:-4px;margin-bottom:13px">';
        html += '<div style="font-size:12px;font-weight:700;color:var(--danger);margin-bottom:6px">Gagal Impor (' + data.errors.length + ')</div>';
        html += '<div style="max-height:150px;overflow-y:auto;display:flex;flex-direction:column;gap:5px">';
        data.errors.forEach(function(err){
          html += '<div style="font-size:12.5px;color:var(--danger);background:#fff;border-radius:6px;padding:6px 9px;line-height:1.4">' + err + '</div>';
        });
        html += '</div></div>';
      }
      result.innerHTML = html;
      btn.disabled = false; btn.textContent = 'Proses Import';
      if (data.status === 'success') setTimeout(() => location.reload(), 1800);
    })
    .catch(() => { result.style.display='block'; result.innerHTML='<div class="alert alert-danger">Gagal.</div>'; btn.disabled=false; });
}
</script>
JS;
require_once '../includes/footer.php';
?>