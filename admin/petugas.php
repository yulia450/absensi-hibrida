<?php
// ============================================================
// FILE: admin/petugas.php - Manajemen Data Petugas/Guru
// ============================================================
require_once '../includes/config.php';
requireLogin('admin');

$msg = ''; $error = '';
$action = $_GET['action'] ?? 'list';
$id = (int)($_GET['id'] ?? 0);

// --- TOGGLE STATUS (POST, bukan GET) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_petugas'])) {
    verifyCsrf();
    $toggleId = (int)($_POST['toggle_id'] ?? 0);
    if ($toggleId > 0) {
        $stmt = $pdo->prepare("UPDATE users SET status = IF(status='aktif','nonaktif','aktif') WHERE id=? AND role='petugas'");
        $stmt->execute([$toggleId]);
        $msg = 'Status petugas berhasil diperbarui.';
        auditLog($pdo, 'TOGGLE_PETUGAS', 'user_id='.$toggleId);
    }
    $action = 'list';
}

// --- HAPUS PETUGAS (POST, bukan GET) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hapus_petugas'])) {
    verifyCsrf();
    $hapusId = (int)($_POST['hapus_id'] ?? 0);
    if ($hapusId > 0) {
        try {
            $pdo->prepare("DELETE FROM users WHERE id=? AND role='petugas'")->execute([$hapusId]);
            $msg = 'Petugas berhasil dihapus.';
            auditLog($pdo, 'HAPUS_PETUGAS', 'user_id='.$hapusId);
        } catch (Exception $e) {
            $error = 'Gagal menghapus petugas.';
        }
    }
    $action = 'list';
}

function buildDefaultPasswordPetugas(string $nama): string {
    $parts = explode(' ', trim($nama));
    $namadepan = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $parts[0]));
    return 'alman@' . $namadepan;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['simpan_petugas'])) {
    verifyCsrf();
    $nama   = sanitize($_POST['nama']);
    $email  = sanitize($_POST['email']);
    $noHp   = sanitize($_POST['no_hp']);
    $kelasIdP = (int)($_POST['kelas_id'] ?? 0) ?: null;
    $editId = (int)($_POST['edit_id'] ?? 0);

    if (!$editId) {
        $plainPassword = buildDefaultPasswordPetugas($nama);
        $password = password_hash($plainPassword, PASSWORD_DEFAULT);
        try {
            $pdo->prepare("INSERT INTO users (nama, email, password, role, no_hp, kelas_id) VALUES (?,?,?,'petugas',?,?)")
                ->execute([$nama, $email, $password, $noHp, $kelasIdP]);
            $newUserId = (int)$pdo->lastInsertId();
            // Sync: update wali_kelas_id di tabel kelas
            if ($kelasIdP) {
                // Lepas wali lama dari kelas ini dulu
                $pdo->prepare("UPDATE users SET kelas_id = NULL WHERE kelas_id = ? AND id != ?")->execute([$kelasIdP, $newUserId]);
                $pdo->prepare("UPDATE kelas SET wali_kelas_id = ? WHERE id = ?")->execute([$newUserId, $kelasIdP]);
            }
            $msg = "Petugas <strong>{$nama}</strong> berhasil ditambahkan. Password default: <strong>{$plainPassword}</strong> (catat dan sampaikan ke petugas).";
        } catch (Exception $e) {
            $error = 'Email sudah digunakan.';
        }
    } else {
        $kelasLama = (int)$pdo->query("SELECT kelas_id FROM users WHERE id = $editId")->fetchColumn();

        $pdo->prepare("UPDATE users SET nama=?, email=?, no_hp=?, kelas_id=? WHERE id=? AND role='petugas'")
            ->execute([$nama, $email, $noHp, $kelasIdP, $editId]);

        // Sync dua arah ke tabel kelas
        // 1. Lepas wali_kelas_id dari kelas lama
        if ($kelasLama && $kelasLama !== $kelasIdP) {
            $pdo->prepare("UPDATE kelas SET wali_kelas_id = NULL WHERE id = ? AND wali_kelas_id = ?")->execute([$kelasLama, $editId]);
        }
        // 2. Set wali_kelas_id di kelas baru
        if ($kelasIdP) {
            // Lepas petugas lain yang mungkin sudah jadi wali di kelas ini
            $pdo->prepare("UPDATE users SET kelas_id = NULL WHERE kelas_id = ? AND id != ?")->execute([$kelasIdP, $editId]);
            $pdo->prepare("UPDATE kelas SET wali_kelas_id = ? WHERE id = ?")->execute([$editId, $kelasIdP]);
        } else {
            // Kelas dikosongkan — clear wali_kelas_id di kelas lama
            if ($kelasLama) {
                $pdo->prepare("UPDATE kelas SET wali_kelas_id = NULL WHERE id = ? AND wali_kelas_id = ?")->execute([$kelasLama, $editId]);
            }
        }

        $msg = 'Data petugas berhasil diperbarui.';
    }
    $action = 'list';
}

