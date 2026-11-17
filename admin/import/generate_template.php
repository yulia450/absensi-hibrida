<?php
ob_start();

require '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

$jenis = $_GET['jenis'] ?? 'siswa';

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();


// ======================================================
// TEMPLATE SISWA
// ======================================================
if ($jenis == 'siswa') {

    $headers = [
        'Nama',
        'Email',
        'No HP',
        'NIS',
        'NISN',
        'Kelas',
        'Jenis Kelamin (L/P)',
        'Tanggal Lahir (YYYY-MM-DD)',
        'Nama Orang Tua',
        'No HP Ortu',
        'Alamat'
    ];

    $sheet->mergeCells('A1:K1');
    $sheet->setCellValue('A1', 'TEMPLATE IMPORT DATA SISWA');

    $sheet->getStyle('A1')->getFont()
        ->setBold(true)
        ->setSize(16);

    $sheet->getStyle('A1')->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Baris 2: catatan nomor HP
    $sheet->mergeCells('A2:K2');
    $sheet->setCellValue('A2', '* Kolom No HP dan No HP Ortu: ketik angka lengkap dengan nol di depan (contoh: 08123456789). Kolom ini sudah diformat sebagai Teks.');
    $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(9);
    $sheet->getStyle('A2')->getFont()->getColor()->setRGB('C00000');

    $col = 'A';

    foreach ($headers as $header) {

        $sheet->setCellValue($col . '3', $header);

        $col++;
    }

    // STYLE HEADER
    $sheet->getStyle('A3:K3')->applyFromArray([
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '1F4E78']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN
            ]
        ]
    ]);

    // FORMAT KOLOM No HP (C) dan No HP Ortu (J) sebagai TEXT
    // agar nol di depan tidak hilang saat pengguna mengisi Excel
    $sheet->getStyle('C4:C1000')->getNumberFormat()
        ->setFormatCode(NumberFormat::FORMAT_TEXT);
    $sheet->getStyle('J4:J1000')->getNumberFormat()
        ->setFormatCode(NumberFormat::FORMAT_TEXT);

    // CONTOH DATA — HP di-set sebagai string eksplisit
    $sheet->setCellValue('A4', 'Budi');
    $sheet->setCellValue('B4', 'budi@mail.com');
    $sheet->setCellValueExplicit('C4', '08123456789', DataType::TYPE_STRING);
    $sheet->setCellValue('D4', '12345');
    $sheet->setCellValue('E4', '99887766');
    $sheet->setCellValue('F4', 'X-TKJ-1');
    $sheet->setCellValue('G4', 'L');
    $sheet->setCellValue('H4', '2005-01-01');
    $sheet->setCellValue('I4', 'Pak Budi');
    $sheet->setCellValueExplicit('J4', '081111111', DataType::TYPE_STRING);
    $sheet->setCellValue('K4', 'Jakarta');

    // AUTO SIZE
    foreach (range('A', 'K') as $column) {

        $sheet->getColumnDimension($column)
            ->setAutoSize(true);
    }

    $filename = 'template_siswa.xlsx';
}


// ======================================================
// TEMPLATE KELAS
// ======================================================
elseif ($jenis == 'kelas') {

    $headers = [
        'Nama Kelas',
        'Tingkat',
        'Jurusan',
        'Nama Wali Kelas'
    ];

    $sheet->mergeCells('A1:D1');
    $sheet->setCellValue('A1', 'TEMPLATE IMPORT DATA KELAS');

    $sheet->getStyle('A1')->getFont()
        ->setBold(true)
        ->setSize(16);

    $sheet->getStyle('A1')->getAlignment()
        ->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $col = 'A';

    foreach ($headers as $header) {

        $sheet->setCellValue($col . '3', $header);

        $col++;
    }

    // STYLE HEADER
    $sheet->getStyle('A3:D3')->applyFromArray([
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '1F4E78']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN
            ]
        ]
    ]);

    // CONTOH DATA
    $sheet->setCellValue('A4', 'X-TKJ-1');
    $sheet->setCellValue('B4', 'X');
    $sheet->setCellValue('C4', 'Teknik Komputer dan Jaringan');
    $sheet->setCellValue('D4', 'Budi Santoso');

    // AUTO SIZE
    foreach (range('A', 'D') as $column) {

        $sheet->getColumnDimension($column)
            ->setAutoSize(true);
    }

    $filename = 'template_kelas.xlsx';
}


