<?php
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../includes/helpers.php';
$pageTitle='My Bookings'; $noSidebar=true;
$email=$_GET['email']??$_SESSION['guest_email']??'';
$filter=$_GET['filter']??'all';
$rows=[];
if ($email) {
  $where="r.email=?"; $p=[$email];
  if ($filter==='upcoming') { $where.=" AND r.booking_status IN ('Pending','Confirmed') AND r.check_in>=NOW()"; }
  elseif ($filter==='completed') { $where.=" AND r.booking_status IN ('Checked-Out')"; }
  elseif ($filter==='cancelled') { $where.=" AND r.booking_status IN ('Cancelled','No-Show')"; }
  $s=$pdo->prepare("SELECT r.*, rm.room_number, rm.room_type FROM reservations r LEFT JOIN rooms rm ON rm.room_id=r.room_id WHERE $where ORDER BY r.check_in DESC");
  $s->execute($p); $rows=$s->fetchAll();
}
function pillFor($st){ $k=strtolower(str_replace([' ','-'],'-',$st)); return '<span class="pill '.e($k).'">'.e(ucwords(str_replace('_',' ',$st))).'</span>'; }
include __DIR__.'/../includes/header.php';
?>
<h1 class="page-title">Your Reservations</h1>
<p class="page-sub">Look up bookings by the email used at booking</p>
<form class="row2" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px">
  <input name="email" class="form-control" style="max-width:340px" placeholder="you@email.com" value="<?= e($email) ?>">
  <input type="hidden" name="filter" value="<?= e($filter) ?>">
  <button class="btn btn-primary">Lookup</button>
  <a class="btn btn-emerald" href="book.php"><i class="fas fa-plus"></i> NEW BOOKING</a>
</form>
<div class="card">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <span>Your Reservations</span>
    <div class="chips">
      <?php foreach (['all'=>'All','upcoming'=>'Upcoming','completed'=>'Completed','cancelled'=>'Cancelled'] as $k=>$v): ?>
        <a class="chip <?= $filter===$k?'active':'' ?>" href="?email=<?= urlencode($email) ?>&filter=<?= $k ?>"><?= e($v) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="table-wrap" style="border:none;box-shadow:none">
  <table class="table emerald">
    <thead><tr><th>#</th><th>Room</th><th>Check-in</th><th>Check-out</th><th>Status</th><th>Action</th></tr></thead>
    <tbody>
    <?php if (!$email): ?><tr><td colspan="6" style="text-align:center;color:#888">Enter your email above to see your bookings.</td></tr><?php endif; ?>
    <?php if ($email && !$rows): ?><tr><td colspan="6" style="text-align:center;color:#888">No bookings found for <?= e($email) ?>.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><b>#<?= (int)$r['reservation_id'] ?></b></td>
        <td><?= e(($r['room_type']??'Room').' '.($r['room_number']??'')) ?></td>
        <td><?= e(date('M j, Y', strtotime($r['check_in']))) ?></td>
        <td><?= e(date('M j, Y', strtotime($r['check_out']))) ?></td>
        <td><?= pillFor($r['booking_status']) ?></td>
        <td><?php if (in_array($r['booking_status'],['Pending','Confirmed'])): ?>
          <a class="btn btn-sm btn-danger" href="cancel_booking.php?id=<?= $r['reservation_id'] ?>&email=<?= urlencode($email) ?>" onclick="return confirm('Cancel this booking?')">Cancel</a>
        <?php else: ?>—<?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php include __DIR__.'/../includes/footer.php'; ?>
