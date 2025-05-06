<?php
require_once 'config.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$is_doctor = ($user_role === 'doctor');

// Set default filter values
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'upcoming';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Build base SQL query
$sql = "SELECT a.*, 
        pd.full_name as patient_name,
        dd.full_name as doctor_name,
        s.name as specialty,
        p.status as payment_status,
        p.amount as payment_amount
        FROM Appointments a
        JOIN Users up ON a.patient_id = up.user_id
        JOIN PatientDetails pd ON up.user_id = pd.patient_id
        JOIN Users ud ON a.doctor_id = ud.user_id
        JOIN DoctorDetails dd ON ud.user_id = dd.doctor_id
        JOIN Specialties s ON dd.specialty_id = s.specialty_id
        LEFT JOIN Payments p ON a.appointment_id = p.appointment_id
        WHERE ";

// Add role-specific conditions
if ($is_doctor) {
    $sql .= "a.doctor_id = ?";
} else {
    $sql .= "a.patient_id = ?";
}

// Add filter conditions
$params = [$user_id];
$current_date = date('Y-m-d H:i:s');

switch ($filter) {
    case 'past':
        $sql .= " AND a.appointment_date < ? AND a.status != 'Cancelled'";
        $params[] = $current_date;
        $order = "a.appointment_date DESC";
        break;
    case 'cancelled':
        $sql .= " AND a.status = 'Cancelled'";
        $order = "a.appointment_date DESC";
        break;
    case 'all':
        $order = "a.appointment_date DESC";
        break;
    default: // upcoming
        $sql .= " AND a.appointment_date >= ? AND a.status = 'Booked'";
        $params[] = $current_date;
        $order = "a.appointment_date ASC";
}

// Complete SQL with ordering and pagination
$sql .= " ORDER BY $order LIMIT $records_per_page OFFSET $offset";

// Get appointments
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$appointments = $stmt->fetchAll();

// Get total count for pagination (without LIMIT/OFFSET)
$count_sql = "SELECT COUNT(*) as total
             FROM Appointments a
             JOIN Users up ON a.patient_id = up.user_id
             JOIN PatientDetails pd ON up.user_id = pd.patient_id
             JOIN Users ud ON a.doctor_id = ud.user_id
             JOIN DoctorDetails dd ON ud.user_id = dd.doctor_id
             JOIN Specialties s ON dd.specialty_id = s.specialty_id
             LEFT JOIN Payments p ON a.appointment_id = p.appointment_id
             WHERE " . ($is_doctor ? "a.doctor_id = ?" : "a.patient_id = ?");

// Add filter conditions to count query
switch ($filter) {
    case 'past':
        $count_sql .= " AND a.appointment_date < ? AND a.status != 'Cancelled'";
        break;
    case 'cancelled':
        $count_sql .= " AND a.status = 'Cancelled'";
        break;
    case 'upcoming':
        $count_sql .= " AND a.appointment_date >= ? AND a.status = 'Booked'";
        break;
}

