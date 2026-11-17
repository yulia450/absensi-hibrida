<?php
// ============================================================
// FILE: admin/settings.php - Pengaturan Sistem
// ============================================================
require_once '../includes/config.php';
requireLogin('admin');

$settings = getSettings($pdo);
$msg = ''; $error = '';

// Ringkasan kelas per tingkat, buat preview di kartu Kenaikan Kelas
$ringkasanKelas = [];
$rk = $pdo->query("
    SELECT k.tingkat, COUNT(DISTINCT k.id) AS jumlah_kelas,
           COUNT(DISTINCT CASE WHEN u.status='aktif' THEN s.id END) AS jumlah_siswa
    FROM kelas k
    LEFT JOIN siswa s ON s.kelas_id = k.id
    LEFT JOIN users u ON s.user_id = u.id
    GROUP BY k.tingkat
")->fetchAll();
foreach ($rk as $r) { $ringkasanKelas[$r['tingkat']] = $r; }

// -------------------------------------------------------
// Helper: simpan ke DB (INSERT jika kosong, UPDATE jika ada)
// -------------------------------------------------------
function simpanSettings(PDO $pdo, array $fields): bool
{
    $cek = (int) $pdo->query("SELECT COUNT(*) FROM settings")->fetchColumn();
    if ($cek === 0) {
        $cols = implode(', ', array_keys($fields));
        $phs  = implode(', ', array_fill(0, count($fields), '?'));
        $stmt = $pdo->prepare("INSERT INTO settings ($cols) VALUES ($phs)");
        return $stmt->execute(array_values($fields));
    } else {
        // Ambil id row pertama agar WHERE id=? tepat sasaran
        $id   = (int) $pdo->query("SELECT id FROM settings ORDER BY id ASC LIMIT 1")->fetchColumn();
        $sets = implode(', ', array_map(fn($k) => "$k=?", array_keys($fields)));
        $stmt = $pdo->prepare("UPDATE settings SET $sets WHERE id=?");
        return $stmt->execute([...array_values($fields), $id]);
    }
}

// -------------------------------------------------------
// Proses POST — bedakan action: lokasi vs waktu
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? 'all';

    try {
        if ($action === 'lokasi') {
            // ── Simpan Lokasi & Info Sekolah ──
            $sekolah_nama   = sanitize($_POST['sekolah_nama']);
            $sekolah_alamat = sanitize($_POST['sekolah_alamat']);
            $sekolah_lat    = str_replace(',', '.', trim($_POST['sekolah_lat']));
            $sekolah_lng    = str_replace(',', '.', trim($_POST['sekolah_lng']));
            $radius_meter   = (int) $_POST['radius_meter'];

            if (!is_numeric($sekolah_lat) || !is_numeric($sekolah_lng)) {
                $error = 'Latitude dan Longitude harus berupa angka. Contoh: -6.362100';
            } else {
                simpanSettings($pdo, [
                    'sekolah_nama'   => $sekolah_nama,
                    'sekolah_alamat' => $sekolah_alamat,
                    'sekolah_lat'    => (float) $sekolah_lat,
                    'sekolah_lng'    => (float) $sekolah_lng,
                    'radius_meter'   => $radius_meter,
                    // Pertahankan nilai lama untuk kolom yang tidak di-update
                    'jam_masuk'        => $settings['jam_masuk'],
                    'jam_pulang'       => $settings['jam_pulang'],
                    'batas_terlambat'  => $settings['batas_terlambat'],
                    'whatsapp_api_url' => $settings['whatsapp_api_url'],
                    'whatsapp_api_key' => $settings['whatsapp_api_key'],
                ]);
                $settings = getSettings($pdo);
                auditLog($pdo, 'UBAH_SETTINGS_LOKASI', 'oleh user_id=' . ($_SESSION['user_id'] ?? ''));
                $msg = '✅ Lokasi sekolah berhasil disimpan — '
                     . $settings['sekolah_lat'] . ', ' . $settings['sekolah_lng']
                     . ' | Radius: ' . $settings['radius_meter'] . ' meter';
            }

        } elseif ($action === 'waktu') {
            // ── Simpan Jam Sekolah ──
            $jam_masuk       = $_POST['jam_masuk'];
            $jam_pulang      = $_POST['jam_pulang'];
            $batas_terlambat = $_POST['batas_terlambat'];

            simpanSettings($pdo, [
                'sekolah_nama'     => $settings['sekolah_nama'],
                'sekolah_alamat'   => $settings['sekolah_alamat'],
                'sekolah_lat'      => $settings['sekolah_lat'],
                'sekolah_lng'      => $settings['sekolah_lng'],
                'radius_meter'     => $settings['radius_meter'],
                'jam_masuk'        => $jam_masuk,
                'jam_pulang'       => $jam_pulang,
                'batas_terlambat'  => $batas_terlambat,
                'whatsapp_api_url' => $settings['whatsapp_api_url'],
                'whatsapp_api_key' => $settings['whatsapp_api_key'],
            ]);
            $settings = getSettings($pdo);
            auditLog($pdo, 'UBAH_SETTINGS_WAKTU', 'oleh user_id=' . ($_SESSION['user_id'] ?? ''));
            $msg = '✅ Jam sekolah berhasil disimpan — Masuk: '
                 . $settings['jam_masuk'] . ' | Terlambat: '
                 . $settings['batas_terlambat'] . ' | Pulang: '
                 . $settings['jam_pulang'];

        } elseif ($action === 'whatsapp') {
            // ── Simpan WhatsApp API ──
            $wa_url = sanitize($_POST['whatsapp_api_url']);
            $wa_key = sanitize($_POST['whatsapp_api_key']);

            simpanSettings($pdo, [
                'sekolah_nama'     => $settings['sekolah_nama'],
                'sekolah_alamat'   => $settings['sekolah_alamat'],
                'sekolah_lat'      => $settings['sekolah_lat'],
                'sekolah_lng'      => $settings['sekolah_lng'],
                'radius_meter'     => $settings['radius_meter'],
                'jam_masuk'        => $settings['jam_masuk'],
                'jam_pulang'       => $settings['jam_pulang'],
                'batas_terlambat'  => $settings['batas_terlambat'],
                'whatsapp_api_url' => $wa_url,
                'whatsapp_api_key' => $wa_key,
            ]);
            $settings = getSettings($pdo);
            auditLog($pdo, 'UBAH_SETTINGS_WA', 'oleh user_id=' . ($_SESSION['user_id'] ?? ''));
            $msg = '✅ Konfigurasi WhatsApp berhasil disimpan.';
        } elseif ($action === 'kenaikan_kelas') {
            // ── Proses Kenaikan Kelas Tahunan ──
            // X naik jadi XI, XI naik jadi XII (nama & tingkat di-update DI
            // BARIS KELAS YANG SAMA, bukan pindah siswa ke kelas lain), lalu
            // siswa kelas XII diluluskan. Riwayat absensi lama TIDAK ikut
            // berubah karena sudah "terkunci" lewat nama_kelas_snapshot.
            // Kelas XII yang baru saja lulus SENGAJA TIDAK di-reset/didaur
            // ulang -- tetap sebagai arsip, siswanya jadi data alumni.
            $tahunAjaranBaru = sanitize($_POST['tahun_ajaran_baru'] ?? '');
            $pindahX = 0; $pindahXI = 0; $lulus = 0; $catatanManual = [];

            try {
                $pdo->beginTransaction();

                // 1) Luluskan siswa aktif di kelas tingkat XII (sebelum kelas
                //    XII diapa-apain oleh langkah lain)
                $ket = 'Lulus - kenaikan kelas' . ($tahunAjaranBaru ? " ({$tahunAjaranBaru})" : '');
                $stmtLulus = $pdo->prepare("
                    UPDATE users SET status='nonaktif', alasan_nonaktif='lulus', keterangan_nonaktif=?
                    WHERE status='aktif' AND id IN (
                        SELECT s.user_id FROM siswa s JOIN kelas k ON s.kelas_id = k.id WHERE k.tingkat='XII'
                    )
                ");
                $stmtLulus->execute([$ket]);
                $lulus = $stmtLulus->rowCount();

                // 2) Kelas XI -> XII (update nama & tingkat di baris yang sama)
                //    Dilakukan SEBELUM langkah 3 supaya filter tingkat='X' di
                //    bawah tidak ikut menangkap baris ini.
                $kelasXI = $pdo->query("SELECT * FROM kelas WHERE tingkat='XI'")->fetchAll();
                foreach ($kelasXI as $k) {
                    if (strpos($k['nama_kelas'], 'XI-') === 0) {
                        $namaBaru = 'XII-' . substr($k['nama_kelas'], 3);
                        $pdo->prepare("UPDATE kelas SET nama_kelas=?, tingkat='XII' WHERE id=?")->execute([$namaBaru, $k['id']]);
                        $pindahXI++;
                    } else {
                        $pdo->prepare("UPDATE kelas SET tingkat='XII' WHERE id=?")->execute([$k['id']]);
                        $catatanManual[] = $k['nama_kelas'] . ' (tingkat dinaikkan ke XII, nama tidak diubah otomatis)';
                    }
                }

                // 3) Kelas X -> XI (update nama & tingkat di baris yang sama)
                $kelasX = $pdo->query("SELECT * FROM kelas WHERE tingkat='X'")->fetchAll();
                foreach ($kelasX as $k) {
                    if (strpos($k['nama_kelas'], 'X-') === 0) {
                        $namaBaru = 'XI-' . substr($k['nama_kelas'], 2);
                        $pdo->prepare("UPDATE kelas SET nama_kelas=?, tingkat='XI' WHERE id=?")->execute([$namaBaru, $k['id']]);
                        $pindahX++;
                    } else {
                        $pdo->prepare("UPDATE kelas SET tingkat='XI' WHERE id=?")->execute([$k['id']]);
                        $catatanManual[] = $k['nama_kelas'] . ' (tingkat dinaikkan ke XI, nama tidak diubah otomatis)';
                    }
                }

                $pdo->commit();
                auditLog($pdo, 'KENAIKAN_KELAS', "tahun_ajaran={$tahunAjaranBaru} X->XI={$pindahX} XI->XII={$pindahXI} lulus={$lulus}");
                $msg = "✅ Kenaikan kelas berhasil diproses: <strong>{$pindahX}</strong> kelas X naik ke XI, "
                     . "<strong>{$pindahXI}</strong> kelas XI naik ke XII, dan <strong>{$lulus}</strong> siswa diluluskan (masuk data alumni).";
                if ($catatanManual) {
                    $msg .= '<br><small>Catatan: ' . implode('; ', $catatanManual) . ' — nama kelasnya tidak mengikuti pola "X-..." jadi silakan cek/ubah manual di menu Data Kelas.</small>';
                }
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Gagal memproses kenaikan kelas: ' . $e->getMessage();
            }
        }
    } catch (PDOException $e) {
        $error = 'Gagal menyimpan: ' . $e->getMessage();
    }
}

$pageTitle = 'Pengaturan Sistem';
require_once '../includes/header.php';
?>

<?php if ($msg): ?>
<div class="alert alert-success">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11"/></svg>
  <?= $msg ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
  <?= $error ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px" class="settings-grid">

  <!-- ══════════════════════════════════════════════════════ -->
  <!-- KARTU 1: Info Sekolah + Geolokasi — form sendiri       -->
  <!-- ══════════════════════════════════════════════════════ -->

  <!-- Info Sekolah -->
  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="lokasi">
    <div class="card">
      <div class="card-header">
        <div class="card-title">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          Informasi Sekolah
        </div>
      </div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Nama Sekolah</label>
          <input type="text" name="sekolah_nama" class="form-control" value="<?= htmlspecialchars($settings['sekolah_nama']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Alamat Sekolah</label>
          <textarea name="sekolah_alamat" class="form-control"><?= htmlspecialchars($settings['sekolah_alamat']) ?></textarea>
        </div>
      </div>
    </div>

    <!-- Geolokasi — satu form dengan Info Sekolah -->
    <div class="card mt-3">
      <div class="card-header">
        <div class="card-title">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
          Pengaturan Geolokasi
        </div>
      </div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Latitude Sekolah</label>
          <input type="text" name="sekolah_lat" id="input-lat" class="form-control"
                 value="<?= $settings['sekolah_lat'] ?>" step="0.000001" required>
          <div class="form-hint">Contoh: -6.362100</div>
        </div>
        <div class="form-group">
          <label class="form-label">Longitude Sekolah</label>
          <input type="text" name="sekolah_lng" id="input-lng" class="form-control"
                 value="<?= $settings['sekolah_lng'] ?>" step="0.000001" required>
          <div class="form-hint">Contoh: 106.748100</div>
        </div>
        <div class="form-group">
          <label class="form-label">Radius Absensi (meter)</label>
          <input type="number" name="radius_meter" id="input-radius" class="form-control"
                 value="<?= $settings['radius_meter'] ?>" min="10" max="1000" required>
          <div class="form-hint">Siswa harus berada dalam radius ini. Default: 100 meter</div>
        </div>
        <button type="button" class="btn btn-outline btn-sm" onclick="ambilKoordinatSekarang()">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
          Ambil Koordinat Lokasi Saya Sekarang
        </button>
        <div id="koordinat-status" style="margin-top:8px;font-size:12px;color:var(--text-muted)">
          Klik tombol di atas untuk mengisi koordinat secara otomatis dari GPS browser.
        </div>
      </div>
    </div>

    <div style="margin-top:16px">
      <button type="submit" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        Simpan Lokasi & Info Sekolah
      </button>
    </div>
  </form>

  <!-- Kolom kanan: Jam Sekolah + WhatsApp -->
  <div>

    <!-- ══════════════════════════════════════════════════════ -->
    <!-- KARTU 2: Jam Sekolah — form sendiri                    -->
    <!-- ══════════════════════════════════════════════════════ -->
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="waktu">
      <div class="card">
        <div class="card-header">
          <div class="card-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Pengaturan Jam Sekolah
          </div>
        </div>
        <div class="card-body">
          <div class="form-group">
            <label class="form-label">Jam Masuk Sekolah</label>
            <input type="time" name="jam_masuk" class="form-control" value="<?= $settings['jam_masuk'] ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Batas Keterlambatan</label>
            <input type="time" name="batas_terlambat" class="form-control" value="<?= $settings['batas_terlambat'] ?>" required>
            <div class="form-hint">Absensi setelah jam ini dianggap TERLAMBAT</div>
          </div>
          <div class="form-group">
            <label class="form-label">Jam Pulang Sekolah</label>
            <input type="time" name="jam_pulang" class="form-control" value="<?= $settings['jam_pulang'] ?>" required>
            <div class="form-hint">Setelah jam ini, sistem memproses auto-alfa</div>
          </div>
        </div>
      </div>
      <div style="margin-top:16px">
        <button type="submit" class="btn btn-primary">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
          Simpan Jam Sekolah
        </button>
      </div>
    </form>

    <!-- ══════════════════════════════════════════════════════ -->
    <!-- KARTU 3: WhatsApp API — form sendiri                   -->
    <!-- ══════════════════════════════════════════════════════ -->
    <form method="POST" style="margin-top:20px">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="whatsapp">
      <div class="card">
        <div class="card-header">
          <div class="card-title">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.5a19.79 19.79 0 01-3.07-8.67A2 2 0 012 .84h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L6.09 8.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg>
            Konfigurasi WhatsApp API
          </div>
        </div>
        <div class="card-body">
          <div class="alert alert-info">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            Mendukung <strong>Fonnte</strong>, <strong>WaLib</strong>, atau gateway WA lainnya.
          </div>
          <div class="form-group">
            <label class="form-label">WhatsApp API URL</label>
            <input type="url" name="whatsapp_api_url" class="form-control"
                   value="<?= htmlspecialchars($settings['whatsapp_api_url']) ?>"
                   placeholder="https://api.fonnte.com/send">
            <div class="form-hint">URL endpoint API gateway WhatsApp</div>
          </div>
          <div class="form-group">
            <label class="form-label">API Key / Token</label>
            <input type="text" name="whatsapp_api_key" class="form-control"
                   value="<?= htmlspecialchars($settings['whatsapp_api_key']) ?>"
                   placeholder="Token API WhatsApp Anda">
          </div>
        </div>
      </div>
      <div style="margin-top:16px">
        <button type="submit" class="btn btn-primary">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:15px;height:15px"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.5"/></svg>
          Simpan WhatsApp API
        </button>
      </div>
    </form>

  </div><!-- /kolom kanan -->
</div><!-- /grid -->

<!-- ══════════════════════════════════════════════════════ -->
<!-- KARTU: Kenaikan Kelas Tahunan                          -->
<!-- ══════════════════════════════════════════════════════ -->
<div class="card mt-3">
  <div class="card-header">
    <div class="card-title">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><path d="M12 2l9 4.5-9 4.5-9-4.5L12 2z"/><path d="M3 11v6l9 4.5 9-4.5v-6"/></svg>
      Kenaikan Kelas Tahunan
    </div>
  </div>
  <div class="card-body">
    <div class="alert alert-info" style="align-items:flex-start">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;flex-shrink:0;margin-top:2px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
      <div>
        Kelas <strong>X</strong> akan diubah jadi <strong>XI</strong>, dan kelas <strong>XI</strong> jadi <strong>XII</strong> (nama & tingkatnya di-update langsung, siswa <u>tidak</u> dipindah ke kelas lain). Siswa yang saat ini di kelas <strong>XII</strong> akan diluluskan (jadi data alumni, tidak bisa absen lagi tapi datanya tetap tersimpan). Riwayat absensi lama tidak berubah sama sekali.
      </div>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:16px">
      <?php foreach (['X'=>'Kelas X','XI'=>'Kelas XI','XII'=>'Kelas XII (akan lulus)'] as $t=>$label): ?>
      <div style="flex:1;min-width:140px;background:var(--info-bg);border-radius:10px;padding:12px;text-align:center">
        <div style="font-size:12px;color:var(--text-muted)"><?= $label ?></div>
        <div style="font-size:20px;font-weight:700;color:var(--primary)"><?= (int)($ringkasanKelas[$t]['jumlah_kelas'] ?? 0) ?> kelas</div>
        <div style="font-size:12px;color:var(--text-muted)"><?= (int)($ringkasanKelas[$t]['jumlah_siswa'] ?? 0) ?> siswa aktif</div>
      </div>
      <?php endforeach; ?>
    </div>

    <form method="POST" id="form-kenaikan">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="kenaikan_kelas">
      <div class="form-group">
        <label class="form-label">Tahun Ajaran Baru (opsional, untuk catatan)</label>
        <input type="text" name="tahun_ajaran_baru" class="form-control" placeholder="Contoh: 2026/2027" style="max-width:280px">
      </div>
      <button type="button" id="btn-proses-kenaikan" class="btn btn-primary">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
        Proses Kenaikan Kelas
      </button>
    </form>
  </div>
</div>

<!-- Preview Map -->
<div class="card mt-3">
  <div class="card-header">
    <div class="card-title">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;opacity:.6"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
      Pratinjau Lokasi Sekolah
    </div>
  </div>
  <div class="card-body">
    <div id="map-preview" style="height:300px;background:var(--info-bg);border-radius:10px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:12px;border:1px solid var(--border-light)">
      <div style="width:52px;height:52px;background:var(--primary-pale);border-radius:12px;display:flex;align-items:center;justify-content:center">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="var(--primary)" stroke-width="1.8"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
      </div>
      <div style="font-weight:700;color:var(--primary)" id="preview-coords">
        Koordinat: <?= $settings['sekolah_lat'] ?>, <?= $settings['sekolah_lng'] ?>
      </div>
      <div style="font-size:13px;color:var(--text-muted)" id="preview-radius">
        Radius: <?= $settings['radius_meter'] ?> meter
      </div>
      <div style="display:flex;gap:10px">
        <a id="btn-gmaps"
           href="https://www.google.com/maps?q=<?= $settings['sekolah_lat'] ?>,<?= $settings['sekolah_lng'] ?>"
           target="_blank" class="btn btn-primary btn-sm">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"/><line x1="8" y1="2" x2="8" y2="18"/><line x1="16" y1="6" x2="16" y2="22"/></svg>
          Buka di Google Maps
        </a>
        <a id="btn-gmaps-radius"
           href="https://www.google.com/maps/search/?api=1&query=<?= $settings['sekolah_lat'] ?>,<?= $settings['sekolah_lng'] ?>"
           target="_blank" class="btn btn-outline btn-sm">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:13px;height:13px"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
          Cek Titik Lokasi
        </a>
      </div>
    </div>
  </div>
</div>

<!-- Migration hint -->


<?php
$extraJs = <<<JS
<script>
function updatePreview() {
    const lat    = document.getElementById('input-lat').value;
    const lng    = document.getElementById('input-lng').value;
    const radius = document.getElementById('input-radius').value;
    if (!lat || !lng) return;
    document.getElementById('preview-coords').textContent = 'Koordinat: ' + lat + ', ' + lng;
    document.getElementById('preview-radius').textContent  = 'Radius: ' + radius + ' meter';
    const url = 'https://www.google.com/maps?q=' + lat + ',' + lng;
    document.getElementById('btn-gmaps').href = url;
    document.getElementById('btn-gmaps-radius').href = url;
}

document.getElementById('input-lat').addEventListener('input', updatePreview);
document.getElementById('input-lng').addEventListener('input', updatePreview);
document.getElementById('input-radius').addEventListener('input', updatePreview);

function ambilKoordinatSekarang() {
    const statusEl = document.getElementById('koordinat-status');
    const btn = event.target.closest('button');
    statusEl.textContent = 'Mendeteksi lokasi GPS...';
    statusEl.style.color = 'var(--warning)';
    btn.disabled = true;

    if (!navigator.geolocation) {
        statusEl.textContent = 'Browser tidak mendukung GPS. Coba di Chrome/Firefox.';
        statusEl.style.color = 'var(--danger)';
        btn.disabled = false;
        return;
    }

    navigator.geolocation.getCurrentPosition(
        (pos) => {
            const lat = pos.coords.latitude.toFixed(8);
            const lng = pos.coords.longitude.toFixed(8);
            document.getElementById('input-lat').value = lat;
            document.getElementById('input-lng').value = lng;
            statusEl.innerHTML = 'Koordinat berhasil diambil: <strong>' + lat + ', ' + lng + '</strong>'
                               + ' &mdash; <small style="color:#888">Akurasi: &plusmn;' + Math.round(pos.coords.accuracy) + ' meter</small>';
            statusEl.style.color = 'var(--success)';
            btn.disabled = false;
            updatePreview();
        },
        (err) => {
            const msgs = {
                1: 'Izin lokasi ditolak. Klik ikon gembok di address bar browser lalu izinkan Lokasi.',
                2: 'Lokasi tidak tersedia. Pastikan GPS aktif.',
                3: 'Timeout. Coba lagi.'
            };
            statusEl.textContent = msgs[err.code] || err.message;
            statusEl.style.color = 'var(--danger)';
            btn.disabled = false;
        },
        { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
    );
}
document.getElementById('btn-proses-kenaikan').addEventListener('click', function(){
    konfirmasi('Proses ini akan mengubah nama & tingkat kelas X ke XI dan XI ke XII, serta meluluskan siswa kelas XII saat ini (jadi data alumni). Tindakan ini tidak mudah dibatalkan. Lanjutkan?', function(){
        document.getElementById('form-kenaikan').submit();
    });
});
</script>
<style>@media(max-width:768px){.settings-grid{grid-template-columns:1fr!important}}</style>
JS;
require_once '../includes/footer.php';
?>