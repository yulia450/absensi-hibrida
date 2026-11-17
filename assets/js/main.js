/* ============================================================
   main.js - Sistem Absensi SMK AL-MANSHURIYAH
   ============================================================ */

// ---- Live Clock ----
function updateClock() {
  const el = document.getElementById('live-clock');
  if (!el) return;
  const now = new Date();
  const opts = { weekday:'short', day:'2-digit', month:'short', year:'numeric',
                 hour:'2-digit', minute:'2-digit', second:'2-digit', hour12: false };
  el.textContent = now.toLocaleString('id-ID', opts);
}
setInterval(updateClock, 1000);
updateClock();

// ---- Sidebar Toggle (Mobile) ----
// ---- Sidebar Toggle (Mobile) ----
const menuToggle = document.getElementById('menu-toggle');
const sidebar    = document.getElementById('sidebar');
const overlay    = document.getElementById('sidebar-overlay');

function closeSidebar() {
  sidebar.classList.remove('open');
  overlay.classList.remove('show');
  document.body.style.overflow = '';
}

function openSidebar() {
  sidebar.classList.add('open');
  overlay.classList.add('show');
  document.body.style.overflow = 'hidden';
}

if (menuToggle) {
  menuToggle.addEventListener('click', () => {
    sidebar.classList.contains('open') ? closeSidebar() : openSidebar();
  });
}
if (overlay) {
  overlay.addEventListener('click', closeSidebar);
}

// Reset sidebar tiap kali layar di-resize / rotate biar gak nyangkut
window.addEventListener('resize', () => {
  if (window.innerWidth > 768) closeSidebar();
});

// ---- Loading Overlay ----
function showLoading(msg = 'Memproses...') {
  let el = document.getElementById('loading-overlay');
  if (!el) {
    el = document.createElement('div');
    el.id = 'loading-overlay';
    el.className = 'loading-overlay';
    el.innerHTML = `<div class="spinner"></div><div style="font-weight:700;color:#1a5276">${msg}</div>`;
    document.body.appendChild(el);
  } else {
    el.querySelector('div:last-child').textContent = msg;
  }
  el.classList.add('show');
}
function hideLoading() {
  const el = document.getElementById('loading-overlay');
  if (el) el.classList.remove('show');
}

// ---- Confirm Modal (pengganti confirm() browser bawaan) ----
// Dipakai di semua halaman (admin/petugas/siswa) karena main.js selalu
// dimuat lewat footer.php. Native confirm()/alert() browser nampilin
// "localhost says..." yang kurang formal buat aplikasi sekolah.
function appConfirm(opts) {
  opts = opts || {};
  var lama = document.getElementById('_app-confirm-overlay');
  if (lama) lama.remove();

  var style = document.getElementById('_app-confirm-style');
  if (!style) {
    style = document.createElement('style');
    style.id = '_app-confirm-style';
    style.textContent = '@keyframes _acIn{from{opacity:0}to{opacity:1}}@keyframes _acPop{from{opacity:0;transform:scale(.93)}to{opacity:1;transform:scale(1)}}';
    document.head.appendChild(style);
  }

  var colors = { 'btn-danger': '#e74c3c', 'btn-success': '#27ae60', 'btn-warning': '#f39c12', 'btn-primary': '#1a5276' };
  var btnClass = opts.btnClass || 'btn-primary';
  var accent = colors[btnClass] || colors['btn-primary'];
  var icon = opts.icon || '<svg viewBox="0 0 24 24" fill="none" stroke="' + accent + '" stroke-width="2" style="width:26px;height:26px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';

  var overlay = document.createElement('div');
  overlay.id = '_app-confirm-overlay';
  overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,.52);z-index:99999;display:flex;align-items:center;justify-content:center;padding:20px;animation:_acIn .18s ease';

  overlay.innerHTML =
    '<div style="background:#fff;border-radius:16px;padding:28px 24px 24px;max-width:400px;width:100%;box-shadow:0 24px 64px rgba(0,0,0,.25);animation:_acPop .2s ease;text-align:center;font-family:\'Nunito\',sans-serif">' +
      '<div style="width:56px;height:56px;background:' + accent + '1a;border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 14px">' +
        icon +
      '</div>' +
      '<div style="font-weight:800;font-size:16px;color:#1c2833;margin-bottom:8px">' + (opts.title || 'Konfirmasi') + '</div>' +
      '<div style="color:#6b7280;font-size:13.5px;line-height:1.55;margin-bottom:22px;white-space:pre-line">' + (opts.body || '') + '</div>' +
      '<div style="display:flex;gap:10px;justify-content:center">' +
        '<button id="_ac-batal" style="padding:9px 22px;border:1.5px solid #d1d9e0;background:#fff;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;font-family:inherit;color:#374151">Batal</button>' +
        '<button id="_ac-oke" style="padding:9px 22px;border:none;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer;font-family:inherit;color:#fff;background:' + accent + '">' + (opts.btnLabel || 'Ya, Lanjutkan') + '</button>' +
      '</div>' +
    '</div>';

  document.body.appendChild(overlay);

  var close = function () { overlay.remove(); };
  overlay.querySelector('#_ac-batal').onclick = function () { close(); if (opts.onCancel) opts.onCancel(); };
  overlay.onclick = function (e) { if (e.target === overlay) close(); };
  overlay.querySelector('#_ac-oke').onclick = function () { close(); if (opts.onConfirm) opts.onConfirm(); };
}

