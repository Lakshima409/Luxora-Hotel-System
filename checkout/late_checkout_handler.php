<?php
require_once '../db.php'; // use your PDO-based DB connection

date_default_timezone_set('Asia/Colombo');
$today = (new DateTime())->format('Y-m-d');

// Fetch reservations that are still checked in but past their check-out date
$stmt = $pdo->prepare("SELECT reservation_id FROM reservations 
                       WHERE status = 'checked_in' AND check_out < ?");
$stmt->execute([$today]);

$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($reservations as $row) {
    $res_id = $row['reservation_id'];

    // Add late fee to billing, or log for review (optional)

    // You may not have a 'late_checkout' status, so log it or notify
    echo "Reservation ID $res_id has passed the checkout date (late checkout).\n";

    // You could also insert a late fee record here or notify admin
}
?>
