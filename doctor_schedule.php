<?php
require_once 'config.php';

// Verify doctor authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header('Location: login.php');
    exit();
}

$doctorId = $_SESSION['user_id'];
$message = '';

// Days of the week for reference
$daysOfWeek = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

try {
    // Fetch existing schedule
    $stmt = $pdo->prepare("SELECT 
        day_of_week,  -- First column for proper grouping
        schedule_id,
        start_time,
        end_time,
        is_available
        FROM DoctorSchedules 
        WHERE doctor_id = ?
        ORDER BY FIELD(day_of_week, 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun')");
    
    $stmt->execute([$doctorId]);
    $existingSchedules = $stmt->fetchAll(PDO::FETCH_GROUP);

} catch (PDOException $e) {
    $message = "Error loading schedule: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Process availability updates
        if (isset($_POST['schedule'])) {
            foreach ($_POST['schedule'] as $day => $data) {
                // Validate times
                if (!empty($data['start_time']) && !empty($data['end_time']) &&
                    strtotime($data['start_time']) >= strtotime($data['end_time'])) {
                    throw new Exception("Invalid time range for $day");
                }

                $stmt = $pdo->prepare("INSERT INTO DoctorSchedules 
                                      (doctor_id, day_of_week, start_time, end_time, is_available)
                                      VALUES (?, ?, ?, ?, ?)
                                      ON DUPLICATE KEY UPDATE
                                      start_time = VALUES(start_time),
                                      end_time = VALUES(end_time),
                                      is_available = VALUES(is_available)");
                
                $isAvailable = isset($data['is_available']) ? 1 : 0;
                $startTime = !empty($data['start_time']) ? $data['start_time'] : null;
                $endTime = !empty($data['end_time']) ? $data['end_time'] : null;
                
                $stmt->execute([
                    $doctorId,
                    $day,
                    $startTime,
                    $endTime,
                    $isAvailable
                ]);
            }
        }

        // Process deletions
        if (isset($_POST['delete_schedules'])) {
            $deleteStmt = $pdo->prepare("DELETE FROM DoctorSchedules 
                                        WHERE schedule_id = ? AND doctor_id = ?");
            
            foreach ($_POST['delete_schedules'] as $scheduleId) {
                $deleteStmt->execute([$scheduleId, $doctorId]);
            }
        }

        $pdo->commit();
        $message = "Schedule updated successfully!";
        header("Refresh:2");
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Schedule - HealthMatrix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .schedule-container {
            max-width: 800px;
            margin: 30px auto;
            background: #fff;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
        }
        .schedule-header {
            background: #007bff;
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
        }
        .day-schedule {
            border-bottom: 1px solid #dee2e6;
            padding: 15px;
        }
        .availability-check {
            margin-right: 15px;
        }
        .time-inputs {
            flex-grow: 1;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="schedule-container">
        <div class="schedule-header text-center">
            <h3>Manage Your Schedule</h3>
            <p>Set your weekly availability</p>
        </div>
        
        <div class="p-4">
            <?php if ($message): ?>
                <div class="alert alert-<?= strpos($message, 'Error') === false ? 'success' : 'danger' ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <!-- Schedule Table -->
                <div class="mb-4">
                    <?php foreach ($daysOfWeek as $day): 
                        $schedule = $existingSchedules[$day][0] ?? null;
                    ?>
                        <div class="day-schedule d-flex align-items-center">
                            <div class="availability-check">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" 
                                           name="schedule[<?= $day ?>][is_available]" 
                                           id="available_<?= $day ?>" 
                                           <?= ($schedule['is_available'] ?? false) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="available_<?= $day ?>">
                                        <strong><?= $day ?></strong>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="time-inputs d-flex gap-2">
                                <input type="time" class="form-control" 
                                       name="schedule[<?= $day ?>][start_time]"
                                       value="<?= $schedule['start_time'] ?? '' ?>">
                                <span class="align-self-center">to</span>
                                <input type="time" class="form-control" 
                                       name="schedule[<?= $day ?>][end_time]"
                                       value="<?= $schedule['end_time'] ?? '' ?>">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Existing Entries Management -->
                <?php if (!empty($existingSchedules)): ?>
    <div class="border-top pt-4 mt-4">
        <h5>Current Schedule Entries</h5>
        <?php foreach ($existingSchedules as $day => $entries): ?>
            <?php foreach ($entries as $entry): ?>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" 
                           name="delete_schedules[]" 
                           value="<?= htmlspecialchars($entry['schedule_id']) ?>" 
                           id="delete_<?= htmlspecialchars($entry['schedule_id']) ?>">
                    <label class="form-check-label" for="delete_<?= htmlspecialchars($entry['schedule_id']) ?>">
                        <strong><?= htmlspecialchars($day) ?>:</strong>
                        <?= $entry['is_available'] ? 'Available' : 'Unavailable' ?>
                        <?php if (!empty($entry['start_time'])): ?>
                            (<?= date('g:i A', strtotime($entry['start_time'])) ?> - 
                             <?= date('g:i A', strtotime($entry['end_time'])) ?>)
                        <?php endif; ?>
                    </label>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
        <button type="submit" name="delete" class="btn btn-danger btn-sm mt-2">
            <i class="fas fa-trash me-1"></i>Delete Selected
        </button>
    </div>
<?php endif; ?>
                <!-- Submit Button -->
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Time input validation
        document.querySelectorAll('input[type="time"]').forEach(input => {
            input.addEventListener('change', function() {
                const day = this.name.match(/\[(.*?)\]/)[1];
                const start = document.querySelector(`input[name="schedule[${day}][start_time]"]`);
                const end = document.querySelector(`input[name="schedule[${day}][end_time]"]`);
                
                if (start.value && end.value && start.value >= end.value) {
                    alert('End time must be after start time');
                    this.value = '';
                }
            });
        });
    </script>
</body>
</html>