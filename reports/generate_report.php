<?php
require_once '../includes/auth.php';
require_role('admin');
require_once '../db.php';

$from = $_POST['from_date'] ?? '';
$to = $_POST['to_date'] ?? '';
$type = $_POST['report_type'] ?? '';

if (!$from || !$to || !$type) {
    die("Invalid report parameters.");
}

switch ($type) {
    case 'bookings':
        $stmt = $pdo->prepare("SELECT * FROM reservations WHERE check_in BETWEEN ? AND ?");
        break;
    case 'revenue':
        $stmt = $pdo->prepare("SELECT * FROM bills WHERE created_at BETWEEN ? AND ?");
        break;
    case 'agency':
        $stmt = $pdo->prepare("SELECT * FROM reservations WHERE guest_name LIKE '%Travel Agency%' AND check_in BETWEEN ? AND ?");
        break;
    default:
        die("Unknown report type.");
}

$stmt->execute([$from, $to]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($results);
