<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/helpers.php';
if (!has_any_role(['clerk','manager','admin'])) require_role('clerk');
$pageTitle = 'Check-In';
$msg=''; $ok=true;

// List reservations eligible for check-in
$pending = $pdo->query("SELECT r.*, rm.room_number AS assigned_room, rm.room_type AS rtype
  FROM reservations r LEFT JOIN rooms rm ON rm.room_id=r.room_id
  WHERE r.booking_status IN ('Pending','Confirmed')
  ORDER BY r.check_in ASC LIMIT 100")->fetchAll();

// Available rooms for assignment
$available = $pdo->query("SELECT * FROM rooms WHERE status='available' OR room_condition='Available' ORDER BY room_number")->fetchAll();

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['checkin_id'])) {
  $rid=(int)$_POST['checkin_id']; $room_id=(int)$_POST['room_id'];
  try {
    $pdo->beginTransaction();
    $st=$pdo->prepare("SELECT * FROM reservations WHERE reservation_id=? AND booking_status IN ('Pending','Confirmed') FOR UPDATE");
    $st->execute([$rid]); $res=$st->fetch();
    if (!$res) throw new Exception('Reservation not found or already checked in.');
    $rm=$pdo->prepare("SELECT * FROM rooms WHERE room_id=? AND (status='available' OR room_condition='Available') FOR UPDATE");
    $rm->execute([$room_id]); $room=$rm->fetch();
    if (!$room) throw new Exception('Selected room not available.');
    $pdo->prepare("UPDATE reservations SET booking_status='Checked-In', status='checked_in', reservation_status='checked_in', room_id=?, room_number=?, room_type=?, check_in=NOW() WHERE reservation_id=?")
        ->execute([$room_id,$room['room_number'],$room['room_type'],$rid]);
    $pdo->prepare("UPDATE rooms SET status='occupied', room_condition='Occupied' WHERE room_id=?")->execute([$room_id]);
    $pdo->commit();
    log_action_pdo($pdo,$_SESSION['user_id'],$_SESSION['role'],'checkin',"Checked in #{$rid} to room {$room['room_number']}");
    $_SESSION['flash']="Checked in to room {$room['room_number']}.";
    header("Location: checkin_form.php"); exit();
  } catch (Exception $e) { $pdo->rollBack(); $msg=$e->getMessage(); $ok=false; }
}
include __DIR__.'/includes/header.php';
?>
<h1 class="page-title">Guest Check-In</h1>
<p class="page-sub">Assign a room and check in a booked guest</p>
<?php if (!empty($_SESSION['flash'])): ?><div class="alert alert-success" data-autohide><?= e($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
<?php if ($msg): ?><div class="alert alert-<?= $ok?'success':'danger' ?>"><?= e($msg) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:24px">
  <div class="card-header">Today's Arrivals &amp; Pending Reservations</div>
  <div class="table-wrap" style="border:none;box-shadow:none">
  <table class="table emerald" id="pendingTable">
    <thead><tr><th>ID</th><th>Guest</th><th>Room Type</th><th>Check-in</th><th>Check-out</th><th>Status</th><th>Assign Room &amp; Check-In</th></tr></thead>
    <tbody>
    <?php if (!$pending): ?><tr><td colspan="7" style="text-align:center;color:#888">No pending reservations to check in.</td></tr><?php endif; ?>
    <?php foreach ($pending as $p): ?>
      <tr>
        <td><b>#<?= (int)$p['reservation_id'] ?></b></td>
        <td><?= e($p['guest_name'] ?? 'Guest') ?></td>
        <td><?= e($p['rtype'] ?? $p['room_type'] ?? '-') ?></td>
        <td><?= e(date('M j, Y', strtotime($p['check_in']))) ?></td>
        <td><?= e(date('M j, Y', strtotime($p['check_out']))) ?></td>
        <td><span class="pill <?= strtolower(str_replace(' ','-',$p['booking_status'])) ?>"><?= e($p['booking_status']) ?></span></td>
        <td>
          <form method="post" style="display:flex;gap:8px;align-items:center">
            <input type="hidden" name="checkin_id" value="<?= (int)$p['reservation_id'] ?>">
            <select name="room_id" class="form-select" style="width:auto;min-width:150px" required>
              <option value="">-- select room --</option>
              <?php foreach ($available as $av): ?>
                <option value="<?= (int)$av['room_id'] ?>"><?= e($av['room_number'].' · '.$av['room_type'].' · '.number_format((float)$av['rate'],0)) ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-emerald">CHECK-IN</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<div class="card">
  <div class="card-header">Available Rooms (<?= count($available) ?>)</div>
  <div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap">
    <?php foreach ($available as $av): ?>
      <div style="border:1px solid #e9edf3;border-radius:8px;padding:10px 14px;min-width:120px">
        <b><?= e($av['room_number']) ?></b><br><span style="font-size:13px;color:var(--muted)"><?= e($av['room_type']) ?></span><br>
        <span class="pill available">Available</span>
      </div>
    <?php endforeach; ?>
    <?php if (!$available): ?><span style="color:#888">No rooms available right now.</span><?php endif; ?>
  </div>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
