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

// Verify appointment ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error'] = "No appointment specified";
    header('Location: '.($is_doctor ? 'doctor_patients.php' : 'dashboard.php'));
    exit();
}

$appointment_id = (int)$_GET['id'];

// Get appointment details with verification
$stmt = $pdo->prepare("SELECT a.*, 
                      p.full_name as patient_name,
                      d.full_name as doctor_name
                      FROM Appointments a
                      JOIN Users up ON a.patient_id = up.user_id
                      JOIN PatientDetails p ON up.user_id = p.patient_id
                      JOIN Users ud ON a.doctor_id = ud.user_id
                      JOIN DoctorDetails d ON ud.user_id = d.doctor_id
                      WHERE a.appointment_id = ? 
                      AND (a.patient_id = ? OR a.doctor_id = ?)");
$stmt->execute([$appointment_id, $user_id, $user_id]);
$appointment = $stmt->fetch();

// Verify appointment exists and belongs to user
if (!$appointment) {
    $_SESSION['error'] = "Appointment not found or access denied";
    header('Location: '.($is_doctor ? 'doctor_patients.php' : 'dashboard.php'));
    exit();
}

// Check if appointment can be cancelled (must be upcoming and not already cancelled/completed)
$is_upcoming = strtotime($appointment['appointment_date']) > time();
if (!$is_upcoming || $appointment['status'] !== 'Booked') {
    $_SESSION['error'] = "Only upcoming appointments can be cancelled";
    header('Location: '.($is_doctor ? 'doctor_patients.php' : 'dashboard.php'));
    exit();
}

// Handle cancellation confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Update appointment status
        $stmt = $pdo->prepare("UPDATE Appointments SET status = 'Cancelled' WHERE appointment_id = ?");
        $stmt->execute([$appointment_id]);

        // Update payment status if exists
        $stmt = $pdo->prepare("UPDATE Payments SET status = 'Refunded' WHERE appointment_id = ? AND status = 'Completed'");
        $stmt->execute([$appointment_id]);

        $pdo->commit();

        $_SESSION['success'] = "Appointment #$appointment_id has been cancelled successfully";
        header('Location: '.($is_doctor ? 'doctor_patients.php' : 'dashboard.php'));
        exit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error cancelling appointment: ".$e->getMessage();
        header('Location: '.($is_doctor ? 'doctor_patients.php' : 'dashboard.php'));
        exit();
    }
}

// Get payment details if exists
$stmt = $pdo->prepare("SELECT * FROM Payments WHERE appointment_id = ?");
$stmt->execute([$appointment_id]);
$payment = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Appointment | Health Matrix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #28a745;
            --danger-color: #dc3545;
            --dark-color: #343a40;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        .cancel-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border: none;
        }
        
        .cancel-header {
            background-color: var(--danger-color);
            color: white;
            border-radius: 10px 10px 0 0 !important;
        }
        
        .user-img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--danger-color);
        }
        
        .btn-cancel {
            background-color: var(--danger-color);
            border-color: var(--danger-color);
        }
        
        .btn-cancel:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="cancel-card card">
                    <div class="cancel-header card-header">
                        <h4 class="mb-0"><i class="fas fa-calendar-times me-2"></i> Cancel Appointment</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                        <?php endif; ?>
                        
                        <div class="text-center mb-4">
                            <i class="fas fa-exclamation-triangle fa-4x text-danger mb-3"></i>
                            <h3>Confirm Appointment Cancellation</h3>
                            <p class="lead">Are you sure you want to cancel this appointment?</p>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-6 text-center">
                                <?php if ($is_doctor): ?>
                                    <img src="images/patients/<?php echo $appointment['patient_id'] % 10 ?>.jpg" 
                                         class="user-img mb-3" 
                                         alt="<?php echo htmlspecialchars($appointment['patient_name']); ?>">
                                    <h5><?php echo htmlspecialchars($appointment['patient_name']); ?></h5>
                                    <p class="text-muted">Patient</p>
                                <?php else: ?>
                                    <img src="images/doctors/<?php echo $appointment['doctor_id'] % 5 ?>.jpg" 
                                         class="user-img mb-3" 
                                         alt="Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?>">
                                    <h5>Dr. <?php echo htmlspecialchars($appointment['doctor_name']); ?></h5>
                                    <p class="text-muted">Doctor</p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <h6>Appointment Details</h6>
                                    <hr>
                                    <p><i class="fas fa-calendar-day me-2 text-danger"></i> 
                                        <?php echo date('l, F j, Y', strtotime($appointment['appointment_date'])); ?>
                                    </p>
                                    <p><i class="fas fa-clock me-2 text-danger"></i> 
                                        <?php echo date('g:i A', strtotime($appointment['appointment_date'])); ?>
                                    </p>
                                    
                                    <?php if (!empty($appointment['notes'])): ?>
                                        <p><i class="fas fa-sticky-note me-2 text-danger"></i> 
                                            <?php echo htmlspecialchars($appointment['notes']); ?>
                                        </p>
                                    <?php endif; ?>
                                    
                                    <?php if ($payment && $payment['amount'] > 0): ?>
                                        <p><i class="fas fa-money-bill-wave me-2 text-danger"></i> 
                                            $<?php echo number_format($payment['amount'], 2); ?>
                                            <span class="badge bg-<?php 
                                                echo $payment['status'] === 'Completed' ? 'success' : 
                                                    ($payment['status'] === 'Failed' ? 'danger' : 'warning'); 
                                            ?> ms-2">
                                                <?php echo $payment['status']; ?>
                                            </span>
                                        </p>
                                        <?php if ($payment['status'] === 'Completed'): ?>
                                            <div class="alert alert-warning">
                                                <i class="fas fa-info-circle me-2"></i>
                                                Payment will be refunded upon cancellation
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <form method="post" action="appointment_cancel.php?id=<?php echo $appointment_id; ?>">
                            <div class="mb-3">
                                <label for="reason" class="form-label">Cancellation Reason (Optional)</label>
                                <textarea class="form-control" id="reason" name="reason" rows="3" 
                                          placeholder="Please provide a reason for cancellation (helpful for our records)"></textarea>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="<?php echo $is_doctor ? 'doctor_patients.php' : 'dashboard.php'; ?>" 
                                   class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i> Go Back
                                </a>
                                <button type="submit" class="btn btn-cancel">
                                    <i class="fas fa-times me-2"></i> Confirm Cancellation
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>