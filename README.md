# Sistem Absensi Hibrida SMK Al-Manshuriyah

Aplikasi web presensi siswa berbasis PHP dan MySQL yang menggabungkan presensi mandiri berbasis geolokasi (GPS) dengan input manual oleh petugas. Sistem ini juga mengelompokkan pola kehadiran siswa menggunakan algoritma K-Means Clustering untuk membantu mendeteksi siswa yang memerlukan perhatian, serta mengirim notifikasi kehadiran kepada orang tua melalui WhatsApp.

Proyek ini dikembangkan sebagai bagian dari penelitian skripsi Program Studi Teknik Informatika, Universitas Pamulang.

## Fitur Utama

**Siswa**
- Presensi masuk dan pulang mandiri dengan validasi lokasi (radius dari koordinat sekolah) melalui `navigator.geolocation`.
- Pengajuan izin atau sakit disertai unggahan bukti, serta riwayat pengajuan.
- Riwayat dan rekap kehadiran pribadi, serta ubah kata sandi.

**Petugas (wali kelas)**
- Input presensi manual untuk kelas yang diampu.
- Dashboard, rekap kehadiran kelas, dan hasil clustering siswa pada kelasnya.
- Persetujuan pengajuan izin.

**Admin**
- Manajemen data siswa, kelas, petugas, dan hari libur (termasuk impor hari libur nasional).
- Impor data siswa, kelas, dan petugas dari Excel, lengkap dengan template unduhan.
- Koreksi presensi, serta proses alfa otomatis untuk siswa yang tidak melakukan presensi.
- Rekap kehadiran berdasarkan bulan atau semester, dengan ekspor ke Excel.
- Clustering K-Means (k = 3: Normal, Perlu Perhatian, Anomali) dengan evaluasi Elbow dan Silhouette Score, serta ekspor detail perhitungan.
- Pengiriman notifikasi WhatsApp ke orang tua secara manual dan batch, dengan jeda antarpesan.
- Pengaturan sistem: koordinat dan radius sekolah, jam masuk, batas keterlambatan, jam pulang, dan konfigurasi gateway WhatsApp.
- Kenaikan kelas dengan pelestarian riwayat melalui kolom `nama_kelas_snapshot`.

**Keamanan**
- Autentikasi dengan hash bcrypt, perlindungan CSRF, PDO prepared statements, manajemen sesi, dan kontrol akses berbasis peran (admin, petugas, siswa).
- Catatan audit (`audit_log`) untuk aksi penting.

## Teknologi

| Komponen | Keterangan |
|---|---|
| Bahasa | PHP (diuji pada PHP 8.2) |
| Basis data | MySQL / MariaDB (diuji pada MariaDB 10.4) |
| Pustaka | [PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet) `^1.8` untuk impor dan ekspor Excel |
| Antarmuka | HTML, CSS, dan JavaScript tanpa kerangka kerja |
| Notifikasi | Gateway WhatsApp pihak ketiga (misalnya Fonnte atau WaLib) |

Ekstensi PHP yang dibutuhkan: `pdo_mysql`, `mbstring`, `gd`, `zip`, `xml`, `curl`.

## Struktur Direktori

```
Absensi_SMK_AlManshuriyah/
├── admin/            Halaman dan proses untuk peran admin (termasuk export/ dan import/)
├── petugas/          Halaman untuk peran petugas
├── siswa/            Halaman untuk peran siswa
├── api/              Endpoint presensi geolokasi, ubah kata sandi, dan auto-alfa
├── includes/         Konfigurasi, fungsi umum, algoritma K-Means, header, sidebar, footer
├── assets/           CSS, JavaScript, dan folder uploads/ (bukti izin)
├── db_absensi_smk.sql   Skema dan data basis data
├── migrasi_*.sql     Skrip migrasi tambahan
├── composer.json
└── index.php         Halaman login
```

## Instalasi

