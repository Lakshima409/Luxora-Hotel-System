<?php
// public/longstay.php - residential suites: weekly/monthly rates + extension without rebooking
require_once __DIR__.'/../config.php'; require_once __DIR__.'/../db.php';
require_once __DIR__.'/../includes/helpers.php';
$msg='';
if(isset($_POST['extend_id'])){
  $d=(int)$_POST['extra_days'];
  $pdo->prepare("UPDATE reservations SET check_out=DATE_ADD(check_out, INTERVAL ? DAY), check_out_date=DATE_ADD(check_out_date, INTERVAL ? DAY), checkout_date=DATE_ADD(checkout_date, INTERVAL ? DAY) WHERE reservation_id=?")->execute([$d,$d,$d,$_POST['extend_id']]);
  log_action_pdo($pdo,$_SESSION['user_id']??0,$_SESSION['role']??'guest','extend',"Extended #{$_POST['extend_id']} by $d days");
  $msg="Extended by $d days (no rebooking needed).";
}
if(isset($_POST['create_long'])){
  $plan=$_POST['plan']; // weekly|monthly
  $mult=$plan==='monthly'?30:7; $disc=$plan==='monthly'?0.15:0.07;
  $rm=$pdo->prepare("SELECT * FROM rooms WHERE room_id=?"); $rm->execute([$_POST['room_id']]); $room=$rm->fetch();
  $rate=$room['rate']*(1-$disc);
  $ci=$_POST['check_in']; $co=date('Y-m-d H:i:s',strtotime($ci." +$mult days"));
  require_once __DIR__.'/../includes/Reservation.php';
  try{ $rid=(new Reservation($pdo))->create(['guest_name'=>$_POST['guest_name'],'email'=>$_POST['email'],'room_id'=>$room['room_id'],'room_number'=>$room['room_number'],'room_type'=>$room['room_type'].' ('.$plan.')','check_in'=>$ci,'check_out'=>$co,'card_last4'=>$_POST['card_last4']??'','special_requests'=>"Long-stay $plan @ $rate/night (discount applied)"]); $msg="Long-stay booked #$rid @ ".number_format($rate,2)."/night."; }
  catch(Exception $e){ $msg=$e->getMessage(); }
}
$rooms=$pdo->query("SELECT * FROM rooms WHERE room_type LIKE '%Suite%' OR rate>=200 ORDER BY rate")->fetchAll();
$stays=$pdo->query("SELECT * FROM reservations WHERE room_type LIKE '%(weekly)%' OR room_type LIKE '%(monthly)%' ORDER BY check_in DESC LIMIT 20")->fetchAll();
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Residential Suites - Luxora</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><div class="container py-4"><h2>Residential Suites (Weekly 7% / Monthly 15% off)</h2>
<?php if($msg): ?><div class="alert alert-info"><?= e($msg) ?></div><?php endif; ?>
<form method="post" class="card card-body mb-3"><input type="hidden" name="create_long" value="1">
<div class="row g-2"><div class="col"><label>Name</label><input name="guest_name" class="form-control" required></div>
<div class="col"><label>Email</label><input name="email" class="form-control"></div>
<div class="col"><label>Room</label><select name="room_id" class="form-control"><?php foreach($rooms as $r): ?><option value="<?= $r['room_id'] ?>"><?= e($r['room_number'].' '.$r['room_type'].' '.$r['rate']) ?></option><?php endforeach; ?></select></div>
<div class="col"><label>Plan</label><select name="plan" class="form-control"><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select></div>
<div class="col"><label>Start</label><input type="datetime-local" name="check_in" class="form-control" required></div></div>
<button class="btn btn-primary mt-2">Book Long-Stay</button></form>
<div class="card card-body"><h5>Extend Stay</h5><table class="table table-sm"><tr><th>ID</th><th>Guest</th><th>Until</th><th></th></tr>
<?php foreach($stays as $s): ?><tr><td><?= $s['reservation_id'] ?></td><td><?= e($s['guest_name']) ?></td><td><?= e($s['check_out']) ?></td>
<td><form method="post" style="display:inline"><input type="hidden" name="extend_id" value="<?= $s['reservation_id'] ?>"><input type="number" name="extra_days" value="7" min="1" style="width:70px"><button class="btn btn-sm btn-success">Extend</button></form></td></tr><?php endforeach; ?></table></div>
</div></body></html>
