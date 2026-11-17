<?php
// ============================================================
// FILE: includes/functions.php
// Fungsi Notifikasi WhatsApp, Auto-Alfa, dll
// ============================================================

require_once __DIR__ . '/config.php';

/**
 * [BARU] Ambil kelas_id + nama_kelas siswa saat ini, dipakai untuk
 * "mengunci" snapshot kelas di setiap baris absensi baru.
 */
function ambilKelasSiswaSaatIni(PDO $pdo, int $siswaId): array {
    $stmt = $pdo->prepare("SELECT s.kelas_id, k.nama_kelas FROM siswa s LEFT JOIN kelas k ON s.kelas_id = k.id WHERE s.id = ?");
    $stmt->execute([$siswaId]);
    $r = $stmt->fetch();
    return [
        'kelas_id'   => $r['kelas_id'] ?? null,
        'nama_kelas' => $r['nama_kelas'] ?? null,
    ];
}

/**
 * Kirim notifikasi WhatsApp via API (Fonnte/WaLib/dll)
 */
function kirimWhatsApp($noHp, $pesan, $apiUrl, $apiKey) {
    // [FIX] Cukup bersihkan karakter non-digit saja. JANGAN ganti '0' di depan
    // jadi '62' secara manual di sini -- itu sudah jadi tugas parameter
    // 'countryCode' => '62' yang dikirim ke Fonnte di bawah. Kalau dua-duanya
    // jalan, nomor berisiko diproses ganda / tidak valid.
    $noHp = preg_replace('/[^0-9]/', '', $noHp);

    if (empty($apiUrl) || empty($apiKey)) {
        return ['status' => false, 'message' => 'API WA belum dikonfigurasi'];
    }

    try {
        $data = [
            'target' => $noHp,
            'message' => $pesan,
            'countryCode' => '62',
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        // [FIX] Fonnte butuh form-data (multipart/x-www-form-urlencoded), BUKAN
        // JSON. Kirim $data sebagai array (bukan json_encode) supaya curl
        // otomatis mengirimnya sebagai multipart/form-data -- sesuai contoh
        // resmi di docs.fonnte.com/api-send-message/.
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: ' . $apiKey,
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response  = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $curlError = curl_error($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErrno !== 0 || $response === false) {
            return ['status' => false, 'message' => 'cURL error (' . $curlErrno . '): ' . $curlError];
        }

        $decoded = json_decode($response, true);

        // [FIX] Fonnte umumnya tetap membalas HTTP 200 walau gagal kirim
        // (nomor invalid, device disconnect, dll), jadi keberhasilan HARUS
        // dicek dari field 'status' di body JSON respons, bukan cuma HTTP code.
        if ($httpCode >= 200 && $httpCode < 300 && is_array($decoded) && !empty($decoded['status'])) {
            return ['status' => true, 'message' => $decoded['reason'] ?? 'Berhasil terkirim'];
        }

        $reason = is_array($decoded) ? ($decoded['reason'] ?? json_encode($decoded)) : $response;
        return ['status' => false, 'message' => "HTTP {$httpCode}: {$reason}"];
    } catch (Throwable $e) {
        // [FIX] Throwable (bukan cuma Exception) supaya TypeError/Error PHP
        // (mis. ekstensi curl belum aktif) ikut tertangkap & ke-log, tidak
        // membuat proses crash diam-diam tanpa jejak.
        error_log('kirimWhatsApp error: ' . $e->getMessage());
        return ['status' => false, 'message' => $e->getMessage()];
    }
}

/**
 * [BARU] Buat teks pesan WhatsApp untuk SATU kejadian absen harian
 * (izin/sakit/alfa/terlambat) - beda dari buatPesanWA() yang isinya
 * rekap satu bulan penuh.
 */
function buatPesanWAHarian(array $siswa, string $tanggal, string $jenis, ?string $waktu = null): string {
    $namaHari  = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][(int)date('w', strtotime($tanggal))];
    $namaBulan = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $tsTanggal = strtotime($tanggal);

    // [BARU] Hari, tanggal, dan jam digabung jadi satu kalimat natural
    // ("hari Selasa, tanggal 18 Agustus 2026 pukul 07:45 WIB") -- supaya
    // langsung jelas dibaca sekali lihat, terutama buat orang tua yang
    // kurang familiar baca format pesan/tabel.
    $tanggalIndo = $namaHari . ', tanggal ' . (int)date('d', $tsTanggal) . ' ' . $namaBulan[(int)date('n', $tsTanggal)] . ' ' . date('Y', $tsTanggal);
    if (!empty($waktu)) {
        $tanggalIndo .= ' pukul ' . date('H:i', strtotime($waktu)) . ' WIB';
    }

    $labelJenis = [
        'hadir'     => 'HADIR',
        'izin'      => 'IZIN',
        'sakit'     => 'SAKIT',
        'alfa'      => 'TANPA KETERANGAN (ALFA)',
        'terlambat' => 'TERLAMBAT',
    ][$jenis] ?? strtoupper($jenis);

    $pesan  = "Assalamu'alaikum Warahmatullahi Wabarakatuh,\n\n";
    $pesan .= "Yang terhormat Bapak/Ibu Orang Tua/Wali dari:\n";
    $pesan .= "Nama  : *{$siswa['nama']}*\n";
    $pesan .= "Kelas : {$siswa['nama_kelas']}\n\n";
    $pesan .= "Kami informasikan bahwa pada hari {$tanggalIndo}, ananda tercatat dengan status:\n\n";
    $pesan .= "*{$labelJenis}*\n";
    $pesan .= "\n";

    if ($jenis === 'hadir') {
        $pesan .= "Ananda telah hadir dan tercatat masuk sekolah tepat waktu. Notifikasi ini kami kirimkan sebagai konfirmasi bahwa ananda sudah sampai di sekolah dengan baik.\n\n";
    } elseif ($jenis === 'alfa') {
        $pesan .= "Ananda tidak hadir tanpa keterangan pada hari tersebut. Mohon Bapak/Ibu dapat mengonfirmasi kondisi ananda kepada pihak sekolah/wali kelas.\n\n";
    } elseif ($jenis === 'terlambat') {
        $pesan .= "Ananda hadir namun terlambat masuk sekolah. Mohon perhatian dan bimbingan Bapak/Ibu agar ananda dapat lebih disiplin dalam kehadiran.\n\n";
    } elseif ($jenis === 'sakit') {
        $pesan .= "Semoga ananda segera pulih dan dapat kembali mengikuti kegiatan belajar seperti biasa.\n\n";
    } else { // izin
        $pesan .= "Terima kasih atas informasi izin yang telah disampaikan.\n\n";
    }

    $pesan .= "Demikian informasi ini kami sampaikan. Atas perhatian dan kerja sama Bapak/Ibu, kami ucapkan terima kasih.\n\n";
    $pesan .= "Wassalamu'alaikum Warahmatullahi Wabarakatuh.\n\n";
    $pesan .= "Hormat kami,\n";
    $pesan .= "*SMK AL-MANSHURIYAH*";

    return $pesan;
}

