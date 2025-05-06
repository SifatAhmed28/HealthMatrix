<?php
require_once 'config.php';

// Redirect if not logged in as patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: login.php');
    exit();
}

$patientId = $_SESSION['user_id'];
$message = '';

// Pagination settings
$recordsPerPage = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $recordsPerPage;

// Search functionality
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Initialize variables
$appointments = [];
$totalRecords = 0;
$totalPages = 1;

try {
    // Base query for appointments
    $sql = "SELECT a.*, d.full_name AS doctor_name, s.name AS specialty, p.prescription_id 
            FROM Appointments a
            JOIN DoctorDetails d ON a.doctor_id = d.doctor_id
            JOIN Specialties s ON d.specialty_id = s.specialty_id
            LEFT JOIN Prescriptions p ON a.appointment_id = p.appointment_id
            WHERE a.patient_id = :patient_id 
            AND a.status IN ('Completed', 'Cancelled')";
    
    $params = [':patient_id' => $patientId];
    
    // Add search conditions
    if (!empty($search)) {
        $sql .= " AND (d.full_name LIKE :search OR a.notes LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    // Get total records for pagination
    $countSql = str_replace('a.*', 'COUNT(*) AS total', $sql);
    $countStmt = $pdo->prepare($countSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value);
    }
    $countStmt->execute();
    $totalRecords = $countStmt->fetchColumn();
    
    // Add sorting and pagination
    $sql .= " ORDER BY a.appointment_date DESC LIMIT :limit OFFSET :offset";
    $params[':limit'] = $recordsPerPage;
    $params[':offset'] = $offset;
    
    // Fetch appointments
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        if ($key === ':limit' || $key === ':offset') {
            $stmt->bindValue($key, (int)$value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }
    $stmt->execute();
    $appointments = $stmt->fetchAll();

} catch (PDOException $e) {
    $message = "Error loading history: " . $e->getMessage();
}

// Calculate total pages
$totalPages = ceil($totalRecords / $recordsPerPage);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medical History - HealthMatrix</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome CSS with fallback -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #28a745;
            --secondary-color: #007bff;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
        }
        
        .history-container {
            max-width: 1200px;
            margin: 30px auto;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .history-header {
            background-color: var(--primary-color);
            color: white;
            padding: 20px;
        }
        
        .appointment-card {
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s;
            margin-bottom: 20px;
        }
        
        .appointment-card.cancelled {
            border-left-color: var(--danger-color);
        }
        
        .status-badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        
        .badge-completed {
            background-color: var(--primary-color);
            color: white;
        }
        
        .badge-cancelled {
            background-color: var(--danger-color);
            color: white;
        }
        
        .prescription-badge {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .search-box {
            max-width: 600px;
            margin: 20px 0;
            flex-grow: 1;
        }

        .search-box .input-group {
            border-radius: 25px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .search-box .form-control {
            border: none;
            padding: 12px 25px;
            background: #f8f9fa;
        }

        .search-box .btn {
            background-color: var(--primary-color);
            border: none;
            padding: 12px;
            width: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.3s;
        }

        .search-box .btn:hover {
            background-color: #218838;
        }

        .search-box .fa-search {
            font-size: 1.2rem;
            color: white !important;
        }
        
        .pagination {
            justify-content: center;
            margin-top: 20px;
        }
        </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="history-container">
        <div class="history-header">
            <h3><i class="fas fa-history me-2"></i>Medical History</h3>
            <p>Your past appointments and medical records</p>
        </div>
        
        <div class="p-4">
            <?php if ($message): ?>
                <div class="alert alert-danger"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <!-- Updated Search Section -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div class="search-box">
                    <form method="GET" class="input-group">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Search by doctor name or notes..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn" type="submit">
                            <i class="fas fa-search"></i>
                        </button>
                        <input type="hidden" name="page" value="1">
                    </form>
                </div>
                
                <?php if ($totalRecords > 0): ?>
                    <div class="text-end">
                        <div class="text-muted small">
                            Showing <?php echo ($offset + 1) . '-' . min($offset + $recordsPerPage, $totalRecords); ?> 
                            of <?php echo $totalRecords; ?> records
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            
            <?php if (empty($appointments)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                    <h5>No medical history found</h5>
                    <p>Your completed appointments and prescriptions will appear here</p>
                </div>
            <?php else: ?>
                <!-- History List -->
                <?php foreach ($appointments as $appt): ?>
                    <div class="card appointment-card <?php echo $appt['status'] === 'Cancelled' ? 'cancelled' : ''; ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h5 class="card-title">
                                        <?php echo date('D, M j, Y \a\t g:i A', strtotime($appt['appointment_date'])); ?>
                                        
                                        <span class="status-badge badge-<?php echo strtolower($appt['status']); ?>">
                                            <?php echo $appt['status']; ?>
                                        </span>
                                        
                                        <?php if ($appt['prescription_id']): ?>
                                            <span class="status-badge prescription-badge">
                                                <i class="fas fa-prescription-bottle-alt"></i> Prescription
                                            </span>
                                        <?php endif; ?>
                                    </h5>
                                    
                                    <div class="mb-2">
                                        <i class="fas fa-user-md text-muted me-1"></i>
                                        Dr. <?php echo htmlspecialchars($appt['doctor_name']); ?> 
                                        (<?php echo htmlspecialchars($appt['specialty']); ?>)
                                    </div>
                                    
                                    <?php if (!empty($appt['notes'])): ?>
                                        <div class="mb-2">
                                            <i class="fas fa-comment-medical text-muted me-1"></i>
                                            <?php echo nl2br(htmlspecialchars($appt['notes'])); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="text-end">
                                    <?php if ($appt['prescription_id']): ?>
                                        <a href="prescription_view.php?id=<?php echo $appt['prescription_id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            View Prescription
                                        </a>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-outline-secondary mt-2"
                                            data-bs-toggle="collapse" 
                                            data-bs-target="#details-<?php echo $appt['appointment_id']; ?>">
                                        More Details
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Collapsible Details -->
                            <div class="collapse mt-3" id="details-<?php echo $appt['appointment_id']; ?>">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="small text-muted">
                                            <i class="fas fa-calendar-check me-1"></i>
                                            Booked on: <?php echo date('M j, Y', strtotime($appt['created_at'])); ?>
                                        </div>
                                        <?php if ($appt['status'] === 'Cancelled'): ?>
                                            <div class="text-danger small mt-1">
                                                <i class="fas fa-times-circle me-1"></i>
                                                Cancelled on: <?php echo date('M j, Y', strtotime($appt['updated_at'])); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <a href="appointment_view.php?id=<?php echo $appt['appointment_id']; ?>" 
                                           class="btn btn-sm btn-outline-secondary">
                                            Full Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination">
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                    <a class="page-link" 
                                       href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-expand details if URL contains hash
        window.addEventListener('DOMContentLoaded', () => {
            if (window.location.hash) {
                const target = document.querySelector(window.location.hash);
                if (target) {
                    new bootstrap.Collapse(target, { toggle: true });
                }
            }
        });
    </script>
</body>
</html>