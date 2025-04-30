<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

// Manual role check if requireRole() isn't available
if (!$session->isLoggedIn()) {
    $redirect = $_SERVER['REQUEST_URI'];
    header("Location: /auth/login.php?redirect=" . urlencode($redirect));
    exit();
}

if ($session->get('user_role') !== 'teacher') {
    header('HTTP/1.0 403 Forbidden');
    die('You do not have permission to access this page.');
}

// Get teacher details
$stmt = $db->prepare("
    SELECT teacher_id FROM teachers WHERE user_id = ?
");
if ($stmt && $stmt->execute([$session->get('user_id')])) {
    $teacherId = $stmt->fetchColumn();
} else {
    die('Error fetching teacher details: ' . implode(' ', $db->errorInfo()));
}

// Get today's classes
$todaysClassesStmt = $db->prepare("
    SELECT c.class_id, c.class_name, cs.schedule_day, cs.start_time, cs.end_time, s.subject_name
    FROM class_subjects cs
    JOIN classes c ON cs.class_id = c.class_id
    JOIN subjects s ON cs.subject_id = s.subject_id
    WHERE cs.teacher_id = ? 
    AND cs.schedule_day = ?
    ORDER BY cs.start_time
");

$todaysClassesStmt->execute([$teacherId, strtolower(date('l'))]);
$todaysClasses = $todaysClassesStmt->fetchAll();


// Get upcoming assignments to grade
$assignmentsToGradeStmt = $db->prepare("
    SELECT a.assignment_id, a.title, a.due_date, c.class_name, s.subject_name,
           COUNT(g.grade_id) as graded_count,
           COUNT(e.enrollment_id) as total_students
    FROM assignments a
    JOIN class_subjects cs ON a.class_subject_id = cs.id
    JOIN classes c ON cs.class_id = c.class_id
    JOIN subjects s ON cs.subject_id = s.subject_id
    JOIN enrollments e ON c.class_id = e.class_id
    LEFT JOIN grades g ON a.assignment_id = g.assignment_id AND g.student_id = e.student_id
    WHERE cs.teacher_id = ?
    AND a.due_date <= CURDATE()
    GROUP BY a.assignment_id
    HAVING graded_count < total_students
    ORDER BY a.due_date
    LIMIT 5
");

if ($assignmentsToGradeStmt && $assignmentsToGradeStmt->execute([$teacherId])) {
    $assignmentsToGrade = $assignmentsToGradeStmt->fetchAll();
} else {
    $assignmentsToGrade = [];
    error_log('Error fetching assignments to grade: ' . implode(' ', $db->errorInfo()));
}

// Set today's date before it's used
$today = date('Y-m-d');

// Fetch total students (example)
$stmt = $db->prepare("SELECT COUNT(*) FROM students");
$stmt->execute();
$totalStudents = $stmt->fetchColumn();

// Now this block can safely use $today
$recentAttendanceStmt = $db->prepare("
    SELECT a.date, c.class_name, 
           COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present,
           COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent,
           COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late
    FROM attendance a
    JOIN classes c ON a.class_id = c.class_id
    JOIN class_subjects cs ON a.class_id = cs.class_id AND a.date = ?
    WHERE cs.teacher_id = ?
    GROUP BY a.date, c.class_name
    ORDER BY a.date DESC
    LIMIT 3
");
$recentAttendanceStmt->execute([$today, $teacherId]);
// Get recent attendance records
$recentAttendanceStmt = $db->prepare("
    SELECT a.date, c.class_name, 
           COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present,
           COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent,
           COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late
    FROM attendance a
    JOIN classes c ON a.class_id = c.class_id
    JOIN class_subjects cs ON a.class_id = cs.class_id AND a.date = ?
    WHERE cs.teacher_id = ?
    GROUP BY a.date, c.class_name
    ORDER BY a.date DESC
    LIMIT 3
");
if ($recentAttendanceStmt && $recentAttendanceStmt->execute([$today, $teacherId])) {
    $recentAttendance = $recentAttendanceStmt->fetchAll();
} else {
    $recentAttendance = [];
    error_log('Error fetching recent attendance: ' . implode(' ', $db->errorInfo()));
}

// Get total students across all classes
$totalStudentsStmt = $db->prepare("
    SELECT COUNT(DISTINCT e.student_id)
    FROM class_subjects cs
    JOIN enrollments e ON cs.class_id = e.class_id
    WHERE cs.teacher_id = ?
");
$totalStudents = 0;
if ($totalStudentsStmt && $totalStudentsStmt->execute([$teacherId])) {
    $totalStudents = $totalStudentsStmt->fetchColumn();
} else {
    error_log('Error fetching total students: ' . implode(' ', $db->errorInfo()));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard | Iftin School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.css" rel="stylesheet">
    <link href="../../assets/css/sidebar.css" rel="stylesheet">
    <style>
        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, .1);
            transition: all 0.3s ease;
        }

        .badge-present {
            background-color: #28a745;
        }

        .badge-absent {
            background-color: #dc3545;
        }

        .badge-late {
            background-color: #ffc107;
            color: #000;
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>
    <?php include 'sidebar.php'; ?>
    <div class="main-content-teacher">
        <div class="container-fluid">

            <div class="row">
                <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                    <div
                        class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2">Teacher Dashboard</h1>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <div class="btn-group me-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary">Export</button>
                            </div>
                        </div>
                    </div>

                    <!-- Summary Cards -->
                    <div class="row mb-4">
                        <div class="col-md-4 mb-3">
                            <div class="card text-white bg-primary card-hover">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="card-title">Total Students</h5>
                                            <h2 class="card-text"><?= $totalStudents ?></h2>
                                        </div>
                                        <i class="bi bi-people-fill" style="font-size: 2rem;"></i>
                                    </div>
                                    <a href="classes/view.php" class="text-white stretched-link"></a>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <div class="card text-white bg-success card-hover">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="card-title">Today's Classes</h5>
                                            <h2 class="card-text"><?= count($todaysClasses) ?></h2>
                                        </div>
                                        <i class="bi bi-calendar-check" style="font-size: 2rem;"></i>
                                    </div>
                                    <a href="#todays-classes" class="text-white stretched-link"></a>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4 mb-3">
                            <div class="card text-white bg-warning card-hover">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="card-title">Assignments to Grade</h5>
                                            <h2 class="card-text"><?= count($assignmentsToGrade) ?></h2>
                                        </div>
                                        <i class="bi bi-journal-check" style="font-size: 2rem;"></i>
                                    </div>
                                    <a href="grades/manage.php" class="text-white stretched-link"></a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Today's Classes -->
                    <div class="card mb-4" id="todays-classes">
                        <div class="card-header">
                            <h5>Today's Classes</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($todaysClasses)): ?>
                                <div class="alert alert-info">No classes scheduled for today.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Time</th>
                                                <th>Class</th>
                                                <th>Subject</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($todaysClasses as $class): ?>
                                                <tr>
                                                    <td>
                                                        <?= date('g:i A', strtotime($class['start_time'])) ?> -
                                                        <?= date('g:i A', strtotime($class['end_time'])) ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($class['class_name']) ?></td>
                                                    <td><?= htmlspecialchars($class['subject_name']) ?></td>
                                                    <td>
                                                        <a href="attendance/take.php?class_id=<?= $class['class_id'] ?>"
                                                            class="btn btn-sm btn-outline-primary">
                                                            <i class="bi bi-clipboard-check"></i> Take Attendance
                                                        </a>
                                                        <a href="classes/view.php?class_id=<?= $class['class_id'] ?>"
                                                            class="btn btn-sm btn-outline-secondary">
                                                            <i class="bi bi-people"></i> View Class
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Attendance -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5>Recent Attendance</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($recentAttendance)): ?>
                                        <div class="alert alert-info">No recent attendance records found.</div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Class</th>
                                                        <th>Present</th>
                                                        <th>Absent</th>
                                                        <th>Late</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($recentAttendance as $attendance): ?>
                                                        <tr>
                                                            <td><?= date('M j', strtotime($attendance['date'])) ?></td>
                                                            <td><?= htmlspecialchars($attendance['class_name']) ?></td>
                                                            <td><span
                                                                    class="badge badge-present"><?= $attendance['present'] ?></span>
                                                            </td>
                                                            <td><span
                                                                    class="badge badge-absent"><?= $attendance['absent'] ?></span>
                                                            </td>
                                                            <td><span class="badge badge-late"><?= $attendance['late'] ?></span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="text-end">
                                            <a href="attendance/view.php" class="btn btn-sm btn-outline-primary">
                                                View All Attendance
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header">
                                    <h5>Assignments to Grade</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($assignmentsToGrade)): ?>
                                        <div class="alert alert-success">All assignments are graded!</div>
                                    <?php else: ?>
                                        <div class="list-group">
                                            <?php foreach ($assignmentsToGrade as $assignment): ?>
                                                <a href="grades/manage.php?assignment_id=<?= $assignment['assignment_id'] ?>"
                                                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                                    <div>
                                                        <h6 class="mb-1"><?= htmlspecialchars($assignment['title']) ?></h6>
                                                        <small class="text-muted">
                                                            <?= htmlspecialchars($assignment['class_name']) ?> -
                                                            <?= htmlspecialchars($assignment['subject_name']) ?>
                                                        </small>
                                                    </div>
                                                    <span class="badge bg-primary rounded-pill">
                                                        <?= $assignment['total_students'] - $assignment['graded_count'] ?> left
                                                    </span>
                                                </a>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="text-end mt-3">
                                            <a href="grades/assignments.php" class="btn btn-sm btn-outline-secondary">
                                                Manage Assignments
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Apply badge classes
        document.querySelectorAll('.badge-present').forEach(el => {
            el.classList.add('bg-success');
        });
        document.querySelectorAll('.badge-absent').forEach(el => {
            el.classList.add('bg-danger');
        });
        document.querySelectorAll('.badge-late').forEach(el => {
            el.classList.add('bg-warning', 'text-dark');
        });
    </script>
</body>

</html>