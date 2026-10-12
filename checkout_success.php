<?php
require_once '../includes/auth_check.php';
require_once '../includes/header.php';
require_once '../db.php';

$reservation_id = filter_input(INPUT_GET, 'reservation_id', FILTER_VALIDATE_INT);

if (!$reservation_id) {
    echo "<div class='alert alert-danger'>Invalid reservation ID.</div>";
    require_once '../includes/footer.php';
    exit;
}

// Verify checkout was completed
$stmt = $pdo->prepare("
    SELECT 
        r.status as reservation_status,
        c.full_name, c.email,
        rm.room_number, rm.room_type,
        r.check_in_date, r.check_out_date, r.actual_checkout,
        b.total_amount, b.payment_method, b.created_at as billing_date
    FROM reservations r
    JOIN customers c ON r.customer_id = c.customer_id
    JOIN rooms rm ON r.room_id = rm.room_id
    JOIN bills b ON b.reservation_id = r.reservation_id
    WHERE r.reservation_id = ?
");
$stmt->execute([$reservation_id]);
$info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$info || $info['reservation_status'] != 'Checked-Out') {
    echo "<div class='alert alert-warning'>Checkout not completed for this reservation.</div>";
    require_once '../includes/footer.php';
    exit;
}

// Get bill items
$items = $pdo->query("
    SELECT description, amount 
    FROM bill_items 
    WHERE bill_id = (
        SELECT bill_id FROM bills WHERE reservation_id = $reservation_id
    )
")->fetchAll();
?>

<div class="container mt-5">
    <div class="card shadow p-4">
        <h2 class="text-center text-success mb-4">✅ Checkout Successful</h2>
        
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <h5>Guest Information</h5>
                    <strong>Name:</strong> <?= htmlspecialchars($info['full_name']) ?><br>
                    <strong>Email:</strong> <?= htmlspecialchars($info['email']) ?><br>
                </div>
                
                <div class="mb-3">
                    <h5>Stay Details</h5>
                    <strong>Room:</strong> <?= htmlspecialchars($info['room_number']) ?> (<?= htmlspecialchars($info['room_type']) ?>)<br>
                    <strong>Check-In:</strong> <?= date('M j, Y h:i A', strtotime($info['check_in_date'])) ?><br>
                    <strong>Check-Out:</strong> <?= date('M j, Y h:i A', strtotime($info['actual_checkout'])) ?>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="mb-3">
                    <h5>Payment Information</h5>
                    <strong>Payment Method:</strong> <?= ucfirst(htmlspecialchars($info['payment_method'])) ?><br>
                    <strong>Total Amount:</strong> $<?= number_format($info['total_amount'], 2) ?><br>
                    <strong>Processed On:</strong> <?= date('M j, Y h:i A', strtotime($info['billing_date'])) ?>
                </div>
                
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> 
                    Room has been marked as <strong>available</strong> for future bookings.
                </div>
            </div>
        </div>
        
        <div class="mt-4">
            <h5>Invoice Details</h5>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Description</th>
                        <th class="text-end">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['description']) ?></td>
                        <td class="text-end">$<?= number_format($item['amount'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="table-active">
                        <td><strong>Total</strong></td>
                        <td class="text-end"><strong>$<?= number_format($info['total_amount'], 2) ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="text-center mt-4">
            <a href="../billing/generate_invoice.php?reservation_id=<?= $reservation_id ?>" 
               class="btn btn-primary me-2">
               <i class="fas fa-file-invoice me-1"></i> Generate Full Invoice
            </a>
            <a href="../dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-tachometer-alt me-1"></i> Back to Dashboard
            </a>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>