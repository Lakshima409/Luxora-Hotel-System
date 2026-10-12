<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/helpers.php';
require_once __DIR__.'/includes/Reservation.php';
require_login(); require_clerk();
$pageTitle = 'Clerk Dashboard';
$today = date('Y-m-d');
$todayIn  = $pdo->query("SELECT COUNT(*) FROM reservations WHERE DATE(check_in)='$today' AND booking_status NOT IN ('Cancelled')")->fetchColumn();
$todayOut = $pdo->query("SELECT COUNT(*) FROM reservations WHERE DATE(check_out)='$today' AND booking_status NOT IN ('Cancelled')")->fetchColumn();
$totalRooms = (int)$pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$occRooms   = (int)$pdo->query("SELECT COUNT(*) FROM rooms WHERE status='occupied' OR room_condition='Occupied'")->fetchColumn();
$revToday   = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM bills WHERE DATE(created_at)='$today'")->fetchColumn();
$occPct = $totalRooms ? round($occRooms/$totalRooms*100) : 0;
$filter = $_GET['filter'] ?? 'all';
$where = "1=1";
if ($filter==='booked') $where = "r.booking_status IN ('Pending','Confirmed','booked')";
elseif ($filter==='checked') $where = "r.booking_status IN ('Checked-In','checked_in')";
elseif ($filter==='cancelled') $where = "r.booking_status IN ('Cancelled','cancelled')";
$rows = $pdo->query("SELECT r.*, rm.room_number, rm.room_type FROM reservations r LEFT JOIN rooms rm ON rm.room_id=r.room_id WHERE $where ORDER BY r.reservation_id DESC LIMIT 50")->fetchAll();
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if (isset($_POST['confirm_id'])) {
    $pdo->prepare("UPDATE reservations SET booking_status='Confirmed', status='confirmed', reservation_status='confirmed' WHERE reservation_id=?")->execute([$_POST['confirm_id']]);
    log_action_pdo($pdo, $_SESSION['user_id']??0, 'clerk', 'confirm', 'Confirmed #'.$_POST['confirm_id']);
  } elseif (isset($_POST['cancel_id'])) {
    (new Reservation($pdo))->cancel($_POST['cancel_id'], $_SESSION['user_id']??0, 'clerk');
  }
  header('Location: clerk_dashboard.php'); exit();
}
include __DIR__.'/includes/header.php';
function pillFor($st){
  $k = str_replace([' ','-'],'-',$st);
  return '<span class="pill '.e(strtolower($k)).'">'.e(ucwords(str_replace('_',' ',$st))).'</span>';
}
?>
<h1 class="page-title">Reservations Management</h1>
<p class="page-sub">Front-desk overview — arrivals, departures, occupancy &amp; revenue</p>
<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:20px">
  <div class="chips">
    <a class="chip <?= $filter==='all'?'active':'' ?>" href="?filter=all">All</a>
    <a class="chip <?= $filter==='booked'?'active':'' ?>" href="?filter=booked">Booked</a>
    <a class="chip <?= $filter==='checked'?'active':'' ?>" href="?filter=checked">Checked-In</a>
    <a class="chip <?= $filter==='cancelled'?'active':'' ?>" href="?filter=cancelled">Cancelled</a>
  </div>
  <a class="btn btn-primary" href="../checkin_form.php"><i class="fas fa-user-check"></i> NEW CHECK-IN</a>
</div>
<div class="kpi-grid">
  <div class="kpi"><div class="icon gold"><i class="fas fa-sign-in-alt"></i></div><div><div class="label">TODAY'S CHECK-INS</div><div class="value"><?= (int)$todayIn ?></div><div class="delta up">&uarr; arrivals today</div></div></div>
  <div class="kpi"><div class="icon red"><i class="fas fa-sign-out-alt"></i></div><div><div class="label">TODAY'S CHECK-OUTS</div><div class="value"><?= (int)$todayOut ?></div><div class="delta down">&darr; departures today</div></div></div>
  <div class="kpi"><div class="icon mint"><i class="fas fa-bed"></i></div><div><div class="label">OCCUPANCY RATE</div><div class="value"><?= $occPct ?>%</div><div class="bar"><span style="width:<?= $occPct ?>%"></span></div></div></div>
  <div class="kpi"><div class="icon navy"><i class="fas fa-coins"></i></div><div><div class="label">REVENUE TODAY</div><div class="value"><?= e(CURRENCY) ?> <?= number_format((float)$revToday,0) ?></div><div class="delta up">bills generated today</div></div></div>
</div>
<div class="card">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <span>Current Reservations</span>
    <input type="text" data-table-search="#resTable" placeholder="Search guest, room, ID..." class="form-control" style="max-width:280px">
  </div>
  <div class="table-wrap" style="border:none;box-shadow:none">
  <table class="table emerald" id="resTable">
    <thead><tr><th>Reservation ID</th><th>Guest Name</th><th>Room Type</th><th>Check-in</th><th>Check-out</th><th>Status</th><th>Actions</th></tr></thead>
    <tbody>
    <?php if (!$rows): ?><tr><td colspan="7" style="text-align:center;color:#888">No reservations yet.</td></tr><?php endif; ?>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><b>#RES-<?= (int)$r['reservation_id'] ?></b></td>
        <td><?= e($r['guest_name'] ?? 'Guest') ?></td>
        <td><?= e(($r['room_type'] ?? '-').($r['room_number']?' ('.$r['room_number'].')':'')) ?></td>
        <td><?= e(date('M j, Y', strtotime($r['check_in']))) ?></td>
        <td><?= e(date('M j, Y', strtotime($r['check_out']))) ?></td>
        <td><?= pillFor($r['booking_status']) ?></td>
        <td>
          <?php if (in_array($r['booking_status'], ['Pending','Confirmed'])): ?>
            <form method="post" style="display:inline"><input type="hidden" name="confirm_id" value="<?= $r['reservation_id'] ?>"><button class="btn btn-sm btn-outline-gold">CONFIRM</button></form>
            <form method="post" style="display:inline" onsubmit="return confirm('Cancel?')"><input type="hidden" name="cancel_id" value="<?= $r['reservation_id'] ?>"><button class="btn btn-sm btn-outline">CANCEL</button></form>
          <?php elseif ($r['booking_status']==='Checked-In'): ?>
            <a class="btn btn-sm btn-outline-gold" href="../checkout/checkout_form.php">CHECK-OUT</a>
            <a class="btn btn-sm btn-primary" href="../billing/receipt.php?id=<?= $r['reservation_id'] ?>">VIEW BILL</a>
          <?php else: ?>
            <a class="btn btn-sm btn-primary" href="../billing/receipt.php?id=<?= $r['reservation_id'] ?>">VIEW BILL</a>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>

?>
