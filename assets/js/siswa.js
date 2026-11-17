// ============================================================
// assets/js/siswa.js
// JavaScript untuk halaman admin/siswa.php
// Data dari PHP tersedia via window.SISWA_EDIT_DATA dan window.CSRF_TOKEN
// ============================================================

// ── Helper: ambil CSRF token yang di-inject PHP ─────────────
function getCsrfToken() {
  return window.CSRF_TOKEN || '';
}

// ── Auto-search debounce ─────────────────────────────────────
(function () {
  var input  = document.getElementById('search-siswa-input');
  var kelas  = document.getElementById('kelas-filter-siswa');
  var form   = document.getElementById('form-filter-siswa');
  var timer;

  function submitFilter() {
    clearTimeout(timer);
    timer = setTimeout(function () { form.submit(); }, 400);
  }

  if (input) input.addEventListener('input', submitFilter);
  if (kelas) kelas.addEventListener('change', function () { form.submit(); });
})();

// ── Dropdown aksi — pakai toggleActionDropdown dari main.js ──
// main.js sudah handle posisi otomatis (getBoundingClientRect),
// jadi tidak perlu fungsi terpisah di sini.
// Alias tetap disediakan untuk kompatibilitas.
var toggleAksi = function(btn) {
  if (typeof toggleActionDropdown === 'function') toggleActionDropdown(btn);
};

// ── Edit Siswa ───────────────────────────────────────────────
function editSiswa(id) {
  location.href = 'siswa.php?action=edit&id=' + id;
}

// ── Reset Form Siswa (kembali ke mode Tambah) ────────────────
function resetFormSiswa() {
  var fields = ['s-nama','s-email','s-nohp','s-nis','s-nisn','s-tgl','s-ortu','s-hportu','s-alamat'];
  fields.forEach(function (fieldId) {
    var el = document.getElementById(fieldId);
    if (el) el.value = '';
  });
  var editId = document.getElementById('siswa-edit-id');
  if (editId) editId.value = '';
  var jk = document.getElementById('s-jk');
  if (jk) jk.value = 'L';
  var kelas = document.getElementById('s-kelas');
  if (kelas) kelas.value = '';
  var infoTambah = document.getElementById('info-tambah');
  if (infoTambah) infoTambah.style.display = '';
  var btnSimpan = document.getElementById('btn-simpan-siswa');
  if (btnSimpan) btnSimpan.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Tambah Siswa';
  var titleEl = document.getElementById('modal-siswa-title');
  if (titleEl) titleEl.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Tambah Siswa Baru';
}

// Jika PHP mengirim data edit (mode edit), isi modal saat load
(function () {
  var data = window.SISWA_EDIT_DATA;
  if (!data || !data.id) return;

  var titleEl = document.getElementById('modal-siswa-title');
  if (titleEl) titleEl.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg> Edit Data Siswa';
  var btnSimpan = document.getElementById('btn-simpan-siswa');
  if (btnSimpan) btnSimpan.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg> Perbarui Data';
})();

// ── Modal tambah/edit siswa ──────────────────────────────────
var modalSiswa = document.getElementById('modal-tambah-siswa');
if (modalSiswa) {
  modalSiswa.addEventListener('click', function (e) {
    if (e.target === this) {
      if (typeof closeModal === 'function') closeModal('modal-tambah-siswa');
      else this.style.display = 'none';
      resetFormSiswa();
    }
  });
}

// ── Modal import: backdrop klik untuk tutup ──────────────────
var modalImport = document.getElementById('modal-import-siswa');
if (modalImport) {
  modalImport.addEventListener('click', function (e) {
    if (e.target === this) {
      if (typeof closeModal === 'function') closeModal('modal-import-siswa');
      else this.style.display = 'none';
    }
  });
}

// ── Import Excel ─────────────────────────────────────────────
function previewImportFile(input, jenis) {
  var file = input.files[0];
  var btn  = document.getElementById('btn-proses-import-' + jenis);
  var prev = document.getElementById('import-preview-' + jenis);

  if (!file) {
    btn.disabled       = true;
    prev.style.display = 'none';
    return;
  }
  prev.style.display = 'block';
  prev.innerHTML     = '<strong>File dipilih:</strong> ' + file.name
                     + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
  btn.disabled       = false;
  document.getElementById('import-result-' + jenis).style.display = 'none';
}

