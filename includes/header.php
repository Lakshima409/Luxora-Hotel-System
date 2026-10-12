<?php
// includes/header.php - shared layout (topbar + sidebar)
if (session_status() == PHP_SESSION_NONE) { session_start(); }
if (!function_exists('e')) { function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
$BASE = defined('BASE_URL') ? rtrim(BASE_URL,'/') : '';
$__user = $_SESSION['username'] ?? 'Guest';
$__role = $_SESSION['role'] ?? '';
$__initials = strtoupper(substr($__user, 0, 2));
$__page = basename($_SERVER['PHP_SELF'] ?? '');
$__links = [];
// Sidebar links are anchored to BASE_URL (absolute) so they resolve correctly
// regardless of which folder the current page lives in (/admin/, /billing/, root...).
if ($__role === 'admin') {
  $__links = [
    [$BASE.'/admin/admin_dashboard.php','fa-tachometer-alt','Overview'],
    [$BASE.'/add_room.php','fa-bed','Room Management'],
    [$BASE.'/reservations.php','fa-calendar-check','Reservations'],
    [$BASE.'/reports/index.php','fa-chart-bar','Reports'],
    [$BASE.'/admin/admin_dashboard.php#crons','fa-clock','Cron Jobs'],
    [$BASE.'/admin/admin_dashboard.php#users','fa-users','Users'],
    [$BASE.'/admin/admin_dashboard.php#logs','fa-scroll','Audit Logs'],
  ];
} elseif ($__role === 'manager') {
  $__links = [
    [$BASE.'/admin/manager_dashboard.php','fa-tachometer-alt','Overview'],
    [$BASE.'/reports/index.php','fa-chart-bar','Reports'],
    [$BASE.'/reports/export_csv.php?type=bookings','fa-file-csv','CSV Export'],
    [$BASE.'/reports/export_pdf.php','fa-file-pdf','PDF Export'],
    [$BASE.'/billing/receipt.php','fa-file-invoice','Bills'],
  ];
} else {
  $__links = [
    [$BASE.'/clerk_dashboard.php','fa-tachometer-alt','Dashboard'],
    [$BASE.'/reservations.php','fa-calendar-check','Reservations'],
    [$BASE.'/checkin_form.php','fa-sign-in-alt','Check-in'],
    [$BASE.'/checkout/checkout_form.php','fa-sign-out-alt','Check-out'],
    [$BASE.'/billing/service_charges.php','fa-concierge-bell','Services'],
    [$BASE.'/billing/bill_generator.php','fa-file-invoice-dollar','Billing'],
    [$BASE.'/reports/index.php','fa-chart-bar','Reports'],
  ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= isset($pageTitle) ? e($pageTitle) : 'Luxora' ?> - Luxora Hotel Suite</title>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="<?= $BASE ?>/assests/css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
<header class="topbar">
  <a class="logo" href="<?= $BASE ?>/index.php">Luxora<span>Hotel Suite</span><span class="tag">(<?= e(ucfirst($__role ?: 'Portal')) ?>)</span></a>
  <div class="topbar-right">
    <div class="avatar"><?= e($__initials) ?></div>
    <span style="font-weight:600"><?= e($__user) ?></span>
    <a class="btn-logout" href="<?= $BASE ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </div>
</header>
<?php if (!empty($noSidebar)): ?>
<main class="main" style="max-width:1280px;margin:0 auto">
<?php else: ?>
<div class="shell">
  <aside class="sidebar">
    <h6>Menu</h6>
    <?php foreach ($__links as $L): ?>
      <a class="nav-link <?= ($__page === basename($L[0])) ? 'active' : '' ?>" href="<?= e($L[0]) ?>">
        <i class="fas <?= e($L[1]) ?>"></i> <?= e($L[2]) ?>
      </a>
    <?php endforeach; ?>
    <div class="nav-sep"></div>
    <a class="nav-link" href="<?= $BASE ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
  </aside>
  <main class="main">
<?php endif; ?>
