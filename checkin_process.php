<?php
require_once '../includes/auth_check.php';
require_once '../db.php';
require_once '../config.php';
// Include all necessary middleware files
require_once '../middleware/guest_type_handler.php';
require_once '../middleware/room_status_checker.php';
require_once '../middleware/validate_credit_policy.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reservation_id = $_POST['reservation_id'] ?? null;
    $room_id = $_POST['room_id'] ?? null;
    $special_requests = trim($_POST['special_requests'] ?? '');

    if (!$reservation_id || !$room_id) {
        $_SESSION['error'] = "Invalid reservation or room selection.";
        header("Location: checkin_form.php");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // Validate reservation
        $stmt = $pdo->prepare("
            SELECT r.*, rm.id AS current_room_id 
            FROM reservations r
            JOIN rooms rm ON r.room_id = rm.id
            WHERE r.id = ? AND r.status = 'booked'
            FOR UPDATE
        ");
        $stmt->execute([$reservation_id]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reservation) {
            throw new Exception("Reservation not found or already checked in.");
        }

        // Validate new room
        $stmt = $pdo->prepare("SELECT * FROM rooms WHERE id = ? AND status = 'available' FOR UPDATE");
        $stmt->execute([$room_id]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$room) {
            throw new Exception("Selected room is not available.");
        }

        // Update reservation status and assign new room
        $stmt = $pdo->prepare("
            UPDATE reservations 
            SET status = 'checked_in', 
                room_id = ?, 
                check_in = NOW(),
                special_requests = ?
            WHERE id = ?
        ");
        $stmt->execute([$room_id, $special_requests, $reservation_id]);

        // Update old room back to available (if room changed)
        if ($reservation['current_room_id'] != $room_id) {
            $stmt = $pdo->prepare("UPDATE rooms SET status = 'available' WHERE id = ?");
            $stmt->execute([$reservation['current_room_id']]);
        }

        // Update new room to occupied
        $stmt = $pdo->prepare("UPDATE rooms SET status = 'occupied' WHERE id = ?");
        $stmt->execute([$room_id]);

        // Insert into activity logs
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action_type, description, timestamp)
            VALUES (?, 'checkin', ?, NOW())
        ");
        $userId = $_SESSION['user_id'] ?? $_SESSION['clerk_id'] ?? 0;
        $description = "Checked in reservation #$reservation_id to room {$room['room_number']}";
        $stmt->execute([$userId, $description]);

        $pdo->commit();

        header("Location: checkin_success.php?reservation_id=$reservation_id");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Check-in failed: " . $e->getMessage();
        header("Location: checkin_form.php");
        exit();
    }
} else {
    $_SESSION['error'] = "Unauthorized access.";
    header("Location: checkin_form.php");
    exit();
}
