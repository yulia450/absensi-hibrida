<?php
// ============================================================
// FILE: admin/export/export_kmeans_detail.php
// Ekspor Detail Perhitungan K-Means (Normalisasi, Centroid, Jarak
// Euclidean) ke Excel — SATU SHEET, layout: tabel detail jarak
// euclidean di kiri (A:L), panel normalisasi & centroid di kanan (N:S).
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

// ── Ambil data perhitungan terakhir (tersimpan permanen di DB) ──
$settings = getSettings($pdo);
$evalData = !empty($settings['kmeans_last_detail']) ? json_decode($settings['kmeans_last_detail'], true) : null;

if (!$evalData || empty($evalData['centroids']) || empty($evalData['detail'])) {
    die('Belum ada hasil perhitungan K-Means. Jalankan Clustering terlebih dahulu.');
}

$namaSekolah = $settings['nama_sekolah'] ?? 'SMK AL-MANSHURIYAH';

$labelPeriode = '-';
if (($settings['kmeans_last_mode'] ?? 'semester') === 'bulanan' && !empty($settings['kmeans_last_bulan'])) {
    $labelPeriode = labelBulanan($settings['kmeans_last_bulan'], $settings['kmeans_last_tahun_bulanan']);
} elseif (!empty($settings['kmeans_last_tahun_ajaran'])) {
    $labelPeriode = labelSemester($settings['kmeans_last_tahun_ajaran'], $settings['kmeans_last_semester']);
}

$namaCluster = [1 => 'Cluster 1 - Normal', 2 => 'Cluster 2 - Perlu Perhatian', 3 => 'Cluster 3 - Anomali'];
$fiturLabel  = ['Hadir (+Terlambat)', 'Izin', 'Sakit', 'Alfa', 'Terlambat'];

function styleSectionTitle($sheet, $cell, $text) {
    $sheet->setCellValue($cell, $text);
    $sheet->getStyle($cell)->applyFromArray([
        'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '111827']],
    ]);
}
function styleSectionNote($sheet, $cell, $text) {
    $sheet->setCellValue($cell, $text);
    $sheet->getStyle($cell)->applyFromArray([
        'font' => ['size' => 9, 'color' => ['rgb' => '6B7280']],
    ]);
}
function styleHeaderRow($sheet, $range) {
    $sheet->getStyle($range)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '305496']],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ]);
}
function styleBorder($sheet, $range) {
    $sheet->getStyle($range)->applyFromArray([
        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ]);
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle('DETAIL PERHITUNGAN K-MEANS');

// ============================================================
// JUDUL
// ============================================================
$sheet->mergeCells('A1:L1');
$sheet->mergeCells('A2:L2');
$sheet->mergeCells('A3:L3');
$sheet->mergeCells('A4:L4');
$sheet->setCellValue('A1', 'LAMPIRAN');
$sheet->setCellValue('A2', 'DETAIL PERHITUNGAN K-MEANS CLUSTERING');
$sheet->setCellValue('A3', $namaSekolah);
$sheet->setCellValue('A4', 'Periode: ' . $labelPeriode);
$sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
$sheet->getStyle('A2')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A3')->getFont()->setBold(true)->setSize(16);
$sheet->getStyle('A4')->getFont()->setSize(11);
$sheet->getStyle('A1:A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// ============================================================
// TABEL UTAMA (KIRI) — JARAK EUCLIDEAN & HASIL KLASTER
// ============================================================
styleSectionTitle($sheet, 'A6', 'Jarak Euclidean ke Setiap Centroid');
styleSectionNote($sheet, 'A7', "Rumus: d = \xE2\x88\x9A\xCE\xA3(x\xE1\xB5\xA2 - c\xE1\xB5\xA2)\xC2\xB2 \xE2\x80\x94 siswa masuk ke cluster dengan jarak terkecil (ditandai hijau)");

$startDetail = 9;
$headers3 = ['No', 'Nama Siswa', 'Kelas', "Hadir'", "Izin'", "Sakit'", "Alfa'", "Terlambat'", 'Jarak C1', 'Jarak C2', 'Jarak C3', 'Klaster'];
$col = 'A';
foreach ($headers3 as $hd) { $sheet->setCellValue($col . '8', $hd); $col++; }
styleHeaderRow($sheet, 'A8:L8');

$row = $startDetail;
$no = 1;
$highlightCells = [];
foreach ($evalData['detail'] as $d) {
    $sheet->setCellValue('A' . $row, $no++);
    $sheet->setCellValue('B' . $row, $d['nama']);
    $sheet->setCellValue('C' . $row, $d['kelas'] ?? '-');
    $norm = $d['normalized'] ?? [0, 0, 0, 0, 0];
    $sheet->setCellValue('D' . $row, round($norm[0], 4));
    $sheet->setCellValue('E' . $row, round($norm[1], 4));
    $sheet->setCellValue('F' . $row, round($norm[2], 4));
    $sheet->setCellValue('G' . $row, round($norm[3], 4));
    $sheet->setCellValue('H' . $row, round($norm[4], 4));
    $sheet->setCellValue('I' . $row, round($d['jarak_ke_semua'][1] ?? 0, 4));
    $sheet->setCellValue('J' . $row, round($d['jarak_ke_semua'][2] ?? 0, 4));
    $sheet->setCellValue('K' . $row, round($d['jarak_ke_semua'][3] ?? 0, 4));
    $sheet->setCellValue('L' . $row, $namaCluster[$d['cluster_id']] ?? '-');

    if ($row % 2 == 1) {
        $sheet->getStyle('A' . $row . ':L' . $row)->getFill()
            ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F9FAFB');
    }

    $colJarak = ['I', 'J', 'K'];
    $terpilih = $colJarak[$d['cluster_id'] - 1] ?? null;
    if ($terpilih) { $highlightCells[] = $terpilih . $row; }

    $row++;
}
$lastDetailRow = $row - 1;
styleBorder($sheet, 'A' . $startDetail . ':L' . $lastDetailRow);
$sheet->setAutoFilter('A8:L' . $lastDetailRow);
$sheet->getStyle('A' . $startDetail . ':A' . $lastDetailRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$sheet->getStyle('D' . $startDetail . ':L' . $lastDetailRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// Highlight jarak terkecil (klaster terpilih) — diterapkan setelah border/zebra
foreach ($highlightCells as $cellRef) {
    $sheet->getStyle($cellRef)->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => '166534']],
        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DCFCE7']],
    ]);
}

