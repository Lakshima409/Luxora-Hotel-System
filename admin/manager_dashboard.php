<?php
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/helpers.php';
if (!has_any_role(['manager','admin'])) require_role('manager');
$pageTitle='Manager Dashboard'; $msg='';
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['refund_bill'])) {
  $pdo->prepare("UPDATE bills SET payment_status='Refunded' WHERE bill_id=?")->execute([$_POST['refund_bill']]);
  log_action_pdo($pdo,$_SESSION['user_id'],$_SESSION['role'],'refund','Refunded bill #'.$_POST['refund_bill']);
  $msg='Refund approved for bill #'.$_POST['refund_bill'];
}
$occ=$pdo->query("SELECT (SELECT COUNT(*) FROM rooms) total,(SELECT COUNT(*) FROM rooms WHERE status='occupied') occ")->fetch();
$rev=$pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM bills b JOIN reservations r ON r.reservation_id=b.reservation_id WHERE r.booking_status<>'Cancelled'")->fetchColumn();
$noShows=(int)$pdo->query("SELECT COUNT(*) FROM reservations WHERE booking_status='No-Show'")->fetchColumn();
$byType=$pdo->query("SELECT COALESCE(rm.room_type,'Unassigned') t, COALESCE(SUM(b.total_amount),0) v FROM bills b JOIN reservations r ON r.reservation_id=b.reservation_id LEFT JOIN rooms rm ON rm.room_id=r.room_id WHERE r.booking_status<>'Cancelled' GROUP BY rm.room_type")->fetchAll();
$bills=$pdo->query("SELECT b.*, r.guest_name FROM bills b JOIN reservations r ON r.reservation_id=b.reservation_id ORDER BY b.created_at DESC LIMIT 20")->fetchAll();
$reports=$pdo->query("SELECT * FROM reports ORDER BY report_date DESC LIMIT 10")->fetchAll();
$logs=$pdo->query("SELECT * FROM action_logs ORDER BY timestamp DESC LIMIT 10")->fetchAll();
$occPct=$occ['total']?round($occ['occ']/$occ['total']*100):0;
include __DIR__.'/../includes/header.php';
?>
<h1 class="page-title">Manager Dashboard</h1>
<p class="page-sub">Revenue, occupancy, refunds &amp; oversight</p>
<?php if ($msg): ?><div class="alert alert-success" data-autohide><?= e($msg) ?></div><?php endif; ?>
<div class="kpi-grid">
  <div class="kpi"><div class="icon mint"><i class="fas fa-bed"></i></div><div><div class="label">Occupied Rooms</div><div class="value"><?= (int)$occ['occ'] ?> <small>/ <?= (int)$occ['total'] ?></small></div><div class="bar"><span style="width:<?= $occPct ?>%"></span></div></div></div>
  <div class="kpi"><div class="icon gold"><i class="fas fa-coins"></i></div><div><div class="label">Revenue (excl. cancelled)</div><div class="value"><?= e(CURRENCY) ?> <?= number_format((float)$rev,2) ?></div></div></div>
  <div class="kpi"><div class="icon red"><i class="fas fa-user-slash"></i></div><div><div class="label">No-Shows</div><div class="value"><?= $noShows ?></div></div></div>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-bottom:22px">
  <div class="card"><div class="card-header">Revenue by Room Type</div><div class="card-body"><canvas id="revChart" height="220"></canvas></div></div>
  <div class="card"><div class="card-header">Daily Occupancy &amp; Revenue</div><div class="table-wrap" style="border:none;box-shadow:none;max-height:320px;overflow:auto">
    <table class="table"><thead><tr><th>Date</th><th>Occ%</th><th>Revenue</th><th>No-shows</th></tr></thead><tbody>
    <?php foreach ($reports as $r): ?><tr><td><?= e($r['report_date']) ?></td><td><?= e($r['occupancy_pct']) ?></td><td><?= number_format((float)$r['revenue'],2) ?></td><td><?= (int)$r['no_shows'] ?></td></tr><?php endforeach; ?>
    <?php if (!$reports): ?><tr><td colspan="4" style="color:#888">Run midnight report from Admin &gt; Cron.</td></tr><?php endif; ?>
    </tbody></table></div></div>
</div>
<div class="card" style="margin-bottom:22px">
  <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <span>Bills &amp; Refund Approvals</span>
    <div><a class="btn btn-sm btn-ghost" href="../reports/export_csv.php?type=revenue">CSV</a> <a class="btn btn-sm btn-danger" href="../reports/export_pdf.php">PDF</a></div>
  </div>
  <div class="table-wrap" style="border:none;box-shadow:none">
    <table class="table"><thead><tr><th>Bill</th><th>Guest</th><th>Total</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($bills as $b): ?>
      <tr><td>#<?= (int)$b['bill_id'] ?></td><td><?= e($b['guest_name']) ?></td><td><?= number_format((float)$b['total_amount'],2) ?></td>
      <td><span class="pill <?= strtolower($b['payment_status'])==='paid'?'paid':'unpaid' ?>"><?= e($b['payment_status']) ?></span></td>
      <td><?php if ($b['payment_status']==='Paid'): ?><form method="post" style="display:inline"><input type="hidden" name="refund_bill" value="<?= $b['bill_id'] ?>"><button class="btn btn-sm btn-outline-gold" onclick="return confirm('Approve refund?')">REFUND</button></form><?php else: ?>—<?php endif; ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$bills): ?><tr><td colspan="5" style="color:#888">No bills yet.</td></tr><?php endif; ?>
    </tbody></table>
  </div>
</div>
<div class="card"><div class="card-header">Staff Action Log</div>
  <div class="table-wrap" style="border:none;box-shadow:none">
  <table class="table"><thead><tr><th>Time</th><th>Type</th><th>Message</th></tr></thead><tbody>
  <?php foreach ($logs as $l): ?><tr><td><?= e($l['timestamp']) ?></td><td><span class="pill reserved"><?= e($l['action_type']) ?></span></td><td><?= e($l['message'] ?? $l['description'] ?? '') ?></td></tr><?php endforeach; ?>
  <?php if (!$logs): ?><tr><td colspan="3" style="color:#888">No logs.</td></tr><?php endif; ?>
  </tbody></table></div>
</div>
<script>
new Chart(document.getElementById('revChart'),{type:'bar',
 data:{labels:<?= json_encode(array_column($byType,'t')) ?>,
 datasets:[{label:'Revenue',data:<?= json_encode(array_column($byType,'v')) ?>,backgroundColor:['#0c4b33','#d4af37','#48e08a','#3d7eff','#e5484d'],borderRadius:6}]},
 options:{plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}});
</script>
<?php include __DIR__.'/../includes/footer.php'; ?>

