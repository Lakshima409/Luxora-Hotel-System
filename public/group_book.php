<?php
// public/group_book.php - travel agency: >=3 rooms, 10% corp discount, consolidated bill
require_once __DIR__.'/../config.php'; require_once __DIR__.'/../db.php';
require_once __DIR__.'/../includes/helpers.php'; require_once __DIR__.'/../includes/Reservation.php';
require_once __DIR__.'/../includes/BillingEngine.php'; require_once __DIR__.'/../includes/mailer.php';
$msg=''; $group_id=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
  $room_ids=$_POST['room_ids']??[];
  if(count($room_ids)<3){ $msg='Agency blocks need at least 3 rooms.'; }
  else{
    try{
      $group_id=uniqid('GRP-'); $R=new Reservation($pdo); $rids=[];
      foreach($room_ids as $i=>$rid){
        $rm=$pdo->prepare("SELECT * FROM rooms WHERE room_id=?"); $rm->execute([$rid]); $room=$rm->fetch();
        $id=$R->create(['guest_name'=>$_POST['guest_name'].' (p'.($i+1).')','email'=>$_POST['email'],'phone'=>$_POST['phone'],'room_id'=>$rid,'room_number'=>$room['room_number'],'room_type'=>$room['room_type'],'check_in'=>$_POST['check_in'],'check_out'=>$_POST['check_out'],'occupants'=>1,'card_last4'=>'AGCY','is_group'=>1,'group_id'=>$group_id,'booked_by'=>'travel_agency','company_name'=>$_POST['company'],'created_by'=>$_SESSION['user_id']??null]);
        $rids[]=$id;
      }
      // consolidated bill with 10% discount on room portion
      $total=0; $bills=[];
      foreach($rids as $r){
        $BE=new BillingEngine($pdo); [$bid,$b,$st]=$BE->finalize($r,0,0,'billed_to_agency',[]);
        // apply 10% corp discount post-hoc
        $disc=round($b['room_charge']*0.10,2);
        $pdo->prepare("UPDATE bills SET discount=?, total_amount=total_amount-?-tax*0 WHERE bill_id=?")->execute([$disc,$disc,$bid]);
        // simpler: recalc
        $nb=$BE->build($r,0,$disc,'billed_to_agency');
        $pdo->prepare("UPDATE bills SET discount=?, subtotal=?, tax=?, total_amount=?, snapshot=? WHERE bill_id=?")->execute([$disc,$nb['subtotal'],$nb['tax'],$nb['total'],json_encode($nb),$bid]);
        $bills[]=$bid; $total+=$nb['total'];
      }
      send_mail($_POST['email'],"Agency block $group_id","$group_id: ".count($rids)." rooms, total $total LKR billed to {$_POST['company']}.");
      $msg="Block $group_id created. Consolidated total: ".number_format($total,2)." LKR (10% corp discount applied). Bills: ".implode(',',$bills);
    }catch(Exception $e){ $msg=$e->getMessage(); }
  }
}
$rooms=$pdo->query("SELECT * FROM rooms WHERE status='available' ORDER BY rate")->fetchAll();
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Agency Block - Luxora</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-light"><div class="container py-4"><h2>Travel Agency Block (3+ rooms)</h2>
<?php if($msg): ?><div class="alert alert-info"><?= e($msg) ?></div><?php endif; ?>
<form method="post" class="card card-body">
<div class="row g-2"><div class="col"><label>Agency / Company</label><input name="company" class="form-control" required></div>
<div class="col"><label>Contact name</label><input name="guest_name" class="form-control" required></div>
<div class="col"><label>Email</label><input name="email" class="form-control"></div><div class="col"><label>Phone</label><input name="phone" class="form-control"></div></div>
<div class="row g-2 mt-2"><div class="col"><label>Check-in</label><input type="datetime-local" name="check_in" class="form-control" required></div>
<div class="col"><label>Check-out</label><input type="datetime-local" name="check_out" class="form-control" required></div></div>
<label class="mt-2">Rooms (pick 3+)</label><?php foreach($rooms as $rm): ?><div><label><input type="checkbox" name="room_ids[]" value="<?= $rm['room_id'] ?>"> <?= e($rm['room_number'].' '.$rm['room_type'].' '.$rm['rate']) ?></label></div><?php endforeach; ?>
<button class="btn btn-primary mt-3">Create Block (10% off)</button></form></div></body></html>
