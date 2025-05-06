<?php
// -------------------------
// Configuration & Setup
// -------------------------
require_once 'config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// -------------------------
// Authentication Check
// -------------------------
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'patient') {
    header('Location: login.php');
    exit();
}

// -------------------------
// Data Initialization
// -------------------------
$patientId = $_SESSION['user_id'];
$patientData = [];
$error = null;

// -------------------------
// Database Operations
// -------------------------
try {
    // Fetch patient details with email from Users table
    $query = "SELECT pd.*, u.email 
              FROM PatientDetails pd
              JOIN Users u ON pd.patient_id = u.user_id
              WHERE pd.patient_id = :patient_id";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([':patient_id' => $patientId]);
    $patientData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$patientData) {
        $error = "Patient record not found.";
    }

} catch (PDOException $e) {
    $error = "Database error: " . htmlspecialchars($e->getMessage());
}

// -------------------------
// View Configuration
// -------------------------
$pageTitle = "Patient Profile";
$stylesheets = [
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
    '/css/custom-profile.css'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <?php foreach ($stylesheets as $stylesheet): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($stylesheet) ?>">
    <?php endforeach; ?>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <main class="container py-5">
        <article>
            <h1 class="text-center mb-5">👤 Patient Profile</h1>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php else: ?>
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="card shadow-sm rounded-4 border-0">
                            <div class="card-body p-4">
                                <?= buildProfileSection([
                                    'Full Name' => $patientData['full_name'] ?? 'Unknown',
                                    'Date of Birth' => $patientData['date_of_birth'] ?? 'N/A',
                                    'Gender' => $patientData['gender'] ?? 'N/A',
                                    'Email' => $patientData['email'] ?? 'N/A',
                                    'Phone' => $patientData['phone'] ?? 'N/A',
                                    'Address' => $patientData['address'] ?? 'N/A'
                                ]) ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </article>
    </main>

    <?php include 'footer.php'; ?>
</body>
</html>

<?php
// -------------------------
// Helper Functions
// -------------------------
function buildProfileSection(array $data): string {
    $html = '';
    foreach ($data as $label => $value) {
        $html .= <<<HTML
            <div class="row mb-3">
                <div class="col-4 fw-bold text-secondary">{$label}:</div>
                <div class="col-8">{$value}</div>
            </div>
        HTML;
    }
    return $html;
}
?>