<?php
require_once 'config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

if (!isset($pdo)) {
    die("Database connection error. Please check config.php");
}

$adminId = $_SESSION['admin_id'];
$adminName = "Admin";
try {
    $stmt = $pdo->prepare("SELECT full_name FROM Admin WHERE user_id = ?");
    $stmt->execute([$adminId]);
    $adminName = $stmt->fetchColumn() ?: $adminName;
} catch (PDOException $e) {
    error_log("Admin name fetch failed: " . $e->getMessage());
}

try {
    $diseaseCount = $pdo->query("SELECT COUNT(*) FROM Diseases")->fetchColumn();
} catch (PDOException $e) {
    $diseaseCount = 0;
}

function countRows($table) {
    global $pdo;
    try {
        return $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

function countPendingPayments() {
    global $pdo;
    try {
        return $pdo->query("SELECT COUNT(*) FROM Payments WHERE status = 'Pending'")->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Admin Dashboard - HealthMatrix</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', sans-serif; background: #f5f8fa; }

    .header {
      background: #2b7a78;
      color: #fff;
      padding: 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding-left: 250px;
    }

    .header h1 { font-size: 24px; }

    .logout-btn {
      background: #fff;
      color: #2b7a78;
      padding: 8px 15px;
      border-radius: 5px;
      text-decoration: none;
      font-weight: bold;
    }

    .sidebar {
      width: 230px;
      background: #17252a;
      color: #fff;
      position: fixed;
      top: 0;
      bottom: 0;
      padding: 20px 15px;
    }

    .sidebar ul { list-style: none; }
    .sidebar li { margin: 15px 0; }
    .sidebar a {
      color: #fff;
      text-decoration: none;
      display: flex;
      align-items: center;
    }
    .sidebar i { margin-right: 10px; }
    .sidebar .active a { font-weight: bold; color: #3de8c2; }

    .badge {
      background: #ff6b6b;
      color: #fff;
      border-radius: 12px;
      padding: 2px 8px;
      font-size: 0.75rem;
      margin-left: 8px;
    }

    .main-content {
      margin-left: 250px;
      padding: 30px;
    }

    .system-overview h2 {
      font-size: 26px;
      margin-bottom: 25px;
    }

    .overview-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      grid-template-rows: auto auto;
      gap: 25px;
      margin-bottom: 40px;
    }

    .overview-card {
      background: #ffffff;
      border-left: 6px solid #2b7a78;
      padding: 30px;
      font-size: 20px;
      font-weight: bold;
      border-radius: 10px;
      box-shadow: 0 5px 10px rgba(0, 0, 0, 0.08);
      transition: 0.3s ease;
    }

    .overview-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(0, 0, 0, 0.12);
      cursor: pointer;
    }

    .chart-container {
      margin-top: 50px;
      background: #fff;
      padding: 30px;
      border-radius: 10px;
      box-shadow: 0 5px 10px rgba(0, 0, 0, 0.08);
    }

    .welcome-text {
      margin-top: 20px;
      font-size: 22px;
      font-weight: 600;
    }

    .welcome-sub {
      font-size: 16px;
      color: #444;
    }
  </style>
</head>
<body>

  <div class="sidebar">
    <ul>
      <li class="active"><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
      <li><a href="sections/doctors.php"><i class="fas fa-user-md"></i> Doctors</a></li>
      <li><a href="sections/patients.php"><i class="fas fa-user-injured"></i> Patients</a></li>
      <li><a href="sections/appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a></li>
      <li><a href="sections/payments.php"><i class="fas fa-credit-card"></i> Payments</a></li>
      <li><a href="sections/prescriptions.php"><i class="fas fa-pills"></i> Prescriptions</a></li>
      <li><a href="sections/metrics.php"><i class="fas fa-heartbeat"></i> Health Metrics</a></li>
      <li><a href="sections/diseases.php"><i class="fas fa-virus"></i> Diseases <span class="badge"><?= $diseaseCount ?></span></a></li>
    </ul>
  </div>

  <header class="header">
    <h1>👨‍⚕️ HealthMatrix Admin Dashboard</h1>
    <div>
      <span style="margin-right: 15px;">👋 Welcome, <?= htmlspecialchars($adminName) ?></span>
      <a href="logout.php" class="logout-btn">Logout</a>
    </div>
  </header>

  <main class="main-content">
    <section class="system-overview">
      <h2>System Overview</h2>
      <div class="overview-grid">
        <div class="overview-card">👥 Users: <?= countRows('Users') ?></div>
        <div class="overview-card">🧑‍⚕️ Doctors: <?= countRows('DoctorDetails') ?></div>
        <div class="overview-card">🧑‍🦽 Patients: <?= countRows('PatientDetails') ?></div>
        <div class="overview-card">📅 Appointments: <?= countRows('Appointments') ?></div>
        <div class="overview-card">💳 Pending Payments: <?= countPendingPayments() ?></div>
      </div>
    </section>

    <div class="chart-container">
      <h2>📊 Summary Chart</h2>
      <canvas id="adminChart" height="100"></canvas>
    </div>

    <div class="welcome-text">Welcome, Admin</div>
    <p class="welcome-sub">This is the starting point of your admin panel. Use the side navigation to manage your system.</p>
  </main>

  <script>
    const ctx = document.getElementById('adminChart').getContext('2d');
    const adminChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels: ['Users', 'Doctors', 'Patients', 'Appointments', 'Pending Payments'],
        datasets: [{
          label: 'Overview Counts',
          data: [
            <?= countRows('Users') ?>,
            <?= countRows('DoctorDetails') ?>,
            <?= countRows('PatientDetails') ?>,
            <?= countRows('Appointments') ?>,
            <?= countPendingPayments() ?>
          ],
          backgroundColor: '#2b7a78'
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true }
        }
      }
    });
  </script>

</body>
</html>