function prosesImport(jenis) {
  var input  = document.getElementById('file-import-' + jenis);
  var result = document.getElementById('import-result-' + jenis);
  var btn    = document.getElementById('btn-proses-import-' + jenis);
  if (!input.files[0]) return;

  btn.disabled    = true;
  btn.textContent = 'Memproses...';

  var fd = new FormData();
  fd.append('jenis', jenis);
  fd.append('file_import', input.files[0]);
  fd.append('csrf_token', getCsrfToken()); // ← CSRF

  fetch('import/proses_import.php', { method: 'POST', body: fd, credentials: 'include' })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      result.style.display = 'block';
      var cls  = data.status === 'success' ? 'alert-success'
               : data.status === 'partial' ? 'alert-warning'
               : 'alert-danger';
      var html = '<div class="alert ' + cls + '">' + data.message + '</div>';
      if (data.errors && data.errors.length > 0) {
        html += '<div style="font-size:12px;color:var(--danger);margin-top:6px">';
        data.errors.forEach(function (e) { html += '<div>' + e + '</div>'; });
        html += '</div>';
      }
      result.innerHTML = html;
      btn.disabled     = false;
      btn.innerHTML    = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> Proses Import';
      if (data.status === 'success') setTimeout(function () { location.reload(); }, 2000);
    })
    .catch(function () {
      result.style.display = 'block';
      result.innerHTML     = '<div class="alert alert-danger">Gagal menghubungi server.</div>';
      btn.disabled         = false;
    });
}

// ── Reset Password Siswa (single) ────────────────────────────
function resetPasswordSiswa(id, nama, nis, tanggalLahir) {
  var last4  = String(nis).slice(-4);
  var preview = last4 + '@alman';
  document.getElementById('reset-pw-nama').textContent    = nama + ' — NIS: ' + nis;
  document.getElementById('reset-pw-preview').textContent = preview;
  document.getElementById('modal-reset-pw').style.display = 'flex';
  document.getElementById('btn-konfirm-reset').onclick     = function () { kirimReset(id); };
}

// ── Reset semua password ─────────────────────────────────────
function resetSemuaPassword() {
  var triggerBtn = (event && (event.target || event.currentTarget))
                  ? (event.target || event.currentTarget).closest('button') : null;
  showKonfirmasiSiswa({
    icon: '<svg viewBox="0 0 24 24" fill="none" stroke="#92540e" stroke-width="2" style="width:26px;height:26px"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>',
    iconBg: '#fef3e2',
    title: 'Reset Semua Password',
    body: 'Reset password <strong>semua siswa</strong> ke format <code style="background:#f3f4f6;padding:1px 5px;border-radius:4px">4 digit NIS terakhir@alman</code>?<br><span style="font-size:12px;color:#b91c1c">Proses ini tidak bisa dibatalkan.</span>',
    btnLabel: 'Ya, Reset Semua',
    btnClass: 'btn-warning',
    onConfirm: function () {
      if (triggerBtn) { triggerBtn.disabled = true; triggerBtn.textContent = 'Memproses...'; }
      var fd = new FormData();
      fd.append('reset_semua', '1');
      fd.append('csrf_token', getCsrfToken());
      fetch('reset_password_siswa.php', { method: 'POST', body: fd, credentials: 'include' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          if (triggerBtn) {
            triggerBtn.disabled = false;
            triggerBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg> Reset Semua Password Siswa';
          }
          if (typeof showToast === 'function') {
            showToast(data.status === 'success' ? data.message : 'Gagal: ' + data.message,
                      data.status === 'success' ? 'success' : 'danger');
          }
        })
        .catch(function () {
          if (triggerBtn) triggerBtn.disabled = false;
          if (typeof showToast === 'function') showToast('Gagal menghubungi server.', 'danger');
        });
    }
  });
}

