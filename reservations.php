<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/helpers.php';
require_once __DIR__.'/includes/Reservation.php';
require_login();
$pageTitle = 'Reservations';
$msg = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (isset($_POST['cancel_id'])) {
        (new Reservation($pdo))->cancel((int)$_POST['cancel_id'], $_SESSION['user_id']??0, $_SESSION['role']??'clerk');
        $msg = 'Reservation #'.(int)$_POST['cancel_id'].' cancelled.';
    } elseif (isset($_POST['confirm_id'])) {
        $pdo->prepare("UPDATE reservations SET booking_status='Confirmed', status='confirmed', reservation_status='confirmed' WHERE reservation_id=?")->execute([(int)$_POST['confirm_id']]);
        log_action_pdo($pdo, $_SESSION['user_id'], $_SESSION['role'], 'confirm', 'Confirmed #'.(int)$_POST['confirm_id']);
        $msg = 'Reservation #'.(int)$_POST['confirm_id'].' confirmed.';
    }
}

$filter = $_GET['filter'] ?? 'all';
$where = '1=1';
if ($filter==='upcoming') {
    $where = "r.booking_status IN ('Pending','Confirmed') AND r.check_in >= NOW()";
} elseif ($filter==='completed') {
    $where = "r.booking_status IN ('Checked-Out')";
} elseif ($filter==='cancelled') {
    $where = "r.booking_status IN ('Cancelled','No-Show')";
}
$rows = $pdo->query("SELECT r.*, rm.room_number, rm.room_type FROM reservations r LEFT JOIN rooms rm ON rm.room_id=r.room_id WHERE $where ORDER BY r.reservation_id DESC LIMIT 200")->fetchAll();

$all   = $pdo->query("SELECT COUNT(*) FROM reservations")->fetchColumn();
$upcoming = $pdo->query("SELECT COUNT(*) FROM reservations WHERE booking_status IN ('Pending','Confirmed')")->fetchColumn();
$completed = $pdo->query("SELECT COUNT(*) FROM reservations WHERE booking_status='Checked-Out'")->fetchColumn();
$cancelled = $pdo->query("SELECT COUNT(*) FROM reservations WHERE booking_status IN ('Cancelled','No-Show')")->fetchColumn();
include __DIR__.'/includes/header.php';
?>
<h1 class="page-title">Reservations</h1>
<p class="page-sub">All bookings at a glance</p>

<?php if ($msg): ?><div class="alert alert-success" data-autohide><?= e($msg) ?></div><?php endif; ?>

<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:22px">
    <a class="btn btn-primary" href="<?= $BASE ?>/public/book.php"><i class="fas fa-plus"></i> NEW BOOKING</a>
    <a class="btn btn-primary" href="<?= $BASE ?>/add_room.php"><i class="fas fa-bed"></i> ADD ROOMS</a>
    <a class="btn btn-primary" href="<?= $BASE ?>/admin/admin_dashboard.php"><i class="fas fa-cogs"></i> ROOM MANAGEMENT</a>
</div>

<div class="kpi-grid">
    <div class="kpi"><div class="icon navy"><i class="fas fa-list"></i></div><div><div class="label">All Reservations</div><div class="value"><?= (int)$all ?></div></div></div>
    <div class="kpi"><div class="icon gold"><i class="fas fa-clock"></i></div><div><div class="label">Upcoming</div><div class="value"><?= (int)$upcoming ?></div></div></div>
    <div class="kpi"><div class="icon mint"><i class="fas fa-check"></i></div><div><div class="label">Completed</div><div class="value"><?= (int)$completed ?></div></div></div>
    <div class="kpi"><div class="icon red"><i class="fas fa-times"></i></div><div><div class="label">Cancelled / No-show</div><div class="value"><?= (int)$cancelled ?></div></div></div>
</div>

<div class="card">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
        <span>Your Reservations</span>
        <div class="chips">
            <a class="chip <?= $filter==='all'?'active':'' ?>" href="?filter=all">All</a>
            <a class="chip <?= $filter==='upcoming'?'active':'' ?>" href="?filter=upcoming">Upcoming</a>
            <a class="chip <?= $filter==='completed'?'active':'' ?>" href="?filter=completed">Completed</a>
            <a class="chip <?= $filter==='cancelled'?'active':'' ?>" href="?filter=cancelled">Cancelled</a>
        </div>
    </div>
    <div class="table-wrap" style="border:none;box-shadow:none">
    <table class="table emerald" data-chip-filter>
        <thead><tr><th>#</th><th>Name</th><th>Email</th><th>Phone</th><th>Room Type</th><th>Check-In</th><th>Check-Out</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php if (!$rows): ?><tr><td colspan="9" style="text-align:center;color:#888">No reservations found.</td></tr><?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><b>#<?= (int)$r['reservation_id'] ?></b></td>
                <td><?= e($r['guest_name'] ?? 'Guest') ?></td>
                <td><?= e($r['email'] ?? '-') ?></td>
                <td><?= e($r['phone'] ?? '-') ?></td>
                <td><?= e(($r['room_type'] ?? '-').($r['room_number'] ? ' ('.$r['room_number'].')' : '')) ?></td>
                <td><?= e(date('M j, Y', strtotime($r['check_in']))) ?></td>
                <td><?= e(date('M j, Y', strtotime($r['check_out']))) ?></td>
                <td><span class="pill <?= e(strtolower(str_replace([' ','-'],'-',$r['booking_status']))) ?>"><?= e(ucwords(str_replace('_',' ',$r['booking_status']))) ?></span></td>
                <td>
                    <?php if (in_array($r['booking_status'],['Pending','Confirmed'])): ?>
                        <form method="post" style="display:inline"><input type="hidden" name="confirm_id" value="<?= (int)$r['reservation_id'] ?>"><button class="btn btn-sm btn-outline-gold">Confirm</button></form>
                        <form method="post" style="display:inline" onsubmit="return confirm('Cancel this reservation?')"><input type="hidden" name="cancel_id" value="<?= (int)$r['reservation_id'] ?>"><button class="btn btn-sm btn-outline">Cancel</button></form>
                    <?php elseif ($r['booking_status']==='Checked-In'): ?>
                        <a class="btn btn-sm btn-outline-gold" href="checkout/checkout_form.php">Check-out</a>
                    <?php else: ?>
                        <a class="btn btn-sm btn-primary" href="billing/receipt.php?id=<?= (int)$r['reservation_id'] ?>">Invoice</a>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
