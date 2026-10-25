<?php
// config.php — Global configuration for Luxora Hotel Suite
date_default_timezone_set('Asia/Colombo');
mb_internal_encoding('UTF-8');

ini_set('display_errors', getenv('APP_DEBUG') ? 1 : 0);
error_reporting(E_ALL);

define('HOTEL_NAME', 'Luxora Hotel Suite');
define('BASE_URL', getenv('BASE_URL') ?: 'http://localhost/hotel-system');
define('ADMIN_EMAIL', 'admin@luxorahotel.com');
define('CURRENCY', 'LKR');
define('TAX_RATE', 0.10);
define('LATE_FEE_FIXED', 50.00);
define('CHECKOUT_HOUR', 12); // 12 PM standard checkout
define('AUTOCANCEL_HOUR', 19); // 7 PM card-less cancel
define('NOSHOW_HOUR', 23); // 11 PM no-show
define('SESSION_TIMEOUT', 1800); // 30 min
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MIN', 15);

if (session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli' && !headers_sent()) { session_start(); }

// Session timeout enforcement (refreshes last_activity; never destroys an active session)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    session_unset();   // idle for >30min - drop stale data but keep the session alive
}
$_SESSION['last_activity'] = time();

