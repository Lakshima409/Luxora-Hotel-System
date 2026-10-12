<?php
session_start();
require_once('C:/xampp/htdocs/hotel-system/includes/db.php');

// Check if user is logged in and is a clerk
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'clerk') {
    header("Location: login.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $reservationId = $_POST['reservation_id'] ?? null;
    $guestId = $_POST['guest_id'] ?? null;
    $roomId = $_POST['room_id'];
    $checkinDate = date('Y-m-d H:i:s');
    $numGuests = $_POST['num_guests'];
    $specialRequests = $_POST['special_requests'];
    
    try {
        $pdo->beginTransaction();
        
        // Update reservation status
        $stmt = $pdo->prepare("UPDATE reservations SET status = 'Checked-In', checkin_date = ?, room_id = ?, num_guests = ?, special_requests = ? WHERE reservation_id = ?");
        $stmt->execute([$checkinDate, $roomId, $numGuests, $specialRequests, $reservationId]);
        
        // Update room status
        $stmt = $pdo->prepare("UPDATE rooms SET status = 'Occupied' WHERE room_id = ?");
        $stmt->execute([$roomId]);
        
        // Log the check-in action
        $stmt = $pdo->prepare("INSERT INTO action_logs (user_id, action_type, description, timestamp) 
                              VALUES (?, 'checkin', ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], "Checked in guest for reservation #$reservationId"]);
        
        $pdo->commit();
        
        $_SESSION['success'] = "Guest checked in successfully!";
        header("Location: clerk_dashboard.php");
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error processing check-in: " . $e->getMessage();
    }
}

// Get available rooms
$availableRooms = $pdo->query("SELECT * FROM rooms WHERE status = 'Available'")->fetchAll();