/**
 * [BARU] Titik panggil TUNGGAL untuk notifikasi WA harian per-kejadian.
 * Panggil fungsi ini setiap kali status_masuk seorang siswa berubah
 * menjadi salah satu dari: izin, sakit, alfa, terlambat.
 *
 * Sengaja TIDAK melempar exception ke pemanggil - kalau WA gagal
 * terkirim (API down, no_hp kosong, dll), proses absensi utama
 * (insert/update tabel absensi) tetap harus jalan normal.
 *
 * @param PDO         $pdo
 * @param int         $siswaId
 * @param string      $tanggal  format Y-m-d
 * @param string      $jenis    'izin' | 'sakit' | 'alfa' | 'terlambat'
 * @param string|null $waktu    format H:i / H:i:s, dikirim kalau caller sudah tahu
 *                              jam pastinya. Kalau null, fungsi ini akan coba
 *                              ambil waktu_masuk dari tabel absensi.
 * @return array ['status' => bool, 'message' => string]
 */
function kirimNotifikasiHarian(PDO $pdo, int $siswaId, string $tanggal, string $jenis, ?string $waktu = null): array {
    // Kelima jenis ini mengirim notifikasi ke ortu:
    // hadir (konfirmasi sampai sekolah), izin, sakit, alfa, terlambat.
    if (!in_array($jenis, ['hadir', 'izin', 'sakit', 'alfa', 'terlambat'], true)) {
        return ['status' => false, 'message' => 'Jenis tidak memerlukan notifikasi'];
    }

    try {
        $settings = getSettings($pdo);

        $stmt = $pdo->prepare("
            SELECT s.id AS siswa_id, u.nama, k.nama_kelas, s.no_hp_ortu, s.nama_orang_tua
            FROM siswa s
            JOIN users u ON s.user_id = u.id
            LEFT JOIN kelas k ON s.kelas_id = k.id
            WHERE s.id = ?
            LIMIT 1
        ");
        $stmt->execute([$siswaId]);
        $siswa = $stmt->fetch();

        if (!$siswa) {
            return ['status' => false, 'message' => 'Data siswa tidak ditemukan'];
        }
        if (empty($siswa['no_hp_ortu'])) {
            return ['status' => false, 'message' => 'No HP orang tua kosong'];
        }

        // [BARU] Kalau jam nggak dikirim langsung sama caller, coba ambil
        // dari waktu_masuk yang sudah tersimpan di tabel absensi. Kalau
        // tetap kosong (mis. jenis izin/sakit/alfa tanpa waktu_masuk),
        // pesan akan dikirim tanpa baris jam -- daripada nampilin jam ngawur.
        if ($waktu === null) {
            $stmtW = $pdo->prepare("SELECT waktu_masuk FROM absensi WHERE siswa_id = ? AND tanggal = ? LIMIT 1");
            $stmtW->execute([$siswaId, $tanggal]);
            $waktu = $stmtW->fetchColumn() ?: null;
        }

        $pesan = buatPesanWAHarian($siswa, $tanggal, $jenis, $waktu);
        $res   = kirimWhatsApp($siswa['no_hp_ortu'], $pesan, $settings['whatsapp_api_url'], $settings['whatsapp_api_key']);
        $status = $res['status'] ? 'terkirim' : 'gagal';

        // [DEBUG] Kalau gagal, catat alasannya (respons Fonnte/cURL error) ke
        // error_log supaya gampang ditelusuri dari log PHP (mis. xampp/php/logs
        // atau log Apache), karena kolom notifikasi_wa tidak menyimpan alasan gagal.
        if (!$res['status']) {
            error_log("kirimNotifikasiHarian gagal (siswa_id={$siswaId}, jenis={$jenis}): " . ($res['message'] ?? 'unknown'));
        }

        // Catat/timpa log per (siswa, tanggal, jenis) - lihat migrasi_notifikasi_wa.sql
        $log = $pdo->prepare("
            INSERT INTO notifikasi_wa (siswa_id, tanggal, jenis, no_hp, pesan, status, bulan, tahun)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                no_hp = VALUES(no_hp), pesan = VALUES(pesan), status = VALUES(status),
                bulan = VALUES(bulan), tahun = VALUES(tahun), created_at = CURRENT_TIMESTAMP
        ");
        $log->execute([
            $siswaId, $tanggal, $jenis, $siswa['no_hp_ortu'], $pesan, $status,
            (int)date('n', strtotime($tanggal)), (int)date('Y', strtotime($tanggal)),
        ]);

        return $res;
    } catch (Throwable $e) {
        // [FIX] Throwable supaya TypeError/Error (fatal) ikut tertangkap &
        // ke-log, bukan cuma Exception biasa.
        error_log('kirimNotifikasiHarian error: ' . $e->getMessage() . ' (file: ' . $e->getFile() . ':' . $e->getLine() . ')');
        return ['status' => false, 'message' => 'Terjadi kesalahan internal saat mengirim notifikasi'];
    }
}

/**
 * Buat teks pesan WhatsApp untuk orang tua
 */
function buatPesanWA($siswa, $bulan, $tahun, $labelCluster) {
    $namaBulanArr = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $namaBulanStr = $namaBulanArr[$bulan];

    $kategori = [
        '1' => 'Baik',
        '2' => 'Cukup / Perlu Perhatian',
        '3' => 'Kurang / Perlu Pendampingan',
    ];
    $labelKategori = $kategori[$siswa['cluster_id']] ?? 'Baik';

    $pesan  = "Assalamu'alaikum Warahmatullahi Wabarakatuh,\n\n";
    $pesan .= "Yang terhormat Bapak/Ibu Orang Tua/Wali dari:\n";
    $pesan .= "Nama  : *{$siswa['nama']}*\n";
    $pesan .= "Kelas : {$siswa['nama_kelas']}\n\n";
    $pesan .= "Dengan hormat, kami sampaikan laporan rekapitulasi kehadiran ananda pada periode *{$namaBulanStr} {$tahun}* sebagai berikut:\n\n";
    $pesan .= "Hadir     : {$siswa['jumlah_hadir']} hari\n";
    $pesan .= "Terlambat : {$siswa['jumlah_terlambat']} hari\n";
    $pesan .= "Izin      : {$siswa['jumlah_izin']} hari\n";
    $pesan .= "Sakit     : {$siswa['jumlah_sakit']} hari\n";
    $pesan .= "Tanpa Keterangan (Alfa) : {$siswa['jumlah_alfa']} hari\n\n";
    $pesan .= "Kategori Kehadiran : *{$labelKategori}*\n\n";

    if ($siswa['cluster_id'] == 3) {
        $pesan .= "Sehubungan dengan jumlah ketidakhadiran tanpa keterangan yang berada di atas rata-rata pada periode ini, kami memohon perhatian dan kerja sama Bapak/Ibu untuk membantu meningkatkan kedisiplinan kehadiran ananda. Apabila diperlukan informasi lebih lanjut, Bapak/Ibu dapat menghubungi wali kelas.\n\n";
    } elseif ($siswa['cluster_id'] == 2) {
        $pesan .= "Kehadiran ananda pada periode ini tergolong cukup baik, namun masih terdapat ruang untuk perbaikan. Kami mengharapkan dukungan dan bimbingan Bapak/Ibu agar kehadiran ananda dapat terus meningkat pada periode berikutnya.\n\n";
    } else {
        $pesan .= "Kami menyampaikan apresiasi atas kehadiran ananda yang baik pada periode ini. Dukungan dan perhatian Bapak/Ibu turut berperan penting dalam menjaga kedisiplinan ananda di sekolah.\n\n";
    }

    $pesan .= "Demikian laporan ini kami sampaikan. Atas perhatian dan kerja sama Bapak/Ibu, kami ucapkan terima kasih.\n\n";
    $pesan .= "Wassalamu'alaikum Warahmatullahi Wabarakatuh.\n\n";
    $pesan .= "Hormat kami,\n";
    $pesan .= "*SMK AL-MANSHURIYAH*";

    return $pesan;
}

/**
 * Cek apakah tanggal tertentu adalah hari libur
 * Mengembalikan array ['libur'=>bool, 'keterangan'=>string, 'jenis'=>string] 
 */
/**
 * Ambil kelas_id petugas yang sedang login.
 * Return int kelas_id, atau 0 jika admin / belum diset.
 */
function getPetugasKelasId(): int {
    if (isset($_SESSION['role']) && $_SESSION['role'] === 'petugas') {
        return (int)($_SESSION['kelas_id'] ?? 0);
    }
    return 0;
}

function isHariLibur(PDO $pdo, $tanggal = null) {
    if (!$tanggal) $tanggal = date('Y-m-d');
    
    // Cek Sabtu (N=6), Minggu (N=7)
    $dow = (int)date('N', strtotime($tanggal));
    if ($dow === 7) return ['libur' => true, 'keterangan' => 'Minggu', 'jenis' => 'mingguan'];
 //   if ($dow === 6) return ['libur' => true, 'keterangan' => 'Sabtu', 'jenis' => 'mingguan'];

    // Cek tabel hari_libur
    try {
        $stmt = $pdo->prepare("SELECT keterangan, jenis FROM hari_libur WHERE tanggal = ? LIMIT 1");
        $stmt->execute([$tanggal]);
        $row = $stmt->fetch();
        if ($row) {
            return ['libur' => true, 'keterangan' => $row['keterangan'], 'jenis' => $row['jenis']];
        }
    } catch (PDOException $e) {
        // Tabel mungkin belum ada, anggap bukan hari libur
    }

    return ['libur' => false, 'keterangan' => '', 'jenis' => ''];
}

/**
 * Proses Auto-Alfa: Tandai siswa yang tidak hadir sebagai Alfa
 * Dijalankan oleh cron job atau dipanggil manual
 */
function prosesAutoAlfa(PDO $pdo, $tanggal = null) {
    if (!$tanggal) $tanggal = date('Y-m-d');
    
    $settings = getSettings($pdo);
    $jamPulang = $settings['jam_pulang'];
    $jamSekarang = date('H:i:s');
    
    // Hanya proses setelah jam pulang
    if ($jamSekarang < $jamPulang) {
        return ['status' => 'info', 'message' => 'Belum waktunya auto alfa (jam pulang: ' . $jamPulang . ')'];
    }
    
    // Cek hari - jangan proses Sabtu (6), Minggu (7)
    // N: 1=Sen, 2=Sel, 3=Rab, 4=Kam, 5=Jum, 6=Sab, 7=Min
    $dayOfWeek = (int)date('N', strtotime($tanggal));
    if ($dayOfWeek >= 7) { //iniiiiiiiii
        return ['status' => 'info', 'message' => 'Hari libur mingguan (Sabtu/Minggu), auto alfa tidak diproses'];
    }

    // Cek hari libur nasional / libur sekolah dari tabel hari_libur
    $stmtLibur = $pdo->prepare("SELECT id, keterangan FROM hari_libur WHERE tanggal = ? LIMIT 1");
    $stmtLibur->execute([$tanggal]);
    $hariLibur = $stmtLibur->fetch();
    if ($hariLibur) {
        return ['status' => 'info', 'message' => 'Hari libur (' . $hariLibur['keterangan'] . '), auto alfa tidak diproses'];
    }
    
    // Ambil semua siswa aktif
    $stmtSiswa = $pdo->query("SELECT s.id FROM siswa s JOIN users u ON s.user_id = u.id WHERE u.status = 'aktif'");
    $siswas = $stmtSiswa->fetchAll();
    
    $count = 0;
    foreach ($siswas as $siswa) {
        // Cek apakah sudah ada record absensi hari ini
        $stmtCek = $pdo->prepare("SELECT id, status_masuk FROM absensi WHERE siswa_id = ? AND tanggal = ?");
        $stmtCek->execute([$siswa['id'], $tanggal]);
        $existing = $stmtCek->fetch();
        
        // Cek apakah ada izin/sakit yang disetujui
        $stmtIzin = $pdo->prepare("
            SELECT id FROM pengajuan_izin 
            WHERE siswa_id = ? AND tanggal = ? AND status = 'disetujui'
        ");
        $stmtIzin->execute([$siswa['id'], $tanggal]);
        $izin = $stmtIzin->fetch();
        
        if ($izin) continue; // Sudah ada izin/sakit disetujui
        
        if (!$existing) {
            // Belum ada record sama sekali = ALFA
            $stmt = $pdo->prepare("
                INSERT INTO absensi (siswa_id, tanggal, status_masuk, status_pulang)
                VALUES (?, ?, 'alfa', 'alfa')
            ");
            $stmt->execute([$siswa['id'], $tanggal]);
            $count++;
            // [WA] Notifikasi real-time ke ortu setiap kejadian Alfa
            kirimNotifikasiHarian($pdo, (int)$siswa['id'], $tanggal, 'alfa');
        } elseif (!$existing['waktu_pulang'] && $existing['status_masuk'] !== 'alfa') {
            // Ada absensi masuk tapi belum pulang
            $stmt = $pdo->prepare("UPDATE absensi SET status_pulang = 'alfa' WHERE siswa_id = ? AND tanggal = ?");
            $stmt->execute([$siswa['id'], $tanggal]);
        }
    }
    
    return ['status' => 'success', 'message' => "Auto alfa diproses: {$count} siswa ditandai alfa"];
}

/**
 * Upload file bukti foto
 */
function uploadBukti($file, $prefix = 'bukti') {
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['status' => false, 'message' => 'Error upload file'];
    }
    
    // [FIX] Gunakan finfo_file untuk cek MIME type asli dari isi file,
    // bukan dari header request yang bisa dipalsukan oleh client
    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $realMime = $finfo->file($file['tmp_name']);
    if (!in_array($realMime, $allowedTypes)) {
        return ['status' => false, 'message' => 'Tipe file tidak diizinkan. Gunakan JPG, PNG, atau PDF'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['status' => false, 'message' => 'Ukuran file maksimal 5MB'];
    }
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $prefix . '_' . time() . '_' . uniqid() . '.' . $ext;
    $destination = UPLOAD_PATH . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return ['status' => true, 'filename' => $filename];
    }
    
    return ['status' => false, 'message' => 'Gagal menyimpan file'];
}

/**
 * Generate laporan absensi bulanan (CSV)
 */
function generateLaporanCSV(PDO $pdo, $bulan, $tahun, $kelasId = null) {
    $params = [$bulan, $tahun];
    $kelasFilter = '';
    
    if ($kelasId) {
        $kelasFilter = 'AND s.kelas_id = ?';
        $params[] = $kelasId;
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            s.nis,
            u.nama,
            k.nama_kelas,
            SUM(CASE WHEN a.status_masuk = 'hadir' THEN 1 ELSE 0 END) as hadir,
            SUM(CASE WHEN a.status_masuk = 'terlambat' THEN 1 ELSE 0 END) as terlambat,
            SUM(CASE WHEN a.status_masuk = 'izin' THEN 1 ELSE 0 END) as izin,
            SUM(CASE WHEN a.status_masuk = 'sakit' THEN 1 ELSE 0 END) as sakit,
            SUM(CASE WHEN a.status_masuk = 'alfa' THEN 1 ELSE 0 END) as alfa
        FROM siswa s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN kelas k ON s.kelas_id = k.id
        LEFT JOIN absensi a ON s.id = a.siswa_id AND MONTH(a.tanggal) = ? AND YEAR(a.tanggal) = ?
        WHERE u.status = 'aktif' $kelasFilter
        GROUP BY s.id, s.nis, u.nama, k.nama_kelas
        ORDER BY k.nama_kelas, u.nama
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    
    $namaBulanArr = ['','Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    
    $csv = "NIS,Nama Siswa,Kelas,Hadir,Terlambat,Izin,Sakit,Alfa\n";
    foreach ($rows as $row) {
        $csv .= implode(',', [
            $row['nis'], $row['nama'], $row['nama_kelas'],
            $row['hadir'], $row['terlambat'], $row['izin'], $row['sakit'], $row['alfa']
        ]) . "\n";
    }
    
    return $csv;
}

// ============================================================
// [FIX] Audit Log - Catat aktivitas penting admin/petugas
// ============================================================

/**
 * Catat aktivitas ke tabel audit_log.
 * Tabel: CREATE TABLE IF NOT EXISTS audit_log (
 *   id INT AUTO_INCREMENT PRIMARY KEY,
 *   user_id INT,
 *   role VARCHAR(20),
 *   aksi VARCHAR(100),
 *   detail TEXT,
 *   ip_address VARCHAR(45),
 *   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
 * );
 */
function auditLog(PDO $pdo, $aksi, $detail = '') {
    try {
        $pdo->prepare("
            INSERT INTO audit_log (user_id, role, aksi, detail, ip_address)
            VALUES (?, ?, ?, ?, ?)
        ")->execute([
            $_SESSION['user_id'] ?? null,
            $_SESSION['role']    ?? null,
            $aksi,
            $detail,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    } catch (Exception $e) {
        // Log error tapi jangan hentikan proses utama
        error_log('auditLog error: ' . $e->getMessage());
    }
}