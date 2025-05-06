<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'matrix');
define('DB_USER', 'root');
define('DB_PASS', '');

// Set timezone
date_default_timezone_set('UTC');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security function to prevent XSS
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Establish database connection
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Test query to confirm connection
    $stmt = $pdo->query("SELECT 1");
    if ($stmt) {
       // echo "✅ Database connection successful and working.";
    }
} catch (PDOException $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}
?>
