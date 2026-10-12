<?php
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/Reservation.php';
require_once __DIR__.'/../includes/mailer.php';
$pageTitle='Book a Room'; $noSidebar=true;
$step=(int)($_GET['step']??$_POST['step']??1);
$ci=$_POST['check_in']??$_GET['check_in']??''; $co=$_POST['check_out']??$_GET['check_out']??'';
$occ=(int)($_POST['occupants']??$_GET['occupants']??1); $msg=''; $rid=null;
if ($step===2 && (!$ci||!$co)) { $msg='Pick dates first.'; $step=1; }
$rooms=[];
if ($step>=2 && $ci && $co) {
  $s=$pdo->prepare("SELECT * FROM rooms WHERE (status='available' OR room_condition IN ('Available','Reserved')) AND capacity>=? ORDER BY rate");
  $s->execute([$occ]);
  $R=new Reservation($pdo);
  foreach ($s->fetchAll() as $rm) if ($R->isAvailable($rm['room_id'],$ci,$co)) $rooms[]=$rm;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && $step===3) {
  try {
    $R=new Reservation($pdo);
    $last4=preg_replace('/\D/','',substr($_POST['card']??'',-4));
    $rid=$R->create(['guest_name'=>$_POST['guest_name'],'email'=>$_POST['email'],'phone'=>$_POST['phone'],'room_id'=>$_POST['room_id'],'room_number'=>$_POST['room_number'],'room_type'=>$_POST['room_type'],'check_in'=>$ci,'check_out'=>$co,'occupants'=>$occ,'card_last4'=>$last4,'booked_by'=>'guest','created_by'=>$_SESSION['user_id']??null]);
    if (!empty($_POST['email'])) notify_booking($_POST['email'],$_POST['guest_name'],"#$rid",$ci,$co,'Confirmed');
    header("Location: book.php?step=4&rid=$rid"); exit();
  } catch (Exception $e) { $msg=$e->getMessage(); }
}
include __DIR__.'/../includes/header.php';
?>
<h1 class="page-title" style="text-align:center">Book a Room</h1>
<div class="steps" style="max-width:760px;margin:0 auto 8px">
  <?php for($i=1;$i<=4;$i++): ?><div class="step <?= $i<=$step?'on':'' ?>">Step <?= $i ?></div><?php endfor; ?>
</div>
<div class="progress" style="max-width:760px;margin:0 auto 26px"><span style="width:<?= $step*25 ?>%"></span></div>
<div style="max-width:760px;margin:0 auto">
<?php if ($msg): ?><div class="alert alert-danger"><?= e($msg) ?></div><?php endif; ?>

<?php if ($step==1): ?>
<form method="post" action="book.php?step=2" class="card"><div class="card-body">
  <div class="form-row">
    <div class="form-group"><label>Check-in</label><input type="datetime-local" name="check_in" class="form-control" required></div>
    <div class="form-group"><label>Check-out</label><input type="datetime-local" name="check_out" class="form-control" required></div>
  </div>
  <div class="form-group" style="max-width:200px"><label>Guests</label><input type="number" name="occupants" min="1" max="6" value="1" class="form-control"></div>
  <button class="btn btn-primary"><i class="fas fa-search"></i> Find Rooms</button>
</div></form>

<?php elseif ($step==2): ?>
<div class="card"><div class="card-body">
  <?php if (!$rooms): ?><div class="alert alert-warning">No rooms available for those dates. Try another range.</div><?php endif; ?>
  <?php foreach ($rooms as $rm): ?>
  <form method="post" action="book.php?step=3&check_in=<?= urlencode($ci) ?>&check_out=<?= urlencode($co) ?>&occupants=<?= $occ ?>" class="svc-row" style="display:flex;justify-content:space-between;align-items:center;border:1px solid #e9edf3;border-radius:8px;padding:14px;margin-bottom:10px">
    <div><b><?= e($rm['room_number']) ?></b> — <?= e($rm['room_type']) ?> <span style="color:var(--muted)">(sleeps <?= (int)$rm['capacity'] ?>)</span><br>
    <b style="color:var(--primary)"><?= e(CURRENCY) ?> <?= number_format((float)$rm['rate'],2) ?></b> / night</div>
    <input type="hidden" name="step" value="3"><input type="hidden" name="room_id" value="<?= $rm['room_id'] ?>">
    <input type="hidden" name="room_number" value="<?= e($rm['room_number']) ?>"><input type="hidden" name="room_type" value="<?= e($rm['room_type']) ?>">
    <button class="btn btn-primary btn-sm">Select</button>
  </form>
  <?php endforeach; ?>
  <a class="btn btn-ghost" href="book.php">&larr; Change dates</a>
</div></div>

<?php elseif ($step==3): ?>
<form method="post" class="card"><div class="card-body">
  <input type="hidden" name="step" value="3"><input type="hidden" name="room_id" value="<?= (int)($_POST['room_id']??0) ?>">
  <input type="hidden" name="room_number" value="<?= e($_POST['room_number']??'') ?>"><input type="hidden" name="room_type" value="<?= e($_POST['room_type']??'') ?>">
  <input type="hidden" name="check_in" value="<?= e($ci) ?>"><input type="hidden" name="check_out" value="<?= e($co) ?>"><input type="hidden" name="occupants" value="<?= $occ ?>">
  <div class="form-group"><label>Full Name</label><input name="guest_name" class="form-control" required></div>
  <div class="form-row">
    <div class="form-group"><label>Email</label><input name="email" type="email" class="form-control"></div>
    <div class="form-group"><label>Phone</label><input name="phone" class="form-control"></div>
  </div>
  <div class="form-group"><label>Credit Card (required before 7 PM — only last 4 digits stored)</label><input name="card" class="form-control" placeholder="4111 1111 1111 1111"></div>
  <button class="btn btn-primary"><i class="fas fa-check"></i> Confirm Booking</button>
</div></form>

<?php else: ?>
<div class="card" style="text-align:center"><div class="card-body" style="padding:46px">
  <i class="fas fa-check-circle" style="font-size:58px;color:var(--success)"></i>
  <h2 style="margin:14px 0 6px">Booking Confirmed!</h2>
  <p>Your reservation reference is <b style="color:var(--primary)">#<?= (int)$rid ?></b></p>
  <div class="form-actions" style="justify-content:center;margin-top:20px">
    <a class="btn btn-primary" href="my_bookings.php">View My Bookings</a>
    <a class="btn btn-ghost" href="index.php">Back to Home</a>
  </div>
</div></div>
<?php endif; ?>
</div>
<?php include __DIR__.'/../includes/footer.php'; ?>
