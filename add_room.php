<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/helpers.php';
require_role('admin');
$pageTitle = 'Add New Room';
$msg=''; $ok=true;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $room_number = trim($_POST['room_number']);
  $room_type = trim($_POST['room_type']);
  $beds = intval($_POST['beds']);
  $price = floatval($_POST['price']);
  $status = trim($_POST['status']);
  $image_path = trim($_POST['image_path']);
  if (empty($room_number) || empty($room_type) || $beds <= 0 || $price <= 0) {
    $msg = 'Please fill all required fields correctly.'; $ok=false;
  } else {
    $ch = $pdo->prepare("SELECT room_id FROM rooms WHERE room_number = ?");
    $ch->execute([$room_number]);
    if ($ch->fetch()) { $msg = 'Room number already exists.'; $ok=false; }
    else {
      $st = $pdo->prepare("INSERT INTO rooms (room_number,room_type,beds,rate,price,room_condition,status,image_path) VALUES (?,?,?,?,?,?,?,?)");
      if ($st->execute([$room_number,$room_type,$beds,$price,$price,$status==='available'?'Available':($status==='occupied'?'Occupied':'Under Maintenance'),$status,$image_path])) {
        log_action_pdo($pdo,$_SESSION['user_id'],'admin','room_add',"Added room $room_number ($room_type)");
        $msg = 'Room added successfully.'; $ok=true;
      } else { $msg = 'Error: could not insert.'; $ok=false; }
    }
  }
}
include __DIR__.'/includes/header.php';
?>
<h1 class="page-title">Add New Room</h1>
<p class="page-sub">Register a room into inventory</p>
<?php if ($msg): ?><div class="alert alert-<?= $ok?'success':'danger' ?>" data-autohide><?= e($msg) ?></div><?php endif; ?>
<div class="card form-card">
  <div class="card-body">
    <form method="post">
      <div class="form-group"><label>Room Number</label><input name="room_number" class="form-control" placeholder="e.g. E010" required></div>
      <div class="form-group"><label>Room Type</label><input name="room_type" class="form-control" list="types" placeholder="e.g. Standard, Deluxe, Suite" required>
        <datalist id="types"><option>Standard</option><option>Deluxe</option><option>Family Room</option><option>Suite</option><option>Luxury Suite</option></datalist></div>
      <div class="form-row">
        <div class="form-group"><label>Number of Beds</label><input type="number" name="beds" min="1" class="form-control" required></div>
        <div class="form-group"><label>Price (<?= e(CURRENCY) ?>)</label><input type="number" step="0.01" name="price" min="0" class="form-control" required></div>
      </div>
      <div class="form-group"><label>Status</label><select name="status" class="form-select"><option value="available">Available</option><option value="occupied">Occupied</option><option value="maintenance">Maintenance</option></select></div>
      <div class="form-group"><label>Image URL</label><input name="image_path" class="form-control" placeholder="https://... (optional)"></div>
      <div class="form-actions"><button class="btn btn-success">Add Room</button><a class="btn btn-ghost" href="admin_dashboard.php">Back to Room Management</a></div>
    </form>
  </div>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
