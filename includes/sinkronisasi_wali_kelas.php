<?php
// ============================================================
// FILE: includes/wali_kelas_sync.php
// Sinkronisasi dua arah antara Data Petugas (wali kelas) dan
// Data Kelas — dipakai oleh import maupun form manual.
//
// Kalau pasangannya (kelas/petugas) belum ada saat salah satu
// dibuat/diimpor, pasangannya itu LANGSUNG DIBUATKAN otomatis
// (tidak ada mode "menunggu" / pending), lalu disambungkan.
// ============================================================

// Bikin email otomatis dari nama, jamin unik (tambah angka kalau bentrok)
function buildEmailOtomatisPetugas(PDO $pdo, string $nama): string {
    $namadepan = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode(' ', $nama)[0] ?? ''));
    if ($namadepan === '') $namadepan = 'petugas';

    $email = $namadepan . '@almanshuriyah.sch.id';
    $n = 1;
    while (true) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if (!$stmt->fetch()) break;
        $n++;
        $email = $namadepan . $n . '@almanshuriyah.sch.id';
    }
    return $email;
}

// Password default, formatnya disamakan dengan yang dipakai import petugas biasa
function buildPasswordOtomatisPetugas(string $nama): string {
    $namadepan = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode(' ', trim($nama))[0] ?? ''));
    if ($namadepan === '') $namadepan = 'petugas';
    return 'almanshuriyah@' . $namadepan;
}

/**
 * Cari petugas berdasarkan nama (persis, tanpa peduli huruf besar/kecil & spasi).
 * Kalau belum ada, LANGSUNG DIBUATKAN akun petugas baru.
 *
 * @return array ['id' => int, 'created' => bool, 'email' => string|null, 'password' => string|null]
 */
function cariAtauBuatPetugas(PDO $pdo, string $nama): array {
    $namaBersih = trim(preg_replace('/\s+/', ' ', $nama));

    $stmt = $pdo->prepare("SELECT id FROM users WHERE role IN ('petugas','admin') AND TRIM(LOWER(nama)) = TRIM(LOWER(?))");
    $stmt->execute([$namaBersih]);
    $u = $stmt->fetch();
    if ($u) {
        return ['id' => (int)$u['id'], 'created' => false, 'email' => null, 'password' => null];
    }

    $email        = buildEmailOtomatisPetugas($pdo, $namaBersih);
    $plainPassword = buildPasswordOtomatisPetugas($namaBersih);
    $hashed       = password_hash($plainPassword, PASSWORD_DEFAULT);

    $pdo->prepare("INSERT INTO users (nama, email, password, role) VALUES (?, ?, ?, 'petugas')")
        ->execute([$namaBersih, $email, $hashed]);
    $newId = (int)$pdo->lastInsertId();

    return ['id' => $newId, 'created' => true, 'email' => $email, 'password' => $plainPassword];
}

/**
 * Cari kelas berdasarkan nama. Kalau belum ada, LANGSUNG DIBUATKAN
 * kelas baru (tingkat default 'X', jurusan kosong — bisa dilengkapi nanti).
 *
 * @return array ['id' => int, 'created' => bool]
 */
function cariAtauBuatKelas(PDO $pdo, string $namaKelas): array {
    $namaBersih = trim(preg_replace('/\s+/', ' ', $namaKelas));

    $stmt = $pdo->prepare("SELECT id FROM kelas WHERE TRIM(LOWER(nama_kelas)) = TRIM(LOWER(?))");
    $stmt->execute([$namaBersih]);
    $k = $stmt->fetch();
    if ($k) {
        return ['id' => (int)$k['id'], 'created' => false];
    }

    $pdo->prepare("INSERT INTO kelas (nama_kelas, jurusan, tingkat) VALUES (?, '', 'X')")
        ->execute([$namaBersih]);
    $newId = (int)$pdo->lastInsertId();

    return ['id' => $newId, 'created' => true];
}

// Sambungkan petugas <-> kelas dua arah (lepas ikatan lama masing-masing dulu)
function hubungkanPetugasKelas(PDO $pdo, int $userId, int $kelasId): void {
    $pdo->prepare("UPDATE users SET kelas_id = NULL WHERE kelas_id = ? AND id != ?")->execute([$kelasId, $userId]);
    $pdo->prepare("UPDATE kelas SET wali_kelas_id = NULL WHERE wali_kelas_id = ? AND id != ?")->execute([$userId, $kelasId]);
    $pdo->prepare("UPDATE users SET kelas_id = ? WHERE id = ?")->execute([$kelasId, $userId]);
    $pdo->prepare("UPDATE kelas SET wali_kelas_id = ? WHERE id = ?")->execute([$userId, $kelasId]);
}

/**
 * Panggil setelah SATU petugas baru berhasil di-INSERT, kalau petugas
 * itu diminta memegang kelas tertentu (boleh null/kosong kalau tidak).
 * Kalau kelasnya belum ada, otomatis dibuatkan.
 *
 * @return array ['linked' => bool, 'kelas_id' => int|null, 'created_kelas' => bool]
 */
function syncSetelahPetugasBaru(PDO $pdo, int $userId, string $namaUser, ?string $namaKelasDiminta): array {
    $namaKelasDiminta = $namaKelasDiminta !== null ? trim($namaKelasDiminta) : '';
    if ($namaKelasDiminta === '') {
        return ['linked' => false, 'kelas_id' => null, 'created_kelas' => false];
    }

    $kelas = cariAtauBuatKelas($pdo, $namaKelasDiminta);
    hubungkanPetugasKelas($pdo, $userId, $kelas['id']);

    return ['linked' => true, 'kelas_id' => $kelas['id'], 'created_kelas' => $kelas['created']];
}

/**
 * Panggil setelah SATU kelas baru berhasil di-INSERT, kalau kelas itu
 * diminta punya wali kelas tertentu (boleh null/kosong kalau tidak).
 * Kalau petugasnya belum ada, otomatis dibuatkan akun petugas baru.
 *
 * @return array ['linked' => bool, 'user_id' => int|null, 'created_petugas' => bool, 'email' => string|null, 'password' => string|null]
 */
function syncSetelahKelasBaru(PDO $pdo, int $kelasId, string $namaKelas, ?string $namaWaliDiminta): array {
    $namaWaliDiminta = $namaWaliDiminta !== null ? trim($namaWaliDiminta) : '';
    if ($namaWaliDiminta === '') {
        return ['linked' => false, 'user_id' => null, 'created_petugas' => false, 'email' => null, 'password' => null];
    }

    $petugas = cariAtauBuatPetugas($pdo, $namaWaliDiminta);
    hubungkanPetugasKelas($pdo, $petugas['id'], $kelasId);

    return [
        'linked'         => true,
        'user_id'        => $petugas['id'],
        'created_petugas'=> $petugas['created'],
        'email'          => $petugas['email'],
        'password'       => $petugas['password'],
    ];
}