<?php
// footer.php
$currentYear = date('Y');
?>
<!-- Footer -->
<footer class="bg-black text-white pt-5 pb-4">
    <div class="container">
        <div class="row g-4">

            <!-- HealthMatrix Info -->
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="footer-section">
                    <h5 class="text-uppercase border-bottom border-secondary pb-2 mb-3">HealthMatrix</h5>
                    <p class="text-white">
                        Advanced Healthcare Solutions<br>
                        Established 2020
                    </p>
                    <div class="social-icons">
                        <a href="#" class="text-white me-3" aria-label="Facebook">
                            <i class="fab fa-facebook fa-lg"></i>
                        </a>
                        <a href="#" class="text-white me-3" aria-label="Twitter">
                            <i class="fab fa-twitter fa-lg"></i>
                        </a>
                        <a href="#" class="text-white me-3" aria-label="LinkedIn">
                            <i class="fab fa-linkedin fa-lg"></i>
                        </a>
                        <a href="#" class="text-white" aria-label="Instagram">
                            <i class="fab fa-instagram fa-lg"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Quick Navigation -->
            <div class="col-lg-2 col-md-6 mb-4">
                <div class="footer-section">
                    <h5 class="text-uppercase border-bottom border-secondary pb-2 mb-3">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="index.php" class="text-white text-decoration-none">Home</a></li>
                        <li class="mb-2"><a href="appointments.php" class="text-white text-decoration-none">Appointments</a></li>
                        <li class="mb-2"><a href="patient_metrics.php" class="text-white text-decoration-none">Health Metrics</a></li>
                        <li class="mb-2"><a href="patient_profile.php" class="text-white text-decoration-none">Profile</a></li>
                        <li><a href="contact.php" class="text-white text-decoration-none">Contact</a></li>
                    </ul>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="footer-section">
                    <h5 class="text-uppercase border-bottom border-secondary pb-2 mb-3">Contact</h5>
                    <ul class="list-unstyled text-white">
                        <li class="mb-3">
                            <i class="fas fa-map-marker-alt me-2"></i>
                            123 Health Street<br>
                            Medical City, MC 12345
                        </li>
                        <li class="mb-3">
                            <i class="fas fa-phone me-2"></i>
                            +1 (234) 567-8900
                        </li>
                        <li>
                            <i class="fas fa-envelope me-2"></i>
                            support@healthmatrix.com
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Legal & Support -->
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="footer-section">
                    <h5 class="text-uppercase border-bottom border-secondary pb-2 mb-3">Legal</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="privacy.php" class="text-white text-decoration-none">Privacy Policy</a></li>
                        <li class="mb-2"><a href="terms.php" class="text-white text-decoration-none">Terms of Service</a></li>
                        <li class="mb-2"><a href="security.php" class="text-white text-decoration-none">Security</a></li>
                        <li><a href="emergency.php" class="text-danger text-decoration-none">Emergency Services</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Copyright -->
        <div class="border-top border-secondary pt-4 mt-3">
            <div class="row">
                <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                    <p class="small text-white mb-0">
                        &copy; <?php echo $currentYear; ?> HealthMatrix. All Rights Reserved.
                    </p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="small text-white mb-0">
                        <i class="fas fa-exclamation-circle text-danger"></i>
                        For medical emergencies, call 911 immediately
                    </p>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
