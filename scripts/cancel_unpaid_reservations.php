<?php
// scripts/cancel_unpaid_reservations.php - 7PM: cancel card-less non-walk-in
require_once __DIR__.'/cron_common.php';
$hour=(int)$NOW->format('H');
if($hour<19 && !$mock){ echo "Too early (runs at 19:00). Use ?mock_time=... to test.\n"; exit(0); }
$today=$NOW->format('Y-m-d');
$s=$pdo->prepare("SELECT reservation_id,room_id,guest_name,email FROM reservations WHERE DATE(check_in)=? AND booking_status IN ('Pending','Confirmed') AND status IN ('booked','reserved','Pending','Confirmed') AND COALESCE(is_walk_in,0)=0 AND COALESCE(is_walkin,0)=0 AND COALESCE(has_card,0)=0 AND (card_last4 IS NULL OR card_last4='')");
$s->execute([$today]); $rows=$s->fetchAll();
$n=0;
foreach($rows as $r){
  $pdo->prepare("UPDATE reservations SET booking_status='Cancelled',status='cancelled',reservation_status='cancelled' WHERE reservation_id=?")->execute([$r['reservation_id']]);
  if(!empty($r['room_id'])) $pdo->prepare("UPDATE rooms SET room_condition='Available',status='available' WHERE room_id=?")->execute([$r['room_id']]);
  log_action_pdo($pdo,0,'system','auto_cancel',"Auto-cancelled #{$r['reservation_id']} ({$r['guest_name']}) - no card by 7PM");
  if(!empty($r['email'])) notify_booking($r['email'],$r['guest_name']??'Guest',"#{$r['reservation_id']}",$today,$today,'Cancelled (no card)');
  $n++; echo "Cancelled #{$r['reservation_id']}\n";
}
cron_log_db($pdo,'cancel_unpaid_reservations','success',$n,"date=$today");
echo "Done. $n cancelled.\n";
