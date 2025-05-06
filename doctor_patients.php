<?php
require_once 'config.php';


// Authentication Check
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SESSION['role'] !== 'doctor') {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied. Doctor authorization required.');
}

$doctor_id = $_SESSION['user_id'];
$current_page = max(1, (int)($_GET['page'] ?? 1));
$records_per_page = 10;
$offset = ($current_page - 1) * $records_per_page;
$search_term = trim($_GET['search'] ?? '');
$patients = [];
$error = null;

try {
    // Get Doctor Profile
    $stmt = $pdo->prepare("SELECT full_name, specialty_id FROM DoctorDetails WHERE doctor_id = ?");
    $stmt->execute([$doctor_id]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doctor) {
        throw new Exception("Doctor profile not found");
    }

    // Base Query Components
    $base_query = "FROM PatientDetails p
                  JOIN Appointments a ON p.patient_id = a.patient_id
                  WHERE a.doctor_id = ?";
    
    $search_clause = "";
    $params = [$doctor_id];
    
    if (!empty($search_term)) {
        $base_query .= " AND (p.full_name LIKE ? OR p.patient_id LIKE ?)";
        $search_param = "%$search_term%";
        array_push($params, $search_param, $search_param);
    }

    // Get Total Patients Count
    $count_query = "SELECT COUNT(DISTINCT p.patient_id) $base_query";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_patients = (int)$count_stmt->fetchColumn();

    // Get Paginated Patients
    $select_query = "SELECT 
                        p.patient_id, 
                        p.full_name, 
                        p.date_of_birth, 
                        p.gender,
                        MAX(a.appointment_date) as last_visit,
                        COUNT(a.appointment_id) as total_visits
                     $base_query
                     GROUP BY p.patient_id
                     ORDER BY last_visit DESC
                     LIMIT ? OFFSET ?";

    $stmt = $pdo->prepare($select_query);
    $limit_params = array_merge($params, [$records_per_page, $offset]);
    
    foreach ($limit_params as $key => $value) {
        $param_type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key + 1, $value, $param_type);
    }

    $stmt->execute();
    $patients = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $error = "A database error occurred. Please try again later.";
} catch (Exception $e) {
    error_log("Application Error: " . $e->getMessage());
    $error = $e->getMessage();
}

