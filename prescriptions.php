<?php
$host = "localhost";
$user = "root";
$password = "";
$db = "matrix";

$conn = new mysqli($host, $user, $password, $db);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$msg = "";


if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_prescription'])) {
    $patient_id = $_POST['patient_id'];
    $diagnosis = $_POST['diagnosis'];
    $instructions = $_POST['instructions'];
    $date = date('Y-m-d');

    
    $doctor_id = 1; 
    $now = date('Y-m-d H:i:s');
    $status = "Completed";

    $stmt1 = $conn->prepare("INSERT INTO Appointments (patient_id, doctor_id, appointment_date, status) VALUES (?, ?, ?, ?)");
    $stmt1->bind_param("iiss", $patient_id, $doctor_id, $now, $status);
    if ($stmt1->execute()) {
        $appointment_id = $stmt1->insert_id;

        // Insert prescription
        $stmt2 = $conn->prepare("INSERT INTO Prescriptions (appointment_id, diagnosis, prescription_date, instructions) VALUES (?, ?, ?, ?)");
        $stmt2->bind_param("isss", $appointment_id, $diagnosis, $date, $instructions);
        if ($stmt2->execute()) {
            $msg = "Prescription created successfully.";
        } else {
            $msg = "Prescription Error: " . $stmt2->error;
        }
        $stmt2->close();
    } else {
        $msg = "Appointment Error: " . $stmt1->error;
    }
    $stmt1->close();
}

// Fetch Prescriptions
$prescriptions = $conn->query("SELECT p.prescription_id, p.diagnosis, p.prescription_date, p.instructions, p.appointment_id, a.patient_id, a.doctor_id FROM Prescriptions p JOIN Appointments a ON p.appointment_id = a.appointment_id ORDER BY p.prescription_date DESC");

// Fetch Patients for dropdown
$patients = $conn->query("SELECT u.user_id, pd.full_name FROM Users u JOIN PatientDetails pd ON u.user_id = pd.patient_id WHERE u.role = 'patient' AND u.is_active = 1");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Prescription Panel</title>
    <link rel="stylesheet" href="styles.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
</head>
<body class="bg-light">
<header>
        <div class="header-top">
            <div class="container">
                <div class="logo">
                    <a href="index.html"><img src="logo.png" alt="Health Matrix Logo"></a>
                </div>
                <div class="header-actions">
                    <a href="login.html" class="btn-login">Patient Login</a>
                    <a href="doctor-login.html" class="btn-login">Doctor Login</a>
                </div>
            </div>
        </div>
    </header>
<div class="container mt-4">
    <h2 class="mb-4 text-center">Doctor Prescription Management</h2>

    <?php if ($msg): ?>
        <div class="alert alert-info"><?php echo $msg; ?></div>
    <?php endif; ?>

    <div class="card mb-4">
        <div class="card-header bg-primary text-white">Add New Prescription</div>
        <div class="card-body">
            <form method="POST">
                <div class="form-group">
                    <label>Patient</label>
                    <select name="patient_id" class="form-control" required>
                        <option value="">Select Patient</option>
                        <?php while($row = $patients->fetch_assoc()): ?>
                            <option value="<?php echo $row['user_id']; ?>"><?php echo htmlspecialchars($row['full_name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Diagnosis</label>
                    <textarea name="diagnosis" class="form-control" required></textarea>
                </div>
                <div class="form-group">
                    <label>Instructions</label>
                    <textarea name="instructions" class="form-control" required></textarea>
                </div>
                <button type="submit" name="add_prescription" class="btn btn-success">Add Prescription</button>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-secondary text-white">Existing Prescriptions</div>
        <div class="card-body p-0">
            <table class="table table-bordered table-striped mb-0">
                <thead class="thead-dark">
                    <tr>
                        <th>ID</th>
                        <th>Appointment ID</th>
                        <th>Patient ID</th>
                        <th>Doctor ID</th>
                        <th>Diagnosis</th>
                        <th>Date</th>
                        <th>Instructions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($prescriptions->num_rows > 0): ?>
                    <?php while($presc = $prescriptions->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $presc['prescription_id']; ?></td>
                            <td><?php echo $presc['appointment_id']; ?></td>
                            <td><?php echo $presc['patient_id']; ?></td>
                            <td><?php echo $presc['doctor_id']; ?></td>
                            <td><?php echo htmlspecialchars($presc['diagnosis']); ?></td>
                            <td><?php echo $presc['prescription_date']; ?></td>
                            <td><?php echo htmlspecialchars($presc['instructions']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="text-center">No prescriptions found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
<!-- Footer -->
<footer>
        <div class="footer-top">
            <div class="container">
                <div class="footer-columns">
                    <!-- About Us -->
                    <div class="footer-section">
                        <h3>About Us</h3>
                        <p>Health Matrix is the number 1 healthcare service provider in Bangladesh, based on one million downloads and ratings on the Play Store.</p>
                    </div>
                    <!-- Quick Links -->
                    <div class="footer-section">
                        <h3>Quick Links</h3>
                        <ul>
                            <li><a href="index.html">Home</a></li>
                            <li><a href="health-plans.html">Health Plans</a></li>
                            <li><a href="about-us.html">About Us</a></li>
                            <li><a href="terms-of-services.html">Terms of Services</a></li>
                            <li><a href="privacy-policy.html">Privacy Policy</a></li>
                            <li><a href="home-diagnostics.html">Home Diagnostics</a></li>
                            <li><a href="doctors-register.html">For Doctors</a></li>
                            <li><a href="contact-us.html">Contact Us</a></li>
                        </ul>
                    </div>
                    <!-- Contact Us -->
                    <div class="footer-section">
                        <h3>Contact Us</h3>
                        <p>📞 09677865599</p>
                        <p>📧 support@healthmatrix.com.bd</p>
                        <!-- Social Media Icons -->
                        <div class="footer-social">
                            <a href="https://facebook.com/healthmatrix" target="_blank"><i class="fab fa-facebook"></i></a>
                            <a href="https://instagram.com/healthmatrix" target="_blank"><i class="fab fa-instagram"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="container">
                <p>Copyright © 2025 Health Matrix. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>

<?php $conn->close(); ?>