// ---- Toast Notification ----
function getToastContainer() {
  let c = document.getElementById('toast-container');
  if (!c) {
    c = document.createElement('div');
    c.id = 'toast-container';
    c.style.cssText = `
      position:fixed; z-index:99999; display:flex; flex-direction:column-reverse; gap:10px;
      bottom:24px; right:24px; left:auto; align-items:flex-end;
      max-width:calc(100vw - 32px); pointer-events:none;
    `;
    document.body.appendChild(c);
  }
  return c;
}

function showToast(msg, type = 'success', duration) {
  const colors = {
    success: '#27ae60', danger: '#e74c3c', warning: '#f39c12', info: '#2980b9'
  };
  const iconsSvg = {
    success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px;color:' + (colors['success']) + '"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>',
    danger:  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px;color:' + (colors['danger']) + '"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>',
    warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px;color:' + (colors['warning']) + '"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    info:    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px;color:' + (colors['info']) + '"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
  };

  // [FIX] Durasi otomatis menyesuaikan panjang pesan, supaya teks yang lumayan
  // panjang (mis. hasil analisis K-Means) tidak keburu hilang sebelum selesai dibaca.
  if (!duration) {
    duration = Math.min(9000, Math.max(4200, msg.length * 90));
  }

  const container = getToastContainer();

  const toast = document.createElement('div');
  toast.style.cssText = `
    position:relative; width:320px; max-width:calc(100vw - 32px);
    background:#fff; border-left:4px solid ${colors[type]||'#333'};
    border-radius:10px; padding:14px 20px;
    box-shadow:0 8px 32px rgba(0,0,0,.18); display:flex; align-items:flex-start;
    gap:10px; animation:slideInRight .3s ease; font-family:'Nunito',sans-serif;
    pointer-events:auto;
  `;
  toast.innerHTML = `
    <span style="display:flex;align-items:center;flex-shrink:0">${iconsSvg[type]||iconsSvg.info}</span>
    <div style="flex:1">
      <div style="font-weight:700;font-size:13px;color:#1c2833">${msg}</div>
    </div>
    <button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;font-size:16px;color:#7f8c8d;padding:0;line-height:1">×</button>
  `;

  const style = document.createElement('style');
  style.textContent = '@keyframes slideInRight{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:translateX(0)}}@media(max-width:480px){#toast-container{left:16px!important;right:16px!important;align-items:stretch!important}#toast-container>div{width:100%!important}}';
  if (!document.querySelector('#toast-style')) {
    style.id = 'toast-style';
    document.head.appendChild(style);
  }

  container.appendChild(toast);

  let remaining = duration;
  let startTime = Date.now();
  let timer = null;

  function removeToast() {
    toast.style.animation = 'slideInRight .3s ease reverse';
    setTimeout(() => toast.remove(), 300);
  }
  function startTimer(ms) {
    startTime = Date.now();
    timer = setTimeout(removeToast, ms);
  }
  function pauseTimer() {
    if (!timer) return;
    clearTimeout(timer);
    timer = null;
    remaining -= (Date.now() - startTime);
  }
  function resumeTimer() {
    if (timer || remaining <= 0) return;
    startTimer(remaining);
  }

  // [FIX] Jeda hitung mundur saat pesan sedang dibaca/disentuh, supaya
  // pengguna yang belum selesai membaca tidak kehilangan pesannya.
  toast.addEventListener('mouseenter', pauseTimer);
  toast.addEventListener('mouseleave', resumeTimer);
  toast.addEventListener('touchstart', pauseTimer, { passive: true });
  toast.addEventListener('touchend', resumeTimer, { passive: true });

  startTimer(remaining);
}

