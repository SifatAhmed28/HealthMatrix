<?php

require_once 'config.php';

// Ensure sessions are started properly
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Initialize variables
$email = $password = '';
$error = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Sanitize inputs
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];

        // Validate inputs
        if (empty($email) || empty($password)) {
            throw new Exception('Please enter both email and password.');
        }

        // Check user exists and is active
        $stmt = $pdo->prepare("SELECT * FROM Users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Verify credentials
        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new Exception('Invalid email or password.');
        }

        // Set session variables
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['last_login'] = time();

        // Update last login
        try {
            $pdo->prepare("UPDATE Users SET last_login = NOW() WHERE user_id = ?")
                ->execute([$user['user_id']]);
        } catch (PDOException $e) {
            error_log("Last login update failed: " . $e->getMessage());
        }

        // Clean redirect
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        header("Location: dashboard.php");
        exit();

    } catch (Exception $e) {
        $error = $e->getMessage();
        error_log("Login Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - HealthMatrix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --primary: #28a745; --secondary: #155724; }
        body { background: #f8f9fa; min-height: 100vh; display: grid; place-items: center; }
        .login-card { background: white; border-radius: 1rem; box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.1); max-width: 400px; width: 100%; padding: 2rem; }
        .form-control:focus { border-color: var(--primary); box-shadow: 0 0 0 0.25rem rgba(40,167,69,.25); }
        .btn-primary { background: var(--primary); border: none; padding: 0.75rem; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="text-center mb-4">
        <a href="index.php">     
    
            <img src="logo.png" alt="Logo" width="120">
            </a>
            <h2 class="mt-3">Sign In</h2>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label>Email</label>
                <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($email) ?>">
            </div>
            
            <div class="mb-3">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            
            <button type="submit" class="btn btn-primary w-100">Sign In</button>
            
            <div class="mt-3 text-center">
                <a href="forgot-password.php">Forgot Password?</a>
                <div class="mt-2">
                    Don't have an account? <a href="register.php">Sign Up</a>
                </div>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>