// Get reservations for today
$today = date('Y-m-d');
$reservations = $pdo->prepare("SELECT r.*, g.first_name, g.last_name 
                              FROM reservations r
                              JOIN guests g ON r.guest_id = g.guest_id
                              WHERE DATE(r.checkin_date) = ? AND r.status = 'Pending'");
$reservations->execute([$today]);
$reservations = $reservations->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Luxora Hotel Suite - Guest Check-In</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #0c4b33;
            --primary-light: #1a6b4a;
            --primary-dark: #083023;
            --secondary: #d4af37;
            --light: #f8f5f2;
            --dark: #1a1a1a;
            --radius: 6px;
            --shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Montserrat', sans-serif;
            background-color: var(--light);
        }

        .card {
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: none;
        }

        .card-header {
            background-color: var(--primary-dark);
            color: white;
            border-radius: var(--radius) var(--radius) 0 0 !important;
        }

        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .room-card {
            cursor: pointer;
            transition: var(--transition);
        }

        .room-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }

        .room-card.selected {
            border: 2px solid var(--secondary);
            background-color: rgba(212, 175, 55, 0.1);
        }

        .guest-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--light);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include 'includes/sidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Guest Check-In</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="clerk_dashboard.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-search me-2"></i> Find Reservation</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Search by Guest Name or Reservation ID</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="reservationSearch" placeholder="Search...">
                                        <button class="btn btn-outline-secondary" type="button">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Guest</th>
                                                <th>Check-In</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reservations as $reservation): ?>
                                                <tr>
                                                    <td>#<?= $reservation['reservation_id'] ?></td>
                                                    <td><?= htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']) ?></td>
                                                    <td><?= date('M j, Y', strtotime($reservation['checkin_date'])) ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-primary select-reservation" 
                                                                data-reservation-id="<?= $reservation['reservation_id'] ?>"
                                                                data-guest-id="<?= $reservation['guest_id'] ?>"
                                                                data-guest-name="<?= htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']) ?>">
                                                            Select
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($reservations)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">No pending reservations found for today</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-user-plus me-2"></i> Walk-In Guest</h5>
                            </div>
                            <div class="card-body">
                                <p>For guests without a reservation, please create a new reservation first.</p>
                                <a href="new_reservation.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i> New Reservation
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <form id="checkinForm" method="POST" action="checkin.php">
                            <input type="hidden" id="reservation_id" name="reservation_id">
                            <input type="hidden" id="guest_id" name="guest_id">
                            
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-user-circle me-2"></i> Guest Information</h5>
                                </div>
                                <div class="card-body text-center">
                                    <img src="https://ui-avatars.com/api/?name=Guest&background=random&size=120" class="guest-photo mb-3" id="guestPhoto">
                                    <h4 id="guestName">Select a reservation</h4>
                                    <p class="text-muted" id="guestDetails">No guest selected</p>
                                </div>
                            </div>
                            
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-bed me-2"></i> Room Assignment</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Available Rooms</label>
                                        <div class="row">
                                            <?php foreach ($availableRooms as $room): ?>
                                                <div class="col-md-6 mb-3">
                                                    <div class="card room-card" data-room-id="<?= $room['room_id'] ?>">
                                                        <div class="card-body">
                                                            <h5 class="card-title"><?= htmlspecialchars($room['room_number']) ?></h5>
                                                            <p class="card-text">
                                                                <strong>Type:</strong> <?= htmlspecialchars($room['room_type']) ?><br>
                                                                <strong>Rate:</strong> $<?= number_format($room['rate'], 2) ?>/night<br>
                                                                <strong>Capacity:</strong> <?= $room['capacity'] ?> guests
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php if (empty($availableRooms)): ?>
                                                <div class="col-12">
                                                    <div class="alert alert-warning">No available rooms found</div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <input type="hidden" name="room_id" id="selectedRoomId" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-clipboard-list me-2"></i> Check-In Details</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="num_guests" class="form-label">Number of Guests</label>
                                        <input type="number" class="form-control" id="num_guests" name="num_guests" min="1" max="10" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="special_requests" class="form-label">Special Requests</label>
                                        <textarea class="form-control" id="special_requests" name="special_requests" rows="3"></textarea>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-check-circle me-1"></i> Complete Check-In
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Handle reservation selection
            $('.select-reservation').click(function() {
                const reservationId = $(this).data('reservation-id');
                const guestId = $(this).data('guest-id');
                const guestName = $(this).data('guest-name');
                
                // Set form values
                $('#reservation_id').val(reservationId);
                $('#guest_id').val(guestId);
                
                // Update guest display
                $('#guestName').text(guestName);
                $('#guestDetails').text(`Reservation #${reservationId}`);
                $('#guestPhoto').attr('src', `https://ui-avatars.com/api/?name=${encodeURIComponent(guestName)}&background=random&size=120`);
                
                // Highlight selected row
                $('tr').removeClass('table-primary');
                $(this).closest('tr').addClass('table-primary');
            });
            
            // Handle room selection
            $('.room-card').click(function() {
                $('.room-card').removeClass('selected');
                $(this).addClass('selected');
                $('#selectedRoomId').val($(this).data('room-id'));
            });
            
            // Form validation
            $('#checkinForm').submit(function(e) {
                if (!$('#selectedRoomId').val()) {
                    e.preventDefault();
                    alert('Please select a room');
                    return false;
                }
                return true;
            });
            
            // Search functionality
            $('#reservationSearch').keyup(function() {
                const searchText = $(this).val().toLowerCase();
                $('tbody tr').each(function() {
                    const rowText = $(this).text().toLowerCase();
                    $(this).toggle(rowText.includes(searchText));
                });
            });
        });
    </script>
