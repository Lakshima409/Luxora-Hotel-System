<?php
/**
 * tests/bootstrap.php
 * Minimal, zero-dependency test framework for Luxora Hotel Suite.
 * No Composer / PHPUnit required — runs on stock XAMPP PHP 8.2.
 *
 * Provides:
 *   - TestCase / DbTestCase base classes with PHPUnit-style assertions
 *   - TestAssertionFailed / SkipTest exceptions
 *   - t_db()      -> shared PDO connection (auto-detects MariaDB port) or null
 *   - t_run_php() -> executes a PHP snippet in a child process (for code that exit()s)
 */
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '0'); // keep the report clean; failures are captured as exceptions
ini_set('log_errors', '0');
mb_internal_encoding('UTF-8');

if (!defined('APP_ROOT')) define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/config.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/auth.php';
require_once APP_ROOT . '/includes/Reservation.php';
require_once APP_ROOT . '/includes/BillingEngine.php';

/* ---------------- Exceptions ---------------- */
class TestAssertionFailed extends Exception {}
class SkipTest extends Exception {}

/* ---------------- Assertion helpers (PHPUnit-style) ---------------- */
abstract class TestCase
{
    public function setUp(): void {}
    public function tearDown(): void {}

    protected function assertTrue($cond, string $m = ''): void
    {
        if ($cond !== true) throw new TestAssertionFailed('Failed asserting value is TRUE. ' . $m);
    }
    protected function assertFalse($cond, string $m = ''): void
    {
        if ($cond !== false) throw new TestAssertionFailed('Failed asserting value is FALSE. ' . $m);
    }
    protected function assertEquals($exp, $act, string $m = ''): void
    {
        if ($exp != $act) throw new TestAssertionFailed(
            'Failed asserting equality. Expected ' . var_export($exp, true)
            . ' got ' . var_export($act, true) . '. ' . $m
        );
    }
    protected function assertSame($exp, $act, string $m = ''): void
    {
        if ($exp !== $act) throw new TestAssertionFailed(
            'Failed asserting identity. Expected ' . var_export($exp, true)
            . ' got ' . var_export($act, true) . '. ' . $m
        );
    }
    protected function assertContains($needle, $haystack, string $m = ''): void
    {
        if (!in_array($needle, (array)$haystack, true)) {
            throw new TestAssertionFailed('Failed asserting array contains ' . var_export($needle, true) . '. ' . $m);
        }
    }
    protected function assertStringContains(string $needle, string $haystack, string $m = ''): void
    {
        if (strpos($haystack, $needle) === false) {
            throw new TestAssertionFailed('Failed asserting string contains "' . $needle . '". ' . $m);
        }
    }
    protected function assertNotContainsString(string $needle, string $haystack, string $m = ''): void
    {
        if (strpos($haystack, $needle) !== false) {
            throw new TestAssertionFailed('Unexpected substring "' . $needle . '" found. ' . $m);
        }
    }
    protected function assertGreaterThan($min, $val, string $m = ''): void
    {
        if (!($val > $min)) throw new TestAssertionFailed('Failed asserting ' . var_export($val, true) . ' > ' . var_export($min, true) . '. ' . $m);
    }
    /** Asserts the callback throws, optionally with a message containing $needle. */
    protected function assertThrows(callable $fn, string $needle = '', string $m = ''): Exception
    {
        try {
            $fn();
        } catch (Throwable $e) {
            if ($needle !== '' && strpos($e->getMessage(), $needle) === false) {
                throw new TestAssertionFailed('Exception message "' . $e->getMessage() . '" does not contain "' . $needle . '". ' . $m);
            }
            return $e;
        }
        throw new TestAssertionFailed('Failed asserting that an exception was thrown. ' . $m);
    }
}

/* ---------------- Global assertion shims (used by test files) ---------------- */
/** These throw TestAssertionFailed on failure; the runner catches it per-test. */
function t_eq($exp, $act, string $m = ''): void
{
    if ($exp != $act) throw new TestAssertionFailed(
        'Expected ' . var_export($exp, true) . ' got ' . var_export($act, true) . ($m ? " [$m]" : '')
    );
}
function t_same($exp, $act, string $m = ''): void
{
    if ($exp !== $act) throw new TestAssertionFailed(
        'Expected identical ' . var_export($exp, true) . ' got ' . var_export($act, true) . ($m ? " [$m]" : '')
    );
}
function t_true($cond, string $m = ''): void
{
    if ($cond !== true) throw new TestAssertionFailed('Expected TRUE, got ' . var_export($cond, true) . ($m ? " [$m]" : ''));
}
function t_false($cond, string $m = ''): void
{
    if ($cond !== false) throw new TestAssertionFailed('Expected FALSE, got ' . var_export($cond, true) . ($m ? " [$m]" : ''));
}
function t_throws(callable $fn, string $needle = ''): void
{
    try { $fn(); }
    catch (Throwable $e) {
        if ($needle !== '' && stripos($e->getMessage(), $needle) === false) {
            throw new TestAssertionFailed('Exception "' . $e->getMessage() . '" does not contain "' . $needle . '"');
        }
        return;
    }
    throw new TestAssertionFailed('Expected an exception but none was thrown.');
}
function t_has(string $needle, string $hay, string $m = ''): void
{
    if (strpos($hay, $needle) === false) throw new TestAssertionFailed('Expected to contain "' . $needle . '"' . ($m ? " [$m]" : ''));
}
function t_hasnot(string $needle, string $hay, string $m = ''): void
{
    if (strpos($hay, $needle) !== false) throw new TestAssertionFailed('Unexpected substring "' . $needle . '"' . ($m ? " [$m]" : ''));
}


