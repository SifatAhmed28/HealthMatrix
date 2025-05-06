<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$allowRegistration = true;

$error = '';
$success = '';
$full_name = $email = $admin_role = $department = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allowRegistration) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $admin_role = $_POST['admin_role'];
    $department = trim($_POST['department']);

    if (empty($full_name) || empty($email) || empty($password) || empty($confirm_password) || empty($admin_role)) {
        $error = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email format';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT 1 FROM Users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                throw new Exception('Email already registered');
            }

            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO Users (email, password_hash, role) VALUES (?, ?, 'admin')");
            $stmt->execute([$email, $hashed_password]);
            $user_id = $pdo->lastInsertId();

            $permissions = [
                'super_admin' => [
                    'users' => ['create', 'read', 'update', 'delete'],
                    'patients' => ['create', 'read', 'update', 'delete'],
                    'doctors' => ['create', 'read', 'update', 'delete'],
                    'appointments' => ['create', 'read', 'update', 'delete'],
                    'prescriptions' => ['create', 'read', 'update', 'delete', 'approve'],
                    'payments' => ['create', 'read', 'update', 'delete', 'process'],
                    'medicines' => ['create', 'read', 'update', 'delete'],
                    'reports' => ['generate']
                ],
                'hospital_admin' => [
                    'patients' => ['read', 'update'],
                    'doctors' => ['read', 'update'],
                    'appointments' => ['read', 'update'],
                    'prescriptions' => ['read', 'approve'],
                    'reports' => ['generate']
                ],
                'records_admin' => [
                    'patients' => ['read', 'update'],
                    'prescriptions' => ['read'],
                    'reports' => ['generate']
                ],
                'billing_admin' => [
                    'payments' => ['create', 'read', 'update', 'process'],
                    'reports' => ['generate']
                ]
            ];

            $stmt = $pdo->prepare("
                INSERT INTO Admin (user_id, full_name, admin_role, department, permissions)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user_id,
                $full_name,
                $admin_role,
                $department,
                json_encode($permissions[$admin_role])
            ]);

            $pdo->commit();
            $success = 'Admin account created successfully!';
            $full_name = $email = $admin_role = $department = '';

        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Registration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS & Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <!-- Custom Styles -->
    <style>
        body {
            background: linear-gradient(to right, #e0f7fa, #e1f5fe);
            font-family: 'Segoe UI', sans-serif;
        }

        .card {
            border-radius: 20px;
            transition: transform 0.3s ease;
        }

        .card:hover {
            transform: scale(1.01);
        }

        .btn-primary {
            border-radius: 30px;
        }

        #password-strength {
            font-size: 0.85rem;
            margin-top: 5px;
        }

        .form-label i {
            margin-right: 5px;
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 1rem;
            transform: translateY(-50%);
            cursor: pointer;
            z-index: 2;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0"><i class="fas fa-user-shield me-2"></i> Admin Registration</h3>
                </div>
                <div class="card-body">
                    <?php if (!$allowRegistration): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-ban me-2"></i>
                            Registration disabled. Contact system administrator.
                        </div>
                    <?php else: ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <?php if ($success): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>

                        <form method="post" novalidate>
                            <div class="mb-3">
                                <label for="full_name" class="form-label"><i class="fas fa-user"></i> Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name"
                                       value="<?= htmlspecialchars($full_name) ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label"><i class="fas fa-envelope"></i> Email Address</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?= htmlspecialchars($email) ?>" required>
                            </div>

                            <div class="mb-3 position-relative">
                                <label for="password" class="form-label"><i class="fas fa-lock"></i> Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <i class="fas fa-eye toggle-password" onclick="togglePasswordVisibility('password')"></i>
                                <div id="password-strength" class="mt-1"></div>
                            </div>

                            <div class="mb-3 position-relative">
                                <label for="confirm_password" class="form-label"><i class="fas fa-lock"></i> Confirm Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                <i class="fas fa-eye toggle-password" onclick="togglePasswordVisibility('confirm_password')"></i>
                            </div>

                            <div class="mb-3">
                                <label for="admin_role" class="form-label"><i class="fas fa-user-tag"></i> Admin Role</label>
                                <select class="form-select" id="admin_role" name="admin_role" required>
                                    <option value="">Select Role</option>
                                    <option value="super_admin" <?= $admin_role === 'super_admin' ? 'selected' : '' ?>>Super Administrator</option>
                                    <option value="hospital_admin" <?= $admin_role === 'hospital_admin' ? 'selected' : '' ?>>Hospital Administrator</option>
                                    <option value="records_admin" <?= $admin_role === 'records_admin' ? 'selected' : '' ?>>Records Administrator</option>
                                    <option value="billing_admin" <?= $admin_role === 'billing_admin' ? 'selected' : '' ?>>Billing Administrator</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="department" class="form-label"><i class="fas fa-building"></i> Department (Optional)</label>
                                <input type="text" class="form-control" id="department" name="department"
                                       value="<?= htmlspecialchars($department) ?>">
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-user-plus me-2"></i> Register Admin Account
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-center">
                    <small class="text-muted">
                        <i class="fas fa-lock me-1"></i> Information is encrypted and securely stored
                    </small>
                </div>
            </div>

            <?php if ($allowRegistration): ?>
                <div class="text-center mt-3">
                    <a href="login.php" class="text-decoration-none">
                        <i class="fas fa-sign-in-alt me-1"></i> Already have an account? Login here
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Bootstrap JS + Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Password Script -->
<script>
function togglePasswordVisibility(fieldId) {
    const field = document.getElementById(fieldId);
    field.type = field.type === 'password' ? 'text' : 'password';
}

document.getElementById('password').addEventListener('input', function () {
    const password = this.value;
    const strengthText = document.getElementById('password-strength');
    if (!strengthText) return;

    if (password.length === 0) {
        strengthText.textContent = '';
        return;
    }

    if (password.length < 8) {
        strengthText.textContent = 'Weak (min 8 characters)';
        strengthText.className = 'text-danger';
    } else if (!/[A-Z]/.test(password) || !/[0-9]/.test(password) || !/[^A-Za-z0-9]/.test(password)) {
        strengthText.textContent = 'Medium (add uppercase, numbers & symbols)';
        strengthText.className = 'text-warning';
    } else {
        strengthText.textContent = 'Strong';
        strengthText.className = 'text-success';
    }
});
</script>

<?php include 'footer.php'; ?>
</body>
</html>
