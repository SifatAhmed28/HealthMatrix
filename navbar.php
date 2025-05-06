<?php
// navbar.php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <img src="logo.png" alt="HealthMatrix Logo" height="40">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage === 'index.php') ? 'active' : ''; ?>" href="index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage === 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">Dashboard</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage === 'patient_metrics.php') ? 'active' : ''; ?>" href="patient_metrics.php">Health Metrics</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage === 'appointments.php') ? 'active' : ''; ?>" href="appointments.php">Appointments</a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle me-1"></i><?php echo $_SESSION['full_name'] ?? 'Profile'; ?>
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="patient_profile.php">My Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php">Logout</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Login</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>