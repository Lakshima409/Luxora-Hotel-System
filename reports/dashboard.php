<?php
require_once '../includes/auth.php';
//require_role('admin');
require_once '../db.php';

// KPIs
$today = date('Y-m-d');
$totalBookings = $pdo->query("SELECT COUNT(*) FROM reservations")->fetchColumn();
$checkedInToday = $pdo->query("SELECT COUNT(*) FROM reservations WHERE status = 'checked_in' AND check_in = '$today'")->fetchColumn();
$revenue = $pdo->query("SELECT SUM(total_amount) FROM bills")->fetchColumn();

