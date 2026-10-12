<?php
// auto_cancel_cron.php

date_default_timezone_set('Asia/Colombo');
include("../includes/db.php");

// Log start of cron job
echo "[" . date("Y-m-d H:i:s") . "] Auto-cancel cron job running...\n";

// Fetch all 'reserved' reservations made today without credit card and not walk-ins
$sql = "
    SELECT r.reservation_id, g.full_name, r.room_id 
    FROM reservations r
    JOIN guests g ON r.guest_id = g.guest_id
    WHERE r.status = 'reserved'
      AND r.is_walkin = 0
      AND (r.credit_card_number IS NULL OR r.credit_card_number = '')
      AND DATE(r.checkin_date) = CURDATE()
";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while ($res = $result->fetch_assoc()) {
        $reservation_id = $res['reservation_id'];
        $room_id = $res['room_id'];
        $guest_name = $res['full_name'];

        // Cancel reservation
        $conn->query("UPDATE reservations SET status = 'cancelled' WHERE reservation_id = $reservation_id");

        // Mark room as available again
        $conn->query("UPDATE rooms SET status = 'available' WHERE room_id = $room_id");

        // Log action
        $action = "Auto-cancelled reservation #$reservation_id for guest $guest_name due to missing credit card.";
        $log = $conn->prepare("INSERT INTO action_logs (user_id, action_type, description, timestamp) VALUES (?, ?, ?, NOW())");
        $system_user = 0; // 0 = system/cron job
        $type = 'auto_cancel';
        $log->bind_param("iss", $system_user, $type, $action);
        $log->execute();

        echo "Cancelled reservation ID $reservation_id for $guest_name\n";
    }
} else {
    echo "No reservations found to cancel.\n";
}

echo "[" . date("Y-m-d H:i:s") . "] Auto-cancel cron job completed.\n";
?>
