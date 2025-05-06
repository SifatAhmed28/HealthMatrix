<?php
require_once 'config.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get user details based on role
$userData = [];
$upcomingAppointments = [];
$recentPrescriptions = [];
$healthMetrics = [];

try {
    if ($_SESSION['role'] === 'patient') {
        // Get patient details
        $stmt = $pdo->prepare("SELECT * FROM PatientDetails WHERE patient_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userData = $stmt->fetch();

        // Get upcoming appointments
        $stmt = $pdo->prepare("SELECT a.*, d.full_name AS doctor_name, s.name AS specialty 
                              FROM Appointments a
                              JOIN Users u ON a.doctor_id = u.user_id
                              JOIN DoctorDetails d ON a.doctor_id = d.doctor_id
                              JOIN Specialties s ON d.specialty_id = s.specialty_id
                              WHERE a.patient_id = ? AND a.status = 'Booked' AND a.appointment_date >= NOW()
                              ORDER BY a.appointment_date ASC
                              LIMIT 3");
        $stmt->execute([$_SESSION['user_id']]);
        $upcomingAppointments = $stmt->fetchAll();

        // Get recent prescriptions
        $stmt = $pdo->prepare("SELECT p.*, d.full_name AS doctor_name 
                              FROM Prescriptions p
                              JOIN Appointments a ON p.appointment_id = a.appointment_id
                              JOIN DoctorDetails d ON a.doctor_id = d.doctor_id
                              WHERE a.patient_id = ?
                              ORDER BY p.prescription_date DESC
                              LIMIT 3");
        $stmt->execute([$_SESSION['user_id']]);
        $recentPrescriptions = $stmt->fetchAll();

        // Get health metrics
        $stmt = $pdo->prepare("SELECT * FROM HealthMetrics 
                              WHERE patient_id = ?
                              ORDER BY recorded_date DESC
                              LIMIT 5");
        $stmt->execute([$_SESSION['user_id']]);
        $healthMetrics = $stmt->fetchAll();

    } elseif ($_SESSION['role'] === 'doctor') {
        // Get doctor details
        $stmt = $pdo->prepare("SELECT d.*, s.name AS specialty_name 
                              FROM DoctorDetails d
                              JOIN Specialties s ON d.specialty_id = s.specialty_id
                              WHERE doctor_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $userData = $stmt->fetch();

        // Get upcoming appointments
        $stmt = $pdo->prepare("SELECT a.*, p.full_name AS patient_name 
                              FROM Appointments a
                              JOIN Users u ON a.patient_id = u.user_id
                              JOIN PatientDetails p ON a.patient_id = p.patient_id
                              WHERE a.doctor_id = ? AND a.status = 'Booked' AND a.appointment_date >= NOW()
                              ORDER BY a.appointment_date ASC
                              LIMIT 5");
        $stmt->execute([$_SESSION['user_id']]);
        $upcomingAppointments = $stmt->fetchAll();

        // Get recent patients
        $stmt = $pdo->prepare("SELECT DISTINCT p.* 
                              FROM PatientDetails p
                              JOIN Appointments a ON p.patient_id = a.patient_id
                              WHERE a.doctor_id = ?
                              ORDER BY a.appointment_date DESC
                              LIMIT 5");
        $stmt->execute([$_SESSION['user_id']]);
        $recentPatients = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $error = "Error loading dashboard data. Please try again later.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - HealthMatrix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #28a745;
            --secondary-color: #007bff;
            --dark-color: #343a40;
            --light-color: #f8f9fa;
            --sidebar-width: 250px;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
            overflow-x: hidden;
        }
        
        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background-color: var(--dark-color);
            color: white;
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 20px;
            background-color: rgba(0, 0, 0, 0.2);
        }
        
        .sidebar-header img {
            height: 40px;
        }
        
        .user-info {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .user-info img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 10px;
            border: 3px solid var(--primary-color);
        }
        
        .user-info h5 {
            margin-bottom: 5px;
        }
        
        .user-info p {
            color: #adb5bd;
            font-size: 0.9rem;
        }
        
        .sidebar-menu {
            padding: 20px 0;
        }
        
        .sidebar-menu a {
            display: block;
            padding: 10px 20px;
            color: #adb5bd;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .sidebar-menu a:hover, .sidebar-menu a.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
            border-left: 3px solid var(--primary-color);
        }
        
        .sidebar-menu a i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            transition: all 0.3s;
        }
        
        .header {
            background-color: white;
            padding: 15px 20px;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h4 {
            margin: 0;
            color: var(--dark-color);
        }
        
        .greeting {
            font-size: 1.2rem;
            margin-bottom: 5px;
        }
        
        .date-time {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        /* Cards */
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header .btn {
            padding: 3px 10px;
            font-size: 0.8rem;
        }
        
        .appointment-card {
            border-left: 3px solid var(--primary-color);
        }
        
        .appointment-card.completed {
            border-left-color: #6c757d;
        }
        
        .appointment-card.cancelled {
            border-left-color: #dc3545;
        }
        
        .appointment-time {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .appointment-doctor, .appointment-patient {
            font-weight: 500;
        }
        
        .appointment-specialty, .appointment-status {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .health-metric {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .health-metric:last-child {
            border-bottom: none;
        }
        
        .metric-value {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        /* Stats Cards */
        .stats-card {
            text-align: center;
            padding: 20px;
        }
        
        .stats-icon {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        .stats-number {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                left: -var(--sidebar-width);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .sidebar.active {
                left: 0;
            }
            
            .main-content.active {
                margin-left: var(--sidebar-width);
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
    <div class="sidebar-header">
    <a href="index.php">
        <img src="logo.png" alt="HealthMatrix Logo" >
    </a>
</div>
        
        <div class="user-info">
    <img src="avater.jpg" alt="User Avatar">
    <h5><?php echo htmlspecialchars($userData['full_name']); ?></h5>
    <p><?php echo ucfirst($_SESSION['role']); ?></p>
    <?php if ($_SESSION['role'] === 'doctor'): ?>
        <p class="text-muted small"><?php echo htmlspecialchars($userData['specialty_name']); ?></p>
    <?php endif; ?>
</div>
        
        <div class="sidebar-menu">
            <a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
            
            <?php if ($_SESSION['role'] === 'patient'): ?>
                <a href="appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
                <a href="prescriptions.php"><i class="fas fa-prescription-bottle-alt"></i> Prescriptions</a>
                <a href="health_metrics.php"><i class="fas fa-heartbeat"></i> Health Metrics</a>
                <a href="medical_history.php"><i class="fas fa-history"></i> Medical History</a>
                <a href="doctors.php"><i class="fas fa-user-md"></i> Find Doctors</a>
            <?php else: ?>
                <a href="doctor_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
                <a href="doctor_patients.php"><i class="fas fa-procedures"></i> Patients</a>
                <a href="doctor_prescriptions.php"><i class="fas fa-prescription-bottle-alt"></i> Prescriptions</a>
                <a href="doctor_schedule.php"><i class="fas fa-clock"></i> Schedule</a>
                <a href="doctor_profile.php"><i class="fas fa-user-cog"></i> Profile</a>
            <?php endif; ?>
            
            <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="header">
            <div>
                <h4>Dashboard</h4>
            </div>
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <div class="greeting">Hello, <?php echo htmlspecialchars($userData['full_name']); ?></div>
                    <div class="date-time" id="datetime"></div>
                </div>
                <button class="btn btn-sm btn-outline-secondary d-md-none" id="sidebarToggle">
                    <i class="fas fa-bars"></i>
                </button>
            </div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Stats Cards -->
            <?php if ($_SESSION['role'] === 'patient'): ?>
                <div class="col-md-4">
                    <div class="card stats-card">
                        <div class="stats-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stats-number">
                            <?php 
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM Appointments WHERE patient_id = ? AND status = 'Booked'");
                                $stmt->execute([$_SESSION['user_id']]);
                                echo $stmt->fetchColumn();
                            ?>
                        </div>
                        <div class="stats-label">Upcoming Appointments</div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card stats-card">
                        <div class="stats-icon">
                            <i class="fas fa-prescription-bottle-alt"></i>
                        </div>
                        <div class="stats-number">
                            <?php 
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM Prescriptions p JOIN Appointments a ON p.appointment_id = a.appointment_id WHERE a.patient_id = ?");
                                $stmt->execute([$_SESSION['user_id']]);
                                echo $stmt->fetchColumn();
                            ?>
                        </div>
                        <div class="stats-label">Active Prescriptions</div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card stats-card">
                        <div class="stats-icon">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <div class="stats-number">
                            <?php 
                                $stmt = $pdo->prepare("SELECT COUNT(DISTINCT doctor_id) FROM Appointments WHERE patient_id = ?");
                                $stmt->execute([$_SESSION['user_id']]);
                                echo $stmt->fetchColumn();
                            ?>
                        </div>
                        <div class="stats-label">Doctors Visited</div>
                    </div>
                </div>
            <?php else: ?>
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="stats-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stats-number">
                            <?php 
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM Appointments WHERE doctor_id = ? AND status = 'Booked' AND appointment_date >= NOW()");
                                $stmt->execute([$_SESSION['user_id']]);
                                echo $stmt->fetchColumn();
                            ?>
                        </div>
                        <div class="stats-label">Upcoming Appointments</div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="stats-icon">
                            <i class="fas fa-procedures"></i>
                        </div>
                        <div class="stats-number">
                            <?php 
                                $stmt = $pdo->prepare("SELECT COUNT(DISTINCT patient_id) FROM Appointments WHERE doctor_id = ?");
                                $stmt->execute([$_SESSION['user_id']]);
                                echo $stmt->fetchColumn();
                            ?>
                        </div>
                        <div class="stats-label">Total Patients</div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="stats-icon">
                            <i class="fas fa-prescription-bottle-alt"></i>
                        </div>
                        <div class="stats-number">
                            <?php 
                                $stmt = $pdo->prepare("SELECT COUNT(*) FROM Prescriptions p JOIN Appointments a ON p.appointment_id = a.appointment_id WHERE a.doctor_id = ?");
                                $stmt->execute([$_SESSION['user_id']]);
                                echo $stmt->fetchColumn();
                            ?>
                        </div>
                        <div class="stats-label">Prescriptions</div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card stats-card">
                        <div class="stats-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="stats-number">
                            <?php echo $userData['years_experience'] ?? '0'; ?>+
                        </div>
                        <div class="stats-label">Years of Experience</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="row">
            <!-- Main Content Area -->
            <?php if ($_SESSION['role'] === 'patient'): ?>
                <!-- Patient Dashboard -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            Upcoming Appointments
                            <a href="appointments.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($upcomingAppointments)): ?>
                                <div class="text-center py-4 text-muted">
                                    No upcoming appointments
                                    <a href="doctors.php" class="d-block mt-2">Book an appointment</a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($upcomingAppointments as $appointment): ?>
                                    <div class="appointment-card mb-3 p-3">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="appointment-time">
                                                    <?php echo date('D, M j, Y \a\t g:i A', strtotime($appointment['appointment_date'])); ?>
                                                </div>
                                                <div class="appointment-doctor">
                                                    Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?>
                                                </div>
                                                <div class="appointment-specialty">
                                                    <?php echo htmlspecialchars($appointment['specialty']); ?>
                                                </div>
                                            </div>
                                            <div>
                                                <a href="appointment_details.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-sm btn-outline-secondary">
                                                    View
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header">
                            Recent Prescriptions
                            <a href="prescriptions.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentPrescriptions)): ?>
                                <div class="text-center py-4 text-muted">
                                    No recent prescriptions
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Prescribed By</th>
                                                <th>Diagnosis</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recentPrescriptions as $prescription): ?>
                                                <tr>
                                                    <td><?php echo date('M j, Y', strtotime($prescription['prescription_date'])); ?></td>
                                                    <td>Dr. <?php echo htmlspecialchars($prescription['doctor_name']); ?></td>
                                                    <td><?php echo strlen($prescription['diagnosis']) > 30 ? substr($prescription['diagnosis'], 0, 30) . '...' : $prescription['diagnosis']; ?></td>
                                                    <td>
                                                        <a href="prescription_details.php?id=<?php echo $prescription['prescription_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                            View
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            Health Metrics
                            <a href="health_metrics.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($healthMetrics)): ?>
                                <div class="text-center py-4 text-muted">
                                    No health metrics recorded
                                    <a href="health_metrics.php" class="d-block mt-2">Add metrics</a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($healthMetrics as $metric): ?>
                                    <div class="health-metric">
                                        <div>
                                            <strong><?php echo date('M j', strtotime($metric['recorded_date'])); ?></strong>
                                            <div class="text-muted small">Recorded</div>
                                        </div>
                                        <div class="text-end">
                                            <?php if ($metric['weight'] && $metric['height']): 
                                                $bmi = $metric['weight'] / (($metric['height']/100) ** 2); ?>
                                                <div class="metric-value">BMI: <?php echo number_format($bmi, 1); ?></div>
                                            <?php endif; ?>
                                            <div class="text-muted small">
                                                <?php if ($metric['weight']): ?>
                                                    <?php echo $metric['weight']; ?> kg
                                                <?php endif; ?>
                                                <?php if ($metric['height']): ?>
                                                    | <?php echo $metric['height']; ?> cm
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <!-- BMI Chart Placeholder -->
                                <div class="mt-4">
                                    <canvas id="bmiChart" height="200"></canvas>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header">
                            Quick Actions
                        </div>
                        <div class="card-body">
                            <a href="appointments.php?action=book" class="btn btn-primary w-100 mb-2">
                                <i class="fas fa-calendar-plus me-2"></i> Book Appointment
                            </a>
                            <a href="health_metrics.php" class="btn btn-outline-primary w-100 mb-2">
                                <i class="fas fa-heartbeat me-2"></i> Record Health Metrics
                            </a>
                            <a href="doctors.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-search me-2"></i> Find Doctors
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Doctor Dashboard -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            Today's Appointments
                            <a href="doctor_appointments.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($upcomingAppointments)): ?>
                                <div class="text-center py-4 text-muted">
                                    No appointments scheduled for today
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Time</th>
                                                <th>Patient</th>
                                                <th>Purpose</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($upcomingAppointments as $appointment): ?>
                                                <tr>
                                                    <td><?php echo date('g:i A', strtotime($appointment['appointment_date'])); ?></td>
                                                    <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                                                    <td><?php echo strlen($appointment['notes']) > 30 ? substr($appointment['notes'], 0, 30) . '...' : ($appointment['notes'] ?: '--'); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $appointment['status'] === 'Booked' ? 'primary' : 
                                                                ($appointment['status'] === 'Completed' ? 'success' : 'danger'); 
                                                        ?>">
                                                            <?php echo $appointment['status']; ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <a href="appointment_details.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                            View
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header">
                            Recent Patients
                            <a href="doctor_patients.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recentPatients)): ?>
                                <div class="text-center py-4 text-muted">
                                    No recent patients
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($recentPatients as $patient): ?>
                                        <div class="col-md-6 mb-3">
                                            <div class="d-flex align-items-center p-2 border rounded">
                                                <img src="images/patient-avatar.jpg" alt="Patient" width="50" height="50" class="rounded-circle me-3">
                                                <div>
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($patient['full_name']); ?></h6>
                                                    <small class="text-muted">
                                                        <?php 
                                                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM Appointments WHERE patient_id = ? AND doctor_id = ?");
                                                            $stmt->execute([$patient['patient_id'], $_SESSION['user_id']]);
                                                            $appointmentCount = $stmt->fetchColumn();
                                                            echo $appointmentCount . ' visit' . ($appointmentCount !== 1 ? 's' : '');
                                                        ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            Your Schedule
                        </div>
                        <div class="card-body">
                            <?php
                            try {
                                $stmt = $pdo->prepare("SELECT day_of_week, start_time, end_time FROM DoctorSchedules WHERE doctor_id = ? AND is_available = TRUE ORDER BY FIELD(day_of_week, 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun')");
                                $stmt->execute([$_SESSION['user_id']]);
                                $schedule = $stmt->fetchAll();
                                
                                if (empty($schedule)): ?>
                                    <div class="text-center py-4 text-muted">
                                        No schedule set up
                                        <a href="doctor_schedule.php" class="d-block mt-2">Set your schedule</a>
                                    </div>
                                <?php else: ?>
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($schedule as $day): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <?php echo $day['day_of_week']; ?>
                                                <span class="badge bg-primary rounded-pill">
                                                    <?php echo date('g:i A', strtotime($day['start_time'])); ?> - <?php echo date('g:i A', strtotime($day['end_time'])); ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif;
                            } catch (PDOException $e) {
                                echo '<div class="alert alert-danger">Error loading schedule</div>';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header">
                            Quick Actions
                        </div>
                        <div class="card-body">
                            <a href="doctor_appointments.php?action=add" class="btn btn-primary w-100 mb-2">
                                <i class="fas fa-calendar-plus me-2"></i> Add Appointment
                            </a>
                            <a href="doctor_prescriptions.php?action=new" class="btn btn-outline-primary w-100 mb-2">
                                <i class="fas fa-prescription-bottle-alt me-2"></i> Create Prescription
                            </a>
                            <a href="doctor_schedule.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-clock me-2"></i> Update Schedule
                            </a>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header">
                            Your Profile Completeness
                        </div>
                        <div class="card-body">
                            <?php
                            $completeFields = 0;
                            $totalFields = 7; // Adjust based on your DoctorDetails fields
                            
                            if (!empty($userData['bio'])) $completeFields++;
                            if (!empty($userData['years_experience'])) $completeFields++;
                            if (!empty($userData['consultation_fee'])) $completeFields++;
                            // Add more fields as needed
                            
                            $completionPercentage = round(($completeFields / $totalFields) * 100);
                            ?>
                            <div class="progress mb-3" style="height: 10px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $completionPercentage; ?>%" 
                                     aria-valuenow="<?php echo $completionPercentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <p class="text-center mb-0"><?php echo $completionPercentage; ?>% Complete</p>
                            <a href="doctor_profile.php" class="d-block text-center mt-2">Complete your profile</a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
            document.getElementById('mainContent').classList.toggle('active');
        });
        
        // Update date and time
        function updateDateTime() {
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' };
            document.getElementById('datetime').textContent = now.toLocaleDateString('en-US', options);
        }
        updateDateTime();
        setInterval(updateDateTime, 60000);
        
        // BMI Chart for patients
        <?php if ($_SESSION['role'] === 'patient' && !empty($healthMetrics)): ?>
            const bmiCtx = document.getElementById('bmiChart').getContext('2d');
            const bmiChart = new Chart(bmiCtx, {
                type: 'line',
                data: {
                    labels: [
                        <?php 
                        $reversedMetrics = array_reverse($healthMetrics);
                        foreach ($reversedMetrics as $metric): 
                            echo "'" . date('M j', strtotime($metric['recorded_date'])) . "',";
                        endforeach; 
                        ?>
                    ],
                    datasets: [{
                        label: 'BMI',
                        data: [
                            <?php 
                            foreach ($reversedMetrics as $metric): 
                                if ($metric['weight'] && $metric['height']) {
                                    $bmi = $metric['weight'] / (($metric['height']/100) ** 2);
                                    echo number_format($bmi, 1) . ',';
                                }
                            endforeach; 
                            ?>
                        ],
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false
                        }
                    }
                }
            });
        <?php endif; ?>
    </script>
</body>
</html>