$editData = null;
if ($action === 'edit' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=? AND role='petugas'");
    $stmt->execute([$id]);
    $editData = $stmt->fetch();
}

$petugasList = $pdo->query("SELECT u.*, k.nama_kelas FROM users u LEFT JOIN kelas k ON u.kelas_id = k.id WHERE u.role='petugas' ORDER BY u.nama")->fetchAll();
$kelasListAdmin = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll();

$pageTitle = 'Manajemen Petugas/Guru';
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

<!-- Tabel Petugas -->
<div class="card">
  <div class="card-header">
    <div class="card-title">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
      Daftar Petugas/Guru (<?= count($petugasList) ?>)
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <button class="btn btn-sm btn-primary" onclick="openModal('modal-tambah-petugas')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Tambah Petugas
      </button>
      <button class="btn btn-sm btn-info" onclick="openModal('modal-import-petugas')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Impor Excel
      </button>
    </div>
  </div>
  <div class="table-responsive">
    <table>
      <thead><tr><th>#</th><th>Nama</th><th>Email</th><th>No. HP</th><th>Kelas</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
        <?php if (empty($petugasList)): ?>
        <tr><td colspan="7" class="text-center text-muted" style="padding:24px">Belum ada petugas</td></tr>
        <?php else: ?>
        <?php foreach ($petugasList as $i => $p): ?>
        <tr>
          <td><?=$i+1?></td>
          <td><strong><?= htmlspecialchars($p['nama']) ?></strong></td>
          <td style="font-size:13px"><?= htmlspecialchars($p['email']) ?></td>
          <td><?= $p['no_hp']??'-' ?></td>
          <td><?= $p['nama_kelas'] ? '<span class="badge" style="background:#eff6ff;color:#1d4ed8">'.$p['nama_kelas'].'</span>' : '<span style="color:#9ca3af;font-size:12px">Belum ditentukan</span>' ?></td>
          <td><?= $p['status']==='aktif' ? '<span class="badge badge-hadir">Aktif</span>' : '<span class="badge badge-alfa">Nonaktif</span>' ?></td>
          <td>
            <div class="action-dropdown">
              <button class="action-dropdown-btn" onclick="toggleActionDropdown(this)" aria-haspopup="true" aria-expanded="false" title="Aksi">
                <svg viewBox="0 0 24 24" fill="currentColor" stroke="none"><circle cx="12" cy="5" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="12" cy="19" r="1.5"/></svg>
              </button>
              <div class="action-dropdown-menu">
                <button onclick="closeAllDropdowns();editPetugas(<?=$p['id']?>, '<?=addslashes($p['nama'])?>', '<?=addslashes($p['email'])?>', '<?=addslashes($p['no_hp']??'')?>', <?=(int)($p['kelas_id']??0)?>)" class="menu-item-info">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                  Edit Data
                </button>
                <button onclick="closeAllDropdowns();resetPasswordPetugas(<?=$p['id']?>, '<?= addslashes($p['nama']) ?>')" class="menu-item-warning">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                  Reset Password
                </button>
                <button onclick="closeAllDropdowns();konfirmasiTogglePetugas(<?=$p['id']?>, '<?=addslashes($p['nama'])?>', '<?=$p['status']?>')" class="<?=$p['status']==='aktif'?'menu-item-warning':'menu-item-success'?>">
                  <?php if ($p['status'] === 'aktif'): ?>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><circle cx="12" cy="12" r="10"/><line x1="9" y1="9" x2="15" y2="15"/><line x1="15" y1="9" x2="9" y2="15"/></svg>
                  Nonaktifkan
                  <?php else: ?>
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><circle cx="12" cy="12" r="10"/><path d="M9 11l3 3L22 4"/></svg>
                  Aktifkan
                  <?php endif; ?>
                </button>
                <hr class="action-dropdown-divider">
                <button onclick="closeAllDropdowns();konfirmasiHapusPetugas(<?=$p['id']?>, '<?=addslashes(htmlspecialchars($p['nama'],ENT_QUOTES))?>')" class="menu-item-danger">
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
</div>

