<?php
require_once '../../includes/config.php';
require_once '../../includes/sinkronisasi_wali_kelas.php';
require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

header('Content-Type: application/json');

// Password otomatis petugas: almanshuriyah@<namadepan>
function buildDefaultPasswordPetugas(string $nama): string {
    $parts     = explode(' ', trim($nama));
    $namadepan = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $parts[0]));
    return 'almanshuriyah@' . $namadepan;
}

// Format nomor HP agar nol di depan tidak hilang
function formatNoHP($value): string {
    $value = trim((string)$value);
    if (empty($value)) return '';
    if (str_starts_with($value, '+')) return $value;
    $digits = preg_replace('/\D/', '', $value);
    if (empty($digits)) return $value;
    if (str_starts_with($digits, '62')) return '0' . substr($digits, 2);
    if (!str_starts_with($digits, '0')) return '0' . $digits;
    return $digits;
}

try {

    if (!isset($_FILES['file_import']) || $_FILES['file_import']['error'] !== 0) {
        throw new Exception('File upload gagal');
    }

    $jenis = trim($_POST['jenis'] ?? 'siswa');
    $file  = $_FILES['file_import']['tmp_name'];

    $spreadsheet = IOFactory::load($file);
    $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);

    $success       = 0;
    $errors        = [];
    $warnings      = []; // baris berhasil, tapi ada bagian opsional (kelas/wali) yang belum nyambung
    $waliTerpasang = 0; // jumlah baris yang berhasil di-assign sebagai wali kelas

    // ======================================================
    // IMPORT PETUGAS/GURU
    // Kolom: Nama | Email | No HP | Role | Kelas (Wali Kelas)
    // ======================================================
    if ($jenis === 'petugas') {

        foreach ($rows as $i => $row) {
            // Lewati baris kop sekolah, judul, catatan, dan header tabel (baris 1-4)
            if ($i < 4) continue;

            $nama      = trim($row[0] ?? '');
            $email     = trim($row[1] ?? '');
            $no_hp     = formatNoHP($row[2] ?? '');
            $role      = trim($row[3] ?? 'petugas');
            $kelasNama = trim($row[4] ?? '');

            // Baris kosong / bagian blok "Mengetahui" di bawah otomatis dilewati
            if (!$nama && !$email) continue;

            if (!$nama || !$email) {
                $errors[] = "Baris " . ($i + 1) . " nama/email kosong.";
                continue;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Baris " . ($i + 1) . " email tidak valid.";
                continue;
            }
            if (!in_array($role, ['petugas', 'admin'])) {
                $role = 'petugas';
            }

            // Password otomatis dari sistem
            $plainPassword  = buildDefaultPasswordPetugas($nama);
            $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

            try {
                $pdo->beginTransaction();

                $cekEmail = $pdo->prepare("SELECT id FROM users WHERE email=?");
                $cekEmail->execute([$email]);
                if ($cekEmail->fetch()) throw new Exception("Email sudah digunakan");

                $pdo->prepare("
                    INSERT INTO users (nama, email, password, role, no_hp)
                    VALUES (?, ?, ?, ?, ?)
                ")->execute([$nama, $email, $hashedPassword, $role, $no_hp]);
                $newUserId = (int)$pdo->lastInsertId();

                // Kelas wali bersifat opsional. Sinkron dua arah otomatis:
                // kalau kelasnya belum ada, langsung dibuatkan kelas barunya juga.
                $kelasNamaBersih = $kelasNama !== '' ? trim(preg_replace('/\s+/', ' ', $kelasNama)) : null;
                $syncResult = syncSetelahPetugasBaru($pdo, $newUserId, $nama, $kelasNamaBersih);

                if ($syncResult['linked']) {
                    $waliTerpasang++;
                    if (!empty($syncResult['created_kelas'])) {
                        $warnings[] = "Baris " . ($i + 1) . " : kelas [$kelasNamaBersih] belum ada, jadi otomatis dibuatkan (tingkat X, jurusan masih kosong) — lengkapi datanya di Data Kelas kalau perlu.";
                    }
                }

                $pdo->commit();
                $success++;

            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = "Baris " . ($i + 1) . " : " . $e->getMessage();
            }
        }

    // ======================================================
    // IMPORT KELAS
    // Kolom: Nama Kelas | Tingkat | Jurusan | Nama Wali Kelas
    // ======================================================
    } elseif ($jenis === 'kelas') {

        foreach ($rows as $i => $row) {
            if ($i < 3) continue;

            $namaKelas = trim($row[0] ?? '');
            $tingkatRaw = trim($row[1] ?? '');
            $jurusan   = trim($row[2] ?? '');
            $namaWali  = trim($row[3] ?? '');

            if (!$namaKelas) continue;

            // Samakan default dengan form manual: tingkat tidak valid -> 'X'
            $tingkat = in_array($tingkatRaw, ['X', 'XI', 'XII']) ? $tingkatRaw : 'X';

            try {
                $pdo->beginTransaction();

                $namaKelasBersih = trim(preg_replace('/\s+/', ' ', $namaKelas));
                $cekKelas = $pdo->prepare("SELECT id FROM kelas WHERE TRIM(LOWER(nama_kelas)) = TRIM(LOWER(?))");
                $cekKelas->execute([$namaKelasBersih]);
                if ($cekKelas->fetch()) throw new Exception("Nama kelas sudah ada");

                $pdo->prepare("
                    INSERT INTO kelas (nama_kelas, jurusan, tingkat)
                    VALUES (?, ?, ?)
                ")->execute([$namaKelasBersih, $jurusan, $tingkat]);
                $newKelasId = (int)$pdo->lastInsertId();

                // Wali kelas bersifat opsional. Sinkron dua arah otomatis:
                // kalau petugasnya belum ada, langsung dibuatkan akun petugas barunya juga.
                $namaWaliBersih = $namaWali !== '' ? trim(preg_replace('/\s+/', ' ', $namaWali)) : null;
                $syncResult = syncSetelahKelasBaru($pdo, $newKelasId, $namaKelasBersih, $namaWaliBersih);

                if (!empty($syncResult['created_petugas'])) {
                    $warnings[] = "Baris " . ($i + 1) . " : wali kelas [$namaWaliBersih] belum ada di Data Petugas, jadi otomatis dibuatkan akun barunya — email: {$syncResult['email']}, password: {$syncResult['password']} (sampaikan ke yang bersangkutan, atau reset lewat Data Petugas).";
                }

                $pdo->commit();
                $success++;

            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = "Baris " . ($i + 1) . " : " . $e->getMessage();
            }
        }

    // ======================================================
    // IMPORT SISWA
    // Kolom: Nama | Email | No HP | NIS | NISN | Kelas | JK | Tgl Lahir | Nama Ortu | No HP Ortu | Alamat
    // ======================================================
    } else {

        foreach ($rows as $i => $row) {
            if ($i < 3) continue;

            $nama    = trim($row[0] ?? '');
            $email   = trim($row[1] ?? '');
            $no_hp   = formatNoHP($row[2] ?? '');
            $nis     = trim($row[3] ?? '');
            $nisn    = trim($row[4] ?? '');
            $kelas   = trim($row[5] ?? '');
            $jk      = trim($row[6] ?? '');
            $tglCell = $row[7] ?? '';
            $ortu    = trim($row[8] ?? '');
            $hp_ortu = formatNoHP($row[9] ?? '');
            $alamat  = trim($row[10] ?? '');

            if (!$nama && !$email && !$nis) continue;

            if (is_numeric($tglCell)) {
                $tgl = Date::excelToDateTimeObject((float)$tglCell)->format('Y-m-d');
            } else {
                $tgl = date('Y-m-d', strtotime(trim($tglCell)));
            }

            if ($tgl == '1970-01-01' || empty($tgl)) {
                $errors[] = "Baris " . ($i + 1) . " format tanggal tidak valid.";
                continue;
            }
            if (!$nama || !$email || !$nis || !$kelas) {
                $errors[] = "Baris " . ($i + 1) . " data wajib kosong.";
                continue;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Baris " . ($i + 1) . " email tidak valid.";
                continue;
            }
            if (!in_array($jk, ['L', 'P'])) {
                $errors[] = "Baris " . ($i + 1) . " JK harus L atau P.";
                continue;
            }

            try {
                $pdo->beginTransaction();

                $cek = $pdo->prepare("SELECT id FROM siswa WHERE nis=?");
                $cek->execute([$nis]);
                if ($cek->fetch()) throw new Exception("NIS sudah ada");

                $cekEmail = $pdo->prepare("SELECT id FROM users WHERE email=?");
                $cekEmail->execute([$email]);
                if ($cekEmail->fetch()) throw new Exception("Email sudah digunakan");

                $kelas = trim(preg_replace('/\s+/', ' ', $kelas));
                $stmt  = $pdo->prepare("SELECT id FROM kelas WHERE TRIM(LOWER(nama_kelas)) = TRIM(LOWER(?))");
                $stmt->execute([$kelas]);
                $k = $stmt->fetch();
                if (!$k) throw new Exception("Kelas tidak ditemukan: [" . $kelas . "]");
                $kelasId = $k['id'];

                $tglLahirFormatted = date('dmY', strtotime($tgl));
                $defaultPassword   = $nis . '@' . substr($tglLahirFormatted, 0, 6);
                $password          = password_hash($defaultPassword, PASSWORD_DEFAULT);

                $pdo->prepare("
                    INSERT INTO users (nama, email, password, role, no_hp)
                    VALUES (?, ?, ?, 'siswa', ?)
                ")->execute([$nama, $email, $password, $no_hp]);
                $userId = $pdo->lastInsertId();

                $pdo->prepare("
                    INSERT INTO siswa
                    (user_id, nis, nisn, kelas_id, nama_orang_tua, no_hp_ortu, alamat, tanggal_lahir, jenis_kelamin)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ")->execute([$userId, $nis, $nisn, $kelasId, $ortu, $hp_ortu, $alamat, $tgl, $jk]);

                $pdo->commit();
                $success++;

            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = "Baris " . ($i + 1) . " : " . $e->getMessage();
            }
        }
    }

    $pesanSukses = "Berhasil: $success data";
    if ($jenis === 'petugas') {
        $pesanSukses .= " (jadi wali kelas: $waliTerpasang)";
    }

    echo json_encode([
        'status'   => $success > 0 ? ($errors ? 'partial' : 'success') : 'error',
        'message'  => $pesanSukses,
        'errors'   => $errors,
        'warnings' => $warnings
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}