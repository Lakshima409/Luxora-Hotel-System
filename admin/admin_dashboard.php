<?php
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/mailer.php';
require_role('admin');
$pageTitle = 'Admin Dashboard';
$msg = ''; $ok = true;

// manual cron trigger
if (isset($_GET['run'])) {
  $map = ['cancel_unpaid'=>'cancel_unpaid_reservations.php','no_show'=>'bill_no_shows.php','report'=>'generate_occupancy_report.php','archive'=>'archive_logs.php'];
  $f = $map[$_GET['run']] ?? null;
  if ($f) { $_GET['mock_time'] = date('Y-m-d H:i:s'); ob_start(); include __DIR__.'/../scripts/'.$f; $msg = 'Ran '.$f.': '.trim(substr(ob_get_clean(),-120)); }
}
// user CRUD
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['new_user'])) {
  $h = password_hash($_POST['password'], PASSWORD_DEFAULT);
  $pdo->prepare("INSERT INTO users (username,password_hash,`password`,role,email) VALUES (?,?,?,?,?)")->execute([$_POST['username'],$h,$h,$_POST['role'],$_POST['email']]);
  log_action_pdo($pdo,$_SESSION['user_id'],'admin','user_create','Created user '.$_POST['username']);
  $msg = 'User '.$_POST['username'].' created.';
}
if (isset($_GET['toggle'])) { $pdo->prepare("UPDATE users SET active=1-active WHERE user_id=?")->execute([$_GET['toggle']]); $msg='Toggled.'; }
if (isset($_POST['save_settings'])) {
  foreach (['tax_rate','late_fee','currency'] as $k) if (isset($_POST[$k])) $pdo->prepare("INSERT INTO settings (skey,svalue) VALUES (?,?) ON DUPLICATE KEY UPDATE svalue=VALUES(svalue)")->execute([$k,$_POST[$k]]);
  $msg='Settings saved.';
}
// KPIs
$totalRooms=(int)$pdo->query("SELECT COUNT(*) FROM rooms")->fetchColumn();
$occRooms=(int)$pdo->query("SELECT COUNT(*) FROM rooms WHERE status='occupied' OR room_condition='Occupied'")->fetchColumn();
$maintRooms=(int)$pdo->query("SELECT COUNT(*) FROM rooms WHERE status='maintenance' OR room_condition='Under Maintenance'")->fetchColumn();
$today=date('Y-m-d');
$todayIn=$pdo->query("SELECT COUNT(*) FROM reservations WHERE DATE(check_in)='$today' AND booking_status NOT IN ('Cancelled')")->fetchColumn();
$todayOut=$pdo->query("SELECT COUNT(*) FROM reservations WHERE DATE(check_out)='$today' AND booking_status NOT IN ('Cancelled')")->fetchColumn();
$users=$pdo->query("SELECT user_id,username,role,email,active FROM users ORDER BY role")->fetchAll();
$crons=$pdo->query("SELECT script,MAX(run_time) last_run,SUM(records_affected) total FROM cron_log GROUP BY script")->fetchAll();
$logs=$pdo->query("SELECT * FROM action_logs ORDER BY timestamp DESC LIMIT 12")->fetchAll();
$occPct=$totalRooms?round($occRooms/$totalRooms*100):0;
include __DIR__.'/../includes/header.php';
?>
<h1 class="page-title">Hotel Overview</h1>
<p class="page-sub">Manage hotel operations, guests, and rooms</p>
<?php if ($msg): ?><div class="alert alert-<?= $ok?'success':'danger' ?>" data-autohide><?= e($msg) ?></div><?php endif; ?>
<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:22px">
  <a class="btn btn-emerald" href="<?= $BASE ?>/public/book.php"><i class="fas fa-plus"></i> RESERVATION</a>
  <a class="btn btn-emerald" href="<?= $BASE ?>/checkin_form.php"><i class="fas fa-user-check"></i> CHECK-IN GUEST</a>
  <a class="btn btn-primary" href="<?= $BASE ?>/add_room.php"><i class="fas fa-bed"></i> ROOM MANAGEMENT</a>
</div>
<div class="kpi-grid">
  <div class="kpi"><div class="icon mint"><i class="fas fa-bed"></i></div><div><div class="label">Occupied Rooms</div><div class="value"><?= $occRooms ?> <small>/ <?= $totalRooms ?></small></div><div class="bar"><span style="width:<?= $occPct ?>%"></span></div></div></div>
  <div class="kpi"><div class="icon green"><i class="fas fa-calendar-check"></i></div><div><div class="label">Today's Check-Ins</div><div class="value"><?= (int)$todayIn ?></div><div class="delta up">&uarr; arrivals today</div></div></div>
  <div class="kpi"><div class="icon gold"><i class="fas fa-calendar-minus"></i></div><div><div class="label">Today's Check-Outs</div><div class="value"><?= (int)$todayOut ?></div><div class="delta down">&darr; departures today</div></div></div>
  <div class="kpi"><div class="icon red"><i class="fas fa-tools"></i></div><div><div class="label">Maintenance Issues</div><div class="value"><?= $maintRooms ?></div><?php if ($maintRooms): ?><span class="badge-urgent"><?= $maintRooms ?> urgent</span><?php endif; ?></div></div>
