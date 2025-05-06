<?php
require_once 'config.php';

// Initialize variables
$errors = [];
$success = '';
$role = isset($_GET['role']) && $_GET['role'] === 'doctor' ? 'doctor' : 'patient';

// Form fields
$fields = [
    'email' => '',
    'password' => '',
    'confirm_password' => '',
    'full_name' => '',
    'date_of_birth' => '',
    'gender' => 'Male',
    'phone' => '',
    'address' => '',
    'specialty_id' => '',
    'license_number' => '',
    'years_experience' => '',
    'bio' => '',
    'consultation_fee' => ''
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    foreach ($fields as $key => $value) {
        $fields[$key] = sanitizeInput($_POST[$key] ?? '');
    }
    $role = sanitizeInput($_POST['role']);

    // Validate common fields
    if (empty($fields['email'])) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    }

    if (empty($fields['password'])) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($fields['password']) < 8) {
        $errors['password'] = 'Password must be at least 8 characters';
    }

    if ($fields['password'] !== $fields['confirm_password']) {
        $errors['confirm_password'] = 'Passwords do not match';
    }

    if (empty($fields['full_name'])) {
        $errors['full_name'] = 'Full name is required';
    }

    // Role-specific validation
    if ($role === 'doctor') {
        if (empty($fields['license_number'])) {
            $errors['license_number'] = 'License number is required';
        }
        if (empty($fields['specialty_id'])) {
            $errors['specialty_id'] = 'Specialty is required';
        }
    }

    // Check if email exists
    if (empty($errors['email'])) {
        $stmt = $pdo->prepare("SELECT user_id FROM Users WHERE email = ?");
        $stmt->execute([$fields['email']]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Email already registered';
        }
    }

    // If no errors, proceed with registration
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Insert into Users table
            $hashedPassword = password_hash($fields['password'], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO Users (email, password_hash, role) VALUES (?, ?, ?)");
            $stmt->execute([$fields['email'], $hashedPassword, $role]);
            $userId = $pdo->lastInsertId();

            // Insert into role-specific table
            if ($role === 'patient') {
                $stmt = $pdo->prepare("INSERT INTO PatientDetails 
                    (patient_id, full_name, date_of_birth, gender, phone, address) 
                    VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $userId,
                    $fields['full_name'],
                    $fields['date_of_birth'],
                    $fields['gender'],
                    $fields['phone'],
                    $fields['address']
                ]);
            } else { // doctor
                $stmt = $pdo->prepare("INSERT INTO DoctorDetails 
                    (doctor_id, full_name, specialty_id, license_number, years_experience, bio, consultation_fee) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $userId,
                    $fields['full_name'],
                    $fields['specialty_id'],
                    $fields['license_number'],
                    $fields['years_experience'],
                    $fields['bio'],
                    $fields['consultation_fee']
                ]);
            }

            $pdo->commit();
            $success = 'Registration successful! Redirecting to login...';
            
            // Redirect after 3 seconds
            header("Refresh: 3; url=login.php");
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['database'] = 'Registration failed: ' . $e->getMessage();
        }
    }
}