/* ---------------- DB-aware base class with auto ZZTEST* cleanup ---------------- */
abstract class DbTestCase extends TestCase
{
    protected ?PDO $pdo = null;

    public function setUp(): void
    {
        $this->pdo = t_db();
        if (!$this->pdo) throw new SkipTest('database unavailable');
    }

    public function tearDown(): void
    {
        if ($this->pdo) $this->cleanup();
    }

    /** Deletes every row created by the suite (marked ZZTEST*). Idempotent + FK-safe. */
    protected function cleanup(): void
    {
        $pdo = $this->pdo;
        $ids = $pdo->query("SELECT reservation_id FROM reservations WHERE guest_name LIKE 'ZZTEST%'")
                   ->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $rid) {
            $pdo->prepare("DELETE FROM payments WHERE bill_id IN (SELECT bill_id FROM bills WHERE reservation_id=?)")->execute([$rid]);
            $pdo->prepare("DELETE FROM bill_items WHERE bill_id IN (SELECT bill_id FROM bills WHERE reservation_id=?)")->execute([$rid]);
            $pdo->prepare("DELETE FROM bills WHERE reservation_id=?")->execute([$rid]);
            $pdo->prepare("DELETE FROM services WHERE reservation_id=?")->execute([$rid]);
            $pdo->prepare("DELETE FROM reservations WHERE reservation_id=?")->execute([$rid]);
        }
        $pdo->exec("DELETE FROM customers WHERE full_name LIKE 'ZZTEST%'");
        $pdo->exec("DELETE FROM rooms WHERE room_number LIKE 'ZZTEST%'");
    }

    /** Creates a throw-away room and returns its id. */
    protected function makeRoom(string $type = 'Standard', float $rate = 100.0): int
    {
        $num = 'ZZTEST-' . substr(uniqid(), -6);
        $this->pdo->prepare(
            "INSERT INTO rooms (room_number,room_type,rate,price,capacity,status,room_condition)
             VALUES (?,?,?,?,?, 'available','Available')"
        )->execute([$num, $type, $rate, $rate, 2]);
        return (int)$this->pdo->lastInsertId();
    }
}

/* ---------------- Shared connection helper ---------------- */
function t_db(): ?PDO
{
    static $pdo = null, $tried = false;
    if ($tried) return $pdo;
    $tried = true;
    foreach ([3307, 3306, null] as $port) {
        try {
            $dsn = $port
                ? "mysql:host=127.0.0.1;port=$port;dbname=hotel_management;charset=utf8mb4"
                : "mysql:host=127.0.0.1;dbname=hotel_management;charset=utf8mb4";
            $pdo = new PDO($dsn, 'root', '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            return $pdo;
        } catch (Throwable $e) { $pdo = null; }
    }
    return null;
}

/* ---------------- Child-process runner (for code that exit()s) ---------------- */
function t_php_bin(): string
{
    return defined('PHP_BINARY') && PHP_BINARY !== '' ? PHP_BINARY : 'php';
}
function t_run_php(string $body): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'lux_test_') . '.php';
    file_put_contents($tmp, "<?php error_reporting(0);\n" . $body);
    $out = []; $rc = 0;
    exec(escapeshellarg(t_php_bin()) . ' ' . escapeshellarg($tmp) . ' 2>&1', $out, $rc);
    @unlink($tmp);
    return ['code' => $rc, 'output' => implode("\n", $out)];
}

/* ---------------- Global assertion shims (functional style used by test_*.php) ----------------
 * These throw TestAssertionFailed so the runner reports a failure (message optional). */
if (!function_exists('t_eq')) {
    function t_eq($exp, $act, string $m = ''): void {
        if ($exp != $act) throw new TestAssertionFailed('Expected ' . var_export($exp, true)
            . ' got ' . var_export($act, true) . '. ' . $m);
    }
}
if (!function_exists('t_same')) {
    function t_same($exp, $act, string $m = ''): void {
        if ($exp !== $act) throw new TestAssertionFailed('Expected identical '
            . var_export($exp, true) . ' got ' . var_export($act, true) . '. ' . $m);
    }
}
if (!function_exists('t_true')) {
    function t_true($cond, string $m = ''): void {
        if ($cond !== true) throw new TestAssertionFailed('Expected TRUE. ' . $m);
    }
}
if (!function_exists('t_false')) {
    function t_false($cond, string $m = ''): void {
        if ($cond !== false) throw new TestAssertionFailed('Expected FALSE. ' . $m);
    }
}
if (!function_exists('t_contains')) {
    function t_contains($needle, $haystack, string $m = ''): void {
        if (!in_array($needle, (array)$haystack, true))
            throw new TestAssertionFailed('Expected ' . var_export($needle, true) . ' in array. ' . $m);
    }
}
if (!function_exists('t_throws')) {
    function t_throws(callable $fn, string $needle = '', string $m = ''): void {
        try { $fn(); }
        catch (Throwable $e) {
            if ($needle !== '' && stripos($e->getMessage(), $needle) === false)
                throw new TestAssertionFailed('Exception "' . $e->getMessage()
                    . '" missing "' . $needle . '". ' . $m);
            return;
        }
        throw new TestAssertionFailed('Expected an exception. ' . $m);
    }
}


