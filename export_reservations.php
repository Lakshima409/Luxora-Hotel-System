<?php
session_start();
require_once 'auth.php';
require_clerk(); // Ensure only clerks can access this page
require 'vendor/autoload.php';
require 'db.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Fetch reservation data with more details
$query = "
    SELECT r.id, r.guest_name, r.room_number, rm.room_type, 
           r.check_in, r.check_out, r.status, r.special_requests,
           r.created_at, r.updated_at
    FROM reservations r
    JOIN rooms rm ON r.room_number = rm.room_number
    ORDER BY r.check_in DESC
";
$result = mysqli_query($conn, $query);
if (!$result) {
    die('Database query failed: ' . mysqli_error($conn));
}

// Create new spreadsheet
$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

// Set headers with more columns
$headers = [
    'ID', 'Guest Name', 'Room Number', 'Room Type', 
    'Check-in Date', 'Check-out Date', 'Status',
    'Special Requests', 'Created At', 'Last Updated'
];
$sheet->fromArray($headers, null, 'A1');

// Fill data
$rowNum = 2;
while ($row = mysqli_fetch_assoc($result)) {
    $sheet->setCellValue("A$rowNum", $row['id']);
    $sheet->setCellValue("B$rowNum", $row['guest_name']);
    $sheet->setCellValue("C$rowNum", $row['room_number']);
    $sheet->setCellValue("D$rowNum", $row['room_type']);
    $sheet->setCellValue("E$rowNum", $row['check_in']);
    $sheet->setCellValue("F$rowNum", $row['check_out']);
    $sheet->setCellValue("G$rowNum", ucfirst(str_replace('_', ' ', $row['status'])));
    $sheet->setCellValue("H$rowNum", $row['special_requests']);
    $sheet->setCellValue("I$rowNum", $row['created_at']);
    $sheet->setCellValue("J$rowNum", $row['updated_at']);
    $rowNum++;
}

// Auto filter and column width
$sheet->setAutoFilter("A1:J1");
foreach (range('A', 'J') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Format date columns
$sheet->getStyle('E2:E'.$rowNum)
      ->getNumberFormat()
      ->setFormatCode('yyyy-mm-dd hh:mm:ss');
$sheet->getStyle('F2:F'.$rowNum)
      ->getNumberFormat()
      ->setFormatCode('yyyy-mm-dd hh:mm:ss');
$sheet->getStyle('I2:I'.$rowNum)
      ->getNumberFormat()
      ->setFormatCode('yyyy-mm-dd hh:mm:ss');
$sheet->getStyle('J2:J'.$rowNum)
      ->getNumberFormat()
      ->setFormatCode('yyyy-mm-dd hh:mm:ss');

// Set title
$sheet->setTitle('Reservations Export');

// Output to browser
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="luxora_reservations_'.date('Y-m-d').'.xlsx"');
header('Cache-Control: max-age=0');

if (ob_get_length()) {
    ob_end_clean();
}

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;