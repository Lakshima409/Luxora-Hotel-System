<?php
session_start();
require_once 'C:/xampp/htdocs/hotel-system/db.php';

// Check if user is logged in and is a clerk
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'clerk') {
    header("Location: login.php");
    exit();
}

// Get occupied rooms
$occupiedRooms = $pdo->query("SELECT r.room_id, r.room_number, r.room_type, 
                             res.reservation_id, res.checkin_date, res.checkout_date,
                             g.guest_id, g.first_name, g.last_name
                             FROM rooms r
                             JOIN reservations res ON r.room_id = res.room_id AND res.status = 'Checked-In'
                             JOIN guests g ON res.guest_id = g.guest_id
                             WHERE r.status = 'Occupied'
                             ORDER BY r.room_number")->fetchAll();

// Handle check-out
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['checkout'])) {
    $reservationId = $_POST['reservation_id'];
    $roomId = $_POST['room_id'];
    $checkoutDate = date('Y-m-d H:i:s');
    
    try {
        $pdo->beginTransaction();
        
        // Update reservation status
        $stmt = $pdo->prepare("UPDATE reservations SET status = 'Checked-Out', checkout_date = ? WHERE reservation_id = ?");
        $stmt->execute([$checkoutDate, $reservationId]);
        
        // Update room status
        $stmt = $pdo->prepare("UPDATE rooms SET status = 'Available' WHERE room_id = ?");
        $stmt->execute([$roomId]);
        
        // Log the check-out action
        $stmt = $pdo->prepare("INSERT INTO action_logs (user_id, action_type, description, timestamp) 
                              VALUES (?, 'checkout', ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], "Checked out guest from reservation #$reservationId"]);
        
        $pdo->commit();
        
        $_SESSION['checkout_success'] = "Guest checked out successfully!";
        header("Location: generate_invoice.php?reservation_id=$reservationId");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error processing check-out: " . $e->getMessage();
    }
}

// Handle room selection
$selectedRoom = null;
if (isset($_GET['room_id'])) {
    $roomId = $_GET['room_id'];
    foreach ($occupiedRooms as $room) {
        if ($room['room_id'] == $roomId) {
            $selectedRoom = $room;
            break;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Luxora Hotel Suite - Guest Check-Out</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2980b9;
            --accent-color: #e74c3c;
            --light-color: #ecf0f1;
            --dark-color: #2c3e50;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
        }
        
        .sidebar {
            background-color: var(--dark-color);
            color: white;
            height: 100vh;
            position: fixed;
            padding-top: 20px;
        }
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            border: none;
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
            font-weight: 600;
        }
        
        .form-section {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .guest-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--light-color);
            margin: 0 auto;
            display: block;
        }
        
        .room-card {
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .room-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }
        
        .room-card.selected {
            border: 2px solid var(--primary-color);
            background-color: rgba(52, 152, 219, 0.1);
        }
        
        .service-item {
            border-bottom: 1px solid #eee;
            padding: 10px 0;
        }
        
        .service-item:last-child {
            border-bottom: none;
        }
        
        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
                overflow: hidden;
            }
            
            .main-content {
                margin-left: 70px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Guest Check-Out</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="clerk_dashboard.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-bed me-2"></i> Occupied Rooms
                            </div>
                            <div class="card-body">
                                <?php if (empty($occupiedRooms)): ?>
                                    <div class="alert alert-info">No occupied rooms found</div>
                                <?php else: ?>
                                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Room</th>
                                                    <th>Guest</th>
                                                    <th>Check-In</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($occupiedRooms as $room): ?>
                                                    <tr class="<?= ($selectedRoom && $selectedRoom['room_id'] == $room['room_id']) ? 'table-primary' : '' ?>">
                                                        <td><?= htmlspecialchars($room['room_number'] . ' (' . $room['room_type'] . ')') ?></td>
                                                        <td><?= htmlspecialchars($room['first_name'] . ' ' . $room['last_name']) ?></td>
                                                        <td><?= date('M j, Y', strtotime($room['checkin_date'])) ?></td>
                                                        <td>
                                                            <a href="checkout.php?room_id=<?= $room['room_id'] ?>" class="btn btn-sm btn-primary">
                                                                Select
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <?php if ($selectedRoom): ?>
                            <form method="POST" action="checkout.php">
                                <input type="hidden" name="reservation_id" value="<?= $selectedRoom['reservation_id'] ?>">
                                <input type="hidden" name="room_id" value="<?= $selectedRoom['room_id'] ?>">
                                
                                <div class="card">
                                    <div class="card-header">
                                        <i class="fas fa-user-circle me-2"></i> Guest Information
                                    </div>
                                    <div class="card-body text-center">
                                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($selectedRoom['first_name'] . '+' . $selectedRoom['last_name']) ?>" class="guest-photo mb-3">
                                        <h4><?= htmlspecialchars($selectedRoom['first_name'] . ' ' . $selectedRoom['last_name']) ?></h4>
                                        <p class="text-muted">
                                            Room: <?= htmlspecialchars($selectedRoom['room_number'] . ' (' . $selectedRoom['room_type'] . ')') ?><br>
                                            Check-In: <?= date('M j, Y h:i A', strtotime($selectedRoom['checkin_date'])) ?><br>
                                            Reservation #<?= $selectedRoom['reservation_id'] ?>
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="card mt-4">
                                    <div class="card-header">
                                        <i class="fas fa-clock me-2"></i> Stay Duration
                                    </div>
                                    <div class="card-body">
                                        <?php
                                        $checkin = new DateTime($selectedRoom['checkin_date']);
                                        $checkout = new DateTime();
                                        $interval = $checkin->diff($checkout);
                                        $nights = $interval->days;
                                        if ($interval->h > 12 || ($interval->days == 0 && $interval->h >= 1)) {
                                            $nights++; // Count as a full night if more than 12 hours or at least 1 hour for same-day
                                        }
                                        ?>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p><strong>Check-In:</strong><br><?= date('M j, Y h:i A', strtotime($selectedRoom['checkin_date'])) ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <p><strong>Check-Out:</strong><br><?= date('M j, Y h:i A') ?></p>
                                            </div>
                                        </div>
                                        <hr>
                                        <h5 class="text-center">Total Stay: <?= $nights ?> night<?= $nights != 1 ? 's' : '' ?></h5>
                                    </div>
                                </div>
                                
                                <div class="card mt-4">
                                    <div class="card-header">
                                        <i class="fas fa-credit-card me-2"></i> Payment Summary
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Payment Method</label>
                                            <select class="form-select" name="payment_method" required>
                                                <option value="">Select payment method</option>
                                                <option value="Cash">Cash</option>
                                                <option value="Credit Card">Credit Card</option>
                                                <option value="Debit Card">Debit Card</option>
                                                <option value="Bank Transfer">Bank Transfer</option>
                                            </select>
                                        </div>
                                        
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="late_checkout" name="late_checkout">
                                            <label class="form-check-label" for="late_checkout">
                                                Late Check-Out (after 12 PM)
                                            </label>
                                        </div>
                                        
                                        <div class="d-grid gap-2">
                                            <button type="submit" name="checkout" class="btn btn-primary btn-lg">
                                                <i class="fas fa-receipt me-1"></i> Generate Invoice & Complete Check-Out
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="card">
                                <div class="card-body text-center">
                                    <i class="fas fa-bed fa-4x text-muted mb-3"></i>
                                    <h4>No Room Selected</h4>
                                    <p class="text-muted">Please select an occupied room from the list to begin check-out process</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>