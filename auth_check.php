<?php
// includes/auth_check.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Verify the user's session is valid (optional additional checks)
// You could add IP validation, user agent checks, etc. here