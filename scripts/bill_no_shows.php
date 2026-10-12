<?php
// scripts/bill_no_shows.php - 11PM: mark No-Show + bill first night + email
require_once __DIR__.'/cron_common.php';
$hour=(int)$NOW->format('H');
if($hour<23 && !$mock){ echo "Too early (runs at 23:00).\n"; exit(0); }
$today=$NOW->format('Y-m-d');
$s=$pdo->prepare("SELECT r.reservation_id,r.guest_name,r.email,r.room_id,rm.rate FROM reservations r LEFT JOIN rooms rm ON rm.room_id=r.room_id WHERE DATE(r.check_in)=? AND r.booking_status IN ('Pending','Confirmed') AND r.status IN ('booked','reserved','Confirmed','Pending')");
$s->execute([$today]); $rows=$s->fetchAll();
$n=0;
foreach($rows as $r){
  $rate=(float)($r['rate']??100);
  $tax=(float)get_setting($pdo,'tax_rate',0.10);
  $taxAmt=round($rate*$tax,2); $total=round($rate+$taxAmt,2);
  $pdo->prepare("UPDATE reservations SET booking_status='No-Show',status='no_show',reservation_status='no_show' WHERE reservation_id=?")->execute([$r['reservation_id']]);
  $b=$pdo->prepare("INSERT INTO bills (reservation_id,subtotal,tax,late_fee,discount,total_amount,payment_status,payment_method,snapshot) VALUES (?,?,?,?,?,?,'Unpaid','card',?)");
  $b->execute([$r['reservation_id'],$rate,$taxAmt,0,0,$total,json_encode(['noshow'=>1,'first_night'=>$rate])]);
  $bid=$pdo->lastInsertId(); $pdo->prepare("UPDATE bills SET id=bill_id WHERE bill_id=?")->execute([$bid]);
  $pdo->prepare("INSERT INTO bill_items (bill_id,description,amount) VALUES (?,?,?)")->execute([$bid,"No-Show: first night charge",$rate]);
  $pdo->prepare("INSERT INTO bill_items (bill_id,description,amount) VALUES (?,?,?)")->execute([$bid,"Tax",$taxAmt]);
  if(!empty($r['email'])) send_mail($r['email'],"Luxora No-Show #{$r['reservation_id']}","Dear {$r['guest_name']},\nYou were marked No-Show for $today. First night charged: $total LKR.");
  $n++; echo "No-show #{$r['reservation_id']} billed $total\n";
}
cron_log_db($pdo,'bill_no_shows','success',$n,"date=$today");
echo "Done. $n no-shows.\n";
