<?php
require_once 'config.php';


// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['role'] : '';
$username = '';

if ($isLoggedIn) {
    $table = ($userRole === 'patient') ? 'PatientDetails' : 'DoctorDetails';
    $stmt = $pdo->prepare("SELECT full_name FROM $table WHERE " . ($userRole === 'patient' ? 'patient' : 'doctor') . "_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $username = $user['full_name'] ?? '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Healthcare Services</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap & Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <!-- Custom Styles -->
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f8f9fc;
        }

        h1, h2 {
            color: #2c3e50;
        }

        .card {
            border: none;
            border-radius: 16px;
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
        }

        .card .btn {
            border-radius: 30px;
            font-weight: 500;
        }

        .jumbotron {
            border-radius: 16px;
            padding: 3rem;
            text-align: center;
        }

        .alert-info {
            border-radius: 12px;
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .card-body i {
            margin-bottom: 20px;
        }

        footer {
            background-color: #f1f1f1;
            padding: 20px 0;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container py-5">
    <h1 class="text-center mb-5 fw-bold">Our Healthcare Services</h1>
    
    <!-- Motivational Section -->
    <div class="alert alert-info text-center mb-5 shadow-sm">
        <h2 class="mb-3">Your Health Matters!</h2>
        <p class="lead">"Take care of your body. It's the only place you have to live." - Jim Rohn</p>
    </div>

    <!-- Services Grid -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
        <!-- 24/7 Doctor Service -->
        <div class="col">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-clock fa-3x text-primary"></i>
                    <h3 class="card-title mt-4">24/7 Doctor Availability</h3>
                    <p class="card-text mt-2">
                        Access qualified medical professionals anytime, anywhere. 
                        Emergency consultations available round the clock.
                    </p>
                    <a href="doctors.php" class="btn btn-primary mt-3">
                        Find Doctors Now <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- BMI Calculator -->
        <div class="col">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-calculator fa-3x text-success"></i>
                    <h3 class="card-title mt-4">BMI Calculator</h3>
                    <p class="card-text mt-2">
                        Monitor your health with our Body Mass Index calculator. 
                        Track your progress and maintain optimal weight.
                    </p>
                    <a href="health_metrics.php" class="btn btn-success mt-3">
                        Calculate BMI <i class="fas fa-heartbeat ms-2"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Doctor Information -->
        <div class="col">
            <div class="card h-100 shadow-sm">
                <div class="card-body text-center">
                    <i class="fas fa-user-md fa-3x text-info"></i>
                    <h3 class="card-title mt-4">Doctor Profiles</h3>
                    <p class="card-text mt-2">
                        Explore detailed profiles of our certified medical experts. 
                        Check qualifications, experience, and patient reviews.
                    </p>
                    <a href="doctors.php" class="btn btn-info mt-3 text-white">
                        View Doctors <i class="fas fa-stethoscope ms-2"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Motivational Section -->
    <div class="jumbotron bg-white mt-5 shadow-sm">
        <h2 class="display-5 mb-3 fw-bold">Stay Healthy, Stay Happy!</h2>
        <p class="lead">"Health is not valued till sickness comes." - Thomas Fuller</p>
        <hr class="my-4">
        <p class="mb-4">Regular checkups and health monitoring can prevent 80% of serious medical conditions.</p>
        <a class="btn btn-primary btn-lg" href="appointments.php" role="button">
            Schedule Checkup Now
        </a>
    </div>
</div>

<?php include 'footer.php'; ?>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
