<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/db.php';
require_once __DIR__.'/includes/auth.php';
require_once __DIR__.'/includes/helpers.php';
require_role('admin');
$pageTitle = 'Edit Room';
if (!isset($_GET['id'])) { header('Location: admin_dashboard.php'); exit(); }
$id = (int)$_GET['id'];
$st = $pdo->prepare("SELECT * FROM rooms WHERE room_id = ?"); $st->execute([$id]); $room = $st->fetch();
if (!$room) { header('Location: admin_dashboard.php'); exit(); }
$msg=''; $ok=true;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $room_number = trim($_POST['room_number']); $room_type = trim($_POST['room_type']);
  $beds = intval($_POST['beds']); $price = floatval($_POST['price']);
  $status = trim($_POST['status']); $image_path = trim($_POST['image_path']);
  if (empty($room_number) || empty($room_type) || $beds <= 0 || $price <= 0) { $msg='Fill all required fields.'; $ok=false; }
  else {
    $cond = $status==='available'?'Available':($status==='occupied'?'Occupied':'Under Maintenance');
    $pdo->prepare("UPDATE rooms SET room_number=?, room_type=?, beds=?, rate=?, price=?, room_condition=?, status=?, image_path=? WHERE room_id=?")
        ->execute([$room_number,$room_type,$beds,$price,$price,$cond,$status,$image_path,$id]);
    log_action_pdo($pdo,$_SESSION['user_id'],'admin','room_edit',"Updated room $room_number ($room_type)");
    $msg='Room updated successfully.'; $ok=true;
    $st = $pdo->prepare("SELECT * FROM rooms WHERE room_id = ?"); $st->execute([$id]); $room = $st->fetch();
  }
}
include __DIR__.'/includes/header.php';
?>
<h1 class="page-title">Edit Room</h1>
<p class="page-sub">Room #<?= (int)$id ?> — <?= e($room['room_number']) ?></p>
<?php if ($msg): ?><div class="alert alert-<?= $ok?'success':'danger' ?>" data-autohide><?= e($msg) ?></div><?php endif; ?>
<div class="card form-card">
  <div class="card-body">
    <form method="post">
      <div class="form-group"><label>Room Number</label><input name="room_number" class="form-control" value="<?= e($room['room_number']) ?>" required></div>
      <div class="form-group"><label>Room Type</label><input name="room_type" class="form-control" value="<?= e($room['room_type']) ?>" required></div>
      <div class="form-row">
        <div class="form-group"><label>Number of Beds</label><input type="number" name="beds" min="1" class="form-control" value="<?= (int)$room['beds'] ?>" required></div>
        <div class="form-group"><label>Price (<?= e(CURRENCY) ?>)</label><input type="number" step="0.01" name="price" min="0" class="form-control" value="<?= e($room['price'] ?? $room['rate']) ?>" required></div>
      </div>
      <div class="form-group"><label>Status</label><select name="status" class="form-select">
        <?php foreach (['available','occupied','maintenance'] as $s): ?>
          <option value="<?= $s ?>" <?= $room['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?>
      </select></div>
      <div class="form-group"><label>Image URL</label><input name="image_path" class="form-control" value="<?= e($room['image_path']) ?>" placeholder="Optional"></div>
      <div class="form-actions"><button class="btn btn-success">Update Room</button><a class="btn btn-ghost" href="admin_dashboard.php">Back to Room Management</a></div>
    </form>
  </div>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
