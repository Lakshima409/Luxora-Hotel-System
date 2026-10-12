<?php
/** @test Shared helper functions: e(), nights_between(), get_setting(), log_action_pdo(). */

class HelpersTest extends TestCase
{
    public function setUp(): void
    {
        require_once __DIR__ . '/../includes/helpers.php';
    }

    public function test_e_escapes_html()
    {
        t_eq('&lt;b&gt;', e('<b>'), 'escapes angle brackets');
        t_eq('&quot;', e('"'), 'escapes double quote');
        t_eq('&#039;', e("'"), 'escapes single quote');
        t_eq('a &amp; b', e('a & b'), 'escapes ampersand');
    }

    public function test_e_handles_null_and_numbers()
    {
        t_eq('', e(null), 'null becomes empty string');
        t_eq('42', e(42), 'int cast to string');
    }

    public function test_nights_between_counts_full_days()
    {
        t_eq(3, nights_between('2026-10-01 14:00:00', '2026-10-04 12:00:00'), '3 nights');
        t_eq(1, nights_between('2026-10-01 14:00:00', '2026-10-02 12:00:00'), '1 night');
    }

    public function test_nights_between_floor_is_one()
    {
        // Same-day must never be zero or negative (min 1 night).
        t_eq(1, nights_between('2026-10-01 14:00:00', '2026-10-01 18:00:00'), 'same day floors to 1');
        // Reversed dates: diff is absolute, but must still be a positive number (never 0/negative).
        $rev = nights_between('2026-10-04 12:00:00', '2026-10-01 14:00:00');
        t_true($rev >= 1, 'reversed dates still yield >= 1 night');
    }

    public function test_get_setting_returns_default_when_missing()
    {
        // FakePdo whose query yields no row => default returned.
        $pdo = new FakeSettingsPdo(false);
        t_eq('FALLBACK', get_setting($pdo, 'tax_rate', 'FALLBACK'), 'missing setting returns default');
    }

    public function test_get_setting_returns_stored_value()
    {
        $pdo = new FakeSettingsPdo('0.15');
        t_eq('0.15', get_setting($pdo, 'tax_rate', 'FALLBACK'), 'stored value returned');
    }

    public function test_get_setting_survives_db_error()
    {
        // PDO that throws must not bubble — helper returns default.
        $pdo = new class extends PDO {
            public function __construct() {}
            #[ReturnTypeWillChange]
            public function prepare($sql, $opts = []) { throw new RuntimeException('boom'); }
        };
        t_eq('DEF', get_setting($pdo, 'x', 'DEF'), 'DB error swallowed, default returned');
    }

    public function test_log_action_swallows_errors()
    {
        // Should never throw even if the insert fails.
        $pdo = new class extends PDO {
            public function __construct() {}
            #[ReturnTypeWillChange]
            public function prepare($sql, $opts = []) { throw new RuntimeException('no table'); }
        };
        $threw = false;
        try { log_action_pdo($pdo, 1, 'clerk', 'test', 'msg'); } catch (Throwable $t) { $threw = true; }
        t_false($threw, 'log_action_pdo must not throw on DB failure');
    }
}

/* --- minimal fakes for get_setting --- */
class FakeSettingsPdo extends PDO
{
    private $val;
    public function __construct($val) { $this->val = $val; }
    #[ReturnTypeWillChange]
    public function prepare($sql, $opts = []) { return new FakeSettingsStmt($this->val); }
}
class FakeSettingsStmt
{
    private $val;
    public function __construct($val) { $this->val = $val; }
    public function execute($p = null) { return true; }
    public function fetchColumn($n = 0) { return $this->val; }
}
