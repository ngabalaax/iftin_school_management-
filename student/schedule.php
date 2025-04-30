<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

// Verify student access
if ($session->get('user_role') !== 'student') {
    header('HTTP/1.0 403 Forbidden');
    die('You do not have permission to access this page.');
}

// Get student details
$stmt = $db->prepare("
    SELECT s.*, c.class_name
    FROM students s
    LEFT JOIN classes c ON s.current_class_id = c.class_id
    WHERE s.user_id = ?
");
$stmt->execute([$session->get('user_id')]);
$student = $stmt->fetch();

if (!$student || !$student['current_class_id']) {
    die('Student record not found or not assigned to a class');
}

// Get weekly schedule
$stmt = $db->prepare("
    SELECT cs.id, s.subject_name, s.subject_code, 
           CONCAT(t.first_name, ' ', t.last_name) AS teacher_name,
           cs.schedule_day, cs.start_time, cs.end_time
    FROM class_subjects cs
    JOIN subjects s ON cs.subject_id = s.subject_id
    JOIN teachers te ON cs.teacher_id = te.teacher_id
    JOIN users t ON te.user_id = t.user_id
    WHERE cs.class_id = ?
    ORDER BY 
        FIELD(cs.schedule_day, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'),
        cs.start_time
");
$stmt->execute([$student['current_class_id']]);
$schedule = $stmt->fetchAll();

// Group by day for display
$scheduleByDay = [];
foreach ($schedule as $item) {
    $scheduleByDay[$item['schedule_day']][] = $item;
}

// Days of week in order
$daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Class Schedule | Iftin School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <style>
        .schedule-day {
            margin-bottom: 2rem;
        }
        .day-header {
            background-color: #f8f9fa;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-weight: 600;
        }
        .schedule-card {
            margin-bottom: 1rem;
            border-left: 4px solid #0d6efd;
            transition: transform 0.2s;
        }
        .schedule-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }
        .current-day {
            background-color: #e7f1ff;
        }
        .time-badge {
            font-size: 0.85rem;
            padding: 0.35rem 0.65rem;
        }
    </style>
</head>
<body>
    <?php include './navbar.php'; ?>
    <?php include './sidebar.php'; ?>
    
    <div class="main-content-teacher">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Class Schedule</h2>
                <div>
                    <span class="badge bg-primary"><?= htmlspecialchars($student['class_name']) ?></span>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Weekly Schedule</h5>
                    <p class="card-text">View your class timetable for the current academic year.</p>
                </div>
            </div>

            <?php foreach ($daysOfWeek as $day): 
                $isToday = strtolower(date('l')) === $day;
                ?>
                <div class="schedule-day <?= $isToday ? 'current-day' : '' ?>">
                    <div class="day-header d-flex justify-content-between">
                        <span><?= ucfirst($day) ?></span>
                        <?php if ($isToday): ?>
                            <span class="badge bg-primary">Today</span>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($scheduleByDay[$day])): ?>
                        <div class="alert alert-secondary">No classes scheduled</div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($scheduleByDay[$day] as $class): 
                                $startTime = date("g:i A", strtotime($class['start_time']));
                                $endTime = date("g:i A", strtotime($class['end_time']));
                                ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="card schedule-card">
                                        <div class="card-body">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h5 class="card-title mb-0"><?= htmlspecialchars($class['subject_name']) ?></h5>
                                                <span class="badge bg-light text-dark time-badge">
                                                    <?= $startTime ?> - <?= $endTime ?>
                                                </span>
                                            </div>
                                            <h6 class="card-subtitle mb-2 text-muted">
                                                <?= htmlspecialchars($class['teacher_name']) ?>
                                            </h6>
                                            <p class="card-text text-muted small mb-1">
                                                <i class="fas fa-book-open me-1"></i> <?= htmlspecialchars($class['subject_code']) ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>