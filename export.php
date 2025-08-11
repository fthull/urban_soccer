<?php
// export_excel.php
require 'vendor/autoload.php';
require 'conn.php'; // Sesuaikan dengan lokasi file koneksi database Anda

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Query data
$query = "SELECT * FROM booking where status = 'booked'";
$result = $conn->query($query);

// Buat spreadsheet baru
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Header kolom
$headers = ['No.', 'Nama', 'No HP', 'Waktu']; // Sesuaikan dengan kolom Anda
$col = 'A';
foreach ($headers as $header) {
    $sheet->setCellValue($col.'1', $header);
    $col++;
}

// Isi data
$row = 2;
while($data = $result->fetch_assoc()) {
$sheet->setCellValue('A'.$row, $row - 1);
    $sheet->setCellValue('B'.$row, $data['nama']);
    $sheet->setCellValue('C'.$row, $data['no_hp']);
    $sheet->setCellValue('D'.$row, $data['waktu']);
    $row++;
}

// Download file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="data_export_'.date('Ymd').'.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>