</div>
<div class="card" style="margin-bottom:26px">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <span style="font-size:18px">Room Management</span>
    <a class="btn btn-success btn-sm" href="<?= $BASE ?>/add_room.php"><i class="fas fa-plus"></i> Add New Room</a>
  </div>
  <div class="table-wrap" style="border:none;box-shadow:none">
    <table class="table" id="roomsTable">
      <thead><tr><th>ID</th><th>Room No</th><th>Type</th><th>Beds</th><th>Price</th><th>Status</th><th>Image</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($pdo->query("SELECT * FROM rooms ORDER BY room_id") as $rm): ?>
        <tr>
          <td><?= (int)$rm['room_id'] ?></td>
          <td><b><?= e($rm['room_number']) ?></b></td>
          <td><?= e($rm['room_type']) ?></td>
          <td><?= (int)$rm['beds'] ?></td>
          <td><?= e(CURRENCY) ?> <?= number_format((float)($rm['price'] ?? $rm['rate']),2) ?></td>
          <td><span class="pill <?= e(strtolower($rm['status'])) ?>"><?= e(ucfirst($rm['status'])) ?></span></td>
          <td><?php if ($rm['image_path']): ?><img class="room-thumb" src="<?= e($rm['image_path']) ?>" alt="<?= e($rm['room_number']) ?>"><?php else: ?><span style="color:#999">&mdash;</span><?php endif; ?></td>
          <td>
            <a class="btn btn-sm" style="background:#3d7eff;color:#fff" href="<?= $BASE ?>/edit_room.php?id=<?= (int)$rm['room_id'] ?>">Edit</a>
            <a class="btn btn-sm btn-danger" href="<?= $BASE ?>/delete_room.php?id=<?= (int)$rm['room_id'] ?>" onclick="return confirm('Delete room <?= e($rm['room_number']) ?>?')">Delete</a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:22px" id="crons">
  <div class="card">
    <div class="card-header">Cron Jobs</div>
    <div class="card-body">
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
        <a class="btn btn-sm btn-outline" href="?run=cancel_unpaid">Run 7PM Cancel</a>
        <a class="btn btn-sm btn-outline" href="?run=no_show">Run 11PM No-Show</a>
        <a class="btn btn-sm btn-outline" href="?run=report">Run Midnight Report</a>
        <a class="btn btn-sm btn-outline" href="?run=archive">Run Archive</a>
      </div>
      <table class="table"><thead><tr><th>Script</th><th>Last run</th><th>Rows</th></tr></thead><tbody>
      <?php foreach ($crons as $c): ?><tr><td><?= e($c['script']) ?></td><td><?= e($c['last_run']) ?></td><td><?= e($c['total']) ?></td></tr><?php endforeach; ?>
      <?php if (!$crons): ?><tr><td colspan="3" style="color:#888">No cron runs yet.</td></tr><?php endif; ?>
      </tbody></table>
    </div>
  </div>
  <div class="card" id="settings">
    <div class="card-header">Settings</div>
    <div class="card-body">
      <form method="post"><input type="hidden" name="save_settings" value="1">
      <?php foreach (['tax_rate','late_fee','currency'] as $k): $v=get_setting($pdo,$k,''); ?>
        <div class="form-group"><label><?= e(str_replace('_',' ',$k)) ?></label><input name="<?= $k ?>" value="<?= e($v) ?>" class="form-control"></div>
      <?php endforeach; ?>
      <button class="btn btn-primary">Save Settings</button></form>
    </div>
  </div>
</div>
<div class="card" style="margin-top:22px" id="users">
  <div class="card-header">Users</div>
  <div class="card-body">
    <form method="post" class="form-row" style="align-items:end">
      <input type="hidden" name="new_user" value="1">
      <div><label>Username</label><input name="username" class="form-control" required></div>
      <div><label>Password</label><input name="password" class="form-control" required></div>
      <div><label>Email</label><input name="email" class="form-control"></div>
      <div><label>Role</label><select name="role" class="form-select"><option>clerk</option><option>manager</option><option>admin</option><option>guest</option></select></div>
      <div><button class="btn btn-success">Add User</button></div>
    </form>
    <table class="table" style="margin-top:18px">
      <thead><tr><th>User</th><th>Role</th><th>Email</th><th>Active</th><th></th></tr></thead><tbody>
      <?php foreach ($users as $u): ?><tr><td><?= e($u['username']) ?></td><td><span class="pill <?= $u['active']?'available':'cancelled' ?>"><?= e($u['role']) ?></span></td><td><?= e($u['email']) ?></td><td><?= $u['active']?'Yes':'No' ?></td><td><a class="btn btn-sm btn-ghost" href="?toggle=<?= $u['user_id'] ?>">toggle</a></td></tr><?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<div class="card" style="margin-top:22px" id="logs">
  <div class="card-header">Recent Audit Logs</div>
  <div class="table-wrap" style="border:none;box-shadow:none">
    <table class="table"><thead><tr><th>Time</th><th>Type</th><th>Message</th></tr></thead><tbody>
    <?php foreach ($logs as $l): ?><tr><td><?= e($l['timestamp']) ?></td><td><span class="pill reserved"><?= e($l['action_type']) ?></span></td><td><?= e($l['message'] ?? $l['description'] ?? '') ?></td></tr><?php endforeach; ?>
    <?php if (!$logs): ?><tr><td colspan="3" style="color:#888">No logs.</td></tr><?php endif; ?>
    </tbody></table>
  </div>
</div>
<?php include __DIR__.'/../includes/footer.php'; ?>