1. **Siapkan lingkungan.** Pasang XAMPP (atau setara) dengan Apache, PHP, dan MySQL/MariaDB.
2. **Salin proyek** ke direktori web server, misalnya `C:\xampp\htdocs\Absensi_SMK_AlManshuriyah`.
3. **Pasang dependensi** (lewati langkah ini jika folder `vendor/` sudah tersedia):
   ```bash
   composer install
   ```
4. **Buat basis data** bernama `db_absensi_smk`, lalu impor `db_absensi_smk.sql` melalui phpMyAdmin atau:
   ```bash
   mysql -u root -p db_absensi_smk < db_absensi_smk.sql
   ```
   Jika memperbarui dari versi lama, jalankan juga `migrasi_kelas_snapshot.sql` dan `migrasi_notifikasi_wa.sql`.
5. **Sesuaikan konfigurasi** pada `includes/config.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   define('DB_NAME', 'db_absensi_smk');
   ```
   `BASE_URL` dihitung otomatis dari nama folder proyek, sehingga tidak perlu diubah.
6. **Pastikan folder `assets/uploads/` dapat ditulis** oleh web server.
7. **Akses aplikasi** melalui `http://localhost/Absensi_SMK_AlManshuriyah/`.

## Konfigurasi Setelah Instalasi

Masuk sebagai admin, kemudian buka menu **Pengaturan** untuk mengisi:
- nama dan alamat sekolah, koordinat (latitude dan longitude), serta radius presensi dalam meter;
- jam masuk, batas keterlambatan, dan jam pulang;
- URL dan API key gateway WhatsApp.

Untuk membuat hash kata sandi baru, gunakan `gen_hash.php?pw=KATA_SANDI` (hanya di lingkungan lokal).

### Proses Alfa Otomatis

Endpoint `api/auto_alfa.php` menandai siswa yang tidak melakukan presensi sebagai alfa. Jalankan setiap hari sekolah setelah jam pulang, misalnya melalui cron:

```cron
0 15 * * 1-5 curl -s "http://HOST/Absensi_SMK_AlManshuriyah/api/auto_alfa.php?token=TOKEN_ANDA"
```

Ganti nilai `$validToken` pada `api/auto_alfa.php` dengan token rahasia milik Anda sendiri.

## Metode Clustering

Setiap siswa direpresentasikan oleh fitur kehadiran pada periode terpilih (bulanan atau per semester), dinormalisasi dengan Min-Max, lalu dikelompokkan dengan K-Means (jarak Euclidean, k = 3). Hasilnya dilabeli sebagai **Normal**, **Perlu Perhatian**, atau **Anomali**. Kualitas klaster dievaluasi dengan metode Elbow (inertia) dan Silhouette Score. Implementasi berada di `includes/kmeans.php`, sedangkan hasil terakhir disimpan di basis data sehingga dapat ditampilkan ulang.

## Catatan Keamanan

- Hapus berkas bantu `debug_login.php`, `gen_hash.php`, dan `test_wa.php` sebelum sistem digunakan di server publik.
- Ganti kredensial basis data bawaan (`root` tanpa kata sandi) dan token `auto_alfa`.
- Jangan mengunggah data siswa, nomor telepon orang tua, API key WhatsApp, maupun isi `assets/uploads/` ke repositori publik.
- Gunakan HTTPS pada lingkungan produksi, karena fitur geolokasi peramban umumnya mensyaratkan koneksi aman.

## Pengembang

Sri Yulia, Program Studi Teknik Informatika, Fakultas Ilmu Komputer, Universitas Pamulang.

## Lisensi

Hak cipta dilindungi. Proyek ini dikembangkan untuk keperluan penelitian skripsi. Kode sumber dapat dilihat untuk tujuan pembelajaran, tetapi tidak diperkenankan disalin, dimodifikasi, atau didistribusikan ulang tanpa izin tertulis dari pengembang.