// ── Kirim reset password satu siswa ──────────────────────────
function kirimReset(id) {
  var btn = document.getElementById('btn-konfirm-reset');
  if (btn) { btn.disabled = true; btn.textContent = 'Memproses...'; }

  var fd = new FormData();
  fd.append('siswa_id', id);
  fd.append('csrf_token', getCsrfToken()); // ← CSRF

  fetch('reset_password_siswa.php', { method: 'POST', body: fd, credentials: 'include' })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      document.getElementById('modal-reset-pw').style.display = 'none';
      if (typeof showToast === 'function') {
        showToast(data.status === 'success' ? 'Berhasil: ' + data.message : 'Gagal: ' + data.message,
                  data.status === 'success' ? 'success' : 'danger');
      } else {
        alert(data.status === 'success' ? data.message : 'Gagal: ' + data.message);
      }
      if (btn) { btn.disabled = false; btn.textContent = 'Reset Password'; }
    })
    .catch(function () {
      if (typeof showToast === 'function') showToast('Gagal menghubungi server.', 'danger');
      else alert('Gagal menghubungi server.');
      if (btn) { btn.disabled = false; btn.textContent = 'Reset Password'; }
    });
}

// ── Nonaktifkan Siswa ─────────────────────────────────────────
var _nonaktifId = 0;
function bukaModalNonaktif(id, nama) {
  _nonaktifId = id;
  var elNama = document.getElementById('nonaktif-nama-siswa');
  if (elNama) elNama.textContent = nama;
  // Reset pilihan alasan
  document.querySelectorAll('input[name="alasan_ui"]').forEach(function(r){ r.checked = false; });
  var ketEl = document.getElementById('input-keterangan-nonaktif');
  if (ketEl) ketEl.value = '';
  if (typeof openModal === 'function') openModal('modal-nonaktif-siswa');
}

function perbaruiAlasan() {
  // visual feedback — opsional, biarkan kosong jika tidak ada label interaktif
}

function submitNonaktif() {
  var alasan = document.querySelector('input[name="alasan_ui"]:checked');
  if (!alasan) { alert('Pilih alasan nonaktif terlebih dahulu.'); return; }
  var ket = document.getElementById('input-keterangan-nonaktif');
  document.getElementById('toggle-siswa-id').value     = _nonaktifId;
  document.getElementById('aksi-toggle-siswa').value   = 'nonaktif';
  document.getElementById('alasan-nonaktif-siswa').value = alasan.value;
  document.getElementById('keterangan-nonaktif-siswa').value = ket ? ket.value : '';
  document.getElementById('form-toggle-siswa').submit();
}

// ── Aktifkan Kembali Siswa ────────────────────────────────────
function aktifkanSiswa(id, nama) {
  showKonfirmasiSiswa({
    icon: '<svg viewBox="0 0 24 24" fill="none" stroke="#1a7a45" stroke-width="2" style="width:26px;height:26px"><circle cx="12" cy="12" r="10"/><path d="M9 11l3 3L22 4"/></svg>',
    iconBg: '#e6f4ec',
    title: 'Aktifkan Kembali Siswa',
    body: 'Aktifkan kembali akun <strong>' + nama + '</strong>? Siswa akan bisa login dan absen seperti biasa.',
    btnLabel: 'Ya, Aktifkan',
    btnClass: 'btn-success',
    onConfirm: function () {
      document.getElementById('toggle-siswa-id').value      = id;
      document.getElementById('aksi-toggle-siswa').value    = 'aktif';
      document.getElementById('alasan-nonaktif-siswa').value = '';
      document.getElementById('keterangan-nonaktif-siswa').value = '';
      document.getElementById('form-toggle-siswa').submit();
    }
  });
}

// ── Hapus Siswa via POST + CSRF ───────────────────────────────
function konfirmasiHapusSiswa(id, nama) {
  showKonfirmasiSiswa({
    icon: '<svg viewBox="0 0 24 24" fill="none" stroke="#b91c1c" stroke-width="2" style="width:26px;height:26px"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 3h6a1 1 0 011 1v2H8V4a1 1 0 011-1z"/></svg>',
    iconBg: '#fce9e9',
    title: 'Hapus Siswa',
    body: 'Hapus data siswa <strong>' + nama + '</strong>?<br><span style="font-size:12px;color:#b91c1c">Tindakan ini tidak dapat dibatalkan.</span>',
    btnLabel: 'Ya, Hapus',
    btnClass: 'btn-danger',
    onConfirm: function () {
      var idInput = document.getElementById('hapus-siswa-id');
      if (idInput) idInput.value = id;
      var form = document.getElementById('form-hapus-siswa');
      if (form) form.submit();
    }
  });
}