// ======================================================
// TEMPLATE PETUGAS
// ======================================================
elseif ($jenis == 'petugas') {

    $headers = [
        'Nama Lengkap',
        'Email',
        'No HP',
        'Role',
        'Kelas (Wali Kelas)'
    ];

    // Jumlah baris kosong yang disediakan untuk diisi data,
    // sebelum blok "Mengetahui" di bagian bawah.
    $jumlahBarisData = 40;

    // --- Baris 1: Kop sekolah ---
    $sheet->mergeCells('A1:E1');
    $sheet->setCellValue('A1', 'SMK AL-MANSHURIYAH');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // --- Baris 2: Judul dokumen ---
    $sheet->mergeCells('A2:E2');
    $sheet->setCellValue('A2', 'TEMPLATE IMPORT DATA PETUGAS/GURU');
    $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(13);
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // --- Baris 3: Catatan ---
    $sheet->mergeCells('A3:E3');
    $sheet->setCellValue('A3', '* Password dibuat otomatis oleh sistem: almanshuriyah@<namadepan>. Kolom No HP sudah diformat sebagai Teks. Kolom Kelas diisi HANYA jika petugas menjadi wali kelas (kosongkan jika tidak), tulis persis sama seperti nama kelas di menu Data Kelas.');
    $sheet->getStyle('A3')->getFont()->setItalic(true)->setSize(9);
    $sheet->getStyle('A3')->getFont()->getColor()->setRGB('C00000');
    $sheet->getRowDimension(3)->setRowHeight(28);
    $sheet->getStyle('A3')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);

    // --- Baris 4: Header tabel ---
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . '4', $header);
        $col++;
    }

    $sheet->getStyle('A4:E4')->applyFromArray([
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF']
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '1F4E78']
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
    $sheet->getRowDimension(4)->setRowHeight(20);

    // FORMAT KOLOM No HP (C) sebagai TEXT
    $sheet->getStyle('C5:C1000')->getNumberFormat()
        ->setFormatCode(NumberFormat::FORMAT_TEXT);

    // --- Baris 5: Contoh data (hapus/timpa baris ini sebelum impor) ---
    $sheet->setCellValue('A5', 'Budi Santoso');
    $sheet->setCellValue('B5', 'budi@sekolah.com');
    $sheet->setCellValueExplicit('C5', '08123456789', DataType::TYPE_STRING);
    $sheet->setCellValue('D5', 'petugas');
    $sheet->setCellValue('E5', 'X-TKJ-1');
    $sheet->getStyle('A5:E5')->getFont()->setItalic(true)->getColor()->setRGB('808080');

    // --- Baris data kosong (baris 6 s/d 6+40-1) dengan border rapi ---
    $barisDataMulai  = 6;
    $barisDataSelesai = $barisDataMulai + $jumlahBarisData - 1;
    $sheet->getStyle('A' . $barisDataMulai . ':E' . $barisDataSelesai)->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color'       => ['rgb' => 'B7C4D6']
            ]
        ]
    ]);
    // Border baris contoh juga disamakan
    $sheet->getStyle('A5:E5')->applyFromArray([
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color'       => ['rgb' => 'B7C4D6']
            ]
        ]
    ]);

    // --- Blok "Mengetahui" — selalu diletakkan di bawah area data ---
    $barisTanggal    = $barisDataSelesai + 3;
    $barisMengetahui = $barisTanggal + 1;
    $barisJabatan    = $barisMengetahui + 1;
    $barisNama       = $barisJabatan + 4; // 4 baris kosong untuk ruang tanda tangan

    $sheet->mergeCells('D' . $barisTanggal . ':E' . $barisTanggal);
    $sheet->setCellValue('D' . $barisTanggal, 'Jakarta, ' . date('d F Y'));
    $sheet->getStyle('D' . $barisTanggal)->getFont()->setItalic(true)->setSize(11);
    $sheet->getStyle('D' . $barisTanggal)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->mergeCells('D' . $barisMengetahui . ':E' . $barisMengetahui);
    $sheet->setCellValue('D' . $barisMengetahui, 'Mengetahui,');
    $sheet->getStyle('D' . $barisMengetahui)->getFont()->setSize(11);
    $sheet->getStyle('D' . $barisMengetahui)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->mergeCells('D' . $barisJabatan . ':E' . $barisJabatan);
    $sheet->setCellValue('D' . $barisJabatan, 'Kepala Sekolah SMK Al-Manshuriyah');
    $sheet->getStyle('D' . $barisJabatan)->getFont()->setBold(true)->setSize(11);
    $sheet->getStyle('D' . $barisJabatan)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    $sheet->mergeCells('D' . $barisNama . ':E' . $barisNama);
    $sheet->setCellValue('D' . $barisNama, 'H. Enung Nuralam, SP.');
    $sheet->getStyle('D' . $barisNama)->getFont()->setBold(true)->setUnderline(true)->setSize(11);
    $sheet->getStyle('D' . $barisNama)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // AUTO SIZE
    foreach (range('A', 'E') as $column) {
        $sheet->getColumnDimension($column)->setAutoSize(true);
    }
    // Kolom D & E dilebarkan cukup untuk blok Mengetahui
    $sheet->getColumnDimension('D')->setAutoSize(false)->setWidth(16);
    $sheet->getColumnDimension('E')->setAutoSize(false)->setWidth(18);

    $filename = 'template_petugas.xlsx';
}


// ======================================================
// OUTPUT
// ======================================================

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');

if (ob_get_length()) {
    ob_end_clean();
}

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');

exit;