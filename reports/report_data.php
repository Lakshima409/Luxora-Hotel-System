<?php
require_once '../includes/auth.php';
require_role('admin');
require_once '../db.php';

$bookings = $pdo->query("
    SELECT DATE(check_in) as date, COUNT(*) as total
    FROM reservations
    WHERE check_in >= CURDATE() - INTERVAL 30 DAY
    GROUP BY DATE(check_in)
")->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($bookings);
