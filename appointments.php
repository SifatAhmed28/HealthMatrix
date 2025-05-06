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

// Handle patient_id parameter (for doctors booking for patients)
$patient_id = null;
if ($is_doctor && isset($_GET['patient_id'])) {
    $patient_id = (int)$_GET['patient_id'];
    // Verify patient exists and has relationship with doctor
    $stmt = $pdo->prepare("SELECT 1 FROM Appointments 
                          WHERE doctor_id = ? AND patient_id = ? LIMIT 1");
    $stmt->execute([$user_id, $patient_id]);
    if (!$stmt->fetch()) {
        $_SESSION['error'] = "Patient not found or not under your care";
        header('Location: doctor_patients.php');
        exit();
    }
}

// Get specialties for dropdown
$specialties = $pdo->query("SELECT * FROM Specialties ORDER BY name")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Validate inputs
        $doctor_id = $is_doctor ? $user_id : (int)$_POST['doctor_id'];
        $appointment_patient_id = $is_doctor ? $patient_id : $user_id;
        $date = $_POST['date'];
        $time = $_POST['time'];
        $reason = !empty($_POST['reason']) ? $_POST['reason'] : null;
        $datetime = "$date $time";

        // Verify doctor exists
        $stmt = $pdo->prepare("SELECT 1 FROM DoctorDetails WHERE doctor_id = ?");
        $stmt->execute([$doctor_id]);
        if (!$stmt->fetch()) {
            throw new Exception("Selected doctor not found");
        }

        // Check if doctor is available at this time
        $stmt = $pdo->prepare("SELECT 1 FROM DoctorSchedules 
                              WHERE doctor_id = ? 
                              AND day_of_week = ? 
                              AND start_time <= ? 
                              AND end_time >= ? 
                              AND is_available = 1");
        $day_of_week = date('D', strtotime($date));
        $stmt->execute([$doctor_id, $day_of_week, $time, $time]);
        if (!$stmt->fetch()) {
            throw new Exception("Doctor is not available at this time");
        }

        // Check for existing appointment at this time
        $stmt = $pdo->prepare("SELECT 1 FROM Appointments 
                              WHERE doctor_id = ? 
                              AND appointment_date = ? 
                              AND status != 'Cancelled'");
        $stmt->execute([$doctor_id, $datetime]);
        if ($stmt->fetch()) {
            throw new Exception("Doctor already has an appointment at this time");
        }

        // Create new appointment
        $stmt = $pdo->prepare("INSERT INTO Appointments 
                              (patient_id, doctor_id, appointment_date, notes, status) 
                              VALUES (?, ?, ?, ?, 'Booked')");
        $stmt->execute([$appointment_patient_id, $doctor_id, $datetime, $reason]);

        $appointment_id = $pdo->lastInsertId();

        // Create payment record if needed
        $stmt = $pdo->prepare("SELECT consultation_fee FROM DoctorDetails WHERE doctor_id = ?");
        $stmt->execute([$doctor_id]);
        $fee = $stmt->fetchColumn();

        if ($fee > 0) {
            $stmt = $pdo->prepare("INSERT INTO Payments 
                                  (appointment_id, amount, status) 
                                  VALUES (?, ?, 'Pending')");
            $stmt->execute([$appointment_id, $fee]);
        }

        $pdo->commit();

        $_SESSION['success'] = "Appointment booked successfully!";
        header("Location: appointment_details.php?id=$appointment_id");
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Get available doctors based on filters
$available_doctors = [];
if (isset($_GET['specialty']) || isset($_GET['date'])) {
    $specialty_id = isset($_GET['specialty']) ? (int)$_GET['specialty'] : null;
    $date_filter = isset($_GET['date']) ? $_GET['date'] : null;
    $day_of_week = $date_filter ? date('D', strtotime($date_filter)) : null;

    $sql = "SELECT d.doctor_id, d.full_name, d.consultation_fee, s.name as specialty 
            FROM DoctorDetails d
            JOIN Specialties s ON d.specialty_id = s.specialty_id
            WHERE 1=1";
    
    $params = [];
    
    if ($specialty_id) {
        $sql .= " AND d.specialty_id = ?";
        $params[] = $specialty_id;
    }
    
    if ($date_filter && $day_of_week) {
        $sql .= " AND EXISTS (
                    SELECT 1 FROM DoctorSchedules ds
                    WHERE ds.doctor_id = d.doctor_id
                    AND ds.day_of_week = ?
                    AND ds.is_available = 1
                )";
        $params[] = $day_of_week;
    }
    
    $sql .= " ORDER BY d.full_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $available_doctors = $stmt->fetchAll();
}

// Get doctor's available time slots for selected date
$available_slots = [];
if (isset($_GET['doctor_id']) && isset($_GET['date'])) {
    $doctor_id = (int)$_GET['doctor_id'];
    $date = $_GET['date'];
    $day_of_week = date('D', strtotime($date));

    // Get doctor's schedule for this day
    $stmt = $pdo->prepare("SELECT start_time, end_time 
                          FROM DoctorSchedules 
                          WHERE doctor_id = ? 
                          AND day_of_week = ? 
                          AND is_available = 1");
    $stmt->execute([$doctor_id, $day_of_week]);
    $schedule = $stmt->fetch();

    if ($schedule) {
        // Generate 30-minute slots between start and end time
        $start = new DateTime($schedule['start_time']);
        $end = new DateTime($schedule['end_time']);
        $interval = new DateInterval('PT30M');

        $current = clone $start;
        while ($current < $end) {
            $slot_time = $current->format('H:i:s');
            $datetime = "$date $slot_time";

            // Check if slot is already booked
            $stmt = $pdo->prepare("SELECT 1 FROM Appointments 
                                  WHERE doctor_id = ? 
                                  AND appointment_date = ? 
                                  AND status != 'Cancelled'");
            $stmt->execute([$doctor_id, $datetime]);
            
            if (!$stmt->fetch()) {
                $available_slots[] = [
                    'time' => $current->format('g:i A'),
                    'value' => $slot_time
                ];
            }

            $current->add($interval);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment | Health Matrix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        :root {
            --primary-color: #28a745;
            --secondary-color: #007bff;
            --dark-color: #343a40;
            --light-color: #f8f9fa;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
        }
        
        .booking-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            border: none;
        }
        
        .booking-header {
            background-color: var(--primary-color);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
        }
        
        .doctor-card {
            transition: all 0.3s;
            cursor: pointer;
            border: 2px solid transparent;
        }
        
        .doctor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .doctor-card.selected {
            border-color: var(--primary-color);
            background-color: rgba(40, 167, 69, 0.05);
        }
        
        .time-slot {
            padding: 10px 15px;
            margin: 5px;
            border: 1px solid #ddd;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .time-slot:hover {
            background-color: #f8f9fa;
        }
        
        .time-slot.selected {
            background-color: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container py-5">
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="booking-card">
                    <div class="booking-header">
                        <h4 class="mb-0">
                            <i class="fas fa-calendar-plus me-2"></i>
                            <?php echo $is_doctor ? 'Book Appointment for Patient' : 'Book New Appointment'; ?>
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                        <?php endif; ?>
                        
                        <form id="appointmentForm" method="get" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                            <!-- Step 1: Select Specialty and Date -->
                            <div id="step1" class="booking-step">
                                <h5 class="mb-4">1. Select Specialty and Date</h5>
                                
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <label for="specialty" class="form-label">Specialty</label>
                                        <select class="form-select" id="specialty" name="specialty">
                                            <option value="">Any Specialty</option>
                                            <?php foreach ($specialties as $specialty): ?>
                                                <option value="<?php echo $specialty['specialty_id']; ?>"
                                                    <?php echo isset($_GET['specialty']) && $_GET['specialty'] == $specialty['specialty_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($specialty['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="dateFilter" class="form-label">Appointment Date</label>
                                        <input type="text" class="form-control datepicker" id="dateFilter" 
                                               name="date" placeholder="Select date" 
                                               value="<?php echo isset($_GET['date']) ? htmlspecialchars($_GET['date']) : ''; ?>">
                                    </div>
                                </div>
                                
                                <div class="text-center">
                                    <button type="submit" class="btn btn-primary" id="findDoctorsBtn">
                                        Find Available Doctors
                                    </button>
                                </div>
                            </div>
                            
                            <?php if (isset($_GET['specialty']) || isset($_GET['date'])): ?>
                            <!-- Step 2: Select Doctor -->
                            <div id="step2" class="booking-step">
                                <h5 class="mb-4">2. Select Doctor</h5>
                                
                                <div id="doctorResults">
                                    <?php if (empty($available_doctors)): ?>
                                        <div class="alert alert-info">
                                            No doctors available matching your criteria. Please adjust your filters.
                                        </div>
                                    <?php else: ?>
                                        <div class="row">
                                            <?php foreach ($available_doctors as $doctor): ?>
                                                <div class="col-md-6 mb-3">
                                                    <div class="doctor-card card p-3 
                                                        <?php echo isset($_GET['doctor_id']) && $_GET['doctor_id'] == $doctor['doctor_id'] ? 'selected' : ''; ?>"
                                                        data-doctor-id="<?php echo $doctor['doctor_id']; ?>">
                                                        <div class="d-flex">
                                                            <img src="images/doctors/<?php echo $doctor['doctor_id'] % 5 ?>.jpg" 
                                                                 class="rounded-circle me-3" width="60" height="60" 
                                                                 alt="Dr. <?php echo htmlspecialchars($doctor['full_name']); ?>">
                                                            <div>
                                                                <h6 class="mb-1"><?php echo htmlspecialchars($doctor['full_name']); ?></h6>
                                                                <p class="text-muted mb-1"><?php echo htmlspecialchars($doctor['specialty']); ?></p>
                                                                <p class="text-success mb-0">
                                                                    <i class="fas fa-money-bill-wave"></i> 
                                                                    $<?php echo number_format($doctor['consultation_fee'], 2); ?>
                                                                </p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <a href="appointments.php<?php echo $is_doctor && $patient_id ? '?patient_id='.$patient_id : ''; ?>" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left me-2"></i> Back
                                    </a>
                                    <?php if (!empty($available_doctors)): ?>
                                    <button type="button" class="btn btn-primary" id="toStep3">
                                        Select Time <i class="fas fa-arrow-right ms-2"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($_GET['doctor_id']) && isset($_GET['date'])): ?>
                            <!-- Step 3: Select Time -->
                            <div id="step3" class="booking-step">
                                <h5 class="mb-4">3. Select Time Slot</h5>
                                
                                <div class="mb-4">
                                    <div class="d-flex align-items-center mb-3">
                                        <img src="images/doctors/<?php echo isset($_GET['doctor_id']) ? ($_GET['doctor_id'] % 5) : '1'; ?>.jpg" 
                                             class="rounded-circle me-3" width="50" height="50" 
                                             alt="Doctor photo" id="selectedDoctorImage">
                                        <div>
                                            <h6 class="mb-0" id="selectedDoctorName">
                                                <?php 
                                                if (isset($_GET['doctor_id'])) {
                                                    foreach ($available_doctors as $doc) {
                                                        if ($doc['doctor_id'] == $_GET['doctor_id']) {
                                                            echo htmlspecialchars($doc['full_name']);
                                                            break;
                                                        }
                                                    }
                                                }
                                                ?>
                                            </h6>
                                            <small class="text-muted" id="selectedDoctorSpecialty">
                                                <?php 
                                                if (isset($_GET['doctor_id'])) {
                                                    foreach ($available_doctors as $doc) {
                                                        if ($doc['doctor_id'] == $_GET['doctor_id']) {
                                                            echo htmlspecialchars($doc['specialty']);
                                                            break;
                                                        }
                                                    }
                                                }
                                                ?>
                                            </small>
                                        </div>
                                    </div>
                                    <p class="text-muted" id="selectedDateDisplay">
                                        Appointment date: <?php echo isset($_GET['date']) ? date('l, F j, Y', strtotime($_GET['date'])) : ''; ?>
                                    </p>
                                </div>
                                
                                <div id="timeSlotsContainer">
                                    <?php if (empty($available_slots)): ?>
                                        <div class="alert alert-info">
                                            No available time slots for selected doctor and date.
                                        </div>
                                    <?php else: ?>
                                        <div class="d-flex flex-wrap">
                                            <?php foreach ($available_slots as $slot): ?>
                                                <div class="time-slot" data-time="<?php echo $slot['value']; ?>">
                                                    <?php echo $slot['time']; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="mt-4">
                                    <label for="reason" class="form-label">Reason for Appointment (Optional)</label>
                                    <textarea class="form-control" id="reason" name="reason" rows="3"></textarea>
                                </div>
                                
                                <input type="hidden" id="doctor_id" name="doctor_id" value="<?php echo $_GET['doctor_id'] ?? ''; ?>">
                                <input type="hidden" id="date" name="date" value="<?php echo $_GET['date'] ?? ''; ?>">
                                <input type="hidden" id="time" name="time">
                                
                                <div class="d-flex justify-content-between mt-4">
                                    <a href="appointments.php?<?php 
                                        echo http_build_query([
                                            'specialty' => $_GET['specialty'] ?? '',
                                            'date' => $_GET['date'] ?? '',
                                            'patient_id' => $patient_id ?? ''
                                        ]); 
                                    ?>" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left me-2"></i> Back
                                    </a>
                                    <button type="submit" class="btn btn-success" id="confirmBookingBtn" formmethod="post" disabled>
                                        <i class="fas fa-calendar-check me-2"></i> Confirm Appointment
                                    </button>
                                </div>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize date picker
            flatpickr("#dateFilter", {
                minDate: "today",
                maxDate: new Date().fp_incr(30), // 30 days from now
                disable: [
                    function(date) {
                        // Disable weekends
                        return (date.getDay() === 0 || date.getDay() === 6);
                    }
                ]
            });
            
            // Doctor selection
            document.querySelectorAll('.doctor-card').forEach(card => {
                card.addEventListener('click', function() {
                    document.querySelectorAll('.doctor-card').forEach(c => {
                        c.classList.remove('selected');
                    });
                    this.classList.add('selected');
                });
            });
            
            // Time slot selection
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('time-slot')) {
                    document.querySelectorAll('.time-slot').forEach(slot => {
                        slot.classList.remove('selected');
                    });
                    e.target.classList.add('selected');
                    document.getElementById('time').value = e.target.getAttribute('data-time');
                    document.getElementById('confirmBookingBtn').disabled = false;
                }
            });
            
            // Handle step 2 to step 3 transition
            document.getElementById('toStep3')?.addEventListener('click', function() {
                const selectedDoctor = document.querySelector('.doctor-card.selected');
                if (!selectedDoctor) {
                    alert('Please select a doctor');
                    return;
                }
                
                const doctorId = selectedDoctor.getAttribute('data-doctor-id');
                const date = document.querySelector('input[name="date"]').value;
                const specialty = document.querySelector('select[name="specialty"]').value;
                
                // Build URL with all parameters
                let url = 'appointments.php?';
                if (specialty) url += `specialty=${specialty}&`;
                url += `date=${date}&doctor_id=${doctorId}`;
                <?php if ($is_doctor && isset($patient_id)): ?>
                    url += `&patient_id=<?php echo $patient_id; ?>`;
                <?php endif; ?>
                
                window.location.href = url;
            });
        });
    </script>
</body>
</html>