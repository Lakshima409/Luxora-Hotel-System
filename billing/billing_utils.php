<?php
// billing_utils.php – Reusable functions for billing logic

// Calculate tax (e.g., 10% VAT)
function calculate_tax($subtotal, $rate = 0.10) {
    return round($subtotal * $rate, 2);
}

// Format price as LKR currency
function format_currency($amount) {
    return number_format($amount, 2);
}

// Calculate split payment across multiple methods
function split_payment($total, $method1 = "cash", $percent1 = 50) {
    $amount1 = round(($total * $percent1) / 100, 2);
    $amount2 = $total - $amount1;
    return [
        $method1 => $amount1,
        'other' => $amount2
    ];
}

// Sum services for a reservation
function get_service_total($conn, $reservation_id) {
    $stmt = $conn->prepare("SELECT SUM(cost) AS total FROM services WHERE reservation_id = ?");
    $stmt->bind_param("i", $reservation_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['total'] ?? 0;
}

// Generate invoice number
function generate_invoice_number($reservation_id) {
    $prefix = "INV";
    $date = date("Ymd");
    $sequence = str_pad($reservation_id, 5, "0", STR_PAD_LEFT);
    return $prefix . "-" . $date . "-" . $sequence;
}

// Get room details for billing
function get_room_details($conn, $room_id) {
    $stmt = $conn->prepare("SELECT room_number, room_type, rate FROM rooms WHERE room_id = ?");
    $stmt->bind_param("i", $room_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Get guest details for billing
function get_guest_details($conn, $guest_id) {
    $stmt = $conn->prepare("SELECT full_name, email, phone FROM guests WHERE guest_id = ?");
    $stmt->bind_param("i", $guest_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}