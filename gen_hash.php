<?php
// PENTING: hapus file ini setelah dipakai, jangan biarkan tersisa di server produksi.
$pw = $_GET['pw'] ?? 'admin123';
echo "Password : " . htmlspecialchars($pw) . "<br>";
echo "Hash     : " . password_hash($pw, PASSWORD_DEFAULT);