</body>
</html>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Luxora Hotel Suite - Guest Check-In</title>
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
                    <h1 class="h2">Guest Check-In</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="clerk_dashboard.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                        </a>
                    </div>
                </div>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($_GET['success']) ?></div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-search me-2"></i> Find Reservation
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Search by Guest Name or Reservation ID</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="reservationSearch" placeholder="Search...">
                                        <button class="btn btn-outline-secondary" type="button">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Guest</th>
                                                <th>Check-In</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reservations as $reservation): ?>
                                                <tr>
                                                    <td>#<?= $reservation['reservation_id'] ?></td>
                                                    <td><?= htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']) ?></td>
                                                    <td><?= date('M j, Y h:i A', strtotime($reservation['checkin_date'])) ?></td>
                                                    <td>
                                                        <button class="btn btn-sm btn-primary select-reservation" 
                                                                data-reservation-id="<?= $reservation['reservation_id'] ?>"
                                                                data-guest-id="<?= $reservation['guest_id'] ?>">
                                                            Select
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <?php if (empty($reservations)): ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">No pending reservations found for today</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mt-4">
                            <div class="card-header">
                                <i class="fas fa-user-plus me-2"></i> Walk-In Guest
                            </div>
                            <div class="card-body">
                                <p>For guests without a reservation, please create a new reservation first.</p>
                                <a href="new_reservation.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i> New Reservation
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <form id="checkinForm" method="POST" action="checkin.php">
                            <input type="hidden" id="reservation_id" name="reservation_id">
                            <input type="hidden" id="guest_id" name="guest_id">
                            
                            <div class="card">
                                <div class="card-header">
                                    <i class="fas fa-user-circle me-2"></i> Guest Information
                                </div>
                                <div class="card-body text-center">
                                    <img src="https://ui-avatars.com/api/?name=Guest&background=random" class="guest-photo mb-3" id="guestPhoto">
                                    <h4 id="guestName">Select a reservation</h4>
                                    <p class="text-muted" id="guestDetails">No guest selected</p>
                                </div>
                            </div>
                            
                            <div class="card mt-4">
                                <div class="card-header">
                                    <i class="fas fa-bed me-2"></i> Room Assignment
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Available Rooms</label>
                                        <div class="row">
                                            <?php foreach ($availableRooms as $room): ?>
                                                <div class="col-md-6 mb-3">
                                                    <div class="card room-card" data-room-id="<?= $room['room_id'] ?>">
                                                        <div class="card-body">
                                                            <h5 class="card-title"><?= htmlspecialchars($room['room_number']) ?></h5>
                                                            <p class="card-text">
                                                                <strong>Type:</strong> <?= htmlspecialchars($room['room_type']) ?><br>
                                                                <strong>Rate:</strong> $<?= number_format($room['rate'], 2) ?>/night<br>
                                                                <strong>Capacity:</strong> <?= $room['capacity'] ?> guests
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php if (empty($availableRooms)): ?>
                                                <div class="col-12">
                                                    <div class="alert alert-warning">No available rooms found</div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <input type="hidden" name="room_id" id="selectedRoomId" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card mt-4">
                                <div class="card-header">
                                    <i class="fas fa-clipboard-list me-2"></i> Check-In Details
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="num_guests" class="form-label">Number of Guests</label>
                                        <input type="number" class="form-control" id="num_guests" name="num_guests" min="1" max="10" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="special_requests" class="form-label">Special Requests</label>
                                        <textarea class="form-control" id="special_requests" name="special_requests" rows="3"></textarea>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-primary btn-lg">
                                            <i class="fas fa-check-circle me-1"></i> Complete Check-In
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Handle reservation selection
            $('.select-reservation').click(function() {
                const reservationId = $(this).data('reservation-id');
                const guestId = $(this).data('guest-id');
                
                // Set form values
                $('#reservation_id').val(reservationId);
                $('#guest_id').val(guestId);
                
                // Update guest display (in a real app, you'd fetch this from the server)
                const guestName = $(this).closest('tr').find('td:nth-child(2)').text();
                $('#guestName').text(guestName);
                $('#guestDetails').text(`Reservation #${reservationId}`);
                $('#guestPhoto').attr('src', `https://ui-avatars.com/api/?name=${encodeURIComponent(guestName)}&background=random`);
                
                // Highlight selected row
                $('tr').removeClass('table-primary');
                $(this).closest('tr').addClass('table-primary');
            });
            
            // Handle room selection
            $('.room-card').click(function() {
                $('.room-card').removeClass('selected');
                $(this).addClass('selected');
                $('#selectedRoomId').val($(this).data('room-id'));
            });
            
            // Form validation
            $('#checkinForm').submit(function(e) {
                if (!$('#selectedRoomId').val()) {
                    e.preventDefault();
                    alert('Please select a room');
                    return false;
                }
                return true;
            });
            
            // Search functionality
            $('#reservationSearch').keyup(function() {
                const searchText = $(this).val().toLowerCase();
                $('tbody tr').each(function() {
                    const rowText = $(this).text().toLowerCase();
                    $(this).toggle(rowText.includes(searchText));
                });
            });
        });
    </script>
</body>
</html>