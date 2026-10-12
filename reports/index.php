<?php
// reports/index.php - unified manager reports (filters + Chart.js + PDF/CSV)
require_once __DIR__.'/../config.php'; require_once __DIR__.'/../db.php';
require_once __DIR__.'/../includes/auth.php'; require_once __DIR__.'/../includes/helpers.php';
if(!has_any_role(['manager','admin','clerk'])) require_role('manager');
$from=$_GET['from']??date('Y-m-01'); $to=$_GET['to']??date('Y-m-d'); $rtype=$_GET['rtype']??'';
$where="DATE(b.created_at) BETWEEN ? AND ? AND r.booking_status<>'Cancelled'";
$p=[$from,$to];
if($rtype){ $where.=" AND rm.room_type=?"; $p[]=$rtype; }
$s=$pdo->prepare("SELECT DATE(b.created_at) d, COALESCE(SUM(b.total_amount),0) t, COUNT(*) n FROM bills b JOIN reservations r ON r.reservation_id=b.reservation_id LEFT JOIN rooms rm ON rm.room_id=r.room_id WHERE $where GROUP BY DATE(b.created_at) ORDER BY d");
$s->execute($p); $rows=$s->fetchAll();
$types=$pdo->query("SELECT DISTINCT room_type FROM rooms")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Reports - Luxora</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script></head>
<body class="bg-light"><div class="container py-4">
<h2>Revenue Reports (cancelled excluded)</h2>
<form class="row g-2 mb-3"><div class="col"><input type="date" name="from" value="<?= e($from) ?>" class="form-control"></div>
<div class="col"><input type="date" name="to" value="<?= e($to) ?>" class="form-control"></div>
<div class="col"><select name="rtype" class="form-control"><option value="">All types</option><?php foreach($types as $t): ?><option <?= $rtype==$t?'selected':'' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
<div class="col"><button class="btn btn-primary">Filter</button>
<a class="btn btn-secondary" href="export_csv.php?type=revenue">CSV</a>
<a class="btn btn-danger" href="export_pdf.php?from=<?= e($from) ?>&to=<?= e($to) ?>">PDF</a></div></form>
<div class="card card-body mb-3"><canvas id="rev"></canvas></div>
<table class="table table-bordered bg-white"><tr><th>Date</th><th>Bills</th><th>Revenue</th></tr>
<?php $tt=0; foreach($rows as $r): $tt+=$r['t']; ?><tr><td><?= e($r['d']) ?></td><td><?= $r['n'] ?></td><td><?= number_format($r['t'],2) ?></td></tr><?php endforeach; ?>
<tr><th colspan="2">Total</th><th><?= number_format($tt,2) ?></th></tr></table></div>
<script>new Chart(document.getElementById('rev'),{type:'line',data:{labels:<?= json_encode(array_column($rows,'d')) ?>,datasets:[{label:'Revenue',data:<?= json_encode(array_column($rows,'t')) ?>}]}});</script>
</body></html>
