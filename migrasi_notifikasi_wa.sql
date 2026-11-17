-- ============================================================
-- MIGRASI: notifikasi_wa
-- Jalankan file ini SEKALI di phpMyAdmin (tab SQL) pada database
-- Absensi kamu. Ini nambahin kolom `tanggal` & `jenis` yang dipakai
-- kirimNotifikasiHarian() di includes/functions.php, plus UNIQUE KEY
-- supaya ON DUPLICATE KEY UPDATE-nya jalan (1 baris log per
-- siswa+tanggal+jenis, bukan numpuk terus tiap kali kirim ulang).
-- ============================================================

ALTER TABLE `notifikasi_wa`
  ADD COLUMN `tanggal` DATE NULL AFTER `siswa_id`,
  ADD COLUMN `jenis` VARCHAR(20) NULL AFTER `tanggal`;

ALTER TABLE `notifikasi_wa`
  ADD UNIQUE KEY `uq_notif_harian` (`siswa_id`, `tanggal`, `jenis`);
