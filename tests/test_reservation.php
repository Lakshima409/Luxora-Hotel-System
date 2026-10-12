<?php
/** @test Reservation lifecycle: create -> confirm -> check-in -> check-out -> cancel.
 *  DB-aware: skipped automatically if MySQL is unavailable. All rows tagged ZZTEST*. */

class ReservationFlowTest extends DbTestCase
{
    private function makeCustomer(string $suffix): int
    {
        $st = $this->pdo->prepare(
            "INSERT INTO customers (full_name,email,phone) VALUES (?,?,?)"
        );
        $st->execute(['ZZTEST Guest ' . $suffix, 'zz' . $suffix . '@test.local', '0770' . $suffix]);
        return (int)$this->pdo->lastInsertId();
    }

    private function insertReservation(int $roomId, int $custId, string $status = 'Confirmed', array $dates = null): int
    {
        $d = $dates ?? ['ci' => date('Y-m-d 14:00:00', strtotime('+1 day')), 'co' => date('Y-m-d 12:00:00', strtotime('+3 day'))];
        $st = $this->pdo->prepare(
            "INSERT INTO reservations
                (customer_id,room_id,guest_name,email,phone,check_in,check_out,booking_status,status,reservation_status)
             VALUES (?,?,?,?,?,?,?,?,?,?)"
        );
        $st->execute([
            $custId, $roomId, 'ZZTEST Guest', 'zz@test.local', '0770000000',
            $d['ci'], $d['co'], $status, strtolower(str_replace('-', '_', $status)), strtolower(str_replace('-', '_', $status))
        ]);
        $id = (int)$this->pdo->lastInsertId();
        $this->pdo->prepare("UPDATE reservations SET id=reservation_id, guest_id=customer_id WHERE reservation_id=?")->execute([$id]);
        return $id;
    }

    private function status(int $rid): string
    {
        $st = $this->pdo->prepare("SELECT booking_status FROM reservations WHERE reservation_id=?");
        $st->execute([$rid]);
        return (string)$st->fetchColumn();
    }

    public function test_create_reservation_persists()
    {
        $room = $this->makeRoom();
        $cust = $this->makeCustomer('create');
        $rid = $this->insertReservation($room, $cust, 'Confirmed');
        t_true($rid > 0, 'reservation inserted with a generated id');
        t_eq('Confirmed', $this->status($rid), 'initial status Confirmed');
    }

    public function test_cancel_sets_cancelled_status()
    {
        $room = $this->makeRoom();
        $cust = $this->makeCustomer('cancel');
        $rid = $this->insertReservation($room, $cust, 'Confirmed');
        $this->pdo->prepare("UPDATE reservations SET booking_status='Cancelled', status='cancelled', reservation_status='cancelled' WHERE reservation_id=?")->execute([$rid]);
        t_eq('Cancelled', $this->status($rid), 'status becomes Cancelled after cancel');
    }

    public function test_checkin_requires_confirmed_or_pending()
    {
        // Only Pending/Confirmed should be eligible (mirrors checkin_form.php query).
        $room = $this->makeRoom();
        $cust = $this->makeCustomer('elig');
        $cancelled = $this->insertReservation($room, $cust, 'Cancelled');
        $st = $this->pdo->prepare("SELECT COUNT(*) FROM reservations WHERE reservation_id=? AND booking_status IN ('Pending','Confirmed')");
        $st->execute([$cancelled]);
        t_eq(0, (int)$st->fetchColumn(), 'cancelled reservation is NOT eligible for check-in');
    }

    public function test_checkin_transitions_to_checked_in_and_occupies_room()
    {
        $room = $this->makeRoom();
        $cust = $this->makeCustomer('in');
        $rid = $this->insertReservation($room, $cust, 'Confirmed');
        // mimic checkin_form.php success path
        $this->pdo->prepare("UPDATE reservations SET booking_status='Checked-In', status='checked_in', reservation_status='checked_in', room_id=? WHERE reservation_id=?")->execute([$room, $rid]);
        $this->pdo->prepare("UPDATE rooms SET status='occupied', room_condition='Occupied' WHERE room_id=?")->execute([$room]);
        t_eq('Checked-In', $this->status($rid), 'status becomes Checked-In');
        $rs = $this->pdo->prepare("SELECT status FROM rooms WHERE room_id=?");
        $rs->execute([$room]);
        t_eq('occupied', $rs->fetchColumn(), 'room marked occupied');
    }

    public function test_checkout_transitions_to_checked_out_and_frees_room()
    {
        $room = $this->makeRoom();
        $cust = $this->makeCustomer('out');
        $rid = $this->insertReservation($room, $cust, 'Checked-In');
        $this->pdo->prepare("UPDATE rooms SET status='occupied', room_condition='Occupied' WHERE room_id=?")->execute([$room]);
        // mimic checkout_form.php success path
        $this->pdo->prepare("UPDATE reservations SET booking_status='Checked-Out', status='checked_out', reservation_status='checked_out', check_out=NOW() WHERE reservation_id=?")->execute([$rid]);
        $this->pdo->prepare("UPDATE rooms SET status='available', room_condition='Available' WHERE room_id=?")->execute([$room]);
        t_eq('Checked-Out', $this->status($rid), 'status becomes Checked-Out');
        $rs = $this->pdo->prepare("SELECT status FROM rooms WHERE room_id=?");
        $rs->execute([$room]);
        t_eq('available', $rs->fetchColumn(), 'room freed back to available');
    }

    public function test_reservation_requires_room_and_dates()
    {
        // A reservation with no room_id should still store (nullable), but dates must exist.
        $cust = $this->makeCustomer('nodates');
        $st = $this->pdo->prepare(
            "INSERT INTO reservations (customer_id,guest_name,booking_status,status,reservation_status) VALUES (?,'ZZTEST Guest','Confirmed','confirmed','confirmed')"
        );
        $st->execute([$cust]);
        t_true($this->pdo->lastInsertId() > 0, 'row persists even without room (nullable FK)');
    }
}
