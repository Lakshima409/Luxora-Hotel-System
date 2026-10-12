<?php
require_once '../includes/auth.php';
require_role('clerk');

require_once '../db.php';

$reservation_id = $_GET['id'] ?? null;
if (!$reservation_id) {
    header("Location: checkout.php");
    exit();
}

try {
    // Begin transaction
    $pdo->beginTransaction();

    // Fetch reservation, guest, and room details
    $stmt = $pdo->prepare("SELECT res.*, r.rate, r.room_id, r.room_number, res.is_travel_agency_guest
                           FROM reservations res
                           JOIN rooms r ON res.room_id = r.room_id
                           WHERE res.reservation_id = ?");
    $stmt->execute([$reservation_id]);
    $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reservation) {
        throw new Exception("Reservation not found.");
    }

    $room_rate = $reservation['rate'];
    $room_id = $reservation['room_id'];
    $is_travel_agency_guest = $reservation['is_travel_agency_guest'] ?? 0;

    // Late checkout fee
    $late_fee = 0;
    $now = new DateTime();
    $checkout_time = new DateTime($reservation['check_out']);
    if ($now > $checkout_time) {
        $late_fee = 50.00;
    }

    // Fetch additional services
    $stmt = $pdo->prepare("SELECT service_name, cost FROM services WHERE reservation_id = ?");
    $stmt->execute([$reservation_id]);
    $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $service_total = array_sum(array_column($services, 'cost'));

    // Total calculation
    $subtotal = $room_rate + $service_total + $late_fee;
    $tax = round($subtotal * 0.10, 2);
    $total = $subtotal + $tax;

    // Determine payment method
    $payment_method = $is_travel_agency_guest ? 'agency' : 'cash';

    // Insert into bills
    $stmt = $pdo->prepare("INSERT INTO bills (reservation_id, total_amount, payment_method) 
                           VALUES (?, ?, ?)");
    $stmt->execute([$reservation_id, $total, $payment_method]);
    $bill_id = $pdo->lastInsertId();

    // Insert bill items
    $bill_items = [
        ['Room Charges', $room_rate],
    ];
    foreach ($services as $s) {
        $bill_items[] = [$s['service_name'], $s['cost']];
    }
    if ($late_fee > 0) {
        $bill_items[] = ['Late Checkout Fee', $late_fee];
    }
    $bill_items[] = ['Tax (10%)', $tax];

    $stmt = $pdo->prepare("INSERT INTO bill_items (bill_id, description, amount) VALUES (?, ?, ?)");
    foreach ($bill_items as $item) {
        $stmt->execute([$bill_id, $item[0], $item[1]]);
    }

    // Update room and reservation status
    $stmt = $pdo->prepare("UPDATE reservations SET status = 'checked_out' WHERE reservation_id = ?");
    $stmt->execute([$reservation_id]);

    $stmt = $pdo->prepare("UPDATE rooms SET status = 'available' WHERE room_id = ?");
    $stmt->execute([$room_id]);

    $pdo->commit();

    header("Location: ../billing/receipt.php?id=$reservation_id");
    exit();

} catch (Exception $e) {
    $pdo->rollBack();
    echo "<div class='alert alert-danger'>Checkout failed: " . $e->getMessage() . "</div>";
}
?>
