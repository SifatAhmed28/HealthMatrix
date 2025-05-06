<?php
require_once 'config.php';

// Check if user is logged in and is a doctor
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: login.php');
    exit();
}

$doctorId = $_SESSION['user_id'];
$message = '';

// Fetch doctor details
try {
    $stmt = $pdo->prepare("SELECT d.*, s.name AS specialty_name, u.email 
                          FROM DoctorDetails d
                          JOIN Specialties s ON d.specialty_id = s.specialty_id
                          JOIN Users u ON d.doctor_id = u.user_id
                          WHERE d.doctor_id = ?");
    $stmt->execute([$doctorId]);
    $doctor = $stmt->fetch();

    // Get all specialties for dropdown
    $specialtiesStmt = $pdo->query("SELECT * FROM Specialties ORDER BY name");
    $specialties = $specialtiesStmt->fetchAll();
} catch (PDOException $e) {
    $message = "Error loading profile: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Sanitize inputs
        $fullName = sanitizeInput($_POST['full_name']);
        $specialtyId = sanitizeInput($_POST['specialty_id']);
        $licenseNumber = sanitizeInput($_POST['license_number']);
        $yearsExperience = sanitizeInput($_POST['years_experience']);
        $bio = sanitizeInput($_POST['bio']);
        $consultationFee = sanitizeInput($_POST['consultation_fee']);

        // Validate inputs
        if (empty($fullName) || empty($licenseNumber)) {
            throw new Exception("Required fields are missing");
        }

        if (!is_numeric($consultationFee) || $consultationFee < 0) {
            throw new Exception("Invalid consultation fee");
        }

        // Update doctor details
        $stmt = $pdo->prepare("UPDATE DoctorDetails SET 
                            full_name = ?,
                            specialty_id = ?,
                            license_number = ?,
                            years_experience = ?,
                            bio = ?,
                            consultation_fee = ?
                            WHERE doctor_id = ?");
        
        $stmt->execute([
            $fullName,
            $specialtyId,
            $licenseNumber,
            $yearsExperience,
            $bio,
            $consultationFee,
            $doctorId
        ]);

        $message = "Profile updated successfully!";
        
        // Refresh doctor data
        $stmt = $pdo->prepare("SELECT d.*, s.name AS specialty_name, u.email 
                             FROM DoctorDetails d
                             JOIN Specialties s ON d.specialty_id = s.specialty_id
                             JOIN Users u ON d.doctor_id = u.user_id
                             WHERE d.doctor_id = ?");
        $stmt->execute([$doctorId]);
        $doctor = $stmt->fetch();

    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Profile - HealthMatrix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .profile-container {
            max-width: 800px;
            margin: 30px auto;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        
        .profile-header {
            background-color: #007bff;
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
        }
        
        .form-label {
            font-weight: 500;
        }
        
        .license-badge {
            background-color: #e9ecef;
            padding: 5px 10px;
            border-radius: 5px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="profile-container">
        <div class="profile-header text-center">
            <h3>Doctor Profile</h3>
            <p>Manage your professional information</p>
        </div>
        
        <div class="p-4">
            <?php if ($message): ?>
                <div class="alert alert-<?= strpos($message, 'Error') === false ? 'success' : 'danger' ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="row g-3">
                    <!-- Left Column -->
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Email Address</label>
                            <input type="email" class="form-control" 
                                   value="<?= htmlspecialchars($doctor['email'] ?? '') ?>" disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="full_name" required
                                   value="<?= htmlspecialchars($doctor['full_name'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Medical Specialty <span class="text-danger">*</span></label>
                            <select class="form-select" name="specialty_id" required>
                                <?php foreach ($specialties as $spec): ?>
                                    <option value="<?= $spec['specialty_id'] ?>" 
                                        <?= ($spec['specialty_id'] == ($doctor['specialty_id'] ?? '')) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($spec['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">License Number <span class="text-danger">*</span></label>
                            <div class="license-badge">
                                <?= htmlspecialchars($doctor['license_number'] ?? '') ?>
                            </div>
                            <input type="text" class="form-control mt-2" name="license_number" required
                                   value="<?= htmlspecialchars($doctor['license_number'] ?? '') ?>">
                        </div>
                    </div>
                    
                    <!-- Right Column -->
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Years of Experience</label>
                            <input type="number" class="form-control" name="years_experience"
                                   value="<?= htmlspecialchars($doctor['years_experience'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Consultation Fee ($)</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" class="form-control" name="consultation_fee"
                                       value="<?= htmlspecialchars($doctor['consultation_fee'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Professional Bio</label>
                            <textarea class="form-control" name="bio" rows="4"
                                ><?= htmlspecialchars($doctor['bio'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary px-4">Save Profile</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>