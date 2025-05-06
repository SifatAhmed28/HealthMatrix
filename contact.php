<?php
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

// Handle form submission
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name)) {
        $errors[] = 'Please enter your name';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address';
    }

    if (empty($subject)) {
        $errors[] = 'Please enter a subject';
    }

    if (empty($message)) {
        $errors[] = 'Please enter your message';
    }

    if (empty($errors)) {
        // Here you would typically send the email or store in database
        $success = true;
    }
}

include 'navbar.php';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="container py-5">
    <h1 class="text-center mb-4">Get in Touch</h1>

    <div class="alert alert-info text-center mb-5">
        <h3 class="mb-3">We're Here to Help!</h3>
        <p class="lead">"Your health questions matter - don't hesitate to reach out. Better to ask a question than to remain ignorant."</p>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-body">
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            Thank you for your message! We'll respond within 24 hours.
                        </div>
                    <?php else: ?>
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <?php foreach ($errors as $error): ?>
                                    <div><?= htmlspecialchars($error) ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="name" class="form-control" 
                                       value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" name="email" class="form-control"
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Subject</label>
                                <input type="text" name="subject" class="form-control"
                                       value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Message</label>
                                <textarea name="message" class="form-control" rows="5" required><?= 
                                    htmlspecialchars($_POST['message'] ?? '') 
                                ?></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-paper-plane me-2"></i>Send Message
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-6 mt-4 mt-md-0">
            <div class="card shadow">
                <div class="card-body">
                    <h4 class="mb-4">Contact Information</h4>

                    <div class="d-flex align-items-start mb-4">
                        <div class="me-3 text-primary">
                            <i class="fas fa-map-marker-alt fa-2x"></i>
                        </div>
                        <div>
                            <h5>Visit Us</h5>
                            <p class="mb-0">
                                123 Health Street<br>
                                Medical City, HC 4567
                            </p>
                        </div>
                    </div>

                    <div class="d-flex align-items-start mb-4">
                        <div class="me-3 text-primary">
                            <i class="fas fa-phone fa-2x"></i>
                        </div>
                        <div>
                            <h5>Call Us</h5>
                            <p class="mb-0">
                                24/7 Helpline: (123) 456-7890<br>
                                Office: (098) 765-4321
                            </p>
                        </div>
                    </div>

                    <div class="d-flex align-items-start mb-4">
                        <div class="me-3 text-primary">
                            <i class="fas fa-envelope fa-2x"></i>
                        </div>
                        <div>
                            <h5>Email Us</h5>
                            <p class="mb-0">
                                General Inquiries: info@healthmatrix.com<br>
                                Support: support@healthmatrix.com
                            </p>
                        </div>
                    </div>

                    <hr>

                    <div class="text-center mt-4">
                        <h5>Follow Us</h5>
                        <div class="social-links">
                            <a href="#" class="text-primary me-3">
                                <i class="fab fa-facebook fa-2x"></i>
                            </a>
                            <a href="#" class="text-primary me-3">
                                <i class="fab fa-twitter fa-2x"></i>
                            </a>
                            <a href="#" class="text-primary">
                                <i class="fab fa-linkedin fa-2x"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-danger mt-5 text-center">
        <h4 class="mb-3"><i class="fas fa-exclamation-triangle me-2"></i>Emergency?</h4>
        <p class="lead mb-0">
            For immediate medical assistance, call our 24/7 emergency line: 
            <strong>999-HELP-NOW</strong>
        </p>
    </div>
</div>

<?php include 'footer.php'; ?>