<?php
require_once 'config.php';

// Redirect if not logged in or not a patient
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header('Location: login.php');
    exit();
}

$patientId = $_SESSION['user_id'];
$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate and sanitize inputs
        $weight = filter_input(INPUT_POST, 'weight', FILTER_VALIDATE_FLOAT);
        $height = filter_input(INPUT_POST, 'height', FILTER_VALIDATE_FLOAT);
        $bloodPressure = sanitizeInput($_POST['blood_pressure'] ?? null);
        $heartRate = filter_input(INPUT_POST, 'heart_rate', FILTER_VALIDATE_INT);
        $temperature = filter_input(INPUT_POST, 'temperature', FILTER_VALIDATE_FLOAT);
        $notes = sanitizeInput($_POST['notes'] ?? '');
        
        // Validate required fields
        if ($weight === false || $weight <= 0) {
            throw new Exception("Please enter a valid weight");
        }
        if ($height === false || $height <= 0) {
            throw new Exception("Please enter a valid height");
        }

        // Insert into database
        $stmt = $pdo->prepare("INSERT INTO HealthMetrics 
                              (patient_id, weight, height, blood_pressure, heart_rate, temperature, notes) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$patientId, $weight, $height, $bloodPressure, $heartRate, $temperature, $notes]);
        
        $message = "Health metrics recorded successfully!";
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
    }
}