<!-- Form tersembunyi untuk Toggle Status (POST + CSRF) -->
<form id="form-toggle-petugas" method="POST" style="display:none">
  <?= csrfField() ?>
  <input type="hidden" name="toggle_petugas" value="1">
  <input type="hidden" name="toggle_id" id="toggle-petugas-id" value="">
</form>

<!-- Form tersembunyi untuk Hapus Petugas (POST + CSRF) -->
<form id="form-hapus-petugas" method="POST" style="display:none">
  <?= csrfField() ?>
  <input type="hidden" name="hapus_petugas" value="1">
  <input type="hidden" name="hapus_id" id="hapus-petugas-id" value="">
</form>


<!-- ============================================================
     MODAL TAMBAH / EDIT PETUGAS
     ============================================================ -->
<div id="modal-tambah-petugas" class="modal-overlay" <?= $editData ? 'style="display:flex"' : '' ?>>
  <div class="modal-box" style="max-width:440px">
    <div class="modal-header">
      <div class="modal-title" id="modal-petugas-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Tambah Petugas
      </div>
      <button class="modal-close" onclick="closeModal('modal-tambah-petugas');resetFormPetugas()">&times;</button>
    </div>
    <div class="modal-body">
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="simpan_petugas" value="1">
        <input type="hidden" name="edit_id" id="p-edit-id" value="<?= $editData['id'] ?? '' ?>">
        <div class="form-group">
          <label class="form-label">Nama Lengkap *</label>
          <input type="text" name="nama" id="p-nama" class="form-control" value="<?= htmlspecialchars($editData['nama'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Email Login *</label>
          <input type="email" name="email" id="p-email" class="form-control" value="<?= htmlspecialchars($editData['email'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">No. HP</label>
          <input type="text" name="no_hp" id="p-nohp" class="form-control" value="<?= htmlspecialchars($editData['no_hp'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Kelas yang Dipegang (Wali Kelas)</label>
          <select name="kelas_id" id="p-kelasid" class="form-control">
            <option value="">-- Belum Ditentukan --</option>
            <?php foreach ($kelasListAdmin as $kl): ?>
            <option value="<?=$kl['id']?>" <?=isset($editData['kelas_id']) && $editData['kelas_id']==$kl['id']?'selected':''?>><?=$kl['nama_kelas']?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div id="info-petugas-tambah" class="alert alert-info" style="font-size:13px;<?= $editData ? 'display:none' : '' ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px;flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          Password default: <strong>alman@namadepan</strong> (contoh: Ahmad Fauzi → <code>alman@ahmad</code>)
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:4px">
          <button type="button" class="btn btn-outline" onclick="closeModal('modal-tambah-petugas');resetFormPetugas()">Batal</button>
          <button type="submit" class="btn btn-primary" id="btn-simpan-petugas">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Tambah
          </button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Modal Import Petugas -->
<div id="modal-import-petugas" class="modal-overlay">
  <div class="modal-box" style="max-width:460px">
    <div class="modal-header">
      <div class="modal-title">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        Impor Data Petugas/Guru dari Excel
      </div>
      <button class="modal-close" onclick="closeModal('modal-import-petugas')">&times;</button>
    </div>
    <div class="modal-body">
      <div style="margin-bottom:16px">
        <a href="import/generate_template.php?jenis=petugas" class="btn btn-outline btn-sm" style="width:100%;justify-content:center;margin-bottom:10px">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Unduh Template Excel Petugas
        </a>
      </div>
      <div class="form-group">
        <label class="form-label">Upload File Excel (.xlsx)</label>
        <input type="file" id="file-import-petugas" accept=".xlsx" class="form-control" onchange="previewImportFile(this,'petugas')">
      </div>
      <div id="import-preview-petugas" style="display:none;background:var(--bg);border-radius:8px;padding:12px;margin-bottom:14px;font-size:13px"></div>
      <div id="import-result-petugas" style="display:none;margin-bottom:14px"></div>
      <div style="display:flex;gap:10px">
        <button class="btn btn-primary" id="btn-proses-import-petugas" onclick="prosesImportPetugas()" disabled>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
          Proses Import
        </button>
        <button class="btn btn-outline" onclick="closeModal('modal-import-petugas')">Tutup</button>
      </div>
    </div>
  </div>
</div>


