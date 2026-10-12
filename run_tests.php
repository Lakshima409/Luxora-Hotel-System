<?php
/**
 * run_tests.php — Luxora Hotel Suite automated test runner (zero-dependency).
 *
 * Usage (from the project root, in a terminal / XAMPP shell):
 *     php run_tests.php            # run everything
 *     php run_tests.php -v         # verbose (also list skipped tests)
 *
 * Auto-discovers every tests/test_*.php file and runs all TestCase subclasses.
 * Exit code: 0 = all passed (skips allowed), 1 = at least one failure/error.
 */
declare(strict_types=1);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', '1');

require_once __DIR__ . '/tests/bootstrap.php';

$verbose = in_array('-v', $argv, true) || in_array('--verbose', $argv, true);

// --- auto-discover test files ---
$files = glob(__DIR__ . '/tests/test_*.php') ?: [];
sort($files);
$loaded = [];
foreach ($files as $f) {
    $before = get_declared_classes();
    require_once $f;
    // capture any classes defined by this file
    foreach (array_diff(get_declared_classes(), $before) as $cls) {
        $loaded[$cls] = $f;
    }
}

// keep only TestCase subclasses that actually have test* methods
$suites = [];
foreach ($loaded as $cls => $file) {
    if (!is_subclass_of($cls, TestCase::class)) continue;
    $refl = new ReflectionClass($cls);
    if ($refl->isAbstract()) continue;
    foreach ($refl->getMethods(ReflectionMethod::IS_PUBLIC) as $m) {
        if (str_starts_with($m->name, 'test')) { $suites[] = $cls; break; }
    }
}

$C = ['g' => "\033[32m", 'r' => "\033[31m", 'y' => "\033[33m", 'b' => "\033[1m", '0' => "\033[0m"];
function supportsColor(): bool { return DIRECTORY_SEPARATOR !== '\\'; }

echo "\n" . (supportsColor() ? "{$C['b']}" : '') .
     "Luxora Hotel Suite — Automated Test Suite" .
     (supportsColor() ? "{$C['0']}" : '') . "\n";
echo str_repeat('=', 60) . "\n";
echo 'PHP ' . PHP_VERSION . '   DB: ' . (t_db() ? 'connected' : 'UNAVAILABLE (DB tests will skip)') . "\n";
echo 'Suites discovered: ' . count($suites) . ' across ' . count($files) . " file(s)\n";
echo str_repeat('=', 60) . "\n\n";

$pass = $fail = $skip = 0;
$failures = [];
$t0 = microtime(true);

foreach ($suites as $cls) {
    $short = (new ReflectionClass($cls))->getShortName();
    echo (supportsColor() ? "{$C['b']}" : '') . $short . (supportsColor() ? "{$C['0']}" : '') . "\n";
    $refl = new ReflectionClass($cls);
    $methods = array_filter(
        $refl->getMethods(ReflectionMethod::IS_PUBLIC),
        static fn($m) => str_starts_with($m->name, 'test')
    );
    usort($methods, static fn($a, $b) => strcmp($a->name, $b->name));

    if (!$methods) { echo "  (no tests)\n\n"; continue; }

    foreach ($methods as $m) {
        $obj = $refl->newInstance();
        $label = substr($m->name, 5); // strip "test"
        try {
            $obj->setUp();
            try {
                $m->invoke($obj);
                $pass++;
                echo '  ' . (supportsColor() ? "{$C['g']}PASS{$C['0']}" : 'PASS') . "  $label\n";
            } finally {
                $obj->tearDown();
            }
        } catch (SkipTest $e) {
            $skip++;
            if ($verbose) echo '  ' . (supportsColor() ? "{$C['y']}SKIP{$C['0']}" : 'SKIP') . "  $label — " . $e->getMessage() . "\n";
        } catch (Throwable $e) {
            $fail++;
            $msg = get_class($e) . ': ' . $e->getMessage()
                 . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
            echo '  ' . (supportsColor() ? "{$C['r']}FAIL{$C['0']}" : 'FAIL') . "  $label — $msg\n";
            $failures[] = [$short, $label, $msg];
        }
    }
    echo "\n";
}

$elapsed = round(microtime(true) - $t0, 3);
echo str_repeat('=', 60) . "\n";
echo (supportsColor() ? "{$C['b']}" : '') . 'Summary:' . (supportsColor() ? "{$C['0']}" : '')
   . "  {$pass} passed, {$fail} failed, {$skip} skipped  ({$elapsed}s)\n";

if ($failures) {
    echo "\n" . (supportsColor() ? "{$C['r']}" : '') . 'Failures:' . (supportsColor() ? "{$C['0']}" : '') . "\n";
    foreach ($failures as [$s, $l, $msg]) {
        echo "  - $s::$l\n      $msg\n";
    }
}
echo str_repeat('=', 60) . "\n";

exit($fail > 0 ? 1 : 0);
