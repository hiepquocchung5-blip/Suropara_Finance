<?php
// Database & Session Config
$host = 'localhost';
$db   = 'zmmlpszw_suropara';
$user = 'zmmlpszw_suropara_usr';
$pass = '@fekgygn85cCM43'; // Set your password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("Finance Portal Database Error.");
}

// API Configuration
define('API_BASE_URL', 'https://gameapi.braix.online/');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}



// Helper: Require Staff Login
function requireFinanceAuth() {
    if (!isset($_SESSION['finance_id']) || !in_array($_SESSION['finance_role'], ['FINANCE', 'GOD'])) {
        header("Location: index.php");
        exit;
    }
}
?>