<?php
// db.example.php - Template only. Copy to db.php and fill in real credentials.
// db.php is gitignored so real passwords never reach the repository.
// PHP 5.6 / MySQL 5.7 Compatible

date_default_timezone_set('Asia/Bangkok');
header('Content-Type: text/html; charset=UTF-8');
ini_set('default_charset', 'UTF-8');
session_start();

if (!defined('AUDIT_ENABLED')) {
    define('AUDIT_ENABLED', true);
}

$host = "localhost";
$user = "DB_USER";
$pass = "DB_PASSWORD";
$db   = "office_budget_edu_db";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("<div style='font-family:sans-serif;padding:20px;color:red;'>Database connection failed: " . $conn->connect_error . "</div>");
}
$conn->set_charset("UTF-8");
$conn->query("SET NAMES UTF-8");

// Create a PDO instance in addition to existing mysqli connection.
// This keeps backward compatibility while enabling new code to use PDO.
try {
    $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ));
} catch (Exception $e) {
    $pdo = null;
}

// ... rest of db.php helpers follow (copy the remainder from your db.php)
