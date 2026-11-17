<?php
// ============================================================
// TEST_WA.php - Tes kirim WA langsung, TANPA lewat proses absensi.
// Taruh file ini di folder root Absensi (sejajar dengan folder admin/, api/, dst)
// lalu buka di browser: http://localhost/Absensi/test_wa.php
//
// HAPUS file ini setelah selesai testing (jangan sampai kebawa ke server publik).
// ============================================================

require_once __DIR__ . '/includes/config.php';

header('Content-Type: text/plain; charset=utf-8');

echo "1) Cek ekstensi curl aktif?\n";
echo function_exists('curl_init') ? "   OK - curl_init() tersedia\n" : "   !! GAGAL - curl_init() TIDAK ada. Aktifkan extension=curl di php.ini XAMPP, lalu restart Apache.\n";

echo "\n2) Cek pengaturan WA di database:\n";
$settings = getSettings($pdo);
echo "   whatsapp_api_url = " . ($settings['whatsapp_api_url'] ?: '(KOSONG)') . "\n";
echo "   whatsapp_api_key = " . (!empty($settings['whatsapp_api_key']) ? substr($settings['whatsapp_api_key'], 0, 4) . '...(ada)' : '(KOSONG)') . "\n";

// GANTI nomor di bawah ini dengan nomor HP kamu sendiri untuk tes
// (pakai nomor yang SAMA dengan yang berhasil nyampe waktu tes dari menu clustering)
$nomorTes = '08993004722'; // <-- GANTI dengan nomor WA aktif kamu

echo "\n3) Mengirim WA tes ke $nomorTes ...\n";
if (!function_exists('curl_init')) {
    echo "   Dilewati karena curl tidak aktif (lihat langkah 1).\n";
} else {
    $hasil = kirimWhatsApp($nomorTes, 'Tes notifikasi dari sistem Absensi SMK Al-Manshuriyah.', $settings['whatsapp_api_url'], $settings['whatsapp_api_key']);
    echo "   Hasil (sudah diolah kirimWhatsApp()):\n";
    print_r($hasil);

    // [DEBUG] Kirim juga request mentah langsung, TANPA lewat fungsi
    // kirimWhatsApp() sama sekali, biar kelihatan persis apa yang Fonnte
    // balikin tanpa ada pemrosesan/interpretasi dari kode kita.
    echo "\n4) Request mentah langsung ke Fonnte (raw, tanpa kirimWhatsApp()):\n";
    $rawNoHp = preg_replace('/[^0-9]/', '', $nomorTes);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $settings['whatsapp_api_url']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'target'      => $rawNoHp,
        'message'     => 'Tes mentah langsung dari test_wa.php',
        'countryCode' => '62',
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: ' . $settings['whatsapp_api_key']]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $rawResponse = curl_exec($ch);
    $rawHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $rawCurlErr  = curl_error($ch);
    curl_close($ch);
    echo "   Target dikirim   : $rawNoHp\n";
    echo "   HTTP code        : $rawHttpCode\n";
    echo "   cURL error       : " . ($rawCurlErr ?: '(tidak ada)') . "\n";
    echo "   RAW response body:\n";
    echo "   " . $rawResponse . "\n";
}

echo "\nSelesai. Kalau langkah 3 statusnya true, cek HP kamu / dashboard Fonnte.\n";
echo "Kalau statusnya false, baca isi 'message' di atas -- itu alasan persisnya.\n";