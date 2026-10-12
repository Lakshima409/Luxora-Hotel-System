<?php
require_once '../includes/auth.php';
require_once '../db.php';
require_once '../config.php';
// Include all necessary middleware files
require_once '../middleware/guest_type_handler.php';
require_once '../middleware/room_status_checker.php';
require_once '../middleware/validate_credit_policy.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reservation_id = $_POST['reservation_id'] ?? null;
    $payment_method = $_POST['payment_method'] ?? '';
    $services = $_POST['services'] ?? [];
    $total_amount = $_POST['total_amount'] ?? 0;
    $late_fee = $_POST['late_fee'] ?? 0;
    
    // Handle agency bookings
    $override_payment = $_POST['override_payment_method'] ?? '';
    if ($override_payment === 'billed_to_agency') {
        $payment_method = 'billed_to_agency';
    }

    if (!$reservation_id || (!$payment_method && $override_payment !== 'billed_to_agency')) {
        $_SESSION['error'] = "Invalid checkout data. Please try again.";
        header("Location: checkout_form.php");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // 1. Validate the reservation and lock it for update
        $stmt = $pdo->prepare("
            SELECT r.*, g.full_name, g.email, g.phone
            FROM reservations r
            JOIN guests g ON r.guest_name = g.full_name AND r.email = g.email
            WHERE r.id = ? AND r.status = 'checked_in'
            FOR UPDATE
        ");
        $stmt->execute([$reservation_id]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reservation) {
            throw new Exception("Reservation not found or already checked out.");
        }

        // 2. Calculate stay duration and room charges
        $checkIn = new DateTime($reservation['check_in']);
        $checkOut = new DateTime();
        $nights = $checkOut->diff($checkIn)->days;
        
        // Count as full night if checkout is after 12 PM
        if ($checkOut->format('H') > 12 || ($nights == 0 && $checkOut->diff($checkIn)->h >= 1)) {
            $nights++;
        }
        
        // Get room price from reservation (since we don't have room_id relationship)
        $room_rate = 0;
        $room_types = [
            'Single' => 100.00,
            'Double' => 150.00,
            'Family Room' => 200.00,
            'Suite' => 250.00,
            'Penthouse Suite' => 400.00,
            'Honeymoon Suite' => 350.00,
            'Luxury Room' => 300.00
        ];
        
        if (isset($room_types[$reservation['room_type']])) {
            $room_rate = $room_types[$reservation['room_type']];
        }
        
        $room_charge = $nights * $room_rate;

        // 3. Calculate service charges
        $service_charges = 0;
        $service_details = [];
        $service_prices = [
            "Room Service" => 25.00,
            "Laundry" => 15.00,
            "Telephone" => 10.00,
            "Club Facility" => 30.00,
            "Key Issuance" => 5.00
        ];
        
        foreach ($services as $service) {
            if (isset($service_prices[$service])) {
                $service_charges += $service_prices[$service];
                $service_details[] = [
                    'name' => $service,
                    'price' => $service_prices[$service]
                ];
            }
        }

        // 4. Calculate taxes (10% of subtotal)
        $subtotal = $room_charge + $service_charges + $late_fee;
        $tax = $subtotal * 0.10;
        $total_amount = $subtotal + $tax;

        // 5. Update reservation status (we don't have bills table)
        $stmt = $pdo->prepare("
            UPDATE reservations 
            SET status = 'checked_out', 
                check_out = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$reservation_id]);

        // 6. Log the activity in action_logs (instead of activity_logs)
        $stmt = $pdo->prepare("
            INSERT INTO action_logs 
            (user_id, action_type, message, timestamp)
            VALUES (?, 'checkout', ?, NOW())
        ");
        $description = "Checked out reservation #{$reservation_id} for {$reservation['guest_name']}";
        $stmt->execute([$_SESSION['user_id'], $description]);

        // 7. Store service charges (if any)
        if (!empty($service_details)) {
            foreach ($service_details as $service) {
                $stmt = $pdo->prepare("
                    INSERT INTO services 
                    (reservation_id, service_name, cost, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$reservation_id, $service['name'], $service['price']]);
            }
        }

        $pdo->commit();

        // Store invoice data in session for success page
        $_SESSION['invoice_data'] = [
            'reservation_id' => $reservation_id,
            'guest_name' => $reservation['guest_name'],
            'room_type' => $reservation['room_type'],
            'check_in' => $reservation['check_in'],
            'check_out' => date('Y-m-d H:i:s'),
            'nights' => $nights,
            'room_rate' => $room_rate,
            'room_charge' => $room_charge,
            'services' => $service_details,
            'late_fee' => $late_fee,
            'tax' => $tax,
            'total_amount' => $total_amount,
            'payment_method' => $payment_method
        ];

        // Redirect to success page
        header("Location: checkout_success.php?reservation_id={$reservation_id}");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Checkout failed: " . $e->getMessage();
        error_log("Checkout Error: " . $e->getMessage());
        header("Location: checkout_form.php");
        exit();
    }
} else {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: checkout_form.php");
    exit();
}