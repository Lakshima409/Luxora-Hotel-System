<?php
// includes/Reservation.php - OOP reservation with overlap guard + 7PM card rule
require_once __DIR__.'/helpers.php';
class Reservation {
  private $pdo;
  public function __construct($pdo){ $this->pdo=$pdo; }
  // Basic server-side validation. Never trust the client — re-check everything here.
  public function validateInput($d){
    $errs=[];
    if(empty($d['guest_name'])) $errs[]='Guest name required';
    if(empty($d['check_in'])||empty($d['check_out'])) $errs[]='Dates required';
    else{
      $ci=strtotime($d['check_in']); $co=strtotime($d['check_out']);
      if($co<=$ci) $errs[]='Check-out must be after check-in'; // logical date sanity
    }
    if(!empty($d['occupants'])&&(int)$d['occupants']<1) $errs[]='Invalid occupants';
    return $errs; // returns array of problems (empty = valid)
  }
  // Business rule: a NON-walk-in booking made before 7PM must provide a card (guarantee).
  // After 7PM the requirement is relaxed; unpaid card-less reservations are auto-cancelled
  // later by the cron job (AUTOCANCEL_HOUR = 19). This function decides whether to warn now.
  public function cardRequired($is_walkin,$card_last4){
    $h=(int)date('H'); // current hour, 0-23
    if(!$is_walkin && $h < 19 && empty($card_last4)) return true;
    return false;
  }
  // Double-booking guard. Two date ranges overlap unless one ends on/before the other starts.
  // Logic: NOT (check_out <= our_start OR check_in >= our_end) => ranges intersect.
  // Cancelled/Checked-Out/No-Show reservations don't block availability. $exclude lets an
  // edit ignore the reservation being edited.
  public function isAvailable($room_id,$ci,$co,$exclude=null){
    $sql="SELECT COUNT(*) FROM reservations WHERE room_id=? AND booking_status NOT IN ('Cancelled','Checked-Out','No-Show') AND NOT (check_out<=? OR check_in>=?)";
    $p=[$room_id,$ci,$co];
    if($exclude){ $sql.=" AND reservation_id<>?"; $p[]=$exclude; }
    $s=$this->pdo->prepare($sql); $s->execute($p);
    return ((int)$s->fetchColumn()===0); // 0 overlaps = available
  }
  // Create a reservation atomically. Uses a DB TRANSACTION so the customer insert,
  // reservation insert, status mirrors and room-status update either ALL succeed or ALL
  // roll back — no half-created bookings if something fails midway (ACID consistency).
  public function create($d){
    $errs=$this->validateInput($d); if($errs) throw new Exception(implode('; ',$errs));
    if(empty($d['room_id'])) throw new Exception('Room required');
    if(!$this->isAvailable($d['room_id'],$d['check_in'],$d['check_out'])) throw new Exception('Room not available for those dates (overlap).');
    // card rule is warning pre-7PM, hard fail handled by cron at 7PM - store flag
    $has_card = !empty($d['card_last4'])?1:0;
    $this->pdo->beginTransaction();
    try{
      // ensure customer exists (reuse customer_id or create a new customer row)
      $cid=null;
      if(!empty($d['customer_id'])) $cid=$d['customer_id'];
      else{
        $s=$this->pdo->prepare("INSERT INTO customers (full_name,email,phone,card_token) VALUES (?,?,?,?)");
        $s->execute([$d['guest_name'],$d['email']??null,$d['phone']??null,$d['card_last4']??null]);
        $cid=$this->pdo->lastInsertId();
      }
      // Insert the reservation. Note the several *_date/*_status columns are historical
      // "mirror" columns kept for backward compatibility with older pages.
      $s=$this->pdo->prepare("INSERT INTO reservations (customer_id,room_id,room_number,room_type,guest_name,email,phone,check_in,check_out,checkin_date,checkout_date,check_in_date,check_out_date,occupants,num_guests,booking_status,status,reservation_status,has_card,card_last4,is_walk_in,is_walkin,is_group,group_id,booked_by,company_name,special_requests,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
      $roomNo=$d['room_number']??null; $roomType=$d['room_type']??null;
      $ci=$d['check_in']; $co=$d['check_out'];
      $s->execute([$cid,$d['room_id'],$roomNo,$roomType,$d['guest_name'],$d['email']??null,$d['phone']??null,$ci,$co,substr($ci,0,10),substr($co,0,10),$ci,$co,$d['occupants']??1,$d['occupants']??1,'Confirmed','booked','booked',$has_card,$d['card_last4']??null,$d['is_walk_in']??0,$d['is_walk_in']??0,$d['is_group']??0,$d['group_id']??null,$d['booked_by']??'guest',$d['company_name']??null,$d['special_requests']??null,$d['created_by']??null]);
      $rid=$this->pdo->lastInsertId();
      // mirrors: keep id/guest_id columns in sync with reservation_id/customer_id
      $this->pdo->prepare("UPDATE reservations SET id=reservation_id, guest_id=customer_id WHERE reservation_id=?")->execute([$rid]);
      // mark the room as reserved so it won't be offered for the same dates
      $this->pdo->prepare("UPDATE rooms SET room_condition='Reserved', status='reserved' WHERE room_id=?")->execute([$d['room_id']]);
      $this->pdo->commit();
      return $rid;
    }catch(Exception $e){ $this->pdo->rollBack(); throw $e; } // undo everything on failure
  }
  // Cancel a reservation and write an audit trail entry (who/when/action).
  public function cancel($rid,$uid,$role){
    $s=$this->pdo->prepare("UPDATE reservations SET booking_status='Cancelled', status='cancelled', reservation_status='cancelled', updated_at=NOW() WHERE reservation_id=?");
    $s->execute([$rid]);
    log_action_pdo($this->pdo,$uid,$role,'cancel',"Cancelled reservation #$rid");
    return true;
  }
}
