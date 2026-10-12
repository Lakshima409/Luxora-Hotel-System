<?php
require_once '../includes/auth_check.php';
require_once '../includes/header.php';
require_once '../db.php';

$reservation_id = $_GET['reservation_id'] ?? null;

if (!$reservation_id) {
    echo "<div class='alert alert-danger'>Invalid reservation ID.</div>";
    require_once '../includes/footer.php';
    exit;
}

// Fetch checked-in reservation
$stmt = $pdo->prepare("SELECT r.check_in_date, r.check_out_date, r.reservation_id,
                              c.full_name, c.email, c.phone,
                              rm.room_number, rm.room_type
                       FROM reservations r
                       JOIN customers c ON r.customer_id = c.customer_id
                       JOIN rooms rm ON r.room_id = rm.room_id
                       WHERE r.reservation_id = ? AND r.reservation_status = 'checked_in'");
$stmt->execute([$reservation_id]);
$reservation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$reservation) {
    echo "<div class='alert alert-warning'>No valid check-in found for this reservation ID.</div>";
    require_once '../includes/footer.php';
    exit;
}
?>

<div class="container mt-5">
    <div class="card shadow p-4">
        <h2 class="text-center text-success mb-4">✅ Check-In Successful</h2>

        <div class="mb-3">
            <strong>Guest Name:</strong> <?= htmlspecialchars($reservation['full_name']) ?><br>
            <strong>Email:</strong> <?= htmlspecialchars($reservation['email']) ?><br>
            <strong>Phone:</strong> <?= htmlspecialchars($reservation['phone']) ?>
        </div>

        <div class="mb-3">
            <strong>Room:</strong> <?= htmlspecialchars($reservation['room_number']) ?> (<?= htmlspecialchars($reservation['room_type']) ?>)<br>
            <strong>Check-In:</strong> <?= htmlspecialchars($reservation['check_in_date']) ?><br>
            <strong>Check-Out:</strong> <?= htmlspecialchars($reservation['check_out_date']) ?>
        </div>

        <div class="alert alert-info">
            The room has been marked as <strong>occupied</strong> and the guest is now checked in.
        </div>

        <div class="text-center mt-4">
            <a href="../index.php" class="btn btn-primary">Back to Dashboard</a>
            <a href="checkin_form.php" class="btn btn-secondary">Check In Another Guest</a>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
