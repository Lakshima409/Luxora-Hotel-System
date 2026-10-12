<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/helpers.php';
require_role('admin');
if (!isset($_GET['id'])) { header('Location: admin_dashboard.php'); exit(); }
$id = (int)$_GET['id'];
$st = $pdo->prepare("SELECT * FROM rooms WHERE room_id = ?"); $st->execute([$id]); $room = $st->fetch();
if (!$room) { header('Location: admin_dashboard.php'); exit(); }
$st = $pdo->prepare("SELECT COUNT(*) FROM reservations WHERE room_id = ? AND booking_status IN ('Pending','Confirmed','Checked-In')");
$st->execute([$id]); $active = (int)$st->fetchColumn();
if ($active > 0) {
  $msg = 'Cannot delete — room has '.$active.' active reservation(s).';
} else {
  $pdo->prepare("DELETE FROM rooms WHERE room_id = ?")->execute([$id]);
  log_action_pdo($pdo,$_SESSION['user_id'],'admin','room_delete',"Deleted room {$room['room_number']}");
  $msg = 'Room '.$room['room_number'].' deleted.';
}
$_SESSION['flash'] = $msg;
header('Location: admin_dashboard.php'); exit();
