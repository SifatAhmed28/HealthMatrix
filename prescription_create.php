<?php
require_once 'config.php';

// Verify doctor authentication 
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if ($_SESSION['role'] !== 'doctor') {
    header('Location: unauthorized.php');
    exit();
}

$doctor_id = $_SESSION['user_id'];

// Initialize variables
$appointment_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;
$appointment = null;
$patient_id = 0;
$error = '';
$success = '';

// Get appointment details if provided
if ($appointment_id > 0) {
    $stmt = $pdo->prepare("SELECT a.*, p.full_name as patient_name, p.patient_id
                          FROM Appointments a
                          JOIN PatientDetails p ON a.patient_id = p.patient_id
                          WHERE a.appointment_id = ? AND a.doctor_id = ? AND a.status = 'Completed'");
    $stmt->execute([$appointment_id, $doctor_id]);
    $appointment = $stmt->fetch();
    
    if ($appointment) {
        $patient_id = $appointment['patient_id'];
    } else {
        $error = "Invalid or incomplete appointment selected";
    }
}

// Get medicines list
$medicines = $pdo->query("SELECT * FROM Medicines WHERE stock > 0 ORDER BY name")->fetchAll();

// Get recent completed appointments for this doctor
$completed_appointments = $pdo->prepare("SELECT a.appointment_id, p.full_name, a.appointment_date 
                                        FROM Appointments a
                                        JOIN PatientDetails p ON a.patient_id = p.patient_id
                                        WHERE a.doctor_id = ? AND a.status = 'Completed'
                                        ORDER BY a.appointment_date DESC
                                        LIMIT 10");
$completed_appointments->execute([$doctor_id]);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    try {
        $pdo->beginTransaction();
        
        // Validate inputs
        $appointment_id = (int)$_POST['appointment_id'];
        $diagnosis = trim($_POST['diagnosis']);
        $instructions = trim($_POST['instructions']);
        $medicines_prescribed = $_POST['medicines'] ?? [];
        
        if ($appointment_id <= 0) {
            throw new Exception("Please select a valid appointment");
        }
        
        if (empty($diagnosis)) {
            throw new Exception("Diagnosis is required");
        }
        
        if (count($medicines_prescribed) === 0) {
            throw new Exception("At least one medicine is required");
        }
        
        // Verify appointment belongs to this doctor
        $stmt = $pdo->prepare("SELECT patient_id FROM Appointments 
                              WHERE appointment_id = ? AND doctor_id = ? AND status = 'Completed'");
        $stmt->execute([$appointment_id, $doctor_id]);
        $appt = $stmt->fetch();
        
        if (!$appt) {
            throw new Exception("Invalid appointment selected");
        }
        
        $patient_id = $appt['patient_id'];
        
        // Create prescription
        $stmt = $pdo->prepare("INSERT INTO Prescriptions 
                              (appointment_id, diagnosis, instructions, prescription_date)
                              VALUES (?, ?, ?, CURDATE())");
        $stmt->execute([$appointment_id, $diagnosis, $instructions]);
        $prescription_id = $pdo->lastInsertId();
        
        // Add prescribed medicines
        foreach ($medicines_prescribed as $medicine_id => $details) {
            $medicine_id = (int)$medicine_id;
            $dosage = trim($details['dosage']);
            $frequency = trim($details['frequency']);
            $duration = trim($details['duration']);
            
            if ($medicine_id <= 0) continue;
            
            $stmt = $pdo->prepare("INSERT INTO PrescriptionMedicines 
                                  (prescription_id, medicine_id, dosage, frequency, duration)
                                  VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$prescription_id, $medicine_id, $dosage, $frequency, $duration]);
            
            // Update stock if needed
            if (isset($details['deduct_stock']) && $details['deduct_stock'] === 'on') {
                $quantity = max(1, (int)$details['quantity']);
                $stmt = $pdo->prepare("UPDATE Medicines SET stock = stock - ? WHERE medicine_id = ?");
                $stmt->execute([$quantity, $medicine_id]);
            }
        }
        
        $pdo->commit();
        
        $_SESSION['success'] = "Prescription created successfully!";
        header("Location: prescription_view.php?id=$prescription_id");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Prescription | Health Matrix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        .medicine-item {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #f8f9fa;
        }
        .medicine-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .stock-info {
            font-size: 0.9rem;
            color: #6c757d;
        }
        .autocomplete-list {
            position: absolute;
            z-index: 1000;
            width: calc(100% - 30px);
            max-height: 200px;
            overflow-y: auto;
            background: white;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            display: none;
        }
        .autocomplete-item {
            padding: 8px 12px;
            cursor: pointer;
        }
        .autocomplete-item:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container py-5">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4><i class="fas fa-file-prescription me-2"></i> Create New Prescription</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                        <?php endif; ?>
                        
                        <form method="post" id="prescriptionForm">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label">Appointment</label>
                                    <?php if ($appointment): ?>
                                        <input type="hidden" name="appointment_id" value="<?php echo $appointment_id; ?>">
                                        <div class="form-control">
                                            <?php echo htmlspecialchars($appointment['patient_name']); ?>
                                            <small class="text-muted">
                                                (Completed on <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?>)
                                            </small>
                                        </div>
                                    <?php else: ?>
                                        <select class="form-select" name="appointment_id" required>
                                            <option value="">Select Completed Appointment</option>
                                            <?php foreach ($completed_appointments as $appt): ?>
                                                <option value="<?php echo $appt['appointment_id']; ?>">
                                                    <?php echo htmlspecialchars($appt['full_name'] . ' - ' . date('M j, Y', strtotime($appt['appointment_date']))); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Prescription Date</label>
                                    <div class="form-control">
                                        <?php echo date('F j, Y'); ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="diagnosis" class="form-label">Diagnosis *</label>
                                <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3" required><?php echo htmlspecialchars($_POST['diagnosis'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="mb-4">
                                <label for="instructions" class="form-label">Patient Instructions *</label>
                                <textarea class="form-control" id="instructions" name="instructions" rows="3"><?php echo htmlspecialchars($_POST['instructions'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="mb-4">
                                <h5 class="mb-3">Medicines Prescribed *</h5>
                                <div id="medicineContainer">
                                    <!-- Medicine items will be added here -->
                                    <div class="medicine-item">
                                        <div class="medicine-header">
                                            <h6>Medicine #1</h6>
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-medicine" disabled>
                                                <i class="fas fa-times"></i> Remove
                                            </button>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-5 mb-3">
                                                <label class="form-label">Medicine *</label>
                                                <select class="form-select" name="medicines[1][id]" required>
                                                    <option value="">Select Medicine</option>
                                                    <?php foreach ($medicines as $med): ?>
                                                        <option value="<?php echo $med['medicine_id']; ?>">
                                                            <?php echo htmlspecialchars($med['name'] . ' (' . $med['dosage_form'] . ') - Stock: ' . $med['stock']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-2 mb-3">
                                                <label class="form-label">Dosage *</label>
                                                <input type="text" class="form-control" name="medicines[1][dosage]" placeholder="e.g. 500mg" required>
                                            </div>
                                            <div class="col-md-2 mb-3">
                                                <label class="form-label">Frequency *</label>
                                                <input type="text" class="form-control" name="medicines[1][frequency]" placeholder="e.g. 2x daily" required>
                                            </div>
                                            <div class="col-md-2 mb-3">
                                                <label class="form-label">Duration *</label>
                                                <input type="text" class="form-control" name="medicines[1][duration]" placeholder="e.g. 7 days" required>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="medicines[1][deduct_stock]" id="deductStock1">
                                                    <label class="form-check-label" for="deductStock1">Deduct from inventory</label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <input type="number" class="form-control" name="medicines[1][quantity]" placeholder="Qty" min="1" value="1" disabled>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-outline-primary mt-2" id="addMedicineBtn">
                                    <i class="fas fa-plus me-2"></i> Add Another Medicine
                                </button>
                            </div>
                            
                            <div class="d-flex justify-content-between mt-4">
                                <a href="<?php echo $appointment_id ? 'appointment_view.php?id='.$appointment_id : 'doctor_dashboard.php'; ?>" 
                                   class="btn btn-outline-secondary">
                                    <i class="fas fa-arrow-left me-2"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i> Save Prescription
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Medicine Template (hidden) -->
    <template id="medicineTemplate">
        <div class="medicine-item">
            <div class="medicine-header">
                <h6>Medicine #<span class="medicine-number"></span></h6>
                <button type="button" class="btn btn-sm btn-outline-danger remove-medicine">
                    <i class="fas fa-times"></i> Remove
                </button>
            </div>
            <div class="row">
                <div class="col-md-5 mb-3">
                    <label class="form-label">Medicine *</label>
                    <select class="form-select" name="medicines[0][id]" required>
                        <option value="">Select Medicine</option>
                        <?php foreach ($medicines as $med): ?>
                            <option value="<?php echo $med['medicine_id']; ?>">
                                <?php echo htmlspecialchars($med['name'] . ' (' . $med['dosage_form'] . ') - Stock: ' . $med['stock']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Dosage *</label>
                    <input type="text" class="form-control" name="medicines[0][dosage]" placeholder="e.g. 500mg" required>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Frequency *</label>
                    <input type="text" class="form-control" name="medicines[0][frequency]" placeholder="e.g. 2x daily" required>
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">Duration *</label>
                    <input type="text" class="form-control" name="medicines[0][duration]" placeholder="e.g. 7 days" required>
                </div>
            </div>
            <div class="row">
                <div class="col-md-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="medicines[0][deduct_stock]" id="deductStock0">
                        <label class="form-check-label" for="deductStock0">Deduct from inventory</label>
                    </div>
                </div>
                <div class="col-md-3">
                    <input type="number" class="form-control" name="medicines[0][quantity]" placeholder="Qty" min="1" value="1" disabled>
                </div>
            </div>
        </div>
    </template>
    
    <?php include 'footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const medicineContainer = document.getElementById('medicineContainer');
            const addMedicineBtn = document.getElementById('addMedicineBtn');
            const medicineTemplate = document.getElementById('medicineTemplate');
            let medicineCount = 1; // Start at 1 since we have one by default
            
            // Add medicine button
            addMedicineBtn.addEventListener('click', function() {
                const newMedicine = medicineTemplate.content.cloneNode(true);
                medicineCount++;
                
                // Update all the indexes in the cloned template
                newMedicine.querySelectorAll('[name]').forEach(el => {
                    el.name = el.name.replace(/\[0\]/, `[${medicineCount}]`);
                });
                
                // Update IDs and labels
                newMedicine.querySelector('.medicine-number').textContent = medicineCount;
                const newId = `deductStock${medicineCount}`;
                newMedicine.querySelector('input[type="checkbox"]').id = newId;
                newMedicine.querySelector('label[for="deductStock0"]').htmlFor = newId;
                
                // Add remove functionality
                newMedicine.querySelector('.remove-medicine').addEventListener('click', function() {
                    medicineContainer.removeChild(this.closest('.medicine-item'));
                });
                
                // Add deduct stock toggle
                const deductCheckbox = newMedicine.querySelector('input[type="checkbox"]');
                const quantityInput = newMedicine.querySelector('input[type="number"]');
                deductCheckbox.addEventListener('change', function() {
                    quantityInput.disabled = !this.checked;
                });
                
                medicineContainer.appendChild(newMedicine);
            });
            
            // Enable remove button and deduct stock toggle for the first medicine
            const firstMedicine = medicineContainer.querySelector('.medicine-item');
            firstMedicine.querySelector('.remove-medicine').disabled = false;
            
            firstMedicine.querySelector('input[type="checkbox"]').addEventListener('change', function() {
                const quantityInput = firstMedicine.querySelector('input[type="number"]');
                quantityInput.disabled = !this.checked;
            });
        });
    </script>
</body>
</html>