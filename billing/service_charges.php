<?php
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../db.php';
require_once __DIR__.'/../includes/auth.php';
require_once __DIR__.'/../includes/helpers.php';
require_login();
include(__DIR__.'/../includes/header.php');
require_once __DIR__.'/billing_utils.php';
$success = ""; $error = "";
$reservation_id = isset($_GET['reservation_id']) ? (int)$_GET['reservation_id'] : null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reservation_id = (int)$_POST['reservation_id'];
    $service_name = trim($_POST['service_name']);
    $cost = floatval($_POST['cost']);
    if ($reservation_id && $service_name && $cost >= 0) {
        $stmt = $pdo->prepare("INSERT INTO services (reservation_id, service_name, cost, added_by) VALUES (?, ?, ?, ?)");
        if ($stmt->execute([$reservation_id,$service_name,$cost,$_SESSION['user_id']??null])) {
            $success = "Service charge added successfully.";
            log_action_pdo($pdo,$_SESSION['user_id']??0,$_SESSION['role']??'','service',"Added $service_name ($cost) to #$reservation_id");
        } else { $error = "Error adding service charge."; }
    } else { $error = "All fields are required and cost must be non-negative."; }
}
$reservations = $pdo->query("SELECT r.reservation_id, c.full_name, rm.room_number FROM reservations r LEFT JOIN customers c ON c.customer_id=r.customer_id LEFT JOIN rooms rm ON rm.room_id=r.room_id WHERE r.booking_status IN ('Confirmed','Checked-In') AND r.status IN ('booked','checked_in','Confirmed') LIMIT 200")->fetchAll();
$guest_info = null;
if ($reservation_id) {
    $s=$pdo->prepare("SELECT c.full_name, rm.room_number FROM reservations r LEFT JOIN customers c ON c.customer_id=r.customer_id LEFT JOIN rooms rm ON rm.room_id=r.room_id WHERE r.reservation_id=?");
    $s->execute([$reservation_id]); $guest_info=$s->fetch();
}
?>

<div class="container mt-5">
  <div class="card shadow">
    <div class="card-header bg-primary text-white">
      <h2 class="mb-0">Add Service Charges</h2>
    </div>
    
    <div class="card-body">
      <?php if ($success): ?>
        <div class="alert alert-success">
          <i class="fas fa-check-circle me-2"></i> <?= $success ?>
          <a href="bill_generator.php?reservation_id=<?= $reservation_id ?>" class="btn btn-sm btn-outline-success ms-3">
            View Bill
          </a>
        </div>
      <?php elseif ($error): ?>
        <div class="alert alert-danger">
          <i class="fas fa-exclamation-circle me-2"></i> <?= $error ?>
        </div>
      <?php endif; ?>

      <?php if ($guest_info): ?>
        <div class="alert alert-info mb-4">
          <i class="fas fa-info-circle me-2"></i> Adding services for: 
          <strong><?= $guest_info['full_name'] ?></strong> (Room <?= $guest_info['room_number'] ?>)
        </div>
      <?php endif; ?>

      <form method="POST">
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Select Guest</label>
            <select name="reservation_id" class="form-select" required>
              <?php if ($reservation_id): ?>
                <option value="<?= $reservation_id ?>" selected>
                  <?= $guest_info['full_name'] ?> (Room <?= $guest_info['room_number'] ?>)
                </option>
              <?php else: ?>
                <option value="">-- Select a guest/reservation --</option>
                <?php foreach ($reservations as $row): ?>
                  <option value="<?= $row['reservation_id'] ?>" <?= ($reservation_id == $row['reservation_id']) ? 'selected' : '' ?>>
                    <?= $row['full_name'] ?> (Room <?= $row['room_number'] ?>)
                  </option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>
        </div>
        
        <div class="row mb-3">
          <div class="col-md-6">
            <label class="form-label">Service Name</label>
            <input type="text" name="service_name" class="form-control" 
                   value="<?= isset($service_name) ? htmlspecialchars($service_name) : '' ?>" 
                   placeholder="e.g., Laundry, Room Service, Mini Bar" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Cost (LKR)</label>
            <input type="number" step="0.01" name="cost" class="form-control" 
                   value="<?= isset($cost) ? htmlspecialchars($cost) : '' ?>" 
                   placeholder="e.g., 1500.00" min="0" required>
          </div>
        </div>

        <div class="d-flex justify-content-between mt-4">
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-2"></i> Save Service
          </button>
          
          <div>
            <a href="bill_generator.php" class="btn btn-outline-secondary me-2">
              <i class="fas fa-arrow-left me-2"></i> Back to Billing
            </a>
            <a href="../reports/service_usage_report.php" class="btn btn-outline-dark">
              <i class="fas fa-chart-bar me-2"></i> View Service Report
            </a>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<?php include(__DIR__."/../includes/footer.php"); ?>