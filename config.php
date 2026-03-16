<?php
require_once __DIR__ . '/includes/functions.php';

// 1. Locate and Load the .env file from the server root
// Assumes .env is placed one level above the Finance folder
$envPath = realpath(__DIR__ . '/../.env'); 
if ($envPath) {
    loadEnv($envPath);
}

// 2. Database Credentials
$host = getEnvSafe('DB_HOST', 'localhost');
$db   = getEnvSafe('DB_NAME', 'suropara_db');
$user = getEnvSafe('DB_USER', 'root');
$pass = getEnvSafe('DB_PASS', ''); 
$charset = 'utf8mb4';

// 3. API Base URL (Required for fetching proof images dynamically)
$apiUrl = getEnvSafe('API_PUBLIC_URL', 'https://apisuro.online');

if (!defined('API_BASE_URL')) {
    define('API_BASE_URL', $apiUrl);
}

// 4. Database Connection
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_PERSISTENT         => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    error_log("Finance Portal DB Error: " . $e->getMessage());
    die("Service Unavailable (Database Connection Failed). Please check server logs.");
}

// 5. Session Setup
if (session_status() === PHP_SESSION_NONE) {
    if (getEnvSafe('APP_ENV') === 'production') {
        ini_set('session.cookie_secure', 1); 
        ini_set('session.cookie_httponly', 1); 
        ini_set('session.use_strict_mode', 1);
        session_set_cookie_params([
            'lifetime' => 86400,
            'path' => '/',
            'domain' => getEnvSafe('COOKIE_DOMAIN') ?: '', 
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }
    session_start();
}
?>