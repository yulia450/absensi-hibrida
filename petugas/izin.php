<?php
// FILE: petugas/izin.php — Pengajuan izin khusus kelas petugas
require_once '../includes/config.php';
requireLogin('petugas');

// Inject filter kelas petugas ke GET agar dipakai admin/izin.php
$petugasKelasId = getPetugasKelasId();
if ($petugasKelasId) {
    $_GET['kelas_id_filter'] = $petugasKelasId;
}

$_SERVER['PHP_SELF'] = '/petugas/izin.php';
include '../admin/izin.php';
