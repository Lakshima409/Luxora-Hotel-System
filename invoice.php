<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: admin_login.php");
    exit();
}
require_once 'db.php';

// Get reservation ID from query string
if (!isset($_GET['reservation_id'])) {
    header("Location: reservations.php");
    exit();
}

$reservation_id = $_GET['reservation_id'];

// Fetch reservation details
$stmt = $conn->prepare("SELECT r.*, b.id as bill_id, b.total_amount, b.payment_method, b.issued_at 
                       FROM reservations r
                       LEFT JOIN bills b ON r.id = b.reservation_id
                       WHERE r.id = ?");
$stmt->bind_param("i", $reservation_id);
$stmt->execute();
$result = $stmt->get_result();
$reservation = $result->fetch_assoc();

if (!$reservation) {
    header("Location: reservations.php");
    exit();
}

// Fetch bill items
$stmt = $conn->prepare("SELECT description, amount FROM bill_items WHERE bill_id = ?");
$stmt->bind_param("i", $reservation['bill_id']);
$stmt->execute();
$result = $stmt->get_result();
$bill_items = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
        .invoice-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 30px;
            background-color: white;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }
        .invoice-header {
            border-bottom: 2px solid #ff6f61;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        .hotel-logo {
            max-width: 150px;
        }
        .invoice-title {
            color: #ff6f61;
            font-weight: 700;
        }
        .table th {
            background-color: #f1f1f1;
        }
        .total-row {
            font-weight: bold;
            background-color: rgba(255, 111, 97, 0.1);
        }
        .signature-line {
            border-top: 1px dashed #ccc;
            margin-top: 80px;
            padding-top: 10px;
        }
        @media print {
            body {
                background-color: white;
            }
            .invoice-container {
                box-shadow: none;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
<div class="container py-5">
    <div class="invoice-container">
        <!-- Invoice Header -->
        <div class="invoice-header">
            <div class="row">
                <div class="col-md-6">
                    <h2 class="invoice-title">INVOICE</h2>
                    <p class="mb-1"><strong>Invoice #:</strong> <?= str_pad($reservation['bill_id'], 6, '0', STR_PAD_LEFT) ?></p>
                    <p class="mb-1"><strong>Date:</strong> <?= date('M j, Y', strtotime($reservation['issued_at'])) ?></p>
                </div>
                <div class="col-md-6 text-end">
                    <h4>Luxora Hotel Suite</h4>
                    <p>123 Ocean View Drive<br>
                    Colombo, Sri Lanka<br>
                    Phone: +94 11 234 5678<br>
                    Email: info@luxorahotel.com</p>
                </div>
            </div>
        </div>

        <!-- Guest Information -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h5>Guest Information</h5>
                <p>
                    <strong><?= htmlspecialchars($reservation['guest_name']) ?></strong><br>
                    <?= htmlspecialchars($reservation['email']) ?><br>
                    Phone: <?= htmlspecialchars($reservation['phone']) ?><br>
                    Room: <?= htmlspecialchars($reservation['room_number'] . ' (' . $reservation['room_type'] . ')') ?>
                </p>
            </div>
            <div class="col-md-6">
                <h5>Reservation Details</h5>
                <p>
                    <strong>Check-In:</strong> <?= date('M j, Y', strtotime($reservation['check_in'])) ?><br>
                    <strong>Check-Out:</strong> <?= date('M j, Y', strtotime($reservation['check_out'])) ?><br>
                    <strong>Status:</strong> <?= ucfirst(str_replace('_', ' ', $reservation['status'])) ?>
                </p>
            </div>
        </div>

        <!-- Bill Items -->
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="text-end">Amount (USD)</th>
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
                    <td class="text-end"><strong>TOTAL</strong></td>
                    <td class="text-end"><strong>$<?= number_format($reservation['total_amount'], 2) ?></strong></td>
                </tr>
                <tr>
                    <td class="text-end"><strong>Payment Method</strong></td>
                    <td class="text-end"><?= ucfirst(str_replace('_', ' ', $reservation['payment_method'])) ?></td>
                </tr>
            </tbody>
        </table>

        <!-- Signature -->
        <div class="row mt-5">
            <div class="col-md-6">
                <div class="signature-line">
                    <p>Guest Signature</p>
                </div>
            </div>
            <div class="col-md-6 text-end">
                <div class="signature-line">
                    <p>Authorized Signature</p>
                </div>
            </div>
        </div>

        <!-- Thank You Note -->
        <div class="text-center mt-4">
            <p>Thank you for staying with us! We hope to see you again soon.</p>
        </div>
    </div>

    <!-- Print Button -->
    <div class="text-center mt-3 no-print">
        <button onclick="window.print()" class="btn btn-primary me-2">
            <i class="fas fa-print me-1"></i> Print Invoice
        </button>
        <a href="reservations.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-1"></i> Back to Reservations
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>