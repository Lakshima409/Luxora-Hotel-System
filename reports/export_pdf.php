<?php
// reports/export_pdf.php - printable PDF-style report (no ext lib needed, LAN-safe)
require_once __DIR__.'/../config.php'; require_once __DIR__.'/../db.php';
require_once __DIR__.'/../includes/auth.php';
if(!has_any_role(['manager','admin'])) require_role('manager');
$from=$_GET['from']??date('Y-m-01'); $to=$_GET['to']??date('Y-m-d');
$s=$pdo->prepare("SELECT b.bill_id,r.guest_name,b.total_amount,b.payment_status,DATE(b.created_at) d FROM bills b JOIN reservations r ON r.reservation_id=b.reservation_id WHERE DATE(b.created_at) BETWEEN ? AND ? AND r.booking_status<>'Cancelled' ORDER BY b.created_at LIMIT 200");
$s->execute([$from,$to]); $rows=$s->fetchAll();
?>
<!DOCTYPE html><html><head><meta charset="utf-8"><title>Luxora Report <?= e($from) ?>-<?= e($to) ?></title>
<style>body{font-family:Arial}table{width:100%;border-collapse:collapse}td,th{border:1px solid #999;padding:6px}@media print{.noprint{display:none}}</style></head>
<body><h2>Luxora Revenue Report <?= e($from) ?> to <?= e($to) ?></h2>
<table><tr><th>Bill</th><th>Guest</th><th>Date</th><th>Total</th><th>Status</th></tr>
<?php $t=0; foreach($rows as $r): $t+=$r['total_amount']; ?><tr><td><?= $r['bill_id'] ?></td><td><?= htmlspecialchars($r['guest_name']) ?></td><td><?= $r['d'] ?></td><td><?= number_format($r['total_amount'],2) ?></td><td><?= $r['payment_status'] ?></td></tr><?php endforeach; ?>
<tr><th colspan="3">Total</th><th><?= number_format($t,2) ?></th><th></th></tr></table>
<p class="noprint"><button onclick="window.print()">Print / Save PDF</button> <a href="index.php">Back</a></p></body></html>

