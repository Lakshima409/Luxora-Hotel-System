<?php
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/BillingEngine.php';
if (!has_any_role(['clerk','manager','admin'])) require_role('clerk');
$pageTitle = 'Billing';
$msg=''; $ok=true; $preview=null; $bill_id=null; $bill_status=null;

// Reservations that can be billed (checked-in or checked-out without a bill)
$cands = $pdo->query("SELECT r.reservation_id, r.guest_name, r.booking_status, rm.room_number, rm.room_type
  FROM reservations r LEFT JOIN rooms rm ON rm.room_id=r.room_id
  WHERE r.booking_status IN ('Checked-In','Checked-Out')
  ORDER BY r.reservation_id DESC LIMIT 100")->fetchAll();

$sel = (int)($_GET['id'] ?? $_POST['reservation_id'] ?? 0);
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['finalize'])) {
  $rid=(int)$_POST['reservation_id']; $late=(float)($_POST['late_fee']??0); $disc=(float)($_POST['discount']??0);
  $method=$_POST['payment_method']??'cash'; $paid=(float)($_POST['paid_amount']??0);
  try {
    $BE=new BillingEngine($pdo);
    [$bid,$b,$st]=$BE->finalize($rid,$late,$disc,$method,[[$method,$paid]]);
    log_action_pdo($pdo,$_SESSION['user_id'],$_SESSION['role'],'billing',"Generated bill #{$bid} for #{$rid} total {$b['total']}");
    $_SESSION['flash']="Bill #{$bid} generated. Total ".CURRENCY." ".number_format($b['total'],2)." ($st).";
    header("Location: receipt.php?id=$rid"); exit();
  } catch (Exception $e) { $msg=$e->getMessage(); $ok=false; }
}
$late=0; $disc=0;
if ($sel) {
  $st=$pdo->prepare("SELECT check_out FROM reservations WHERE reservation_id=?"); $st->execute([$sel]); $co=$st->fetchColumn();
  if ($co && new DateTime()>new DateTime($co)) $late=(float)get_setting($pdo,'late_fee',50);
  try { $BE=new BillingEngine($pdo); $preview=$BE->build($sel,$late,$disc,'cash'); }
  catch (Exception $e) { $msg=$e->getMessage(); $ok=false; }
}
include __DIR__.'/../includes/header.php';
?>
<h1 class="page-title">Billing &amp; Invoicing</h1>
<p class="page-sub">Preview and finalize a guest bill — room + services + late fee − discount + tax</p>
<?php if (!empty($_SESSION['flash'])): ?><div class="alert alert-success" data-autohide><?= e($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']); endif; ?>
<?php if ($msg): ?><div class="alert alert-<?= $ok?'success':'danger' ?>"><?= e($msg) ?></div><?php endif; ?>

<div class="card" style="margin-bottom:24px">
  <div class="card-header">Select a Reservation</div>
  <div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
    <select class="form-select" style="max-width:420px" onchange="if(this.value)location.href='bill_generator.php?id='+this.value">
      <option value="">-- choose guest --</option>
      <?php foreach ($cands as $c): ?>
        <option value="<?= (int)$c['reservation_id'] ?>" <?= $sel===$c['reservation_id']?'selected':'' ?>>
          #<?= (int)$c['reservation_id'] ?> — <?= e($c['guest_name'] ?? 'Guest') ?> (<?= e($c['room_number'] ?? $c['room_type'] ?? 'Room') ?>) [<?= e($c['booking_status']) ?>]
        </option>
      <?php endforeach; ?>
    </select>
    <a class="btn btn-ghost" href="service_charges.php">+ Add Service</a>
  </div>
</div>

<?php if ($preview): ?>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
  <div class="card"><div class="card-header">Bill Preview</div>
    <div class="table-wrap" style="border:none;box-shadow:none">
      <table class="table">
        <tbody>
          <tr><td>Room (<?= (int)$preview['nights'] ?> nights × <?= number_format($preview['rate'],2) ?>)</td><td style="text-align:right"><?= number_format($preview['room_charge'],2) ?></td></tr>
          <tr><td>Services</td><td style="text-align:right"><?= number_format($preview['services'],2) ?></td></tr>
          <tr><td>Late fee</td><td style="text-align:right"><?= number_format($preview['late_fee'],2) ?></td></tr>
          <tr><td>Tax (<?= round(get_setting($pdo,'tax_rate',0.10)*100) ?>%)</td><td style="text-align:right"><?= number_format($preview['tax'],2) ?></td></tr>
          <tr style="font-weight:800;background:#f7f9fc"><td>TOTAL (<?= e(CURRENCY) ?>)</td><td style="text-align:right;color:var(--primary)"><?= number_format($preview['total'],2) ?></td></tr>
        </tbody>
      </table>
    </div>
  </div>
  <div class="card"><div class="card-header">Finalize Payment</div>
    <div class="card-body">
      <form method="post">
        <input type="hidden" name="reservation_id" value="<?= $sel ?>">
        <div class="form-row">
          <div class="form-group"><label>Late fee (<?= e(CURRENCY) ?>)</label><input type="number" step="0.01" name="late_fee" class="form-control" value="<?= e($late) ?>"></div>
          <div class="form-group"><label>Discount (<?= e(CURRENCY) ?>)</label><input type="number" step="0.01" name="discount" class="form-control" value="0"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label>Payment method</label><select name="payment_method" class="form-select"><option>cash</option><option>credit_card</option><option>debit_card</option><option>bank_transfer</option><option>billed_to_agency</option></select></div>
          <div class="form-group"><label>Amount paid now</label><input type="number" step="0.01" name="paid_amount" class="form-control" value="<?= e(number_format($preview['total'],2,'.','')) ?>"></div>
        </div>
        <button class="btn btn-primary btn-block" name="finalize" value="1"><i class="fas fa-check-circle"></i> Finalize &amp; Generate Receipt</button>
      </form>
    </div>
  </div>
</div>
<?php else: ?>
<div class="alert alert-info">Pick a reservation above to preview its bill.</div>
<?php endif; ?>
<?php include __DIR__.'/../includes/footer.php'; ?>
