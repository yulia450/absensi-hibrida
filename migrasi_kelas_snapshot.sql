-- ============================================================
-- MIGRASI TAMBAHAN: absensi.nama_kelas_snapshot
-- Jalankan SEKALI di phpMyAdmin (tab SQL).
--
-- Kolom ini menyimpan TEKS nama kelas (bukan cuma ID) pada saat siswa
-- absen. Tujuannya: begitu fitur Kenaikan Kelas mengubah nama kelas X
-- jadi XI (di baris kelas yang SAMA), riwayat absensi lama tetap
-- membaca nama kelas yang benar sesuai waktunya -- karena teksnya
-- sudah "terkunci" di baris absensi itu sendiri, bukan ikut berubah
-- mengikuti nama kelas yang berlaku sekarang.
-- ============================================================

ALTER TABLE `absensi`
  ADD COLUMN `nama_kelas_snapshot` VARCHAR(30) NULL AFTER `kelas_id`;