$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_appointments = $stmt->fetchColumn();
$total_pages = ceil($total_appointments / $records_per_page);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_doctor ? 'My Appointments' : 'My Appointments'; ?> | Health Matrix</title>
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
            background-color: #f8f9fa;
        }
        
        .appointment-card {
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
            border: none;
            transition: all 0.3s;
        }
        
        .appointment-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            border-radius: 8px 8px 0 0 !important;
        }
        
        .badge-status {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 50px;
        }
        
        .doctor-img, .patient-img {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary-color);
        }
        
        .filter-btn {
            border-radius: 50px;
            margin-right: 8px;
            margin-bottom: 8px;
        }
        
        .filter-btn.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        @media (max-width: 768px) {
            .appointment-details {
                text-align: center;
            }
            
            .doctor-img, .patient-img {
                margin-bottom: 15px;
            }
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container py-5">
        <div class="row mb-4">
            <div class="col-md-8">
                <h2><i class="fas fa-calendar-alt me-2"></i> My Appointments</h2>
                <p class="text-muted">View and manage your <?php echo $is_doctor ? 'patient' : ''; ?> appointments</p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="appointment_book.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Book New
                </a>
            </div>
        </div>
        
        <!-- Filter Buttons -->
        <div class="mb-4">
            <div class="d-flex flex-wrap">
                <a href="?filter=upcoming" class="btn btn-outline-primary filter-btn <?php echo $filter === 'upcoming' ? 'active' : ''; ?>">
                    Upcoming
                </a>
                <a href="?filter=past" class="btn btn-outline-primary filter-btn <?php echo $filter === 'past' ? 'active' : ''; ?>">
                    Past
                </a>
                <a href="?filter=cancelled" class="btn btn-outline-primary filter-btn <?php echo $filter === 'cancelled' ? 'active' : ''; ?>">
                    Cancelled
                </a>
                <a href="?filter=all" class="btn btn-outline-primary filter-btn <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    All Appointments
                </a>
            </div>
        </div>
        
        <?php if (empty($appointments)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                    <h5>No appointments found</h5>
                    <p class="text-muted">You don't have any <?php echo $filter; ?> appointments yet.</p>
                    <a href="appointment_book.php" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Book an Appointment
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($appointments as $appointment): 
                    $is_upcoming = strtotime($appointment['appointment_date']) > time() && $appointment['status'] === 'Booked';
                    $is_past = strtotime($appointment['appointment_date']) < time() && $appointment['status'] !== 'Cancelled';
                ?>
                <div class="col-md-6 mb-4">
                    <div class="appointment-card card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <?php echo date('D, M j, Y', strtotime($appointment['appointment_date'])); ?>
                            </h5>
                            <span class="badge-status badge bg-<?php 
                                echo $appointment['status'] === 'Completed' ? 'success' : 
                                    ($appointment['status'] === 'Cancelled' ? 'danger' : 
                                    ($is_upcoming ? 'primary' : 'secondary')); 
                            ?>">
                                <?php echo $appointment['status']; ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="row appointment-details">
                                <div class="col-md-5 text-center text-md-start mb-3 mb-md-0">
                                    <?php if ($is_doctor): ?>
                                        <img src="images/patients/<?php echo $appointment['patient_id'] % 10 ?>.jpg" 
                                             class="patient-img mb-2" 
                                             alt="<?php echo htmlspecialchars($appointment['patient_name']); ?>">
                                        <h6><?php echo htmlspecialchars($appointment['patient_name']); ?></h6>
                                        <small class="text-muted">Patient</small>
                                    <?php else: ?>
                                        <img src="images/doctors/<?php echo $appointment['doctor_id'] % 5 ?>.jpg" 
                                             class="doctor-img mb-2" 
                                             alt="Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?>">
                                        <h6>Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></h6>
                                        <small class="text-muted"><?php echo htmlspecialchars($appointment['specialty']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-7">
                                    <div class="mb-2">
                                        <i class="fas fa-clock me-2 text-primary"></i>
                                        <?php echo date('g:i A', strtotime($appointment['appointment_date'])); ?>
                                    </div>
                                    
                                    <?php if (!empty($appointment['notes'])): ?>
                                        <div class="mb-2">
                                            <i class="fas fa-sticky-note me-2 text-primary"></i>
                                            <?php echo htmlspecialchars($appointment['notes']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($appointment['payment_amount'] > 0): ?>
                                        <div class="mb-2">
                                            <i class="fas fa-money-bill-wave me-2 text-primary"></i>
                                            $<?php echo number_format($appointment['payment_amount'], 2); ?>
                                            <span class="badge bg-<?php 
                                                echo $appointment['payment_status'] === 'Completed' ? 'success' : 
                                                    ($appointment['payment_status'] === 'Failed' ? 'danger' : 'warning'); 
                                            ?> ms-2">
                                                <?php echo $appointment['payment_status']; ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-3">
                                        <a href="appointment_detail.php?id=<?php echo $appointment['appointment_id']; ?>" 
                                           class="btn btn-sm btn-outline-primary me-2">
                                            <i class="fas fa-eye me-1"></i> Details
                                        </a>
                                        
                                        <?php if ($is_upcoming): ?>
                                            <a href="appointment_cancel.php?id=<?php echo $appointment['appointment_id']; ?>" 
                                               class="btn btn-sm btn-outline-danger me-2">
                                                <i class="fas fa-times me-1"></i> Cancel
                                            </a>
                                            
                                            <?php if (!$is_doctor && $appointment['payment_status'] === 'Pending'): ?>
                                                <a href="payment.php?appointment_id=<?php echo $appointment['appointment_id']; ?>" 
                                                   class="btn btn-sm btn-success">
                                                    <i class="fas fa-credit-card me-1"></i> Pay Now
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Appointment pagination">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $page - 1; ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $page + 1; ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add confirmation for cancel action
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.btn-outline-danger').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to cancel this appointment?')) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>