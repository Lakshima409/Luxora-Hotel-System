<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}
include 'db.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid reservation ID.";
    header("Location: reservations.php");
    exit();
}

$id = intval($_GET['id']);

$query = mysqli_query($conn, "SELECT * FROM reservations WHERE id = $id");

if (mysqli_num_rows($query) == 0) {
    $_SESSION['error'] = "Reservation not found.";
    header("Location: reservations.php");
    exit();
}

$reservation = mysqli_fetch_assoc($query);

// Check if bill already exists
$bill_query = mysqli_query($conn, "SELECT id FROM bills WHERE reservation_id = $id");
$has_bill = mysqli_num_rows($bill_query) > 0;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reservation Details</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin-top: 30px;
        }
        .detail-table th {
            width: 30%;
            background-color: #f8f9fa;
        }
        .status-booked { color: #28a745; font-weight: bold; }
        .status-checked_in { color: #007bff; font-weight: bold; }
        .status-checked_out { color: #6c757d; font-weight: bold; }
        .status-cancelled { color: #dc3545; font-weight: bold; }
        .action-buttons .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-calendar-alt me-2"></i>Reservation Details</h2>
        <a href="reservations.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Reservations
        </a>
    </div>

    <table class="table table-bordered detail-table">
        <tr>
            <th>ID</th>
            <td><?= $reservation['id'] ?></td>
        </tr>
        <tr>
            <th>Customer Name</th>
            <td><?= htmlspecialchars($reservation['customer_name']) ?></td>
        </tr>
        <tr>
            <th>Email</th>
            <td><?= htmlspecialchars($reservation['email']) ?></td>
        </tr>
        <tr>
            <th>Phone</th>
            <td><?= htmlspecialchars($reservation['phone']) ?></td>
        </tr>
        <tr>
            <th>Room</th>
            <td><?= htmlspecialchars($reservation['room_name']) ?></td>
        </tr>
        <tr>
            <th>Check-in Date</th>
            <td><?= date('M j, Y', strtotime($reservation['checkin_date'])) ?></td>
        </tr>
        <tr>
            <th>Check-out Date</th>
            <td><?= date('M j, Y', strtotime($reservation['checkout_date'])) ?></td>
        </tr>
        <tr>
            <th>Status</th>
            <td class="status-<?= strtolower(str_replace(' ', '_', $reservation['status'])) ?>">
                <?= $reservation['status'] ?>
            </td>
        </tr>
        <tr>
            <th>Notes</th>
            <td><?= nl2br(htmlspecialchars($reservation['notes'])) ?></td>
        </tr>
    </table>

    <div class="action-buttons mt-4">
        <?php if ($reservation['status'] === 'checked_in' && !$has_bill): ?>
            <a href="bill_generator.php?reservation_id=<?= $id ?>" class="btn btn-success">
                <i class="fas fa-file-invoice-dollar me-1"></i> Generate Bill
            </a>
        <?php elseif ($has_bill): ?>
            <a href="invoice.php?reservation_id=<?= $id ?>" class="btn btn-primary">
                <i class="fas fa-file-invoice me-1"></i> View Invoice
            </a>
        <?php endif; ?>
        
        <?php if ($reservation['status'] === 'booked'): ?>
            <a href="checkin_process.php?id=<?= $id ?>" class="btn btn-info">
                <i class="fas fa-sign-in-alt me-1"></i> Check In
            </a>
        <?php endif; ?>
        
        <a href="update_reservation.php?id=<?= $id ?>" class="btn btn-warning">
            <i class="fas fa-edit me-1"></i> Edit
        </a>
        
        <?php if ($reservation['status'] !== 'cancelled' && $reservation['status'] !== 'checked_out'): ?>
            <form method="POST" action="cancel_reservation.php" onsubmit="return confirm('Are you sure you want to cancel this reservation?');" style="display:inline;">
                <input type="hidden" name="id" value="<?= $id ?>">
                <button type="submit" class="btn btn-danger">
                    <i class="fas fa-times me-1"></i> Cancel
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