// Calculate Pagination
$total_pages = max(1, ceil($total_patients / $records_per_page));
$start_page = max(1, $current_page - 2);
$end_page = min($total_pages, $current_page + 2);
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="auto">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Patients | Health Matrix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --hm-primary: #2A5C82;
            --hm-secondary: #5BA4E6;
            --hm-success: #28a745;
            --hm-light: #F8F9FA;
        }

        .patient-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid var(--hm-primary);
        }

        .patient-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }

        .patient-avatar {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border: 2px solid var(--hm-secondary);
        }

        .last-visit-badge {
            background: var(--hm-success);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
        }

        .search-box {
            max-width: 400px;
        }

        @media (max-width: 768px) {
            .patient-avatar {
                width: 50px;
                height: 50px;
            }
            
            .table-responsive {
                border: none;
            }
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-end mb-4">
            <div>
                <h1 class="h3 mb-1">
                    <i class="fas fa-user-md text-primary me-2"></i>My Patients
                </h1>
                <p class="text-muted mb-0">
                    <?= $doctor['full_name'] ?? 'Doctor' ?> • 
                    <?= $total_patients ?> registered patients
                </p>
            </div>
            
            <form method="GET" class="search-box">
                <div class="input-group">
                    <input type="text" 
                           name="search" 
                           class="form-control" 
                           placeholder="Search patients..."
                           value="<?= htmlspecialchars($search_term) ?>"
                           aria-label="Patient search">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </form>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php elseif (empty($patients)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <div class="text-muted mb-3">
                        <i class="fas fa-user-slash fa-4x"></i>
                    </div>
                    <h4 class="text-muted">No patients found</h4>
                    <p class="text-muted">Start by booking appointments for your patients</p>
                    <a href="appointment_book.php" class="btn btn-primary">
                        <i class="fas fa-calendar-plus me-2"></i>Book Appointment
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0">Patient Directory</h5>
                    <div class="text-muted small">
                        Page <?= $current_page ?> of <?= $total_pages ?>
                    </div>
                </div>
                
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Patient</th>
                                    <th class="text-center">Age</th>
                                    <th class="text-center">Gender</th>
                                    <th>Last Visit</th>
                                    <th class="text-center">Visits</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($patients as $patient): 
                                    $dob = new DateTime($patient['date_of_birth']);
                                    $age = $dob->diff(new DateTime())->y;
                                ?>
                                <tr class="patient-card">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?= getPatientAvatar($patient['patient_id']) ?>" 
                                                 class="patient-avatar rounded-circle me-3"
                                                 alt="Patient avatar">
                                            <div>
                                                <div class="fw-medium"><?= htmlspecialchars($patient['full_name']) ?></div>
                                                <div class="text-muted small">ID: <?= $patient['patient_id'] ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center"><?= $age ?></td>
                                    <td class="text-center">
                                        <?= match($patient['gender']) {
                                            'Male' => '<i class="fas fa-mars text-primary"></i>',
                                            'Female' => '<i class="fas fa-venus text-danger"></i>',
                                            default => '<i class="fas fa-genderless text-secondary"></i>'
                                        } ?>
                                    </td>
                                    <td>
                                        <?php if ($patient['last_visit']): ?>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="last-visit-badge">
                                                    <?= date('M j, Y', strtotime($patient['last_visit'])) ?>
                                                </span>
                                                <span class="text-muted small">
                                                    <?= time_elapsed_string($patient['last_visit']) ?>
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">No visits yet</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary rounded-pill">
                                            <?= $patient['total_visits'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="patient_records.php?id=<?= $patient['patient_id'] ?>" 
                                               class="btn btn-sm btn-outline-primary"
                                               data-bs-toggle="tooltip"
                                               title="View Medical Records">
                                                <i class="fas fa-file-medical"></i>
                                            </a>
                                            <a href="appointment_book.php?patient_id=<?= $patient['patient_id'] ?>" 
                                               class="btn btn-sm btn-outline-success"
                                               data-bs-toggle="tooltip"
                                               title="Book New Appointment">
                                                <i class="fas fa-calendar-plus"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if ($total_pages > 1): ?>
                <div class="card-footer bg-white">
                    <nav aria-label="Patient pagination">
                        <ul class="pagination justify-content-center mb-0">
                            <li class="page-item <?= $current_page === 1 ? 'disabled' : '' ?>">
                                <a class="page-link" 
                                   href="?page=<?= $current_page - 1 ?>&search=<?= urlencode($search_term) ?>">
                                   <i class="fas fa-chevron-left"></i>
                                </a>
                            </li>

                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <li class="page-item <?= $i === $current_page ? 'active' : '' ?>">
                                    <a class="page-link" 
                                       href="?page=<?= $i ?>&search=<?= urlencode($search_term) ?>">
                                       <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <li class="page-item <?= $current_page === $total_pages ? 'disabled' : '' ?>">
                                <a class="page-link" 
                                   href="?page=<?= $current_page + 1 ?>&search=<?= urlencode($search_term) ?>">
                                   <i class="fas fa-chevron-right"></i>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize Bootstrap components
        document.addEventListener('DOMContentLoaded', function() {
            // Tooltips
            const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
            [...tooltipTriggerList].forEach(tooltip => new bootstrap.Tooltip(tooltip));

            // Search form persistence
            const searchForm = document.querySelector('form');
            if (searchForm) {
                searchForm.addEventListener('submit', function(e) {
                    const searchInput = this.querySelector('[name="search"]');
                    if (!searchInput.value.trim()) {
                        window.location.href = window.location.pathname;
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>
</html>

<?php
// Helper functions
function getPatientAvatar($patient_id) {
    $avatar_path = "assets/avatars/patient_{$patient_id}.jpg";
    return file_exists($avatar_path) ? $avatar_path : "avater.jpg";
}

function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $string = [
        'y' => 'year',
        'm' => 'month',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    ];

    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>