<!-- Modal Reset Password Petugas -->
<div id="modal-reset-pw-petugas" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9999;align-items:center;justify-content:center">
  <div style="background:var(--card-bg,#fff);border-radius:12px;padding:24px;width:380px;max-width:90vw;box-shadow:0 8px 32px rgba(0,0,0,.2)">
    <h3 style="margin:0 0 4px;font-size:15px">Reset Password Petugas</h3>
    <p id="reset-pw-petugas-nama" style="margin:0 0 16px;font-size:13px;color:var(--text-muted)"></p>
    <div style="background:var(--bg-secondary,#f8f9fa);border-radius:8px;padding:12px;margin-bottom:16px;font-size:13px">
      Password akan direset ke: <strong id="reset-pw-petugas-preview" style="font-family:monospace"></strong><br>
      <span style="font-size:11px;color:var(--text-muted)">Format: alman@namadepan</span>
    </div>
    <div style="display:flex;gap:8px;justify-content:flex-end">
      <button class="btn btn-outline" onclick="document.getElementById('modal-reset-pw-petugas').style.display='none'">Batal</button>
      <button id="btn-konfirm-reset-petugas" class="btn btn-warning">Reset Password</button>
    </div>
  </div>
</div>

<?php
// Inject data PHP ke JS, lalu semua logika di inline script (tidak pakai heredoc)
$editDataJson = json_encode($editData ?: null);
$csrfToken    = json_encode(csrfToken());
$extraJs = '<script>'
         . 'window.PETUGAS_EDIT_DATA = ' . $editDataJson . ';'
         . 'window.CSRF_TOKEN_PETUGAS = ' . $csrfToken . ';'
         . '</script>'
         . '<script>
// ── Helper CSRF ──────────────────────────────────────────────
function getCsrfTokenPetugas() { return window.CSRF_TOKEN_PETUGAS || ""; }

// ── Isi modal jika mode edit ─────────────────────────────────
(function(){
  var d = window.PETUGAS_EDIT_DATA;
  if (!d || !d.id) return;
  var t = document.getElementById("modal-petugas-title");
  var b = document.getElementById("btn-simpan-petugas");
  if (t) t.innerHTML = \'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Edit Petugas\';
  if (b) b.innerHTML = \'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg> Perbarui\';
})();

// ── Edit Petugas ─────────────────────────────────────────────
function editPetugas(id, nama, email, nohp, kelasId) {
  document.getElementById("p-edit-id").value = id;
  document.getElementById("p-nama").value    = nama;
  document.getElementById("p-email").value   = email;
  document.getElementById("p-nohp").value    = nohp;
  var kelasEl = document.getElementById("p-kelasid");
  if (kelasEl) kelasEl.value = kelasId || "";
  var info = document.getElementById("info-petugas-tambah");
  if (info) info.style.display = "none";
  var t = document.getElementById("modal-petugas-title");
  var b = document.getElementById("btn-simpan-petugas");
  if (t) t.innerHTML = \'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Edit Petugas\';
  if (b) b.innerHTML = \'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg> Perbarui\';
  openModal("modal-tambah-petugas");
}

// ── Reset Form ───────────────────────────────────────────────
function resetFormPetugas() {
  ["p-edit-id","p-nama","p-email","p-nohp"].forEach(function(x){ var e=document.getElementById(x); if(e) e.value=""; });
  var kelasEl = document.getElementById("p-kelasid"); if(kelasEl) kelasEl.value = "";
  var info = document.getElementById("info-petugas-tambah");
  if (info) info.style.display = "";
  var t = document.getElementById("modal-petugas-title");
  var b = document.getElementById("btn-simpan-petugas");
  if (t) t.innerHTML = \'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Tambah Petugas\';
  if (b) b.innerHTML = \'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Tambah\';
}

// ── closeAllDropdowns (fallback jika main.js belum load) ─────
if (typeof closeAllDropdowns === "undefined") {
  function closeAllDropdowns() {
    document.querySelectorAll(".action-dropdown-menu.open").forEach(function(m){ m.classList.remove("open"); });
  }
}

// ── Toggle Status via POST + CSRF ────────────────────────────
function konfirmasiTogglePetugas(id, nama, statusSaat) {
  var pesan = statusSaat === "aktif"
    ? "Nonaktifkan petugas " + nama + "?"
    : "Aktifkan kembali petugas " + nama + "?";
  konfirmasi(pesan, function() {
    document.getElementById("toggle-petugas-id").value = id;
    document.getElementById("form-toggle-petugas").submit();
  });
}

// ── Hapus Petugas via POST + CSRF ────────────────────────────
function konfirmasiHapusPetugas(id, nama) {
  konfirmasi("Hapus petugas " + nama + "? Tindakan ini tidak dapat dibatalkan.", function() {
    document.getElementById("hapus-petugas-id").value = id;
    document.getElementById("form-hapus-petugas").submit();
  });
}

