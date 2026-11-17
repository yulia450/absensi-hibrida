<?php

ob_start();

error_reporting(0);
ini_set('display_errors', 0);

require '../../vendor/autoload.php';
require '../../includes/config.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

$jenis = $_GET['jenis'] ?? 'rekap';

// ── Resolve semester filter ──────────────────────────────────
$sf          = parsePeriodeFilter();
$tahunAjaran = $sf['tahun_ajaran'];
$semester    = $sf['semester'];
$range       = $sf['range'];
$labelPeriode = $sf['label'];

$bulanDari   = $range['bulan_dari'];
$bulanSampai = $range['bulan_sampai'];
$tahunRange  = $range['tahun_mulai'];

$bulanNama = ['','Januari','Februari','Maret','April','Mei','Juni',
              'Juli','Agustus','September','Oktober','November','Desember'];

function styleHeader($sheet, $range, $bgRgb = '1F4E78') {
    $sheet->getStyle($range)->applyFromArray([
        'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
        'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bgRgb]],
        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
    ]);
}

function autosizeColumns($sheet, $from, $to) {
    foreach (range($from, $to) as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
}

try {

    // =========================================================
    // EKSPOR DATA SISWA
    // =========================================================
    if ($jenis === 'siswa') {

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->mergeCells('A1:K1');
        $sheet->setCellValue('A1', 'SMK AL-MANSHURIYAH');
        $sheet->mergeCells('A2:K2');
        $sheet->setCellValue('A2', 'DAFTAR DATA PESERTA DIDIK');

        $sheet->getStyle('A1:K2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(13);

        $headers = ['No.','Nama Lengkap','Alamat Email','No. HP','NIS','NISN','Kelas','Jenis Kelamin','Tanggal Lahir','Nama Orang Tua','No. HP Ortu'];
        $col = 'A';
        foreach ($headers as $h) { $sheet->setCellValue($col.'4', $h); $col++; }
        styleHeader($sheet, 'A4:K4');

        $query = $pdo->query("
            SELECT s.*, u.nama, u.email, u.no_hp, k.nama_kelas
            FROM siswa s
            JOIN users u ON s.user_id = u.id
            LEFT JOIN kelas k ON s.kelas_id = k.id
            ORDER BY k.nama_kelas, u.nama ASC
        ");
        $data = $query->fetchAll(PDO::FETCH_ASSOC);

        $row = 5; $no = 1;
        foreach ($data as $d) {
            $sheet->setCellValue('A'.$row, $no++);
            $sheet->setCellValue('B'.$row, $d['nama']);
            $sheet->setCellValue('C'.$row, $d['email']);
            $sheet->setCellValue('D'.$row, $d['no_hp']);
            $sheet->setCellValue('E'.$row, $d['nis']);
            $sheet->setCellValue('F'.$row, $d['nisn']);
            $sheet->setCellValue('G'.$row, $d['nama_kelas']);
            $sheet->setCellValue('H'.$row, $d['jenis_kelamin']);
            $sheet->setCellValue('I'.$row, $d['tanggal_lahir']);
            $sheet->setCellValue('J'.$row, $d['nama_orang_tua']);
            $sheet->setCellValue('K'.$row, $d['no_hp_ortu']);
            $row++;
        }

        $sheet->getStyle('A4:K'.($row-1))->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
        autosizeColumns($sheet, 'A', 'K');

        $filename = 'Data_Peserta_Didik_'.date('Ymd_His').'.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Cache-Control: max-age=0');
        ob_end_clean();
        (new Xlsx($spreadsheet))->save('php://output');
        exit;
    }

    // =========================================================
    // EKSPOR PER KELAS — setiap kelas jadi sheet tersendiri
    // =========================================================
    if ($jenis === 'perkelas') {

        $kelasList = $pdo->query("SELECT * FROM kelas ORDER BY tingkat, nama_kelas")->fetchAll(PDO::FETCH_ASSOC);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        foreach ($kelasList as $idx => $kelas) {

            $sheetK = $spreadsheet->createSheet($idx);
            $sheetK->setTitle(substr($kelas['nama_kelas'], 0, 31));

            $sheetK->mergeCells('A1:J1');
            $sheetK->setCellValue('A1', 'REKAPITULASI ABSENSI — '.strtoupper($kelas['nama_kelas']));
            $sheetK->mergeCells('A2:J2');
            $sheetK->setCellValue('A2', 'Periode: '.$labelPeriode);

            $sheetK->getStyle('A1:A2')->getFont()->setBold(true)->setSize(13);
            $sheetK->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            $headers = ['No.','NIS','Nama Lengkap','Hadir','Terlambat','Izin','Sakit','Alfa','Total Hari','% Kehadiran'];
            $col = 'A';
            foreach ($headers as $h) { $sheetK->setCellValue($col.'4', $h); $col++; }
            styleHeader($sheetK, 'A4:J4');

            $stmt = $pdo->prepare("
                SELECT s.nis, u.nama,
                    SUM(CASE WHEN a.status_masuk='hadir'     THEN 1 ELSE 0 END) AS hadir,
                    SUM(CASE WHEN a.status_masuk='terlambat' THEN 1 ELSE 0 END) AS terlambat,
                    SUM(CASE WHEN a.status_masuk='izin'      THEN 1 ELSE 0 END) AS izin,
                    SUM(CASE WHEN a.status_masuk='sakit'     THEN 1 ELSE 0 END) AS sakit,
                    SUM(CASE WHEN a.status_masuk='alfa'      THEN 1 ELSE 0 END) AS alfa
                FROM siswa s
                JOIN users u ON s.user_id = u.id
                LEFT JOIN absensi a ON s.id = a.siswa_id
                    AND MONTH(a.tanggal) BETWEEN ? AND ?
                    AND YEAR(a.tanggal) = ?
                WHERE u.status='aktif' AND s.kelas_id=?
                GROUP BY s.id, s.nis, u.nama
                ORDER BY u.nama
            ");
            $stmt->execute([$bulanDari, $bulanSampai, $tahunRange, $kelas['id']]);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $row = 5; $no = 1;
            $totals = ['hadir'=>0,'terlambat'=>0,'izin'=>0,'sakit'=>0,'alfa'=>0];

            foreach ($data as $d) {
                $total  = $d['hadir'] + $d['terlambat'] + $d['izin'] + $d['sakit'] + $d['alfa'];
                $persen = $total > 0 ? round(($d['hadir'] + $d['terlambat']) / $total * 100) : 0;

                $sheetK->setCellValue('A'.$row, $no++);
                $sheetK->setCellValue('B'.$row, $d['nis']);
                $sheetK->setCellValue('C'.$row, $d['nama']);
                $sheetK->setCellValue('D'.$row, $d['hadir']);
                $sheetK->setCellValue('E'.$row, $d['terlambat']);
                $sheetK->setCellValue('F'.$row, $d['izin']);
                $sheetK->setCellValue('G'.$row, $d['sakit']);
                $sheetK->setCellValue('H'.$row, $d['alfa']);
                $sheetK->setCellValue('I'.$row, $total);
                $sheetK->setCellValue('J'.$row, $persen.'%');

                if ($persen < 75) {
                    $sheetK->getStyle('A'.$row.':J'.$row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEE2E2');
                }

                foreach (['hadir','terlambat','izin','sakit','alfa'] as $k) $totals[$k] += $d[$k];
                $row++;
            }

            $totalAll = array_sum($totals);
            $sheetK->setCellValue('C'.$row, 'TOTAL');
            $sheetK->setCellValue('D'.$row, $totals['hadir']);
            $sheetK->setCellValue('E'.$row, $totals['terlambat']);
            $sheetK->setCellValue('F'.$row, $totals['izin']);
            $sheetK->setCellValue('G'.$row, $totals['sakit']);
            $sheetK->setCellValue('H'.$row, $totals['alfa']);
            $sheetK->setCellValue('I'.$row, $totalAll);
            $sheetK->getStyle('A'.$row.':J'.$row)->getFont()->setBold(true);
            $sheetK->getStyle('A'.$row.':J'.$row)->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0F2FE');

            $sheetK->getStyle('A4:J'.$row)->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
            autosizeColumns($sheetK, 'A', 'J');
        }

        $spreadsheet->setActiveSheetIndex(0);
        $semLabel = str_replace(['/', ' '], ['_', '_'], $labelPeriode);
        $namaFile = 'Rekap_PerKelas_'.$semLabel.'_'.date('His').'.xlsx';

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="'.$namaFile.'"');
        header('Cache-Control: max-age=0');
        ob_end_clean();
        (new Xlsx($spreadsheet))->save('php://output');
        exit;
    }

    // =========================================================
    // EKSPOR REKAPITULASI ABSENSI (sesuai filter)
    // =========================================================

    $kelasId    = (int)($_GET['kelas'] ?? 0);
    // [FIX] Sama seperti admin/rekap.php: nama kelas ikut snapshot di
    // periode yang diekspor, dan siswa yang sudah lulus/keluar tetap
    // tampil kalau punya riwayat di periode itu.
    $params     = [
        $bulanDari, $bulanSampai, $tahunRange,
        $bulanDari, $bulanSampai, $tahunRange,
        $bulanDari, $bulanSampai, $tahunRange,
    ];
    $kelasWhere = '';
    if ($kelasId) { $kelasWhere = 'AND s.kelas_id = ?'; $params[] = $kelasId; }

    $stmt = $pdo->prepare("
        SELECT
            s.nis, u.nama,
            COALESCE(
                (SELECT a2.nama_kelas_snapshot FROM absensi a2
                 WHERE a2.siswa_id = s.id AND MONTH(a2.tanggal) BETWEEN ? AND ? AND YEAR(a2.tanggal) = ?
                 AND a2.nama_kelas_snapshot IS NOT NULL
                 ORDER BY a2.tanggal DESC LIMIT 1),
                k.nama_kelas
            ) AS nama_kelas,
            SUM(CASE WHEN a.status_masuk = 'hadir'     THEN 1 ELSE 0 END) AS hadir,
            SUM(CASE WHEN a.status_masuk = 'terlambat' THEN 1 ELSE 0 END) AS terlambat,
            SUM(CASE WHEN a.status_masuk = 'izin'      THEN 1 ELSE 0 END) AS izin,
            SUM(CASE WHEN a.status_masuk = 'sakit'     THEN 1 ELSE 0 END) AS sakit,
            SUM(CASE WHEN a.status_masuk = 'alfa'      THEN 1 ELSE 0 END) AS alfa
        FROM siswa s
        JOIN users u ON s.user_id = u.id
        LEFT JOIN kelas k ON s.kelas_id = k.id
        LEFT JOIN absensi a
            ON s.id = a.siswa_id
            AND MONTH(a.tanggal) BETWEEN ? AND ?
            AND YEAR(a.tanggal) = ?
        WHERE (u.status = 'aktif' OR EXISTS (
            SELECT 1 FROM absensi a3 WHERE a3.siswa_id = s.id
            AND MONTH(a3.tanggal) BETWEEN ? AND ? AND YEAR(a3.tanggal) = ?
        )) $kelasWhere
        GROUP BY s.id, s.nis, u.nama, nama_kelas
        ORDER BY nama_kelas, u.nama
    ");
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $labelKelas = 'Semua Kelas';
    if ($kelasId) {
        $stK = $pdo->prepare("SELECT nama_kelas FROM kelas WHERE id=?");
        $stK->execute([$kelasId]);
        $nK = $stK->fetchColumn();
        if ($nK) $labelKelas = $nK;
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->mergeCells('A1:J1');
    $sheet->setCellValue('A1', 'REKAPITULASI ABSENSI PESERTA DIDIK');
    $sheet->mergeCells('A2:J2');
    $sheet->setCellValue('A2', 'Periode: '.$labelPeriode.' | Kelas: '.$labelKelas);

    $sheet->getStyle('A1:A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    $sheet->getStyle('A2')->getFont()->setSize(11);

    $headers = ['No.','NIS','Nama Lengkap','Kelas','Hadir','Terlambat','Izin','Sakit','Alfa','% Kehadiran'];
    $col = 'A';
    foreach ($headers as $h) { $sheet->setCellValue($col.'4', $h); $col++; }
    styleHeader($sheet, 'A4:J4');

    $row = 5; $no = 1;
    foreach ($data as $d) {
        $total  = $d['hadir'] + $d['terlambat'] + $d['izin'] + $d['sakit'] + $d['alfa'];
        $persen = $total > 0 ? round(($d['hadir'] + $d['terlambat']) / $total * 100) : 0;

        $sheet->setCellValue('A'.$row, $no++);
        $sheet->setCellValue('B'.$row, $d['nis']);
        $sheet->setCellValue('C'.$row, $d['nama']);
        $sheet->setCellValue('D'.$row, $d['nama_kelas'] ?? '-');
        $sheet->setCellValue('E'.$row, $d['hadir']);
        $sheet->setCellValue('F'.$row, $d['terlambat']);
        $sheet->setCellValue('G'.$row, $d['izin']);
        $sheet->setCellValue('H'.$row, $d['sakit']);
        $sheet->setCellValue('I'.$row, $d['alfa']);
        $sheet->setCellValue('J'.$row, $persen.'%');

        if ($persen < 75) {
            $sheet->getStyle('A'.$row.':J'.$row)->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEE2E2');
        }
        $row++;
    }

    $sheet->getStyle('A4:J'.($row-1))->applyFromArray(['borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]]);
    autosizeColumns($sheet, 'A', 'J');

    $semLabel  = str_replace(['/', ' '], ['_', '_'], $labelPeriode);
    $filename  = 'Rekap_Absensi_'.str_replace(' ','_',$labelKelas).'_'.$semLabel.'_'.date('His').'.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    header('Cache-Control: max-age=0');
    ob_end_clean();
    (new Xlsx($spreadsheet))->save('php://output');
    exit;

} catch (Exception $e) {
    ob_end_clean();
    die('Terjadi kesalahan saat memproses ekspor: ' . $e->getMessage());
}
