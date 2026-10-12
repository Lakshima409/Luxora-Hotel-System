<?php
require_once '../includes/auth.php';
require_role('admin');
require_once '../db.php';

$report = $_GET['type'] ?? 'bookings';
header("Content-Type: text/csv");
header("Content-Disposition: attachment; filename=$report.csv");

$output = fopen("php://output", "w");
fputcsv($output, ['ID', 'Guest', 'Check In', 'Check Out', 'Status']);

$rows = $pdo->query("SELECT id, guest_name, check_in, check_out, status FROM reservations")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    fputcsv($output, $row);
}
fclose($output);
exit();
