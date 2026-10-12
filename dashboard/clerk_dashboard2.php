<?php
session_start();
require_once 'auth.php';
require_clerk(); // Ensure only clerks can access this page
require_once 'db.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Luxora Hotel Suite - Clerk Dashboard</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* All previous CSS styles remain exactly the same */
        /* ... */
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container header-container">
            <a href="clerk_dashboard.php" class="logo">Luxora<span>Hotel Suite</span></a>
            <div class="user-menu">
                <div class="user-info">
                    <div class="user-avatar"><?= substr($_SESSION['username'], 0, 2) ?></div>
                    <span><?= htmlspecialchars($_SESSION['username']) ?> (Clerk)</span>
                </div>
                <a href="logout.php" class="btn btn-secondary">Logout</a>
            </div>
        </div>
    </header>

    <!-- Dashboard Layout -->
    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <ul class="sidebar-menu">
                <li class="menu-item"><a href="clerk_dashboard.php" class="menu-link active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li class="menu-item"><a href="clerk_reservations.php" class="menu-link"><i class="fas fa-calendar-check"></i> Reservations</a></li>
                <li class="menu-item"><a href="clerk_checkin.php" class="menu-link"><i class="fas fa-sign-in-alt"></i> Check-In</a></li>
                <li class="menu-item"><a href="clerk_checkout.php" class="menu-link"><i class="fas fa-sign-out-alt"></i> Check-Out</a></li>
                <li class="menu-item"><a href="clerk_invoice.php" class="menu-link"><i class="fas fa-file-invoice-dollar"></i> Invoices</a></li>
                <li class="menu-item"><a href="clerk_reports.php" class="menu-link"><i class="fas fa-chart-bar"></i> Reports</a></li>
            </ul>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title">Clerk Dashboard</h1>
                <div>
                    <button class="btn btn-sm"><i class="fas fa-calendar-alt"></i> <?= date('F j, Y') ?></button>
                    <a href="export_reservations.php" class="btn btn-sm" style="margin-left: 10px;">
                        <i class="fas fa-file-export"></i> Export Reservations
                    </a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-cards">
                <?php
                // Get today's stats
                $today = date('Y-m-d');
                $checkins = $conn->query("SELECT COUNT(*) FROM reservations WHERE DATE(check_in) = '$today'")->fetch_row()[0];
                $checkouts = $conn->query("SELECT COUNT(*) FROM reservations WHERE DATE(check_out) = '$today'")->fetch_row()[0];
                $available_rooms = $conn->query("SELECT COUNT(*) FROM rooms WHERE status = 'available'")->fetch_row()[0];
                $pending_reservations = $conn->query("SELECT COUNT(*) FROM reservations WHERE status = 'pending'")->fetch_row()[0];
                ?>
                <div class="stat-card">
                    <h3>Today's Check-ins</h3>
                    <div class="value"><?= $checkins ?></div>
                    <div class="change positive"><i class="fas fa-arrow-up"></i> View Arrivals</div>
                </div>
                <div class="stat-card">
                    <h3>Today's Check-outs</h3>
                    <div class="value"><?= $checkouts ?></div>
                    <div class="change negative"><i class="fas fa-arrow-down"></i> View Departures</div>
                </div>
                <div class="stat-card">
                    <h3>Available Rooms</h3>
                    <div class="value"><?= $available_rooms ?></div>
                    <div class="change positive"><i class="fas fa-arrow-up"></i> View Rooms</div>
                </div>
                <div class="stat-card">
                    <h3>Pending Reservations</h3>
                    <div class="value"><?= $pending_reservations ?></div>
                    <div class="change negative"><i class="fas fa-arrow-down"></i> View Reservations</div>
                </div>
            </div>

            <!-- Today's Arrivals and Departures -->
            <div class="arrivals-departures">
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title"><i class="fas fa-user-clock"></i> Today's Arrivals</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="status-table">
                            <thead>
                                <tr>
                                    <th>Guest</th>
                                    <th>Room</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $arrivals = $conn->query("
                                    SELECT r.id, r.guest_name, r.room_number, rm.room_type, r.check_in, r.status 
                                    FROM reservations r
                                    JOIN rooms rm ON r.room_number = rm.room_number
                                    WHERE DATE(r.check_in) = '$today'
                                    ORDER BY r.check_in
                                ");
                                
                                while ($arrival = $arrivals->fetch_assoc()):
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($arrival['guest_name']) ?></td>
                                    <td><?= htmlspecialchars($arrival['room_number']) ?> (<?= htmlspecialchars($arrival['room_type']) ?>)</td>
                                    <td><?= date('g:i A', strtotime($arrival['check_in'])) ?></td>
                                    <td>
                                        <span class="status-badge <?= 
                                            $arrival['status'] == 'checked_in' ? 'badge-success' : 
                                            ($arrival['status'] == 'pending' ? 'badge-warning' : 'badge-danger') 
                                        ?>">
                                            <?= ucfirst(str_replace('_', ' ', $arrival['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="clerk_checkin.php?id=<?= $arrival['id'] ?>" class="btn btn-secondary btn-sm">
                                            <?= $arrival['status'] == 'checked_in' ? 'Details' : 'Check-In' ?>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title"><i class="fas fa-user-check"></i> Today's Departures</h2>
                    </div>
                    <div class="table-responsive">
                        <table class="status-table">
                            <thead>
                                <tr>
                                    <th>Guest</th>
                                    <th>Room</th>
                                    <th>Time</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $departures = $conn->query("
                                    SELECT r.id, r.guest_name, r.room_number, rm.room_type, r.check_out, r.status 
                                    FROM reservations r
                                    JOIN rooms rm ON r.room_number = rm.room_number
                                    WHERE DATE(r.check_out) = '$today'
                                    ORDER BY r.check_out
                                ");
                                
                                while ($departure = $departures->fetch_assoc()):
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($departure['guest_name']) ?></td>
                                    <td><?= htmlspecialchars($departure['room_number']) ?> (<?= htmlspecialchars($departure['room_type']) ?>)</td>
                                    <td><?= date('g:i A', strtotime($departure['check_out'])) ?></td>
                                    <td>
                                        <span class="status-badge <?= 
                                            $departure['status'] == 'checked_out' ? 'badge-success' : 
                                            ($departure['status'] == 'checked_in' ? 'badge-warning' : 'badge-danger') 
                                        ?>">
                                            <?= ucfirst(str_replace('_', ' ', $departure['status'])) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?= $departure['status'] == 'checked_out' ? 'clerk_invoice.php?id='.$departure['id'] : 'clerk_checkout.php?id='.$departure['id'] ?>" 
                                           class="btn btn-secondary btn-sm">
                                            <?= $departure['status'] == 'checked_out' ? 'Invoice' : 'Check-Out' ?>
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-bolt"></i> Quick Actions</h2>
                </div>
                <div class="card-body">
                    <div class="quick-actions">
                        <a href="clerk_reservations.php" class="action-card">
                            <i class="fas fa-plus"></i>
                            <h3>New Reservation</h3>
                        </a>
                        <a href="clerk_checkin.php" class="action-card">
                            <i class="fas fa-sign-in-alt"></i>
                            <h3>Check-In Guest</h3>
                        </a>
                        <a href="clerk_checkout.php" class="action-card">
                            <i class="fas fa-sign-out-alt"></i>
                            <h3>Check-Out Guest</h3>
                        </a>
                        <a href="export_reservations.php" class="action-card">
                            <i class="fas fa-file-export"></i>
                            <h3>Export Data</h3>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Room Status Overview -->
            <div class="card room-status">
                <div class="card-header">
                    <h2 class="card-title"><i class="fas fa-bed"></i> Room Status Overview</h2>
                </div>
                <div class="table-responsive">
                    <table class="room-table">
                        <thead>
                            <tr>
                                <th>Room No.</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Guest</th>
                                <th>Check-Out</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $rooms = $conn->query("
                                SELECT r.room_number, r.room_type, r.status, 
                                       res.guest_name, res.check_out
                                FROM rooms r
                                LEFT JOIN reservations res ON r.room_number = res.room_number 
                                    AND res.status = 'checked_in'
                                ORDER BY r.room_number
                            ");
                            
                            while ($room = $rooms->fetch_assoc()):
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($room['room_number']) ?></td>
                                <td><?= htmlspecialchars($room['room_type']) ?></td>
                                <td>
                                    <span class="status-indicator 
                                        status-<?= 
                                            $room['status'] == 'available' ? 'available' : 
                                            ($room['status'] == 'occupied' ? 'occupied' : 
                                            ($room['status'] == 'reserved' ? 'reserved' : 'maintenance'))
                                        ?>">
                                    </span>
                                    <?= ucfirst($room['status']) ?>
                                </td>
                                <td><?= $room['guest_name'] ? htmlspecialchars($room['guest_name']) : '-' ?></td>
                                <td><?= $room['check_out'] ? date('M j, Y', strtotime($room['check_out'])) : '-' ?></td>
                                <td>
                                    <?php if ($room['status'] == 'available'): ?>
                                        <a href="clerk_checkin.php?room=<?= $room['room_number'] ?>" class="btn btn-secondary btn-sm">Check-In</a>
                                    <?php elseif ($room['status'] == 'occupied'): ?>
                                        <a href="clerk_checkout.php?room=<?= $room['room_number'] ?>" class="btn btn-secondary btn-sm">Check-Out</a>
                                    <?php else: ?>
                                        <a href="clerk_reservations.php?room=<?= $room['room_number'] ?>" class="btn btn-secondary btn-sm">Details</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Highlight active menu item
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = window.location.pathname.split('/').pop();
            const menuLinks = document.querySelectorAll('.menu-link');
            
            menuLinks.forEach(link => {
                if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active');
                }
            });
            
            // Auto-refresh dashboard every 5 minutes
            setTimeout(function() {
                window.location.reload();
            }, 300000);
        });
        
        // Make quick action cards clickable
        document.querySelectorAll('.action-card').forEach(card => {
            card.addEventListener('click', function() {
                window.location.href = this.getAttribute('href');
            });
        });
    </script>
</body>
</html>