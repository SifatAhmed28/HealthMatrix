<?php
require_once 'config.php';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $isLoggedIn ? $_SESSION['role'] : '';
$username = '';

if ($isLoggedIn) {
    // Get user's name based on role
    if ($userRole === 'patient') {
        $stmt = $pdo->prepare("SELECT full_name FROM PatientDetails WHERE patient_id = ?");
    } elseif ($userRole === 'doctor') {
        $stmt = $pdo->prepare("SELECT full_name FROM DoctorDetails WHERE doctor_id = ?");
    }
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    $username = $user['full_name'] ?? '';
}

// Get featured doctors for the homepage
$featuredDoctors = [];
try {
    $stmt = $pdo->query("SELECT d.doctor_id, d.full_name, d.years_experience, d.consultation_fee, s.name AS specialty 
                         FROM DoctorDetails d 
                         JOIN Specialties s ON d.specialty_id = s.specialty_id 
                         ORDER BY RAND() LIMIT 3");
    $featuredDoctors = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching featured doctors: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Matrix - Comprehensive Medical Database System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        :root {
            --primary-color: #28a745;
            --secondary-color: #007bff;
            --dark-color: #007bff;
            --light-color: #f8f9fa;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
        }

        /* Header Styles */
        .header-top {
            background-color: var(--dark-color);
            color: white;
            padding: 10px 0;
        }

        .header-top .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo img {
            height: 50px;
        }

        .header-actions .btn {
            margin-left: 10px;
            padding: 5px 15px;
            border-radius: 5px;
            text-decoration: none;
        }

        .btn-login {
            color: white;
            border: 1px solid white;
        }

        .btn-login:hover {
            background-color: rgba(255,255,255,0.1);
        }

        .btn-signup {
            background-color: var(--primary-color);
            color: white;
        }

        .btn-signup:hover {
            background-color: #218838;
        }

        /* Navigation */
        .navbar {
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 15px 0;
        }

        .nav-links {
            list-style: none;
            display: flex;
            margin: 0;
            padding: 0;
        }

        .nav-links li {
            margin-right: 20px;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--dark-color);
            font-weight: 500;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: var(--primary-color);
        }

        /* Hero Section */
        #hero {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('medical-banner.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 120px 0;
            text-align: center;
            position: relative;
        }

        #hero h1 {
            font-size: 3.5rem;
            margin-bottom: 20px;
            font-weight: 700;
        }

        #hero p {
            font-size: 1.3rem;
            margin-bottom: 30px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
        }

        .cta-btn {
            padding: 12px 30px;
            background-color: var(--primary-color);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-block;
        }

        .cta-btn:hover {
            background-color: #218838;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        /* Services Section */
        #services {
            padding: 80px 0;
            background-color: var(--light-color);
        }

        .section-title {
            text-align: center;
            margin-bottom: 60px;
            color: var(--primary-color);
        }

        .service-card {
            background-color: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s;
            height: 100%;
            text-align: center;
        }

        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }

        .service-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 20px;
        }

        /* Doctors Section */
        #doctors {
            padding: 80px 0;
        }

        .doctor-card {
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: all 0.3s;
            margin-bottom: 30px;
        }

        .doctor-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }

        .doctor-img {
            height: 10px;
            width: 100%;
            background-color:rgba(13, 160, 218, 0.75); /* Fallback color */
            background-image: url('doctor.png');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: cover;
           
            position: relative;
        }

     
        .doctor-info {
            padding: 20px;
        }

        .doctor-specialty {
            color: var(--primary-color);
            font-weight: 500;
        }

        /* Testimonials */
        #testimonials {
            padding: 80px 0;
            background-color: var(--light-color);
        }

        .testimonial-card {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            position: relative;
            margin-bottom: 30px;
        }

        .testimonial-card:before {
            content: '"';
            font-size: 5rem;
            color: rgba(40, 167, 69, 0.1);
            position: absolute;
            top: 10px;
            left: 20px;
        }

        .testimonial-text {
            position: relative;
            z-index: 1;
            font-style: italic;
            margin-bottom: 20px;
        }

        /* CTA Section */
        #cta {
            background-color: var(--primary-color);
            color: white;
            padding: 80px 0;
            text-align: center;
        }

        /* Footer */
        footer {
            background-color: var(--dark-color);
            color: white;
            padding: 60px 0 0;
        }

        .footer-section h3 {
            color: white;
            margin-bottom: 20px;
            font-size: 1.2rem;
        }

        .footer-section ul {
            list-style: none;
            padding: 0;
        }

        .footer-section ul li {
            margin-bottom: 10px;
        }

        .footer-section ul li a {
            color: #adb5bd;
            text-decoration: none;
            transition: color 0.3s;
        }

        .footer-section ul li a:hover {
            color: white;
        }

        .footer-social a {
            color: white;
            font-size: 1.2rem;
            margin-right: 15px;
            transition: color 0.3s;
        }

        .footer-social a:hover {
            color: var(--primary-color);
        }

        .footer-bottom {
            background-color: rgba(0,0,0,0.2);
            padding: 20px 0;
            margin-top: 40px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            #hero h1 {
                font-size: 2.5rem;
            }
            
            .header-top .container {
                flex-direction: column;
                text-align: center;
            }
            
            .header-actions {
                margin-top: 10px;
            }
            
            .nav-links {
                flex-direction: column;
                align-items: center;
            }
            
            .nav-links li {
                margin: 5px 0;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header-top">
        <div class="container">
            <div class="logo">
                <a href="index.php"><img src="logo.png" alt="Health Matrix Logo" width="90" ></a>
            </div>
            <div class="header-actions">
                <?php if ($isLoggedIn): ?>
                    <span class="text-white me-3">Welcome, <?php echo $username; ?></span>
                    <?php if ($userRole === 'doctor'): ?>
                        <a href="dashboard.php" class="btn btn-sm btn-login">Dashboard</a>
                    <?php else: ?>
                        <a href="dashboard.php" class="btn btn-sm btn-login">Dashboard</a>
                    <?php endif; ?>
                    <a href="logout.php" class="btn btn-sm btn-signup">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-sm btn-login">Login</a>
                    <a href="register.php?role=doctor" class="btn btn-sm btn-login">Doctor Login</a>
                    <a href="register.php" class="btn btn-sm btn-signup">Sign Up</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="nav-links navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link active" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="doctors.php">Doctors</a></li>
                    <li class="nav-item"><a class="nav-link" href="services.php">Services</a></li>
                    <li class="nav-item"><a class="nav-link" href="appointments.php">Appointments</a></li>
                    <li class="nav-item"><a class="nav-link" href="health_records.php">Health Records</a></li>
                    <li class="nav-item"><a class="nav-link" href="contact.php">Contact</a></li>
                </ul>
                <form class="d-flex" action="search.php" method="GET">
                    <input class="form-control me-2" type="search" name="query" placeholder="Search doctors, services...">
                    <button class="btn btn-outline-success" type="submit"><i class="fas fa-search"></i></button>
                </form>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="hero">
        <div class="container">
            <h1>Your Health, Our Priority</h1>
            <p>Health Matrix provides comprehensive medical database management with seamless appointment scheduling, electronic health records, and telemedicine services.</p>
            <div class="d-flex justify-content-center gap-3">
                <a href="appointments.php" class="cta-btn">Book an Appointment</a>
                <?php if (!$isLoggedIn): ?>
                    <a href="register.php" class="cta-btn" style="background-color: var(--secondary-color);">Create Account</a>
                <?php endif; ?>
            </div>
        </div>
    </section>
    
    <!-- Services Section -->
    <section id="services">
        <div class="container">
            <h2 class="section-title">Our Services</h2>
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h3>Appointment Scheduling</h3>
                        <p>Book appointments with specialists at your convenience with our easy-to-use scheduling system.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-file-medical"></i>
                        </div>
                        <h3>Electronic Health Records</h3>
                        <p>Secure digital storage of your medical history accessible anytime, anywhere.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="service-card">
                        <div class="service-icon">
                            <i class="fas fa-video"></i>
                        </div>
                        <h3>Telemedicine</h3>
                        <p>Virtual consultations with healthcare providers from the comfort of your home.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Doctors Section -->
    <section id="doctors">
        <div class="container">
            <h2 class="section-title">Featured Doctors</h2>
            <div class="row">
                <?php foreach ($featuredDoctors as $doctor): ?>
                <div class="col-md-4">
                    <div class="doctor-card">
                        <div class="doctor-img" style="background-image: url('images/doctor-<?php echo $doctor['doctor_id'] % 3 + 1; ?>.jpg');"></div>
                        <div class="doctor-info">
                            <h4><?php echo htmlspecialchars($doctor['full_name']); ?></h4>
                            <p class="doctor-specialty"><?php echo htmlspecialchars($doctor['specialty']); ?></p>
                            <p><?php echo $doctor['years_experience']; ?>+ years experience</p>
                            <p class="text-success">$<?php echo $doctor['consultation_fee']; ?> consultation fee</p>
                            <a href="doctor_profile.php?id=<?php echo $doctor['doctor_id']; ?>" class="btn btn-sm btn-outline-primary">View Profile</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-4">
                <a href="doctors.php" class="btn btn-primary">View All Doctors</a>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonials">
        <div class="container">
            <h2 class="section-title">Patient Testimonials</h2>
            <div class="row">
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <p class="testimonial-text">Health Matrix has transformed how I manage my family's healthcare. The appointment system is so convenient!</p>
                        <p><strong>- Sarah Johnson</strong></p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <p class="testimonial-text">As a doctor, I appreciate how Health Matrix streamlines patient management and record keeping.</p>
                        <p><strong>- Dr. Michael Chen</strong></p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="testimonial-card">
                        <p class="testimonial-text">The telemedicine feature saved me during lockdown. I could consult my doctor without leaving home.</p>
                        <p><strong>- Robert Williams</strong></p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Call-to-Action Section -->
    <section id="cta">
        <div class="container">
            <h2>Ready to experience better healthcare management?</h2>
            <div class="d-flex justify-content-center gap-3 mt-4">
                <a href="register.php" class="cta-btn">Join Now</a>
                <a href="contact.php" class="cta-btn" style="background-color: white; color: var(--primary-color);">Contact Us</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <div class="footer-section">
                        <h3>About Health Matrix</h3>
                        <p>A comprehensive medical database management system providing seamless healthcare solutions for patients and providers.</p>
                        <div class="footer-social mt-3">
                            <a href="#"><i class="fab fa-facebook-f"></i></a>
                            <a href="#"><i class="fab fa-twitter"></i></a>
                            <a href="#"><i class="fab fa-linkedin-in"></i></a>
                            <a href="#"><i class="fab fa-instagram"></i></a>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 mb-4">
                    <div class="footer-section">
                        <h3>Quick Links</h3>
                        <ul>
                            <li><a href="index.php">Home</a></li>
                            <li><a href="services.php">Services</a></li>
                            <li><a href="doctors.php">Doctors</a></li>
                            <li><a href="appointments.php">Appointments</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-2 mb-4">
                    <div class="footer-section">
                        <h3>Resources</h3>
                        <ul>
                            <li><a href="admin_login.php">Admin</a></li>
                            <li><a href="faq.php">FAQs</a></li>
                            <li><a href="health_tips.php">Health Tips</a></li>
                            <li><a href="disease_library.php">Disease Library</a></li>
                        </ul>
                    </div>
                </div>
                <div class="col-md-4 mb-4">
                    <div class="footer-section">
                        <h3>Contact Us</h3>
                        <p><i class="fas fa-map-marker-alt me-2"></i> 123 Medical Drive, Health City</p>
                        <p><i class="fas fa-phone me-2"></i> (123) 456-7890</p>
                        <p><i class="fas fa-envelope me-2"></i> info@healthmatrix.com</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="container text-center">
                <p>&copy; <?php echo date('Y'); ?> Health Matrix. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Simple animation for elements when they come into view
        document.addEventListener('DOMContentLoaded', function() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate__animated', 'animate__fadeInUp');
                    }
                });
            }, {threshold: 0.1});

            document.querySelectorAll('#services .service-card, #doctors .doctor-card, #testimonials .testimonial-card').forEach(card => {
                observer.observe(card);
            });
        });
    </script>

    
</body>
</html>