// ── Konfirmasi Simpan Siswa (Tambah / Edit) ───────────────────
function konfirmasiSimpanSiswa() {
  var form = document.getElementById('form-siswa');
  if (!form) return;

  // Validasi HTML5 native terlebih dahulu
  if (!form.checkValidity()) {
    form.reportValidity();
    return;
  }

  showKonfirmasiSiswa({
    icon: '<svg viewBox="0 0 24 24" fill="none" stroke="#1e3a5f" stroke-width="2" style="width:26px;height:26px"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>',
    iconBg: '#e8f0fb',
    title: 'Konfirmasi Penambahan Data',
    body: 'Apakah Anda yakin ingin menyimpan data siswa ini? Pastikan seluruh informasi yang dimasukkan sudah benar sebelum melanjutkan.',
    btnLabel: 'Simpan Data',
    btnClass: 'btn-primary',
    onConfirm: function () {
      // Tambahkan hidden input name="simpan" lalu submit form
      var inp = document.createElement('input');
      inp.type  = 'hidden';
      inp.name  = 'simpan';
      inp.value = '1';
      form.appendChild(inp);
      form.submit();
    }
  });
}

// ── Custom konfirmasi modal (pengganti confirm() browser) ─────
function showKonfirmasiSiswa(opts) {
  // Hapus modal lama jika ada
  var lama = document.getElementById('_modal-konfirmasi-siswa');
  if (lama) lama.remove();

  var overlay = document.createElement('div');
  overlay.id = '_modal-konfirmasi-siswa';
  overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.52);z-index:99999;display:flex;align-items:center;justify-content:center;padding:20px;animation:_kfIn .18s ease';

  var style = document.getElementById('_kf-style');
  if (!style) {
    style = document.createElement('style');
    style.id = '_kf-style';
    style.textContent = '@keyframes _kfIn{from{opacity:0}to{opacity:1}}@keyframes _kfPop{from{opacity:0;transform:scale(.93)}to{opacity:1;transform:scale(1)}}';
    document.head.appendChild(style);
  }

  overlay.innerHTML =
    '<div style="background:#fff;border-radius:16px;padding:28px 24px 24px;max-width:380px;width:100%;box-shadow:0 24px 64px rgba(0,0,0,.25);animation:_kfPop .2s ease;text-align:center">' +
      '<div style="width:56px;height:56px;background:' + opts.iconBg + ';border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 14px">' +
        opts.icon +
      '</div>' +
      '<div style="font-weight:800;font-size:16px;color:#1e3a5f;margin-bottom:8px">' + opts.title + '</div>' +
      '<div style="color:#6b7280;font-size:13.5px;line-height:1.55;margin-bottom:22px">' + opts.body + '</div>' +
      '<div style="display:flex;gap:10px;justify-content:center">' +
        '<button id="_kf-batal" style="padding:9px 22px;border:1.5px solid #d1d9e0;background:#fff;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;font-family:inherit;color:#374151;transition:background .15s">Batal</button>' +
        '<button id="_kf-oke" style="padding:9px 22px;border:none;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;font-family:inherit;color:#fff;transition:opacity .15s">' + opts.btnLabel + '</button>' +
      '</div>' +
    '</div>';

  document.body.appendChild(overlay);

  // Warna tombol konfirmasi
  var btnOke = overlay.querySelector('#_kf-oke');
  var colors = { 'btn-danger': '#b91c1c', 'btn-success': '#1a7a45', 'btn-warning': '#d97706', 'btn-primary': '#1e3a5f' };
  btnOke.style.background = colors[opts.btnClass] || '#1e3a5f';

  var close = function () { overlay.remove(); };
  overlay.querySelector('#_kf-batal').onclick = close;
  overlay.onclick = function (e) { if (e.target === overlay) close(); };
  btnOke.onclick = function () { close(); opts.onConfirm(); };
}