// ---- Confirm Dialog ----
function konfirmasi(msg, callback) {
  const modal = document.createElement('div');
  modal.style.cssText = `
    position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99998;
    display:flex;align-items:center;justify-content:center;padding:20px;
  `;
  modal.innerHTML = `
    <div style="background:#fff;border-radius:16px;padding:28px;max-width:380px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.3);text-align:center">
      <div style="width:52px;height:52px;background:#fce8e8;border-radius:12px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#b91c1c" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      </div>
      <div style="font-weight:800;font-size:16px;color:#1c2833;margin-bottom:8px">Konfirmasi</div>
      <div style="color:#7f8c8d;font-size:14px;margin-bottom:24px">${msg}</div>
      <div style="display:flex;gap:10px;justify-content:center">
        <button id="btn-no" style="padding:10px 24px;border:2px solid #d5dbe3;background:#fff;border-radius:8px;font-weight:700;cursor:pointer;font-size:14px">Batal</button>
        <button id="btn-yes" style="padding:10px 24px;background:#e74c3c;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;font-size:14px">Ya, Lanjutkan</button>
      </div>
    </div>
  `;
  document.body.appendChild(modal);
  modal.querySelector('#btn-no').onclick = () => modal.remove();
  modal.querySelector('#btn-yes').onclick = () => { modal.remove(); callback(); };
  modal.onclick = (e) => { if (e.target === modal) modal.remove(); };
}

// ---- Modal Peringatan: Di Luar Radius Sekolah ----
function showRadiusModal(jarak, radius) {
  // Hindari duplikat modal jika sempat terpanggil dua kali
  const existing = document.getElementById('modal-radius-ditolak');
  if (existing) existing.remove();

  const modal = document.createElement('div');
  modal.id = 'modal-radius-ditolak';
  modal.style.cssText = `
    position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:99998;
    display:flex;align-items:center;justify-content:center;padding:20px;
  `;
  modal.innerHTML = `
    <div style="background:#fff;border-radius:16px;padding:28px;max-width:380px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.3);text-align:center">
      <div style="width:56px;height:56px;background:#fef3e2;border-radius:14px;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="1.8">
          <path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
          <line x1="12" y1="9" x2="12" y2="13"/>
          <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
      </div>
      <div style="font-weight:800;font-size:17px;color:#1c2833;margin-bottom:8px">Absensi Ditolak</div>
      <div style="color:#7f8c8d;font-size:14px;margin-bottom:18px;line-height:1.5">
        Anda berada di luar radius sekolah sehingga absensi tidak dapat dilakukan.
      </div>
      <div style="background:#f8f9fb;border-radius:10px;padding:14px;margin-bottom:22px;text-align:left">
        <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px;padding:4px 0">
          <span style="color:#7f8c8d">Radius maksimal</span>
          <span style="font-weight:700;color:#1c2833;font-family:'Courier New',monospace">${radius} meter</span>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;font-size:13px;padding:4px 0">
          <span style="color:#7f8c8d">Jarak Anda</span>
          <span style="font-weight:700;color:#e74c3c;font-family:'Courier New',monospace">${jarak} meter</span>
        </div>
      </div>
      <button id="btn-radius-kembali" style="padding:11px 32px;background:#1c2833;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;font-size:14px;width:100%">Kembali</button>
    </div>
  `;
  document.body.appendChild(modal);
  const closeModal = () => modal.remove();
  modal.querySelector('#btn-radius-kembali').onclick = closeModal;
  modal.onclick = (e) => { if (e.target === modal) closeModal(); };
}

// ---- Geolokasi Absensi ----
let userLat = null, userLng = null;

