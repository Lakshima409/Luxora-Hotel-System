<?php
require_once '../includes/auth.php';
require_role('clerk'); // Only clerks can access this page
require_once '../includes/header.php';

// Validate reservation ID
$reservation_id = filter_input(INPUT_GET, 'reservation_id', FILTER_VALIDATE_INT);

if (!$reservation_id) {
    echo "<div class='alert alert-danger'>Invalid reservation ID.</div>";
    require_once '../includes/footer.php';
    exit;
}

// Fetch checkout details
$stmt = $pdo->prepare("
    SELECT 
        r.reservation_id, r.check_in_date, r.check_out_date, r.actual_checkout,
        c.full_name, c.email, c.phone,
        rm.room_number, rm.room_type,
        b.total_amount, b.payment_method, b.issued_at as billing_date
    FROM reservations r
    JOIN customers c ON r.customer_id = c.customer_id
    JOIN rooms rm ON r.room_id = rm.room_id
    JOIN bills b ON b.reservation_id = r.reservation_id
    WHERE r.reservation_id = ? AND r.reservation_status = 'checked_out'
");
$stmt->execute([$reservation_id]);
$checkout_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$checkout_info) {
    echo "<div class='alert alert-warning'>Checkout not completed for this reservation.</div>";
    require_once '../includes/footer.php';
    exit;
}

// Fetch bill items
$items = $pdo->prepare("
    SELECT description, amount 
    FROM bill_items 
    WHERE bill_id = (
        SELECT bill_id FROM bills WHERE reservation_id = ?
    )
");
$items->execute([$reservation_id]);
$bill_items = $items->fetchAll(PDO::FETCH_ASSOC);

// Calculate stay duration
$check_in = new DateTime($checkout_info['check_in_date']);
$check_out = new DateTime($checkout_info['actual_checkout']);
$stay_duration = $check_out->diff($check_in);
$nights = $stay_duration->days;
if ($stay_duration->h > 12 || ($nights == 0 && $stay_duration->h >= 1)) {
    $nights++; // Count as full night if checkout is late
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout Success - Luxora Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2980b9;
            --success-color: #28a745;
            --light-color: #f8f9fa;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-color);
        }
        
        .success-card {
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            border: none;
            overflow: hidden;
        }
        
        .success-header {
            background-color: var(--success-color);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .detail-card {
            border-left: 4px solid var(--primary-color);
            border-radius: 5px;
        }
        
        .invoice-table th {
            background-color: #f1f1f1;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .total-row {
            font-weight: bold;
            background-color: #f8f9fa;
        }
        
        @media (max-width: 768px) {
            .detail-card {
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container py-4">
        <div class="success-card">
            <div class="success-header">
                <h2><i class="fas fa-check-circle me-2"></i> Checkout Successful</h2>
                <p class="mb-0">Reservation #<?= $checkout_info['reservation_id'] ?> has been completed</p>
            </div>
            
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card detail-card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-user me-2"></i>Guest Information</h5>
                                <p class="card-text">
                                    <strong>Name:</strong> <?= htmlspecialchars($checkout_info['full_name']) ?><br>
                                    <strong>Email:</strong> <?= htmlspecialchars($checkout_info['email']) ?><br>
                                    <strong>Phone:</strong> <?= htmlspecialchars($checkout_info['phone']) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card detail-card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-bed me-2"></i>Stay Details</h5>
                                <p class="card-text">
                                    <strong>Room:</strong> <?= htmlspecialchars($checkout_info['room_number']) ?> (<?= htmlspecialchars($checkout_info['room_type']) ?>)<br>
                                    <strong>Duration:</strong> <?= $nights ?> night<?= $nights != 1 ? 's' : '' ?><br>
                                    <strong>Check-In:</strong> <?= date('M j, Y h:i A', strtotime($checkout_info['check_in_date'])) ?><br>
                                    <strong>Check-Out:</strong> <?= date('M j, Y h:i A', strtotime($checkout_info['actual_checkout'])) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card detail-card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><i class="fas fa-credit-card me-2"></i>Payment Information</h5>
                                <p class="card-text">
                                    <strong>Method:</strong> <?= ucfirst(str_replace('_', ' ', htmlspecialchars($checkout_info['payment_method']))) ?><br>
                                    <strong>Total Amount:</strong> $<?= number_format($checkout_info['total_amount'], 2) ?><br>
                                    <strong>Processed On:</strong> <?= date('M j, Y h:i A', strtotime($checkout_info['billing_date'])) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card bg-light h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                                <h5>Room Status Updated</h5>
                                <p class="mb-0">Room <?= htmlspecialchars($checkout_info['room_number']) ?> has been marked as <strong>available</strong></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <h4 class="mb-3"><i class="fas fa-file-invoice me-2"></i>Invoice Summary</h4>
                <div class="table-responsive">
                    <table class="table invoice-table">
                        <thead>
                            <tr>
                                <th>Description</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bill_items as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['description']) ?></td>
                                <td class="text-end">$<?= number_format($item['amount'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <tr class="total-row">
                                <td><strong>Total</strong></td>
                                <td class="text-end"><strong>$<?= number_format($checkout_info['total_amount'], 2) ?></strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="d-flex flex-column flex-md-row justify-content-between mt-4">
                    <a href="../dashboard.php" class="btn btn-outline-secondary mb-2 mb-md-0">
                        <i class="fas fa-tachometer-alt me-1"></i> Back to Dashboard
                    </a>
                    
                    <div class="btn-group">
                        <a href="../billing/generate_invoice.php?reservation_id=<?= $reservation_id ?>" 
                           class="btn btn-primary me-2">
                           <i class="fas fa-file-pdf me-1"></i> Download Invoice
                        </a>
                        <a href="checkout_form.php" class="btn btn-success">
                            <i class="fas fa-user-check me-1"></i> Check Out Another Guest
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php require_once '../includes/footer.php'; ?>