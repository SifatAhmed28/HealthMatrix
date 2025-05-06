<?php
require_once 'config.php';

// Redirect if not logged in or not a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: login.php');
    exit();
}

$patientId = $_SESSION['user_id'];
$message = '';

// Fetch current patient data
try {
    $stmt = $pdo->prepare("SELECT * FROM PatientDetails WHERE patient_id = ?");
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch();

    if (!$patient) {
        // Initialize empty patient record if none exists
        $patient = [
            'patient_id' => $patientId,
            'full_name' => '',
            'date_of_birth' => '',
            'gender' => 'Male',
            'phone' => '',
            'address' => '',
            'blood_type' => '',
            'allergies' => ''
        ];
    }
} catch (PDOException $e) {
    $message = "Error loading profile: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Sanitize inputs
        $fullName = sanitizeInput($_POST['full_name']);
        $dob = sanitizeInput($_POST['date_of_birth']);
        $gender = sanitizeInput($_POST['gender']);
        $phone = sanitizeInput($_POST['phone']);
        $address = sanitizeInput($_POST['address']);
        $bloodType = sanitizeInput($_POST['blood_type']);
        $allergies = sanitizeInput($_POST['allergies']);

        // Validate required fields
        if (empty($fullName)) {
            throw new Exception("Full name is required");
        }

        // Update or insert patient details
        if (isset($patient['patient_id'])) {
            $stmt = $pdo->prepare("UPDATE PatientDetails SET 
                full_name = ?, date_of_birth = ?, gender = ?, phone = ?, 
                address = ?, blood_type = ?, allergies = ?
                WHERE patient_id = ?");
            $stmt->execute([$fullName, $dob, $gender, $phone, $address, $bloodType, $allergies, $patientId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO PatientDetails 
                (patient_id, full_name, date_of_birth, gender, phone, address, blood_type, allergies) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$patientId, $fullName, $dob, $gender, $phone, $address, $bloodType, $allergies]);
        }

        $message = "Profile updated successfully!";
        
        // Refresh patient data
        $stmt = $pdo->prepare("SELECT * FROM PatientDetails WHERE patient_id = ?");
        $stmt->execute([$patientId]);
        $patient = $stmt->fetch();
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Fetch user email from Users table
try {
    $stmt = $pdo->prepare("SELECT email FROM Users WHERE user_id = ?");
    $stmt->execute([$patientId]);
    $userEmail = $stmt->fetchColumn();
} catch (PDOException $e) {
    $userEmail = "Error loading email";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - HealthMatrix</title>
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
        }
        
        .profile-container {
            max-width: 1000px;
            margin: 30px auto;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .profile-header {
            background-color: var(--primary-color);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid white;
            margin-top: -70px;
            background-color: #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
            font-size: 3rem;
            font-weight: bold;
        }
        
        .profile-body {
            padding: 30px;
        }
        
        .profile-section {
            margin-bottom: 30px;
        }
        
        .profile-section h4 {
            color: var(--primary-color);
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 500;
        }
        
        .profile-info {
            margin-bottom: 15px;
        }
        
        .profile-info-label {
            font-weight: 500;
            color: var(--dark-color);
        }
        
        .profile-info-value {
            color: #495057;
        }
        
        .edit-toggle {
            cursor: pointer;
            color: var(--secondary-color);
        }
        
        .edit-toggle:hover {
            text-decoration: underline;
        }
        
        .medical-badge {
            display: inline-block;
            padding: 5px 10px;
            background-color: #e9ecef;
            border-radius: 20px;
            margin-right: 5px;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="profile-container">
        <div class="profile-header">
            <h3>My Profile</h3>
        </div>
        
        <div class="d-flex justify-content-center">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($patient['full_name'] ?? 'P', 0, 1)); ?>
            </div>
        </div>
        
        <div class="profile-body">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo strpos($message, 'Error') === false ? 'success' : 'danger'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <ul class="nav nav-tabs mb-4" id="profileTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal" type="button" role="tab">
                        Personal Information
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="medical-tab" data-bs-toggle="tab" data-bs-target="#medical" type="button" role="tab">
                        Medical Information
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="profileTabsContent">
                <!-- Personal Information Tab -->
                <div class="tab-pane fade show active" id="personal" role="tabpanel">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="full_name" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" 
                                           value="<?php echo htmlspecialchars($patient['full_name'] ?? ''); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" 
                                           value="<?php echo htmlspecialchars($userEmail); ?>" disabled>
                                    <small class="text-muted">Contact support to change your email</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($patient['phone'] ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date_of_birth" class="form-label">Date of Birth</label>
                                    <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                           value="<?php echo htmlspecialchars($patient['date_of_birth'] ?? ''); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="gender" class="form-label">Gender</label>
                                    <select class="form-select" id="gender" name="gender">
                                        <option value="Male" <?php echo ($patient['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo ($patient['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                                        <option value="Other" <?php echo ($patient['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="address" class="form-label">Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($patient['address'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                        </div>
                    </form>
                </div>
                
                <!-- Medical Information Tab -->
                <div class="tab-pane fade" id="medical" role="tabpanel">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="blood_type" class="form-label">Blood Type</label>
                                    <select class="form-select" id="blood_type" name="blood_type">
                                        <option value="">Select Blood Type</option>
                                        <option value="A+" <?php echo ($patient['blood_type'] ?? '') === 'A+' ? 'selected' : ''; ?>>A+</option>
                                        <option value="A-" <?php echo ($patient['blood_type'] ?? '') === 'A-' ? 'selected' : ''; ?>>A-</option>
                                        <option value="B+" <?php echo ($patient['blood_type'] ?? '') === 'B+' ? 'selected' : ''; ?>>B+</option>
                                        <option value="B-" <?php echo ($patient['blood_type'] ?? '') === 'B-' ? 'selected' : ''; ?>>B-</option>
                                        <option value="AB+" <?php echo ($patient['blood_type'] ?? '') === 'AB+' ? 'selected' : ''; ?>>AB+</option>
                                        <option value="AB-" <?php echo ($patient['blood_type'] ?? '') === 'AB-' ? 'selected' : ''; ?>>AB-</option>
                                        <option value="O+" <?php echo ($patient['blood_type'] ?? '') === 'O+' ? 'selected' : ''; ?>>O+</option>
                                        <option value="O-" <?php echo ($patient['blood_type'] ?? '') === 'O-' ? 'selected' : ''; ?>>O-</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="allergies" class="form-label">Allergies</label>
                                    <textarea class="form-control" id="allergies" name="allergies" rows="2" 
                                              placeholder="List any allergies separated by commas"><?php echo htmlspecialchars($patient['allergies'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Display parsed allergies as badges -->
                        <?php if (!empty($patient['allergies'])): ?>
                            <div class="mb-4">
                                <h6>Your Allergies:</h6>
                                <?php 
                                $allergyList = explode(',', $patient['allergies']);
                                foreach ($allergyList as $allergy): 
                                    if (trim($allergy)): ?>
                                        <span class="medical-badge">
                                            <i class="fas fa-allergy me-1"></i><?php echo htmlspecialchars(trim($allergy)); ?>
                                        </span>
                                    <?php endif;
                                endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn btn-primary px-4">Save Medical Information</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tab functionality
        const profileTabs = new bootstrap.Tab(document.getElementById('personal-tab'));
        
        // Calculate age from date of birth
        document.getElementById('date_of_birth').addEventListener('change', function() {
            if (this.value) {
                const dob = new Date(this.value);
                const today = new Date();
                let age = today.getFullYear() - dob.getFullYear();
                const monthDiff = today.getMonth() - dob.getMonth();
                
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                    age--;
                }
                
                // You could display this somewhere if needed
                console.log("Patient age:", age);
            }
        });
        
        // Format phone number input
        document.getElementById('phone').addEventListener('input', function(e) {
            const x = e.target.value.replace(/\D/g, '').match(/(\d{0,3})(\d{0,3})(\d{0,4})/);
            e.target.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? '-' + x[3] : '');
        });
    </script>
</body>
</html>