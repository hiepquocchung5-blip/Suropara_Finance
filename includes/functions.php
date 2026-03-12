<?php
// Ensure session starts if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================================================
// 1. SECURE ENVIRONMENT LOADER
// ============================================================================
function loadEnv($path) {
    if (!file_exists($path)) return false;
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            
            if (preg_match('/^([\'"])(.*)\1$/', $value, $matches)) {
                $value = $matches[2];
            }
            
            if (function_exists('putenv')) putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
    return true;
}

function getEnvSafe($key, $default = null) {
    if (isset($_ENV[$key])) return $_ENV[$key];
    if (isset($_SERVER[$key])) return $_SERVER[$key];
    $val = getenv($key);
    return $val !== false ? $val : $default;
}

// ============================================================================
// 2. FINANCE AUTHENTICATION & UTILITIES
// ============================================================================
function requireFinanceAuth() {
    if (!isset($_SESSION['finance_id']) || !in_array($_SESSION['finance_role'], ['FINANCE', 'GOD'])) {
        header("Location: index.php");
        exit;
    }
}

function cleanInput($data) {
    return htmlspecialchars(stripslashes(trim($data)), ENT_QUOTES, 'UTF-8');
}
?>