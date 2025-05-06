<?php
session_start();
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

// Get all doctors
$doctors = [];
try {
    $stmt = $pdo->query("
        SELECT d.doctor_id, d.full_name, d.years_experience, 
               d.consultation_fee, d.bio, s.name AS specialty 
        FROM DoctorDetails d
        JOIN Specialties s ON d.specialty_id = s.specialty_id
        ORDER BY d.years_experience DESC
    ");
    $doctors = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching doctors: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Our Doctors</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap & Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <!-- Custom Styles -->
    <style>
        body {
            background: #f4f7fa;
            font-family: 'Segoe UI', sans-serif;
        }

        h1 {
            color: #2c3e50;
            font-weight: 600;
        }

        .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            border-radius: 20px;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .btn-primary {
            background-color: #4e73df;
            border: none;
            border-radius: 12px;
        }

        .btn-primary:hover {
            background-color: #375ac1;
        }

        .form-control, .form-select {
            border-radius: 12px;
        }

        .doctor-image {
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
            background-color: #e9ecef;
        }

        .card-body h3 {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2d3436;
        }

        .card-text {
            font-size: 0.95rem;
            color: #636e72;
        }

        .alert-info {
            font-size: 1.1rem;
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">Health Matrix</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="doctors.php">Doctors</a></li>
                <li class="nav-item"><a class="nav-link" href="appointments.php">Appointments</a></li>
                <?php if ($isLoggedIn): ?>
                    <li class="nav-item"><a class="nav-link" href="#"><?php echo htmlspecialchars($username); ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="logout.php">Logout</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="login.php">Login</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Main Content -->
<div class="container py-5 mt-4">
    <h1 class="text-center mb-5">Meet Our Medical Experts</h1>

    <!-- Search & Filter -->
    <div class="row mb-4">
        <div class="col-md-6 mb-2">
            <form action="doctors.php" method="GET">
                <div class="input-group shadow-sm">
                    <input type="text" class="form-control" name="search" placeholder="Search doctors..."
                        value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                    <button class="btn btn-primary" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>
        <div class="col-md-6">
            <select class="form-select shadow-sm" id="specialtyFilter">
                <option value="">All Specialties</option>
                <?php
                $specialties = $pdo->query("SELECT * FROM Specialties")->fetchAll();
                foreach ($specialties as $specialty): ?>
                    <option value="<?php echo $specialty['name']; ?>">
                        <?php echo htmlspecialchars($specialty['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Doctors Grid -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4" id="doctorGrid">
        <?php if (count($doctors) > 0): ?>
            <?php foreach ($doctors as $doctor): ?>
                <div class="col doctor-card" data-specialty="<?php echo htmlspecialchars($doctor['specialty']); ?>">
                    <div class="card h-100 shadow-sm">
                        <div class="card-img-top doctor-image"
                            style="background-image: url('logo.png'); height: 220px; background-size: cover;
                                   background-position: center;">
                        </div>
                        <div class="card-body">
                            <h3 class="card-title"><?php echo htmlspecialchars($doctor['full_name']); ?></h3>
                            <p class="text-primary mb-1 specialty-text"><?php echo htmlspecialchars($doctor['specialty']); ?></p>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span class="badge bg-success">
                                    <?php echo htmlspecialchars($doctor['years_experience']); ?>+ Years
                                </span>
                                <span class="text-success fw-bold">
                                    $<?php echo number_format($doctor['consultation_fee'], 2); ?>
                                </span>
                            </div>
                            <?php if (!empty($doctor['bio'])): ?>
                                <p class="card-text"><?php echo nl2br(htmlspecialchars($doctor['bio'])); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-white border-0">
                            <a href="appointments.php?doctor=<?php echo $doctor['doctor_id']; ?>"
                               class="btn btn-primary w-100">Book Appointment</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5">
                <div class="alert alert-info">No doctors found in our system.</div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Footer -->
<footer class="bg-light text-center py-4 mt-5 border-top shadow-sm">
    <div class="container">
        <p class="mb-0">© <?php echo date("Y"); ?> Health Matrix. All rights reserved.</p>
    </div>
</footer>

<!-- JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('specialtyFilter').addEventListener('change', function () {
        const selected = this.value.toLowerCase();
        const cards = document.querySelectorAll('.doctor-card');

        cards.forEach(card => {
            const specialty = card.dataset.specialty.toLowerCase();
            card.style.display = (!selected || specialty === selected) ? '' : 'none';
        });
    });
</script>

</body>
</html>
