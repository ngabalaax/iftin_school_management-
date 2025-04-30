<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

if ($session->get('user_role') !== 'parent') {
    header('HTTP/1.0 403 Forbidden');
    die('Access denied');
}

// Verify parent has access to this child
$stmt = $db->prepare("
    SELECT s.student_id, u.first_name, u.last_name, c.class_name, c.class_id
    FROM parent_student ps
    JOIN students s ON ps.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN classes c ON s.current_class_id = c.class_id
    WHERE ps.parent_id = (SELECT parent_id FROM parents WHERE user_id = ?)
    AND s.student_id = ?
");
$stmt->execute([$session->get('user_id'), $_GET['child_id']]);
$child = $stmt->fetch();

if (!$child || !$child['class_id']) {
    die('Child not found, not assigned to a class, or access denied');
}

// Get weekly schedule grouped by day
$stmt = $db->prepare("
    SELECT cs.schedule_day, 
           s.subject_name, s.subject_code,
           CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
           cs.start_time, cs.end_time
    FROM class_subjects cs
    JOIN subjects s ON cs.subject_id = s.subject_id
    JOIN teachers t ON cs.teacher_id = t.teacher_id
    JOIN users u ON t.user_id = u.user_id
    WHERE cs.class_id = ?
    ORDER BY 
        FIELD(cs.schedule_day, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'),
        cs.start_time
");
$stmt->execute([$child['class_id']]);
$schedule = $stmt->fetchAll();

// Group by day
$scheduleByDay = [];
foreach ($schedule as $class) {
    $scheduleByDay[$class['schedule_day']][] = $class;
}

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
    <style>
        .day-header {
            background-color: #f8f9fa;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        .class-card {
            border-left: 4px solid #0d6efd;
            margin-bottom: 1rem;
            transition: transform 0.2s;
        }
        .class-card:hover {
            transform: translateY(-3px);
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
    <div class="main-content-parent">
    <div class="container-fluid">
        <div class="row">            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h2>Class Schedule</h2>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <span class="me-2">Viewing:</span>
                        <strong><?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?></strong>
                        <span class="badge bg-primary ms-2"><?= htmlspecialchars($child['class_name']) ?></span>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Weekly Schedule</h5>
                        <p class="card-text">View your child's class timetable for the current academic year.</p>
                    </div>
                </div>

                <?php foreach ($daysOfWeek as $day): 
                    $isToday = strtolower(date('l')) === $day;
                ?>
                    <div class="mb-4 <?= $isToday ? 'current-day p-3 rounded' : '' ?>">
                        <div class="day-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><?= ucfirst($day) ?></h5>
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
                                        <div class="card class-card h-100">
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
            </main>
        </div>
    </div>
</div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>