function ambilLokasi(callback) {
  const statusEl = document.getElementById('geo-status');
  const coordsEl = document.getElementById('geo-coords');
  
  if (!navigator.geolocation) {
    if (statusEl) {
      statusEl.className = 'geo-status error';
      statusEl.innerHTML = 'Browser tidak mendukung GPS';
    }
    return;
  }
  
  if (statusEl) {
    statusEl.className = 'geo-status detecting';
    statusEl.textContent = 'Mendeteksi lokasi GPS...';
  }
  
  navigator.geolocation.getCurrentPosition(
    (pos) => {
      userLat = pos.coords.latitude;
      userLng = pos.coords.longitude;
      
      if (coordsEl) {
        coordsEl.textContent = `Lat: ${userLat.toFixed(6)}, Lng: ${userLng.toFixed(6)} | Akurasi: ±${Math.round(pos.coords.accuracy)}m`;
      }
      
      if (callback) callback(userLat, userLng);
    },
    (err) => {
      const msgs = {
        1: 'Akses lokasi ditolak. Izinkan GPS di browser.',
        2: 'Lokasi tidak tersedia. Pastikan GPS aktif.',
        3: 'Waktu deteksi habis. Coba lagi.',
      };
      if (statusEl) {
        statusEl.className = 'geo-status error';
        statusEl.textContent = msgs[err.code] || 'Gagal mendapatkan lokasi';
      }
    },
    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
  );
}

function prosesAbsensi(jenis) { // jenis: 'masuk' atau 'pulang'
  const statusEl = document.getElementById('geo-status');
  const btnAbsen = document.getElementById('btn-absen-' + jenis);

  if (statusEl) {
    statusEl.className = 'geo-status detecting';
    statusEl.textContent = 'Mendeteksi lokasi GPS...';
  }
  if (btnAbsen) btnAbsen.disabled = true;

  if (!navigator.geolocation) {
    showToast('Browser tidak mendukung geolokasi', 'danger');
    if (btnAbsen) btnAbsen.disabled = false;
    return;
  }

  navigator.geolocation.getCurrentPosition(
    (pos) => {
      userLat = pos.coords.latitude;
      userLng = pos.coords.longitude;

      const coordsEl = document.getElementById('geo-coords');
      if (coordsEl) {
        coordsEl.textContent = `Lat: ${userLat.toFixed(6)}, Lng: ${userLng.toFixed(6)}`;
      }

      // URL API sudah disuntikkan PHP di header.php — selalu benar
      const apiUrl = window.ABSENSI_API_URL;
      if (!apiUrl) {
        showToast('Konfigurasi URL API tidak ditemukan. Hubungi admin.', 'danger');
        if (btnAbsen) btnAbsen.disabled = false;
        return;
      }
      console.log('[Absensi] POST ke:', apiUrl);

      showLoading('Memproses absensi...');

      fetch(apiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',   // <-- wajib agar session cookie ikut terkirim
        body: JSON.stringify({ jenis, lat: userLat, lng: userLng })
      })
      .then(r => {
        if (!r.ok) throw new Error('HTTP ' + r.status + ' — pastikan XAMPP aktif dan file ada');
        return r.text(); // ambil sebagai text dulu untuk debug
      })
      .then(text => {
        hideLoading();
        if (btnAbsen) btnAbsen.disabled = false;
        let data;
        try {
          data = JSON.parse(text);
        } catch(e) {
          // Response bukan JSON — kemungkinan PHP error
          if (statusEl) {
            statusEl.className = 'geo-status error';
            statusEl.textContent = 'Server mengembalikan response tidak valid. Buka F12 → Console untuk detail.';
          }
          showToast('Server error — lihat F12 Console', 'danger');
          console.error('Response bukan JSON:', text.substring(0, 500));
          return;
        }

        if (data.status === 'success') {
          if (statusEl) {
            statusEl.className = 'geo-status success';
            statusEl.textContent = data.message;
          }
          showToast(data.message, 'success');
          setTimeout(() => location.reload(), 1800);
        } else {
          if (statusEl) {
            statusEl.className = 'geo-status error';
            statusEl.textContent = data.message;
          }
          // Khusus kasus di luar radius sekolah: tampilkan modal peringatan
          if (data.jarak !== undefined && data.radius !== undefined) {
            showRadiusModal(data.jarak, data.radius);
          } else {
            showToast(data.message, 'danger');
          }
          // Tampilkan debug info jika ada
          if (data.debug) console.warn('Debug info:', data.debug);
        }
      })
      .catch(err => {
        hideLoading();
        if (btnAbsen) btnAbsen.disabled = false;
        if (statusEl) {
          statusEl.className = 'geo-status error';
          statusEl.textContent = 'Gagal menghubungi server. Cek F12 → Console.';
        }
        showToast('Error: ' + err.message, 'danger');
        console.error('Fetch error:', err, '| URL:', apiUrl);
      });
    },
    (err) => {
      const msgs = { 1: 'Akses lokasi ditolak', 2: 'Lokasi tidak tersedia', 3: 'Waktu habis' };
      if (statusEl) {
        statusEl.className = 'geo-status error';
        statusEl.textContent = msgs[err.code] || 'Gagal GPS';
      }
      showToast(msgs[err.code] || 'Gagal mendapatkan lokasi', 'danger');
      if (btnAbsen) btnAbsen.disabled = false;
    },
    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
  );
}

