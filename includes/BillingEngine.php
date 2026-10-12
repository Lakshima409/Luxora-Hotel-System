<?php
// includes/BillingEngine.php - Bill = rate*nights + services + late - discount + tax
require_once __DIR__.'/helpers.php';
class BillingEngine {
  private $pdo; public function __construct($pdo){ $this->pdo=$pdo; }
  public function build($reservation_id,$late_fee=0,$discount=0,$payment_method='cash'){
    $s=$this->pdo->prepare("SELECT r.*, rm.rate FROM reservations r LEFT JOIN rooms rm ON rm.room_id=r.room_id WHERE r.reservation_id=?");
    $s->execute([$reservation_id]); $r=$s->fetch();
    if(!$r) throw new Exception('Reservation not found');
    $rate=(float)($r['rate']??100);
    $n=nights_between($r['check_in'],$r['check_out']);
    $room_charge=$rate*$n;
    $q=$this->pdo->prepare("SELECT COALESCE(SUM(cost),0) FROM services WHERE reservation_id=?");
    $q->execute([$reservation_id]); $svc=(float)$q->fetchColumn();
    $tax_rate=(float)get_setting($this->pdo,'tax_rate',0.10);
    $subtotal=$room_charge+$svc+$late_fee-$discount;
    if($subtotal<0) $subtotal=0;
    $tax=round($subtotal*$tax_rate,2);
    $total=round($subtotal+$tax,2);
    return ['nights'=>$n,'rate'=>$rate,'room_charge'=>$room_charge,'services'=>$svc,'late_fee'=>$late_fee,'discount'=>$discount,'subtotal'=>$subtotal,'tax'=>$tax,'total'=>$total,'payment_method'=>$payment_method,'reservation'=>$r];
  }
  public function finalize($reservation_id,$late_fee=0,$discount=0,$payment_method='cash',$paid_amounts=[]){
    $b=$this->build($reservation_id,$late_fee,$discount,$payment_method);
    $this->pdo->beginTransaction();
    try{
      $s=$this->pdo->prepare("INSERT INTO bills (reservation_id,subtotal,tax,late_fee,discount,total_amount,payment_status,payment_method,snapshot) VALUES (?,?,?,?,?,?,?, ?,?)");
      // status decided after payments
      $s->execute([$reservation_id,$b['subtotal'],$b['tax'],$late_fee,$discount,$b['total'],'Unpaid',$payment_method,json_encode($b)]);
      $bid=$this->pdo->lastInsertId();
      $this->pdo->prepare("UPDATE bills SET id=bill_id WHERE bill_id=?")->execute([$bid]);
      $items=[["Room Charge ({$b['nights']} nights @ {$b['rate']})",$b['room_charge']]];
      // expand services
      $q=$this->pdo->prepare("SELECT service_name,cost FROM services WHERE reservation_id=?"); $q->execute([$reservation_id]);
      foreach($q->fetchAll() as $row) $items[]=[$row['service_name'],(float)$row['cost']];
      if($late_fee>0) $items[]=['Late Checkout Fee',(float)$late_fee];
      if($discount>0) $items[]=['Discount',-(float)$discount];
      $items[]=['Tax ('.((float)get_setting($this->pdo,'tax_rate',0.10)*100).'%)',$b['tax']];
      $ins=$this->pdo->prepare("INSERT INTO bill_items (bill_id,description,amount) VALUES (?,?,?)");
      foreach($items as $it) $ins->execute([$bid,$it[0],$it[1]]);
      $paid=0;
      foreach($paid_amounts as $pm){ $p=$this->pdo->prepare("INSERT INTO payments (bill_id,method,amount) VALUES (?,?,?)"); $p->execute([$bid,$pm[0],$pm[1]]); $paid+=(float)$pm[1]; }
      $status='Unpaid'; if($paid>= $b['total']-0.01 && $b['total']>0) $status='Paid'; elseif($paid>0) $status='Partial';
      $this->pdo->prepare("UPDATE bills SET payment_status=? WHERE bill_id=?")->execute([$status,$bid]);
      $this->pdo->commit();
      return [$bid,$b,$status];
    }catch(Exception $e){ $this->pdo->rollBack(); throw $e; }
  }
}