// ============================================================
// PANEL KANAN (1) — NORMALISASI MIN-MAX
// ============================================================
styleSectionTitle($sheet, 'N9', 'Normalisasi Min-Max');
$sheet->mergeCells('N10:P10');
$sheet->setCellValue('N10', "Rumus Normalisasi: x' = (x - min) / (max - min)");
$sheet->getStyle('N10')->getFont()->setItalic(true)->setSize(11);

$sheet->setCellValue('N11', 'Fitur');
$sheet->setCellValue('O11', 'Min');
$sheet->setCellValue('P11', 'Max');
styleHeaderRow($sheet, 'N11:P11');

$r = 12;
foreach ($fiturLabel as $i => $fl) {
    $sheet->setCellValue('N' . $r, $fl);
    $sheet->setCellValue('O' . $r, $evalData['min_max']['min'][$i] ?? 0);
    $sheet->setCellValue('P' . $r, $evalData['min_max']['max'][$i] ?? 0);
    $r++;
}
styleBorder($sheet, 'N12:P16');
$sheet->getStyle('O12:P16')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// ============================================================
// PANEL KANAN (2) — CENTROID FINAL
// ============================================================
styleSectionTitle($sheet, 'N19', 'Titik Pusat (Centroid) Final');
$sheet->setCellValue('N20', 'Nilai centroid dalam bentuk ternormalisasi [0,1] setelah iterasi K-Means konvergen ');
$sheet->getStyle('N20')->getFont()->setItalic(true)->setSize(11);

$headers2 = array_merge(['Cluster'], $fiturLabel);
$col = 'N';
foreach ($headers2 as $hd) { $sheet->setCellValue($col . '21', $hd); $col++; }
styleHeaderRow($sheet, 'N21:S21');

$r = 22;
foreach ($evalData['centroids'] as $ci => $centroid) {
    $sheet->setCellValue('N' . $r, $namaCluster[$ci + 1] ?? ('Cluster ' . ($ci + 1)));
    $col = 'O';
    foreach ($centroid as $val) {
        $sheet->setCellValue($col . $r, round($val, 4));
        $col++;
    }
    $r++;
}
styleBorder($sheet, 'N22:S' . ($r - 1));
$sheet->getStyle('O22:S' . ($r - 1))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// ============================================================
// FORMAT KOLOM & HALAMAN
// ============================================================
$widths = [
    'A' => 4.57, 'B' => 20, 'C' => 12.86, 'D' => 8.14, 'E' => 13, 'F' => 13,
    'G' => 13, 'H' => 12.86, 'I' => 10.57, 'J' => 13, 'K' => 13, 'L' => 33,
    'M' => 13, 'N' => 19.29, 'O' => 18.86, 'P' => 13, 'Q' => 13, 'R' => 13, 'S' => 13,
];
foreach ($widths as $c => $w) { $sheet->getColumnDimension($c)->setWidth($w); }

$sheet->freezePane('A' . $startDetail);
$sheet->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
$sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);

// ============================================================
// OUTPUT
// ============================================================
$filename = 'Lampiran_Perhitungan_KMeans_' . str_replace(['/', ' '], '_', $labelPeriode) . '.xlsx';

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

if (ob_get_length()) {
    ob_end_clean();
}

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;