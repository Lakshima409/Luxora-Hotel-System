<?php
require_once __DIR__.'/../config.php'; require_once __DIR__.'/../db.php';
require_once __DIR__.'/../includes/helpers.php'; require_once __DIR__.'/../includes/Reservation.php';
require_once __DIR__.'/../includes/mailer.php';
$id=(int)($_GET['id']??0); $email=$_GET['email']??'';
$s=$pdo->prepare("SELECT * FROM reservations WHERE reservation_id=?"); $s->execute([$id]); $r=$s->fetch();
if($r && ($r['email']==$email || ($_SESSION['role']??'')==='clerk')){
  (new Reservation($pdo))->cancel($id,$_SESSION['user_id']??0,$_SESSION['role']??'guest');
  if(!empty($r['email'])) notify_booking($r['email'],$r['guest_name'],"#$id",$r['check_in'],$r['check_out'],'Cancelled');
}
redirect('my_bookings.php?email='.urlencode($email));