// Fetch all health metrics for this patient
try {
    $stmt = $pdo->prepare("SELECT * FROM HealthMetrics 
                          WHERE patient_id = ? 
                          ORDER BY recorded_date DESC");
    $stmt->execute([$patientId]);
    $metrics = $stmt->fetchAll();
} catch (PDOException $e) {
    $message = "Error loading health metrics: " . $e->getMessage();
    $metrics = [];
}

// Calculate BMI for the most recent record if available
$latestBmi = null;
if (!empty($metrics)) {
    $latest = $metrics[0];
    if ($latest['weight'] && $latest['height']) {
        $latestBmi = $latest['weight'] / (($latest['height']/100) ** 2);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Health Metrics - HealthMatrix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome@6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --primary-color: #28a745;
            --secondary-color: #007bff;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f5f5;
        }
        
        .metrics-container {
            max-width: 1200px;
            margin: 30px auto;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .metrics-header {
            background-color: var(--primary-color);
            color: white;
            padding: 20px;
        }
        
        .card-metric {
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s;
        }
        
        .card-metric:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .metric-value {
            font-size: 1.8rem;
            font-weight: 700;
        }
        
        .metric-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .bmi-indicator {
            height: 10px;
            border-radius: 5px;
            background: linear-gradient(to right, 
                var(--danger-color) 0%, 
                var(--warning-color) 50%, 
                var(--primary-color) 100%);
            position: relative;
            margin-top: 15px;
        }
        
        .bmi-marker {
            position: absolute;
            top: -5px;
            width: 2px;
            height: 20px;
            background-color: var(--dark-color);
        }
        
        .bmi-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .form-section {
            background-color: var(--light-color);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .nav-tabs .nav-link.active {
            font-weight: 600;
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
        }
        
        .metric-table th {
            background-color: var(--light-color);
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="metrics-container">
        <div class="metrics-header">
            <h3><i class="fas fa-heartbeat me-2"></i>Health Metrics</h3>
            <p>Track and monitor your health measurements</p>
        </div>
        
        <div class="p-4">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo strpos($message, 'Error') === false ? 'success' : 'danger'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <ul class="nav nav-tabs mb-4" id="metricsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="record-tab" data-bs-toggle="tab" data-bs-target="#record" type="button">
                        <i class="fas fa-plus-circle me-1"></i> Record Metrics
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="history-tab" data-bs-toggle="tab" data-bs-target="#history" type="button">
                        <i class="fas fa-history me-1"></i> History
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="trends-tab" data-bs-toggle="tab" data-bs-target="#trends" type="button">
                        <i class="fas fa-chart-line me-1"></i> Trends
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="metricsTabsContent">
                <!-- Record Metrics Tab -->
                <div class="tab-pane fade show active" id="record" role="tabpanel">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="form-section">
                                <h4 class="mb-4"><i class="fas fa-edit me-2"></i>Enter New Measurements</h4>
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="weight" class="form-label">Weight (kg)</label>
                                            <input type="number" step="0.1" class="form-control" id="weight" name="weight" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="height" class="form-label">Height (cm)</label>
                                            <input type="number" step="0.1" class="form-control" id="height" name="height" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="blood_pressure" class="form-label">Blood Pressure (mmHg)</label>
                                            <input type="text" class="form-control" id="blood_pressure" name="blood_pressure" placeholder="120/80">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="heart_rate" class="form-label">Heart Rate (bpm)</label>
                                            <input type="number" class="form-control" id="heart_rate" name="heart_rate">
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="temperature" class="form-label">Temperature (°C)</label>
                                            <input type="number" step="0.1" class="form-control" id="temperature" name="temperature">
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="notes" class="form-label">Notes</label>
                                        <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-1"></i> Save Measurements
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <div class="card card-metric mb-4">
                                <div class="card-body text-center">
                                    <h5 class="card-title">BMI Calculator</h5>
                                    <div class="my-3">
                                        <div id="bmiResult" class="metric-value">--</div>
                                        <div class="metric-label">Body Mass Index</div>
                                        <div id="bmiCategory" class="text-muted">Enter your measurements</div>
                                    </div>
                                    <div class="bmi-indicator">
                                        <div id="bmiMarker" class="bmi-marker" style="left: 50%;"></div>
                                    </div>
                                    <div class="bmi-labels">
                                        <span>Underweight (<18.5)</span>
                                        <span>Normal (18.5-24.9)</span>
                                        <span>Overweight (25-29.9)</span>
                                        <span>Obese (30+)</span>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($latestBmi): ?>
                                <div class="card card-metric">
                                    <div class="card-body">
                                        <h5 class="card-title">Latest Reading</h5>
                                        <div class="row text-center">
                                            <div class="col-4 mb-3">
                                                <div class="metric-value"><?php echo $latest['weight']; ?></div>
                                                <div class="metric-label">kg</div>
                                            </div>
                                            <div class="col-4 mb-3">
                                                <div class="metric-value"><?php echo $latest['height']; ?></div>
                                                <div class="metric-label">cm</div>
                                            </div>
                                            <div class="col-4 mb-3">
                                                <div class="metric-value"><?php echo number_format($latestBmi, 1); ?></div>
                                                <div class="metric-label">BMI</div>
                                            </div>
                                        </div>
                                        <div class="text-muted small">
                                            Recorded on <?php echo date('M j, Y', strtotime($latest['recorded_date'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- History Tab -->
                <div class="tab-pane fade" id="history" role="tabpanel">
                    <?php if (empty($metrics)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                            <h5>No health metrics recorded yet</h5>
                            <p>Start by entering your measurements in the "Record Metrics" tab</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table metric-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Weight (kg)</th>
                                        <th>Height (cm)</th>
                                        <th>BMI</th>
                                        <th>Blood Pressure</th>
                                        <th>Heart Rate</th>
                                        <th>Temperature</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($metrics as $metric): 
                                       $bmi = $metric['weight'] && $metric['height'] ? 
                                       number_format($metric['weight'] / (($metric['height']/100) ** 2), 1) : '--';
                                    ?>
                                        <tr>
                                            <td><?php echo date('M j, Y', strtotime($metric['recorded_date'])); ?></td>
                                            <td><?php echo $metric['weight']; ?></td>
                                            <td><?php echo $metric['height']; ?></td>
                                            <td><?php echo $bmi; ?></td>
                                            <td><?php echo $metric['blood_pressure'] ?: '--'; ?></td>
                                            <td><?php echo $metric['heart_rate'] ?: '--'; ?></td>
                                            <td><?php echo $metric['temperature'] ? $metric['temperature'].'°C' : '--'; ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary view-notes" 
                                                        data-notes="<?php echo htmlspecialchars($metric['notes']); ?>">
                                                    <i class="fas fa-eye"></i> Notes
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Trends Tab -->
                <div class="tab-pane fade" id="trends" role="tabpanel">
                    <?php if (count($metrics) < 2): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                            <h5>Not enough data for trends</h5>
                            <p>Record at least 2 measurements to see trends</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">Weight Trend</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="weightChart" height="250"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">BMI Trend</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="bmiTrendChart" height="250"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Blood Pressure Trend</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="bpChart" height="250"></canvas>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Notes Modal -->
    <div class="modal fade" id="notesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Measurement Notes</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="notesModalBody">
                    Loading notes...
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // BMI Calculator
        function calculateBMI() {
            const weight = parseFloat(document.getElementById('weight').value);
            const height = parseFloat(document.getElementById('height').value);
            
            if (weight && height) {
                const bmi = weight / ((height/100) ** 2);
                document.getElementById('bmiResult').textContent = bmi.toFixed(1);
                
                // Position marker on BMI scale (0-40 range)
                const markerPosition = Math.min(Math.max(bmi, 15), 40); // Clamp between 15 and 40
                const percentage = ((markerPosition - 15) / (40 - 15)) * 100;
                document.getElementById('bmiMarker').style.left = `${percentage}%`;
                
                // Set category
                const bmiCategory = document.getElementById('bmiCategory');
                if (bmi < 18.5) {
                    bmiCategory.textContent = "Underweight";
                    bmiCategory.className = "text-danger";
                } else if (bmi < 25) {
                    bmiCategory.textContent = "Normal weight";
                    bmiCategory.className = "text-success";
                } else if (bmi < 30) {
                    bmiCategory.textContent = "Overweight";
                    bmiCategory.className = "text-warning";
                } else {
                    bmiCategory.textContent = "Obese";
                    bmiCategory.className = "text-danger";
                }
            }
        }
        
        // Attach BMI calculation to input events
        document.getElementById('weight').addEventListener('input', calculateBMI);
        document.getElementById('height').addEventListener('input', calculateBMI);
        
        // Notes modal
        const notesModal = new bootstrap.Modal(document.getElementById('notesModal'));
        document.querySelectorAll('.view-notes').forEach(button => {
            button.addEventListener('click', function() {
                const notes = this.getAttribute('data-notes') || 'No notes recorded';
                document.getElementById('notesModalBody').textContent = notes;
                notesModal.show();
            });
        });
        
        // Charts for trends tab
        <?php if (count($metrics) >= 2): ?>
            // Prepare data for charts
            const dates = [
                <?php foreach ($metrics as $metric): 
                    echo "'" . date('M j', strtotime($metric['recorded_date'])) . "',";
                endforeach; ?>
            ].reverse();
            
            const weights = [
                <?php foreach (array_reverse($metrics) as $metric): 
                    echo $metric['weight'] . ",";
                endforeach; ?>
            ];
            
            const bmis = [
                <?php foreach (array_reverse($metrics) as $metric): 
                    if ($metric['weight'] && $metric['height']) {
                        echo number_format($metric['weight'] / (($metric['height']/100) ** 2), 1) . ",";
                    } else {
                        echo "null,";
                    }
                endforeach; ?>
            ];
            
            // Extract systolic and diastolic BP if available
            const systolicBP = [];
            const diastolicBP = [];
            <?php foreach (array_reverse($metrics) as $metric): 
                if ($metric['blood_pressure'] && strpos($metric['blood_pressure'], '/') !== false): 
                    $parts = explode('/', $metric['blood_pressure']); ?>
                    systolicBP.push(<?php echo (int)$parts[0]; ?>);
                    diastolicBP.push(<?php echo (int)$parts[1]; ?>);
                <?php else: ?>
                    systolicBP.push(null);
                    diastolicBP.push(null);
                <?php endif;
            endforeach; ?>
            
            // Weight Chart
            new Chart(
                document.getElementById('weightChart'),
                {
                    type: 'line',
                    data: {
                        labels: dates,
                        datasets: [{
                            label: 'Weight (kg)',
                            data: weights,
                            borderColor: '#28a745',
                            backgroundColor: 'rgba(40, 167, 69, 0.1)',
                            tension: 0.3,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'top',
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: false
                            }
                        }
                    }
                }
            );
            
            // BMI Trend Chart
            new Chart(
                document.getElementById('bmiTrendChart'),
                {
                    type: 'line',
                    data: {
                        labels: dates,
                        datasets: [{
                            label: 'BMI',
                            data: bmis,
                            borderColor: '#007bff',
                            backgroundColor: 'rgba(0, 123, 255, 0.1)',
                            tension: 0.3,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'top',
                            },
                            annotation: {
                                annotations: {
                                    line1: {
                                        type: 'line',
                                        yMin: 18.5,
                                        yMax: 18.5,
                                        borderColor: 'rgb(255, 99, 132)',
                                        borderWidth: 2,
                                        borderDash: [6, 6],
                                        label: {
                                            content: 'Underweight',
                                            enabled: true,
                                            position: 'left'
                                        }
                                    },
                                    line2: {
                                        type: 'line',
                                        yMin: 25,
                                        yMax: 25,
                                        borderColor: 'rgb(255, 159, 64)',
                                        borderWidth: 2,
                                        borderDash: [6, 6],
                                        label: {
                                            content: 'Overweight',
                                            enabled: true,
                                            position: 'left'
                                        }
                                    },
                                    line3: {
                                        type: 'line',
                                        yMin: 30,
                                        yMax: 30,
                                        borderColor: 'rgb(255, 99, 132)',
                                        borderWidth: 2,
                                        borderDash: [6, 6],
                                        label: {
                                            content: 'Obese',
                                            enabled: true,
                                            position: 'left'
                                        }
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: false
                            }
                        }
                    }
                }
            );
            
            // Blood Pressure Chart
            new Chart(
                document.getElementById('bpChart'),
                {
                    type: 'line',
                    data: {
                        labels: dates,
                        datasets: [
                            {
                                label: 'Systolic (mmHg)',
                                data: systolicBP,
                                borderColor: '#dc3545',
                                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                                tension: 0.3,
                                fill: true
                            },
                            {
                                label: 'Diastolic (mmHg)',
                                data: diastolicBP,
                                borderColor: '#007bff',
                                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                                tension: 0.3,
                                fill: true
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {
                                position: 'top',
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: false
                            }
                        }
                    }
                }
            );
        <?php endif; ?>
    </script>
</body>
</html>