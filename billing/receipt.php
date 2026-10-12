<?php
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/helpers.php';
require_once __DIR__.'/../includes/BillingEngine.php';
require_login();
$reservation_id = (int)($_GET['id'] ?? 0);
$billing=null; $engine=new BillingEngine($pdo);
if($reservation_id){
  $late_fee = 0;
  try{
    $s=$pdo->prepare("SELECT check_out FROM reservations WHERE reservation_id=?"); $s->execute([$reservation_id]);
    $co=$s->fetchColumn();
    if($co && new DateTime()>new DateTime($co)) $late_fee=(float)get_setting($pdo,'late_fee',50);
    $b=$engine->build($reservation_id,$late_fee,0,'cash');
    // fetch display rows securely
    $d=$pdo->prepare("SELECT r.*, c.full_name, c.email, c.phone, rm.room_number, rm.room_type, rm.rate FROM reservations r LEFT JOIN customers c ON c.customer_id=r.customer_id LEFT JOIN rooms rm ON rm.room_id=r.room_id WHERE r.reservation_id=?");
    $d->execute([$reservation_id]); $billing=$d->fetch();
    $sv=$pdo->prepare("SELECT service_name,cost FROM services WHERE reservation_id=?"); $sv->execute([$reservation_id]); $services=$sv->fetchAll();
  }catch(Exception $e){ $billing=null; }
}
function format_currency($a){ return number_format($a,2); }
function generate_invoice_number($id){ return 'INV-'.date('Ymd').'-'.str_pad((int)$id,5,'0',STR_PAD_LEFT); }
function calculate_tax($sub,$rate=0.10){ return round($sub*$rate,2); }
include(__DIR__.'/../includes/header.php');
$invoice_number = generate_invoice_number($reservation_id);
?>
<div class="container mt-4" id="printArea">
  <div class="card border-0 shadow">
    <div class="card-body p-4">
      <div class="row mb-4">
        <div class="col-md-6">
          <h2 class="text-primary mb-0">Luxora Hotel</h2>
          <p class="text-muted">123 Luxury Avenue, Colombo, Sri Lanka</p>
          <p class="text-muted">Tel: +94 11 234 5678 | Email: info@luxorahotel.com</p>
        </div>
        <div class="col-md-6 text-end">
          <h4 class="text-uppercase text-primary">Invoice</h4>
          <p class="mb-1"><strong>Invoice #:</strong> <?= $invoice_number ?></p>
          <p class="mb-1"><strong>Date:</strong> <?= date('F j, Y') ?></p>
          <?php if ($billing): ?>
            <p class="mb-1"><strong>Reservation ID:</strong> <?= $reservation_id ?></p>
          <?php endif; ?>
        </div>
      </div>

      <hr class="my-4">

      <?php if ($billing): ?>
        <div class="row mb-4">
          <div class="col-md-6">
            <h5 class="text-uppercase">Guest Information</h5>
            <p class="mb-1"><strong>Name:</strong> <?= $billing['full_name'] ?></p>
            <p class="mb-1"><strong>Email:</strong> <?= $billing['email'] ?></p>
            <p class="mb-1"><strong>Phone:</strong> <?= $billing['phone'] ?></p>
          </div>
          <div class="col-md-6">
            <h5 class="text-uppercase">Stay Details</h5>
            <p class="mb-1"><strong>Room:</strong> <?= $billing['room_type'] ?> (<?= $billing['room_number'] ?>)</p>
            <p class="mb-1"><strong>Check-in:</strong> <?= date('M j, Y', strtotime($billing['checkin_date'])) ?></p>
            <p class="mb-1"><strong>Check-out:</strong> <?= date('M j, Y', strtotime($billing['checkout_date'])) ?></p>
          </div>
        </div>

        <div class="table-responsive">
          <table class="table table-bordered">
            <thead class="bg-light">
              <tr>
                <th>Description</th>
                <th class="text-end">Amount (LKR)</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Room Charge (<?= $billing['room_type'] ?>)</td>
                <td class="text-end"><?= format_currency($billing['rate']) ?></td>
              </tr>
              
              <?php 
                $service_total = 0;
                if (!empty($services)): 
                  foreach ($services as $s): 
                    $service_total += $s['cost'];
              ?>
                <tr>
                  <td><?= $s['service_name'] ?></td>
                  <td class="text-end"><?= format_currency($s['cost']) ?></td>
                </tr>
              <?php endforeach; endif; ?>

              <?php if ($late_fee > 0): ?>
                <tr>
                  <td>Late Checkout Fee</td>
                  <td class="text-end"><?= format_currency($late_fee) ?></td>
                </tr>
              <?php endif; ?>
              
              <tr>
                <td>Tax (10%)</td>
                <td class="text-end"><?= format_currency(calculate_tax($billing['rate'] + $service_total + $late_fee)) ?></td>
              </tr>
              
              <tr class="table-active fw-bold">
                <td>TOTAL AMOUNT DUE</td>
                <td class="text-end"><?= format_currency($billing['rate'] + $service_total + $late_fee + calculate_tax($billing['rate'] + $service_total + $late_fee)) ?></td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="mt-4 p-3 bg-light rounded">
          <h5 class="text-uppercase">Payment Information</h5>
          <p class="mb-1"><strong>Payment Status:</strong> <span class="badge bg-success">Paid</span></p>
          <p class="mb-1"><strong>Payment Method:</strong> Credit Card</p>
          <p class="mb-1"><strong>Payment Date:</strong> <?= date('F j, Y') ?></p>
        </div>

        <div class="text-center mt-5">
          <p class="text-muted">Thank you for choosing Luxora Hotel. We hope to see you again soon!</p>
          <p class="text-muted small">This is an electronically generated receipt. No signature required.</p>
        </div>
      <?php else: ?>
        <div class="alert alert-danger">
          <i class="fas fa-exclamation-circle me-2"></i> Invalid or missing reservation ID.
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="text-center mt-3">
    <button class="btn btn-primary me-2" onclick="window.print()">
      <i class="fas fa-print me-2"></i> Print Receipt
    </button>
    <a href="bill_generator.php" class="btn btn-outline-secondary">
      <i class="fas fa-arrow-left me-2"></i> Back to Billing
    </a>
  </div>
</div>

<script>
// Print only the receipt content
function printReceipt() {
    var printContents = document.getElementById('printArea').innerHTML;
    var originalContents = document.body.innerHTML;
    document.body.innerHTML = printContents;
    window.print();
    document.body.innerHTML = originalContents;
    window.location.reload();
}
</script>

<?php include(__DIR__.'/../includes/footer.php'); ?>