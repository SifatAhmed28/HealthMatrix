<?php
require_once 'config.php';

// Initialize the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Unset all session variables
$_SESSION = array();

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Clear remember me cookie if it exists
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
    
    // Optional: Clear the token from database
    try {
        require_once 'config.php';
        $pdo->prepare("UPDATE Users SET remember_token = NULL, token_expiry = NULL WHERE user_id = ?")
            ->execute([$_SESSION['user_id']]);
    } catch (PDOException $e) {
        error_log("Error clearing remember token: " . $e->getMessage());
    }
}

// Redirect to login page
header("Location: login.php");
exit();
?>