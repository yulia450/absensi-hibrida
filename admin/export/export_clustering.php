<?php
// ============================================================
// FILE: admin/export_clustering.php (VERSI LAMPIRAN SKRIPSI)
// ============================================================

ob_start();

require_once '../../includes/config.php';
require_once '../../includes/functions.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

requireLogin('admin');

// FILTER
$bulan = (int)($_GET['bulan'] ?? date('n'));
$tahun = (int)($_GET['tahun'] ?? date('Y'));

// QUERY (TIDAK DIUBAH)
$stmt = $pdo->prepare("
    SELECT 
        hc.*,
        s.nis,
        u.nama,
        k.nama_kelas
    FROM hasil_clustering hc
    JOIN siswa s ON hc.siswa_id = s.id
    JOIN users u ON s.user_id = u.id
    LEFT JOIN kelas k ON s.kelas_id = k.id
    WHERE hc.bulan = ?
    AND hc.tahun = ?
    ORDER BY hc.cluster_id ASC, u.nama ASC
");

$stmt->execute([$bulan, $tahun]);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// VALIDASI
if (empty($data)) {
    die('Data clustering tidak ditemukan.');
}

// BUAT SPREADSHEET
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();


// ============================================================
// HEADER LAMPIRAN
// ============================================================

$sheet->mergeCells('A1:K1');
$sheet->mergeCells('A2:K2');
$sheet->mergeCells('A3:K3');

$sheet->setCellValue('A1', 'LAMPIRAN');
$sheet->setCellValue('A2', 'LAPORAN HASIL ANALISIS K-MEANS CLUSTERING');
$sheet->setCellValue('A3', 'Periode: ' . namaBulan($bulan) . ' ' . $tahun);

$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A2')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A3')->getFont()->setSize(12);

$sheet->getStyle('A1:A3')->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER);


// ============================================================
// HEADER TABLE
// ============================================================

$headers = [
    'No',
    'NIS',
    'Nama Siswa',
    'Kelas',
    'Hadir',
    'Terlambat',
    'Izin',
    'Sakit',
    'Alfa',
    'Cluster',
    'Keterangan'
];

$col = 'A';

foreach ($headers as $header) {
    $sheet->setCellValue($col . '5', $header);
    $col++;
}


// ============================================================
// STYLE HEADER
// ============================================================

$sheet->getStyle('A5:K5')->applyFromArray([
    'font' => [
        'bold' => true,
        'color' => ['rgb' => 'FFFFFF']
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['rgb' => '305496']
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical'   => Alignment::VERTICAL_CENTER
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN
        ]
    ]
]);


// ============================================================
// DATA
// ============================================================

$row = 6;
$no = 1;

foreach ($data as $d) {

    $status = 'Kehadiran Normal';

    if ($d['cluster_id'] == 2) {
        $status = 'Perlu Perhatian';
    } elseif ($d['cluster_id'] == 3) {
        $status = 'Anomali';
    }

    $sheet->setCellValue('A' . $row, $no++);
    $sheet->setCellValue('B' . $row, $d['nis']);
    $sheet->setCellValue('C' . $row, $d['nama']);
    $sheet->setCellValue('D' . $row, $d['nama_kelas']);
    $sheet->setCellValue('E' . $row, $d['jumlah_hadir']);
    $sheet->setCellValue('F' . $row, $d['jumlah_terlambat']);
    $sheet->setCellValue('G' . $row, $d['jumlah_izin']);
    $sheet->setCellValue('H' . $row, $d['jumlah_sakit']);
    $sheet->setCellValue('I' . $row, $d['jumlah_alfa']);
    $sheet->setCellValue('J' . $row, $d['cluster_id']);
    $sheet->setCellValue('K' . $row, $status);

    // Zebra row
    if ($row % 2 == 0) {
        $sheet->getStyle('A' . $row . ':K' . $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F9FAFB');
    }

    $row++;
}


// ============================================================
// BORDER
// ============================================================

$sheet->getStyle('A5:K' . ($row - 1))->applyFromArray([
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['rgb' => 'D1D5DB']
        ]
    ]
]);


// ============================================================
// ALIGNMENT
// ============================================================

$sheet->getStyle('A6:K' . ($row - 1))
    ->getAlignment()
    ->setVertical(Alignment::VERTICAL_CENTER);

$sheet->getStyle('A6:A' . ($row - 1))
    ->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER);

$sheet->getStyle('E6:J' . ($row - 1))
    ->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER);


// ============================================================
// WARNA STATUS (TIDAK DIUBAH)
// ============================================================

for ($i = 6; $i < $row; $i++) {

    $cluster = $sheet->getCell('J' . $i)->getValue();

    if ($cluster == 1) {

        $sheet->getStyle('K' . $i)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '166534']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'DCFCE7']
            ]
        ]);

    } elseif ($cluster == 2) {

        $sheet->getStyle('K' . $i)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '92400E']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FEF3C7']
            ]
        ]);

    } else {

        $sheet->getStyle('K' . $i)->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => '991B1B']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FEE2E2']
            ]
        ]);
    }
}


// ============================================================
// KETERANGAN
// ============================================================

$keteranganRow = $row + 2;

$sheet->setCellValue('A' . $keteranganRow, 'Keterangan:');
$sheet->setCellValue('A' . ($keteranganRow + 1), 'Cluster 1 : Siswa dengan tingkat kehadiran baik');
$sheet->setCellValue('A' . ($keteranganRow + 2), 'Cluster 2 : Siswa dengan tingkat kehadiran sedang');
$sheet->setCellValue('A' . ($keteranganRow + 3), 'Cluster 3 : Siswa dengan tingkat kehadiran bermasalah');

$sheet->getStyle('A' . $keteranganRow)->getFont()->setBold(true);


// ============================================================
// TANDA TANGAN
// ============================================================

$ttdRow = $keteranganRow + 6;

$sheet->setCellValue('H' . $ttdRow, 'Mengetahui,');
$sheet->setCellValue('H' . ($ttdRow + 1), 'Kepala Sekolah');

$sheet->setCellValue('H' . ($ttdRow + 5), '(____________________)');
$sheet->setCellValue('H' . ($ttdRow + 6), 'NIP. _____________');

$sheet->getStyle('H' . $ttdRow . ':H' . ($ttdRow + 6))
    ->getAlignment()
    ->setHorizontal(Alignment::HORIZONTAL_CENTER);


// ============================================================
// AUTO SIZE
// ============================================================

foreach (range('A', 'K') as $column) {
    $sheet->getColumnDimension($column)->setAutoSize(true);
}


// ============================================================
// FREEZE
// ============================================================

$sheet->freezePane('A6');


// ============================================================
// PAGE SETUP
// ============================================================

$sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
$sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);


// ============================================================
// FILE NAME
// ============================================================

$filename = 'Lampiran_Clustering_' .
            namaBulan($bulan) . '_' .
            $tahun . '.xlsx';


// ============================================================
// OUTPUT
// ============================================================

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

header(
    'Content-Disposition: attachment; filename="' .
    $filename . '"'
);

header('Cache-Control: max-age=0');

if (ob_get_length()) {
    ob_end_clean();
}

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

exit;
?>