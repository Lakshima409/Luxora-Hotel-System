<?php
// public/index.php - guest landing (Harborlights-style hero)
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../db.php';
$pageTitle = 'Home';
$rooms = $pdo->query("SELECT * FROM rooms ORDER BY rate ASC LIMIT 6")->fetchAll();
$BASE = defined('BASE_URL') ? rtrim(BASE_URL,'/') : '';
$noSidebar = true;
include __DIR__.'/../includes/header.php';
?>
<section style="min-height:78vh;display:grid;place-items:center;text-align:center;color:#fff;position:relative;background:linear-gradient(rgba(8,48,35,.55),rgba(8,48,35,.75)),url('https://images.unsplash.com/photo-1566073771259-6a8506099945?auto=format&fit=crop&w=2000&q=80') center/cover fixed">
  <div style="padding:60px 24px;max-width:840px">
    <div style="letter-spacing:3px;font-weight:700;color:var(--secondary);margin-bottom:14px">EXPERIENCE LUXURY REDEFINED</div>
    <h1 style="color:#fff;font-size:clamp(34px,5vw,58px);margin-bottom:18px">Where Elegance Meets Comfort</h1>
    <p style="font-size:18px;opacity:.95;margin-bottom:30px">Discover our exquisite accommodations, world-class amenities, and impeccable service at Luxora Hotel &amp; Resort.</p>
    <div style="display:flex;gap:14px;justify-content:center;flex-wrap:wrap">
      <a class="btn btn-primary" href="book.php" style="padding:14px 30px">EXPLORE ROOMS</a>
      <a class="btn btn-outline-gold" href="my_bookings.php" style="padding:14px 30px;color:#fff;border-color:#fff">MY BOOKING</a>
      <a class="btn btn-outline-gold" href="<?= $BASE ?>/login.php" style="padding:14px 30px">ADMIN LOGIN</a>
    </div>
  </div>
</section>

<section class="container-fluid" style="padding:56px 24px">
  <h2 style="text-align:center;font-size:32px;margin-bottom:8px">Our Rooms</h2>
  <p style="text-align:center;color:var(--muted);margin-bottom:30px">Handpicked stays for every occasion</p>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:22px">
    <?php foreach ($rooms as $rm): ?>
      <div class="card" style="overflow:hidden">
        <?php if ($rm['image_path']): ?><img src="<?= e($rm['image_path']) ?>" alt="<?= e($rm['room_number']) ?>" style="height:180px;object-fit:cover;width:100%"><?php else: ?>
        <div style="height:180px;background:linear-gradient(135deg,var(--primary),var(--primary-light));display:grid;place-items:center;color:#fff;font-size:40px"><i class="fas fa-bed"></i></div><?php endif; ?>
        <div class="card-body">
          <h3 style="font-size:20px;margin-bottom:4px"><?= e($rm['room_type']) ?></h3>
          <div style="color:var(--muted);font-size:14px">Room <?= e($rm['room_number']) ?> · <?= (int)$rm['beds'] ?> bed(s)</div>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px">
            <div style="font-weight:800;color:var(--primary)"><?= e(CURRENCY) ?> <?= number_format((float)($rm['price'] ?? $rm['rate']),0) ?> <small style="color:var(--muted);font-weight:500">/night</small></div>
            <a class="btn btn-sm btn-primary" href="book.php">Book</a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>
<?php include __DIR__.'/../includes/footer.php'; ?>
