<?php
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/BillingEngine.php';
if (!has_any_role(['clerk','manager','admin'])) require_role('clerk');
$pageTitle = 'Check-Out';
$msg=''; $ok=true;

// Handle checkout: mark reservation Checked-Out, free the room, then bill
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['checkout_id'])) {
  $rid=(int)$_POST['checkout_id'];
  try {
    $pdo->beginTransaction();
    $st=$pdo->prepare("SELECT * FROM reservations WHERE reservation_id=? AND booking_status='Checked-In' FOR UPDATE");
    $st->execute([$rid]); $res=$st->fetch();
    if (!$res) throw new Exception('Reservation not found or not currently checked in.');
    $pdo->prepare("UPDATE reservations SET booking_status='Checked-Out', status='checked_out', reservation_status='checked_out', check_out=NOW() WHERE reservation_id=?")
        ->execute([$rid]);
    if (!empty($res['room_id'])) {
      $pdo->prepare("UPDATE rooms SET status='available', room_condition='Available' WHERE room_id=?")->execute([$res['room_id']]);
    }
    $pdo->commit();
    log_action_pdo($pdo,$_SESSION['user_id'],$_SESSION['role'],'checkout',"Checked out reservation #{$rid}");
    header("Location: ../billing/bill_generator.php?id=$rid"); exit();
  } catch (Exception $e) { $pdo->rollBack(); $msg=$e->getMessage(); $ok=false; }
}

// Guests currently checked in
$guests = $pdo->query("SELECT r.reservation_id, r.guest_name, r.email, r.phone, r.room_type,
    r.check_in, r.check_out, rm.room_number, r.booked_by, r.company_name
  FROM reservations r LEFT JOIN rooms rm ON rm.room_id=r.room_id
  WHERE r.booking_status='Checked-In'
  ORDER BY rm.room_number")->fetchAll();

include __DIR__.'/../includes/header.php';
?>
<h1 class="page-title">Guest Check-Out</h1>
<p class="page-sub">Select a checked-in guest to close their stay and generate the bill</p>

<?php if ($msg): ?><div class="alert alert-<?= $ok?'success':'danger' ?>"><?= e($msg) ?></div><?php endif; ?>

<div class="card">
  <div class="card-header">Currently Checked-In (<?= count($guests) ?>)</div>
  <div class="table-wrap" style="border:none;box-shadow:none">
  <table class="table emerald">
    <thead><tr><th>#</th><th>Guest</th><th>Room</th><th>Type</th><th>Check-In</th><th>Contact</th><th>Action</th></tr></thead>
    <tbody>
    <?php if (!$guests): ?><tr><td colspan="7" style="text-align:center;color:#888">No guests currently checked in.</td></tr><?php endif; ?>
    <?php foreach ($guests as $g): ?>
      <tr>
        <td><b>#<?= (int)$g['reservation_id'] ?></b></td>
        <td>
          <?= e($g['guest_name'] ?? 'Guest') ?>
          <?php if (($g['booked_by'] ?? '')==='travel_agency' && !empty($g['company_name'])): ?>
            <span class="pill booked"><?= e($g['company_name']) ?></span>
          <?php endif; ?>
        </td>
        <td><?= e($g['room_number'] ?? '-') ?></td>
        <td><?= e($g['room_type'] ?? '-') ?></td>
        <td><?= e($g['check_in'] ? date('M j, Y g:i A', strtotime($g['check_in'])) : '-') ?></td>
        <td><?= e(trim(($g['phone'] ?? '').' '.($g['email'] ?? '')) ?: '-') ?></td>
        <td>
          <form method="post" style="display:inline" onsubmit="return confirm('Check out <?= e(addslashes($g['guest_name'] ?? 'this guest')) ?> and generate the bill?')">
            <input type="hidden" name="checkout_id" value="<?= (int)$g['reservation_id'] ?>">
            <button class="btn btn-sm btn-emerald"><i class="fas fa-sign-out-alt"></i> CHECK-OUT</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>
<?php include __DIR__.'/../includes/footer.php'; ?>
