<?php
// db.php - Unified PDO + MySQLi compat layer (human-dev fix)
// DB: hotel_management (MariaDB via XAMPP). Change via setup.php, env, or edit below.
$local = __DIR__ . '/db.local.php';
if (file_exists($local)) require_once $local; // setup.php credentials win
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: null; // 3307=XAMPP MariaDB, 3306=MySQL84
$dbname = getenv('DB_NAME') ?: 'hotel_management';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') ?: '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
if ($port) $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    // Don't leak credentials; log and show friendly error
    error_log('DB connection failed: ' . $e->getMessage());
    die('Database connection failed. Check config / XAMPP MySQL is running.');
}

// Legacy mysqli compat ($conn) for old pages - same DB, same creds
$conn = $port
  ? new mysqli($host, $username, $password, $dbname, (int)$port)
  : new mysqli($host, $username, $password, $dbname);
if ($conn->connect_error) {
    error_log('MySQLi connection failed: ' . $conn->connect_error);
    // Keep $pdo as primary; $conn failures should not kill PDO pages
} else {
    $conn->set_charset($charset);
}

