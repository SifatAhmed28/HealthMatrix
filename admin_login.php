\<?php
// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include 'config.php';

$login_error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = sanitizeInput($_POST['email']);
    $password = sanitizeInput($_POST['password']);

    try {
        $stmt = $pdo->prepare("SELECT * FROM Users WHERE email = ? AND role = 'admin' LIMIT 1");
        $stmt->execute([$email]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            $_SESSION['admin_id'] = $admin['user_id'];
            $_SESSION['admin_email'] = $admin['email'];
            header("Location: admin_dashboard.php");
            exit();
        } else {
            $login_error = "Invalid email or password.";
        }
    } catch (PDOException $e) {
        $login_error = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login - HealthMatrix</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #28a745;
            --light-color: #f8f9fa;
        }

        body {
            background-color: var(--light-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }

        .login-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 40px;
            max-width: 480px;
            width: 100%;
        }

        .login-header img {
            height: 60px;
            margin-bottom: 20px;
        }

        .login-header h2 {
            color: var(--primary-color);
            font-weight: 700;
        }

        .btn-login {
            background-color: var(--primary-color);
            color: white;
            font-weight: 500;
            height: 45px;
            border: none;
        }

        .btn-login:hover {
            background-color: #218838;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }

        .error-message {
            color: #dc3545;
            margin-bottom: 15px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header text-center">
            <img src="logo.png" alt="HealthMatrix Logo">
            <h2>Admin Login</h2>
        </div>

        <?php if (!empty($login_error)): ?>
            <div class="error-message text-center">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($login_error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label for="email" class="form-label">Admin Email</label>
                <input type="email" class="form-control" name="email" id="email" required>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" name="password" id="password" required>
            </div>

            <button type="submit" class="btn btn-login w-100">Log In</button>
        </form>
    </div>
</body>
</html>