// ---- Preview Foto Upload ----
function previewFoto(input) {
  const preview = document.getElementById('preview-foto');
  if (!preview) return;
  const file = input.files[0];
  if (!file) return;
  
  if (file.type.startsWith('image/')) {
    const reader = new FileReader();
    reader.onload = (e) => {
      preview.innerHTML = `<img src="${e.target.result}" style="max-width:100%;max-height:200px;border-radius:8px;border:2px solid #d5dbe3">`;
    };
    reader.readAsDataURL(file);
  } else {
    preview.innerHTML = `<div style="padding:12px;background:#eaf4fd;border-radius:8px;color:#1a5276;font-weight:600;display:flex;align-items:center;gap:8px"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>${file.name}</div>`;
  }
}

// ---- Chart Helpers (untuk Chart.js) ----
function buatChartDonut(canvasId, labels, data, colors) {
  const ctx = document.getElementById(canvasId);
  if (!ctx || typeof Chart === 'undefined') return;
  return new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels,
      datasets: [{ data, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }]
    },
    options: {
      responsive: true,
      plugins: {
        legend: { position: 'bottom', labels: { font: { family: 'Nunito', weight: '700' }, padding: 16 } }
      },
      cutout: '65%'
    }
  });
}

function buatChartBar(canvasId, labels, datasets) {
  const ctx = document.getElementById(canvasId);
  if (!ctx || typeof Chart === 'undefined') return;
  return new Chart(ctx, {
    type: 'bar',
    data: { labels, datasets },
    options: {
      responsive: true,
      plugins: { legend: { labels: { font: { family: 'Nunito', weight: '700' } } } },
      scales: { y: { beginAtZero: true, ticks: { font: { family: 'Nunito' } } } }
    }
  });
}

// ---- Auto dismiss alerts ----
document.querySelectorAll('.alert').forEach(el => {
  setTimeout(() => {
    el.style.transition = 'opacity .5s';
    el.style.opacity = '0';
    setTimeout(() => el.remove(), 500);
  }, 5000);
});

// ============================================================
// ACTION DROPDOWN MENU
// ============================================================
document.addEventListener('click', function(e) {
  // Close all dropdowns if clicking outside
  if (!e.target.closest('.action-dropdown')) {
    document.querySelectorAll('.action-dropdown-menu.open').forEach(m => m.classList.remove('open'));
  }
});

function toggleActionDropdown(btn) {
  const menu = btn.nextElementSibling;
  const isOpen = menu.classList.contains('open');
  // Close all others
  document.querySelectorAll('.action-dropdown-menu.open').forEach(m => m.classList.remove('open'));
  if (!isOpen) {
    const menuW = 170;
    // [FIX] Ukur tinggi menu dulu (di luar layar) sebelum menentukan posisi akhir,
    // supaya perhitungan "buka ke atas" benar-benar akurat, bukan cuma tebakan.
    menu.style.left = '-9999px';
    menu.style.top = '-9999px';
    menu.style.width = menuW + 'px';
    menu.classList.add('open');
    const menuH = menu.offsetHeight;

    const rect = btn.getBoundingClientRect();
    let left = rect.right - menuW;
    if (left < 8) left = 8;
    if (left + menuW > window.innerWidth - 8) left = window.innerWidth - menuW - 8;

    let top = rect.bottom + 4;
    if (top + menuH > window.innerHeight - 8) {
      // Buka ke atas tombol, bukan ke bawah
      top = rect.top - menuH - 4;
      if (top < 8) top = 8;
    }

    menu.style.left = left + 'px';
    menu.style.top = top + 'px';
  }
}

// ============================================================
// MODAL HELPERS
// ============================================================
function openModal(id) {
  const el = document.getElementById(id);
  if (el) { el.classList.add('open'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
  const el = document.getElementById(id);
  if (el) { el.classList.remove('open'); document.body.style.overflow = ''; }
}
// Close modal on overlay click
document.addEventListener('click', function(e) {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
    document.body.style.overflow = '';
  }
});