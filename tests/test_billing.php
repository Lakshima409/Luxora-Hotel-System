<?php
/** @test BillingEngine math + tax + discount + late-fee correctness. Pure logic (no DB). */

/** Stand-in PDO just enough for BillingEngine: returns a canned reservation row
 *  and a canned services sum; no real DB required. */
class FakePdoBilling extends PDO
{
    public $row = [];
    public $svc = 0.0;
    public $settings = ['tax_rate' => '0.10'];
    public function __construct() { /* no parent::__construct — not a real connection */ }
    #[ReturnTypeWillChange]
    public function prepare($sql, $opts = []) { return new FakeStmt($this, (string)$sql); }
}
class FakeStmt
{
    private string $sql;
    public function __construct(private FakePdoBilling $pdo, string $sql = '') { $this->sql = $sql; }
    public function prepare($sql) { $this->sql = (string)$sql; return $this; }
    public function execute($params = null) { return true; }
    public function fetch($mode = 0, $a = null, $b = null) {
        if (stripos($this->sql, 'FROM reservations') !== false) return $this->pdo->row;
        if (stripos($this->sql, 'SUM(cost)') !== false) return ['sum' => $this->pdo->svc];
        return false;
    }
    public function fetchColumn($n = 0) {
        if (stripos($this->sql, 'SUM(cost)') !== false) return $this->pdo->svc;
        if (stripos($this->sql, 'settings') !== false) return $this->pdo->settings['tax_rate'];
        return 0;
    }
}

class BillingMathTest extends TestCase
{
    private function engine(array $row, float $svc, array $settings = ['tax_rate' => '0.10']): array
    {
        $pdo = new FakePdoBilling();
        $pdo->row = $row;
        $pdo->svc = $svc;
        $pdo->settings = $settings;
        require_once __DIR__ . '/../includes/BillingEngine.php';
        $be = new BillingEngine($pdo);
        return $be->build((int)($row['reservation_id'] ?? 1), 0, 0, 'cash');
    }

    public function test_nightly_rate_times_nights_plus_tax()
    {
        // 3 nights @ 100 + no services => subtotal 300, tax 30, total 330
        $b = $this->engine([
            'reservation_id' => 1,
            'check_in' => '2026-10-01 14:00:00',
            'check_out' => '2026-10-04 12:00:00',
            'rate' => 100,
        ], 0);
        t_eq(3, $b['nights'], 'nights');
        t_eq(300.0, $b['room_charge'], 'room charge');
        t_eq(30.0, $b['tax'], 'tax');
        t_eq(330.0, $b['total'], 'total');
    }

    public function test_services_added_before_tax()
    {
        // 2 nights @ 150 = 300 + 50 services => subtotal 350, tax 35, total 385
        $b = $this->engine([
            'reservation_id' => 2,
            'check_in' => '2026-10-01 14:00:00',
            'check_out' => '2026-10-03 12:00:00',
            'rate' => 150,
        ], 50);
        t_eq(350.0, $b['subtotal'], 'subtotal');
        t_eq(35.0, $b['tax'], 'tax');
        t_eq(385.0, $b['total'], 'total');
    }

    public function test_late_fee_and_discount_applied()
    {
        // 1 night @ 100 = 100, late +20, discount -10 => subtotal 110, tax 11, total 121
        $pdo = new FakePdoBilling();
        $pdo->row = ['reservation_id' => 3, 'check_in' => '2026-10-01 14:00:00', 'check_out' => '2026-10-02 12:00:00', 'rate' => 100];
        $pdo->svc = 0;
        require_once __DIR__ . '/../includes/BillingEngine.php';
        $be = new BillingEngine($pdo);
        $b = $be->build(3, 20, 10, 'cash');
        t_eq(110.0, $b['subtotal'], 'subtotal after late+discount');
        t_eq(11.0, $b['tax'], 'tax');
        t_eq(121.0, $b['total'], 'total');
    }

    public function test_discount_cannot_push_subtotal_negative()
    {
        $pdo = new FakePdoBilling();
        $pdo->row = ['reservation_id' => 4, 'check_in' => '2026-10-01 14:00:00', 'check_out' => '2026-10-02 12:00:00', 'rate' => 100];
        $pdo->svc = 0;
        require_once __DIR__ . '/../includes/BillingEngine.php';
        $be = new BillingEngine($pdo);
        $b = $be->build(4, 0, 999, 'cash'); // huge discount
        t_true($b['subtotal'] >= 0, 'subtotal never negative');
        t_eq(0.0, $b['subtotal'], 'subtotal floored at 0');
        t_eq(0.0, $b['total'], 'total 0 when nothing chargeable');
    }

    public function test_custom_tax_rate()
    {
        // 1 night @ 100, tax_rate 0.20 => subtotal 100, tax 20, total 120
        $b = $this->engine(
            ['reservation_id' => 5, 'check_in' => '2026-10-01 14:00:00', 'check_out' => '2026-10-02 12:00:00', 'rate' => 100],
            0,
            ['tax_rate' => '0.20']
        );
        t_eq(20.0, $b['tax'], 'tax at 20%');
        t_eq(120.0, $b['total'], 'total');
    }

    public function test_missing_reservation_throws()
    {
        $pdo = new FakePdoBilling();
        $pdo->row = false; // simulate not found
        require_once __DIR__ . '/../includes/BillingEngine.php';
        $be = new BillingEngine($pdo);
        t_throws(function () use ($be) { $be->build(999); }, 'not found');
    }
}