// ── Reset Password Petugas ───────────────────────────────────
function resetPasswordPetugas(id, nama) {
  var namadepan = nama.trim().split(" ")[0].toLowerCase().replace(/[^a-z0-9]/g,"");
  var preview   = "alman@" + namadepan;
  document.getElementById("reset-pw-petugas-nama").textContent    = nama;
  document.getElementById("reset-pw-petugas-preview").textContent = preview;
  document.getElementById("modal-reset-pw-petugas").style.display = "flex";
  document.getElementById("btn-konfirm-reset-petugas").onclick     = function() { kirimResetPetugas(id); };
}

function kirimResetPetugas(id) {
  var btn = document.getElementById("btn-konfirm-reset-petugas");
  if (btn) { btn.disabled = true; btn.textContent = "Memproses..."; }
  var fd = new FormData();
  fd.append("petugas_id", id);
  fd.append("csrf_token", getCsrfTokenPetugas()); // ← CSRF
  fetch("reset_password_petugas.php", { method:"POST", body:fd, credentials:"include" })
    .then(function(r){ return r.json(); })
    .then(function(data){
      document.getElementById("modal-reset-pw-petugas").style.display = "none";
      if (typeof showToast === "function") {
        showToast(data.status==="success" ? "Berhasil: "+data.message : "Gagal: "+data.message,
                  data.status==="success" ? "success" : "danger");
      } else { alert(data.message); }
      if (btn) { btn.disabled = false; btn.textContent = "Reset Password"; }
    })
    .catch(function(){
      if (typeof showToast === "function") showToast("Gagal menghubungi server.", "danger");
      else alert("Gagal menghubungi server.");
      if (btn) { btn.disabled = false; btn.textContent = "Reset Password"; }
    });
}

// ── Import Petugas ───────────────────────────────────────────
function previewImportFile(input, jenis) {
  var file = input.files[0];
  var btn  = document.getElementById("btn-proses-import-" + jenis);
  var prev = document.getElementById("import-preview-" + jenis);
  if (!file) { btn.disabled = true; prev.style.display = "none"; return; }
  prev.style.display = "block";
  prev.innerHTML     = "<strong>File:</strong> " + file.name + " (" + (file.size/1024).toFixed(1) + " KB)";
  btn.disabled       = false;
}

function prosesImportPetugas() {
  var input  = document.getElementById("file-import-petugas");
  var result = document.getElementById("import-result-petugas");
  var btn    = document.getElementById("btn-proses-import-petugas");
  if (!input.files[0]) return;
  btn.disabled = true; btn.textContent = "Memproses...";
  var fd = new FormData();
  fd.append("jenis", "petugas");
  fd.append("file_import", input.files[0]);
  fd.append("csrf_token", getCsrfTokenPetugas()); // ← CSRF
  fetch("import/proses_import.php", { method:"POST", body:fd, credentials:"include" })
    .then(function(r){ return r.json(); })
    .then(function(data){
      result.style.display = "block";
      var cls = data.status==="success"?"alert-success":(data.status==="partial"?"alert-warning":"alert-danger");
      var html = "<div class=\\"alert "+cls+"\\">"+data.message+"</div>";
      if (data.warnings && data.warnings.length) {
        html += "<div class=\\"alert alert-warning\\" style=\\"margin-top:8px;max-height:220px;overflow:auto;font-size:12px\\">";
        html += "<strong>Catatan (" + data.warnings.length + " baris):</strong>";
        html += "<ul style=\\"margin:6px 0 0;padding-left:18px\\">";
        for (var w = 0; w < data.warnings.length; w++) {
          html += "<li>" + data.warnings[w] + "</li>";
        }
        html += "</ul></div>";
      }
      if (data.errors && data.errors.length) {
        html += "<div class=\\"alert alert-danger\\" style=\\"margin-top:8px;max-height:220px;overflow:auto;font-size:12px\\">";
        html += "<strong>Detail error (" + data.errors.length + " baris):</strong>";
        html += "<ul style=\\"margin:6px 0 0;padding-left:18px\\">";
        for (var e = 0; e < data.errors.length; e++) {
          html += "<li>" + data.errors[e] + "</li>";
        }
        html += "</ul></div>";
      }
      result.innerHTML = html;
      btn.disabled = false; btn.textContent = "Proses Import";
      if (data.status === "success") setTimeout(function(){ location.reload(); }, 1800);
    })
    .catch(function(){
      result.style.display="block";
      result.innerHTML="<div class=\\"alert alert-danger\\">Gagal menghubungi server.</div>";
      btn.disabled=false;
    });
}
</script>';
require_once '../includes/footer.php';
?>