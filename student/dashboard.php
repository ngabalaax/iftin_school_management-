<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

// Verify student access
if ($session->get('user_role') !== 'student') {
    header('HTTP/1.0 403 Forbidden');
    die('You do not have permission to access this page.');
}

// Get student details - updated to match your schema
$stmt = $db->prepare("
    SELECT s.*, u.first_name, u.last_name, c.class_name
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN classes c ON s.current_class_id = c.class_id
    WHERE s.user_id = ?
");
$stmt->execute([$session->get('user_id')]);
$student = $stmt->fetch();

if (!$student) {
    die('Student record not found');
}

// Get current class and subjects
$stmt = $db->prepare("
    SELECT cs.id as class_subject_id, s.subject_name, t.first_name as teacher_first_name, 
           t.last_name as teacher_last_name, cs.schedule_day, cs.start_time, cs.end_time
    FROM class_subjects cs
    JOIN subjects s ON cs.subject_id = s.subject_id
    JOIN teachers te ON cs.teacher_id = te.teacher_id
    JOIN users t ON te.user_id = t.user_id
    WHERE cs.class_id = ?
    ORDER BY cs.schedule_day, cs.start_time
");
$stmt->execute([$student['current_class_id']]);
$subjects = $stmt->fetchAll();

// Get today's schedule
$today = strtolower(date('l'));
$stmt = $db->prepare("
    SELECT s.subject_name, cs.start_time, cs.end_time, 
           CONCAT(t.first_name, ' ', t.last_name) as teacher_name
    FROM class_subjects cs
    JOIN subjects s ON cs.subject_id = s.subject_id
    JOIN teachers te ON cs.teacher_id = te.teacher_id
    JOIN users t ON te.user_id = t.user_id
    WHERE cs.class_id = ? AND cs.schedule_day = ?
    ORDER BY cs.start_time
");
$stmt->execute([$student['current_class_id'], $today]);
$todays_schedule = $stmt->fetchAll();

// Get upcoming assignments (next 7 days)
$stmt = $db->prepare("
    SELECT a.assignment_id, a.title, a.due_date, s.subject_name, 
           DATEDIFF(a.due_date, CURDATE()) as days_remaining
    FROM assignments a
    JOIN class_subjects cs ON a.class_subject_id = cs.id
    JOIN subjects s ON cs.subject_id = s.subject_id
    WHERE cs.class_id = ? 
    AND a.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY a.due_date
    LIMIT 5
");
$stmt->execute([$student['current_class_id']]);
$upcoming_assignments = $stmt->fetchAll();

// Get recent grades
$stmt = $db->prepare("
    SELECT g.score, a.max_score, a.title, s.subject_name, a.due_date,
           ROUND((g.score/a.max_score)*100) as percentage
    FROM grades g
    JOIN assignments a ON g.assignment_id = a.assignment_id
    JOIN class_subjects cs ON a.class_subject_id = cs.id
    JOIN subjects s ON cs.subject_id = s.subject_id
    WHERE g.student_id = ?
    ORDER BY a.due_date DESC
    LIMIT 5
");
$stmt->execute([$student['student_id']]);
$recent_grades = $stmt->fetchAll();

// Get attendance summary
$stmt = $db->prepare("
    SELECT 
        COUNT(CASE WHEN status = 'present' THEN 1 END) as present,
        COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent,
        COUNT(CASE WHEN status = 'late' THEN 1 END) as late,
        COUNT(CASE WHEN status = 'excused' THEN 1 END) as excused,
        COUNT(*) as total
    FROM attendance
    WHERE student_id = ? AND date BETWEEN DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND CURDATE()
");
$stmt->execute([$student['student_id']]);
$attendance = $stmt->fetch();
$attendance_percentage = $attendance['total'] > 0 ? round(($attendance['present']/$attendance['total'])*100) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | Iftin School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <style>
        .dashboard-card {
            transition: transform 0.2s;
            height: 100%;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
        }
        .subject-badge {
            font-size: 0.8rem;
            padding: 5px 8px;
        }
        .attendance-present {
            background-color: #28a745;
        }
        .attendance-absent {
            background-color: #dc3545;
        }
        .attendance-late {
            background-color: #ffc107;
            color: #000;
        }
        .attendance-excused {
            background-color: #17a2b8;
        }
        .grade-A {
            color: #28a745;
            font-weight: bold;
        }
        .grade-B {
            color: #5cb85c;
            font-weight: bold;
        }
        .grade-C {
            color: #f0ad4e;
            font-weight: bold;
        }
        .grade-D {
            color: #d9534f;
            font-weight: bold;
        }
        .grade-F {
            color: #dc3545;
            font-weight: bold;
        }
        .main-content-student {
            margin-left: 220px;
            padding: 20px;
        }
    </style>
</head>
<body>
    <?php include './navbar.php'; ?>
    <?php include './sidebar.php'; ?>
    
    <div class="main-content-teacher">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-0">Student Dashboard</h2>
                    <small class="text-muted">Welcome back, <?= htmlspecialchars($student['first_name']) ?></small>
                </div>
                <div class="d-flex align-items-center">
                    <span class="badge bg-primary me-2">
                        <?= htmlspecialchars($student['class_name'] ?? 'No Class') ?>
                    </span>
                    <span class="badge bg-secondary">
                        <?= htmlspecialchars($student['admission_number']) ?>
                    </span>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card dashboard-card text-white bg-primary">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Attendance</h6>
                                    <h3 class="card-text"><?= $attendance_percentage ?>%</h3>
                                </div>
                                <i class="fas fa-calendar-check" style="font-size: 2rem;"></i>
                            </div>
                            <a href="attendance.php" class="stretched-link"></a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card dashboard-card text-white bg-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Today's Classes</h6>
                                    <h3 class="card-text"><?= count($todays_schedule) ?></h3>
                                </div>
                                <i class="fas fa-clock" style="font-size: 2rem;"></i>
                            </div>
                            <a href="#todays-schedule" class="stretched-link"></a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card dashboard-card text-white bg-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Upcoming Assignments</h6>
                                    <h3 class="card-text"><?= count($upcoming_assignments) ?></h3>
                                </div>
                                <i class="fas fa-tasks" style="font-size: 2rem;"></i>
                            </div>
                            <a href="#upcoming-assignments" class="stretched-link"></a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card dashboard-card text-white bg-info">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Average Grade</h6>
                                    <h3 class="card-text">
                                        <?php 
                                            if (!empty($recent_grades)) {
                                                $avg = array_reduce($recent_grades, fn($carry, $grade) => $carry + $grade['percentage'], 0) / count($recent_grades);
                                                echo round($avg) . '%';
                                            } else {
                                                echo 'N/A';
                                            }
                                        ?>
                                    </h3>
                                </div>
                                <i class="fas fa-chart-line" style="font-size: 2rem;"></i>
                            </div>
                            <a href="#recent-grades" class="stretched-link"></a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rest of your dashboard HTML remains the same -->
            <!-- ... [Previous HTML content continues unchanged] ... -->
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Attendance chart
        const ctx = document.getElementById('attendanceChart');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Absent', 'Late', 'Excused'],
                datasets: [{
                    data: [
                        <?= $attendance['present'] ?? 0 ?>,
                        <?= $attendance['absent'] ?? 0 ?>,
                        <?= $attendance['late'] ?? 0 ?>,
                        <?= $attendance['excused'] ?? 0 ?>
                    ],
                    backgroundColor: [
                        '#28a745',
                        '#dc3545',
                        '#ffc107',
                        '#17a2b8'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                cutout: '70%',
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // Highlight current day in schedule
        document.querySelectorAll('.list-group-item').forEach(item => {
            const now = new Date();
            const currentHour = now.getHours();
            const currentMinute = now.getMinutes();
            
            const timeText = item.querySelector('small')?.textContent;
            if (timeText) {
                const [times] = timeText.split(' - ');
                const [time, period] = times.split(' ');
                const [hours, minutes] = time.split(':').map(Number);
                
                let classHour = hours;
                if (period === 'PM' && hours < 12) classHour += 12;
                if (period === 'AM' && hours === 12) classHour = 0;
                
                if (currentHour === classHour || 
                    (currentHour === classHour && currentMinute <= minutes)) {
                    item.classList.add('border-start');
                    item.classList.add('border-primary');
                    item.classList.add('border-3');
                }
            }
        });
    </script>
</body>
</html>

<?php
// Helper function to convert percentage to letter grade
function getLetterGrade($percentage) {
    if ($percentage >= 90) return 'A';
    if ($percentage >= 80) return 'B';
    if ($percentage >= 70) return 'C';
    if ($percentage >= 60) return 'D';
    return 'F';
}
?>