// Fetch specialties for doctor registration
$specialties = [];
try {
    $stmt = $pdo->query("SELECT specialty_id, name FROM Specialties ORDER BY name");
    $specialties = $stmt->fetchAll();
} catch (PDOException $e) {
    $errors['database'] = 'Failed to load specialties: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - HealthMatrix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #28a745;
            --secondary-color: #007bff;
            --dark-color: #343a40;
            --light-color: #f8f9fa;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light-color);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        
        .register-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 800px;
            padding: 40px;
            margin: 20px;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .register-header img {
            height: 60px;
            margin-bottom: 20px;
        }
        
        .register-header h2 {
            color: var(--primary-color);
            font-weight: 700;
        }
        
        .form-control, .form-select {
            height: 45px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
        }
        
        .btn-register {
            background-color: var(--primary-color);
            color: white;
            border: none;
            height: 45px;
            border-radius: 5px;
            font-weight: 500;
            width: 100%;
            transition: all 0.3s;
        }
        
        .btn-register:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }
        
        .form-footer {
            text-align: center;
            margin-top: 20px;
        }
        
        .form-footer a {
            color: var(--primary-color);
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .form-footer a:hover {
            color: #218838;
            text-decoration: underline;
        }
        
        .error-message {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: -10px;
            margin-bottom: 10px;
        }
        
        .success-message {
            color: var(--primary-color);
            text-align: center;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .role-tabs {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .role-tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }
        
        .role-tab.active {
            border-bottom-color: var(--primary-color);
            color: var(--primary-color);
            font-weight: 500;
        }
        
        .role-form {
            display: none;
        }
        
        .role-form.active {
            display: block;
        }
        
        .form-section {
            margin-bottom: 25px;
        }
        
        .form-section h5 {
            color: var(--primary-color);
            margin-bottom: 15px;
            padding-bottom: 5px;
            border-bottom: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <img src="logo.png" alt="HealthMatrix Logo">
            <h2>Create Your Account</h2>
            <p>Join HealthMatrix as a <?php echo $role === 'doctor' ? 'Doctor' : 'Patient'; ?></p>
        </div>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($errors['database'])): ?>
            <div class="alert alert-danger">
                <?php echo $errors['database']; ?>
            </div>
        <?php endif; ?>
        
        <div class="role-tabs">
            <div class="role-tab <?php echo $role === 'patient' ? 'active' : ''; ?>" 
                 onclick="window.location.href='register.php'">Patient</div>
            <div class="role-tab <?php echo $role === 'doctor' ? 'active' : ''; ?>" 
                 onclick="window.location.href='register.php?role=doctor'">Doctor</div>
        </div>
        
        <form action="register.php<?php echo $role === 'doctor' ? '?role=doctor' : ''; ?>" method="POST">
            <input type="hidden" name="role" value="<?php echo $role; ?>">
            
            <div class="form-section">
                <h5>Account Information</h5>
                <div class="row">
                    <div class="col-md-6">
                        <label for="email" class="form-label">Email Address</label>
                        <input type="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                               id="email" name="email" value="<?php echo htmlspecialchars($fields['email']); ?>" required>
                        <?php if (isset($errors['email'])): ?>
                            <div class="error-message"><?php echo $errors['email']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label for="full_name" class="form-label">Full Name</label>
                        <input type="text" class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>" 
                               id="full_name" name="full_name" value="<?php echo htmlspecialchars($fields['full_name']); ?>" required>
                        <?php if (isset($errors['full_name'])): ?>
                            <div class="error-message"><?php echo $errors['full_name']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <label for="password" class="form-label">Password</label>
                        <input type="password" class="form-control <?php echo isset($errors['password']) ? 'is-invalid' : ''; ?>" 
                               id="password" name="password" required>
                        <?php if (isset($errors['password'])): ?>
                            <div class="error-message"><?php echo $errors['password']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <input type="password" class="form-control <?php echo isset($errors['confirm_password']) ? 'is-invalid' : ''; ?>" 
                               id="confirm_password" name="confirm_password" required>
                        <?php if (isset($errors['confirm_password'])): ?>
                            <div class="error-message"><?php echo $errors['confirm_password']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="form-section">
                <h5>Personal Information</h5>
                <div class="row">
                    <div class="col-md-4">
                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                               value="<?php echo htmlspecialchars($fields['date_of_birth']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="gender" class="form-label">Gender</label>
                        <select class="form-select" id="gender" name="gender">
                            <option value="Male" <?php echo $fields['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo $fields['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo $fields['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="phone" class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($fields['phone']); ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($fields['address']); ?></textarea>
                    </div>
                </div>
            </div>
            
            <?php if ($role === 'doctor'): ?>
            <div class="form-section">
                <h5>Professional Information</h5>
                <div class="row">
                    <div class="col-md-6">
                        <label for="specialty_id" class="form-label">Specialty</label>
                        <select class="form-select <?php echo isset($errors['specialty_id']) ? 'is-invalid' : ''; ?>" 
                                id="specialty_id" name="specialty_id" required>
                            <option value="">Select Specialty</option>
                            <?php foreach ($specialties as $specialty): ?>
                                <option value="<?php echo $specialty['specialty_id']; ?>" 
                                    <?php echo $fields['specialty_id'] == $specialty['specialty_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($specialty['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['specialty_id'])): ?>
                            <div class="error-message"><?php echo $errors['specialty_id']; ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label for="license_number" class="form-label">License Number</label>
                        <input type="text" class="form-control <?php echo isset($errors['license_number']) ? 'is-invalid' : ''; ?>" 
                               id="license_number" name="license_number" 
                               value="<?php echo htmlspecialchars($fields['license_number']); ?>" required>
                        <?php if (isset($errors['license_number'])): ?>
                            <div class="error-message"><?php echo $errors['license_number']; ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <label for="years_experience" class="form-label">Years of Experience</label>
                        <input type="number" class="form-control" id="years_experience" name="years_experience" 
                               value="<?php echo htmlspecialchars($fields['years_experience']); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="consultation_fee" class="form-label">Consultation Fee ($)</label>
                        <input type="number" step="0.01" class="form-control" id="consultation_fee" name="consultation_fee" 
                               value="<?php echo htmlspecialchars($fields['consultation_fee']); ?>">
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <label for="bio" class="form-label">Professional Bio</label>
                        <textarea class="form-control" id="bio" name="bio" rows="3"><?php echo htmlspecialchars($fields['bio']); ?></textarea>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <button type="submit" class="btn btn-register">Register</button>
            
            <div class="form-footer">
                <p>Already have an account? <a href="login.php">Sign in</a></p>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthIndicator = document.getElementById('password-strength');
            
            if (password.length === 0) {
                if (strengthIndicator) strengthIndicator.textContent = '';
                return;
            }
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^A-Za-z0-9]/)) strength++;
            
            const strengthText = ['Weak', 'Fair', 'Good', 'Strong'][strength - 1] || '';
            const strengthColor = ['#dc3545', '#fd7e14', '#ffc107', '#28a745'][strength - 1] || '';
            
            if (!strengthIndicator) {
                const indicator = document.createElement('div');
                indicator.id = 'password-strength';
                indicator.style.fontSize = '0.875rem';
                indicator.style.marginTop = '-10px';
                indicator.style.marginBottom = '10px';
                this.parentNode.appendChild(indicator);
            }
            
            document.getElementById('password-strength').textContent = strengthText;
            document.getElementById('password-strength').style.color = strengthColor;
        });
    </script>
</body>
</html>