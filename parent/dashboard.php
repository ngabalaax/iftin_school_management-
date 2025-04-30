<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

// Verify parent access
if ($session->get('user_role') !== 'parent') {
    header('HTTP/1.0 403 Forbidden');
    die('You do not have permission to access this page.');
}

// Get parent details
$stmt = $db->prepare("
    SELECT p.*, u.first_name, u.last_name, u.email, u.phone
    FROM parents p
    JOIN users u ON p.user_id = u.user_id
    WHERE p.user_id = ?
");
$stmt->execute([$session->get('user_id')]);
$parent = $stmt->fetch();

if (!$parent) {
    die('Parent record not found');
}

// Get all children of this parent
$stmt = $db->prepare("
    SELECT s.student_id, s.admission_number, s.date_of_birth, s.gender,
           u.first_name, u.last_name, c.class_name, ay.year_name,
           ps.relationship
    FROM parent_student ps
    JOIN students s ON ps.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN classes c ON s.current_class_id = c.class_id
    LEFT JOIN academic_years ay ON c.academic_year_id = ay.year_id
    WHERE ps.parent_id = ?
    ORDER BY u.first_name
");
$stmt->execute([$parent['parent_id']]);
$children = $stmt->fetchAll();

// If no children found
if (empty($children)) {
    die('No children records found for this parent');
}

// Get selected child (default to first child)
$selectedChildId = $_GET['child_id'] ?? $children[0]['student_id'];
$selectedChild = null;

foreach ($children as $child) {
    if ($child['student_id'] == $selectedChildId) {
        $selectedChild = $child;
        break;
    }
}

if (!$selectedChild) {
    die('Selected child not found in your records');
}

// Get child's attendance summary
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
$stmt->execute([$selectedChildId]);
$attendance = $stmt->fetch();
$attendancePercentage = $attendance['total'] > 0 ? round(($attendance['present'] / $attendance['total']) * 100) : 0;

// Get child's recent grades
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
$stmt->execute([$selectedChildId]);
$recentGrades = $stmt->fetchAll();

// Get upcoming assignments
$stmt = $db->prepare("
    SELECT a.title, a.due_date, s.subject_name, 
           DATEDIFF(a.due_date, CURDATE()) as days_remaining
    FROM assignments a
    JOIN class_subjects cs ON a.class_subject_id = cs.id
    JOIN subjects s ON cs.subject_id = s.subject_id
    WHERE cs.class_id = (
        SELECT current_class_id FROM students WHERE student_id = ?
    )
    AND a.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY a.due_date
    LIMIT 5
");
$stmt->execute([$selectedChildId]);
$upcomingAssignments = $stmt->fetchAll();

// Get announcements for parents
$stmt = $db->prepare("
    SELECT title, content, start_date, end_date
    FROM announcements
    WHERE JSON_CONTAINS(target_roles, '\"parent\"') 
    AND start_date <= CURDATE() AND end_date >= CURDATE()
    ORDER BY start_date DESC
    LIMIT 3
");
$stmt->execute();
$announcements = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard | Iftin School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .dashboard-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
            height: 100%;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
        }

        .child-selector {
            cursor: pointer;
            transition: all 0.2s;
        }

        .child-selector:hover {
            background-color: #f1f1f1;
        }

        .child-selector.active {
            background-color: #e2e6ea;
            border-left: 4px solid #0d6efd;
        }

        .attendance-present {
            background-color: #28a745;
        }

        .attendance-absent {
            background-color: #dc3545;
        }

        .attendance-late {
            background-color: #ffc107;
        }

        .attendance-excused {
            background-color: #17a2b8;
        }

        .announcement-card {
            border-left: 4px solid #0d6efd;
        }
    </style>
</head>

<body>
    <?php include './navbar.php'; ?>
    <?php include './sidebar.php'; ?>
    <div class="main-content-teacher">
        <div class="container-fluid">
            

                <!-- Main content -->
                <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                    <div
                        class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h2>Parent Dashboard</h2>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <span class="me-2">Viewing:</span>
                            <strong><?= htmlspecialchars($selectedChild['first_name'] . ' ' . $selectedChild['last_name']) ?></strong>
                            <span
                                class="badge bg-primary ms-2"><?= htmlspecialchars($selectedChild['class_name'] ?? 'No Class') ?></span>
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
                                            <h3 class="card-text"><?= $attendancePercentage ?>%</h3>
                                        </div>
                                        <i class="fas fa-calendar-check" style="font-size: 2rem;"></i>
                                    </div>
                                    <a href="parent_attendance.php?child_id=<?= $selectedChildId ?>"
                                        class="stretched-link"></a>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <div class="card dashboard-card text-white bg-success">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="card-title">Upcoming Assignments</h6>
                                            <h3 class="card-text"><?= count($upcomingAssignments) ?></h3>
                                        </div>
                                        <i class="fas fa-tasks" style="font-size: 2rem;"></i>
                                    </div>
                                    <a href="parent_assignments.php?child_id=<?= $selectedChildId ?>"
                                        class="stretched-link"></a>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <div class="card dashboard-card text-white bg-warning">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="card-title">Average Grade</h6>
                                            <h3 class="card-text">
                                                <?php
                                                if (!empty($recentGrades)) {
                                                    $avg = array_reduce($recentGrades, fn($carry, $grade) => $carry + $grade['percentage'], 0) / count($recentGrades);
                                                    echo round($avg) . '%';
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </h3>
                                        </div>
                                        <i class="fas fa-chart-line" style="font-size: 2rem;"></i>
                                    </div>
                                    <a href="parent_grades.php?child_id=<?= $selectedChildId ?>"
                                        class="stretched-link"></a>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mb-3">
                            <div class="card dashboard-card text-white bg-info">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="card-title">Relationship</h6>
                                            <h3 class="card-text"><?= ucfirst($selectedChild['relationship']) ?></h3>
                                        </div>
                                        <i class="fas fa-user-tag" style="font-size: 2rem;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Recent Grades -->
                        <div class="col-md-6 mb-4">
                            <div class="card dashboard-card h-100">
                                <div class="card-header">
                                    <h5 class="mb-0">Recent Grades</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($recentGrades)): ?>
                                        <div class="alert alert-info">No grades recorded yet</div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Subject</th>
                                                        <th>Assignment</th>
                                                        <th>Score</th>
                                                        <th>Date</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($recentGrades as $grade): ?>
                                                        <tr>
                                                            <td><?= htmlspecialchars($grade['subject_name']) ?></td>
                                                            <td><?= htmlspecialchars($grade['title']) ?></td>
                                                            <td>
                                                                <?= $grade['score'] ?>/<?= $grade['max_score'] ?>
                                                                (<?= $grade['percentage'] ?>%)
                                                            </td>
                                                            <td><?= date("M j", strtotime($grade['due_date'])) ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                        <div class="text-end">
                                            <a href="parent_grades.php?child_id=<?= $selectedChildId ?>"
                                                class="btn btn-sm btn-outline-primary">
                                                View All Grades
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Upcoming Assignments -->
                        <div class="col-md-6 mb-4">
                            <div class="card dashboard-card h-100">
                                <div class="card-header">
                                    <h5 class="mb-0">Upcoming Assignments</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($upcomingAssignments)): ?>
                                        <div class="alert alert-success">No upcoming assignments due this week</div>
                                    <?php else: ?>
                                        <div class="list-group">
                                            <?php foreach ($upcomingAssignments as $assignment): ?>
                                                <div class="list-group-item">
                                                    <div class="d-flex justify-content-between">
                                                        <h6 class="mb-1"><?= htmlspecialchars($assignment['title']) ?></h6>
                                                        <small class="text-muted"><?= $assignment['days_remaining'] ?> days
                                                            left</small>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($assignment['subject_name']) ?> •
                                                        Due: <?= date("M j", strtotime($assignment['due_date'])) ?>
                                                    </small>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                        <div class="text-end mt-3">
                                            <a href="parent_assignments.php?child_id=<?= $selectedChildId ?>"
                                                class="btn btn-sm btn-outline-primary">
                                                View All Assignments
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Announcements -->
                        <div class="col-12 mb-4">
                            <div class="card dashboard-card">
                                <div class="card-header">
                                    <h5 class="mb-0">School Announcements</h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($announcements)): ?>
                                        <div class="alert alert-info">No current announcements</div>
                                    <?php else: ?>
                                        <div class="row">
                                            <?php foreach ($announcements as $announcement): ?>
                                                <div class="col-md-4 mb-3">
                                                    <div class="card announcement-card h-100">
                                                        <div class="card-body">
                                                            <h6><?= htmlspecialchars($announcement['title']) ?></h6>
                                                            <p class="card-text">
                                                                <?= nl2br(htmlspecialchars(substr($announcement['content'], 0, 150) . '...')) ?>
                                                            </p>
                                                            <small class="text-muted">
                                                                <?= date("M j", strtotime($announcement['start_date'])) ?> -
                                                                <?= date("M j", strtotime($announcement['end_date'])) ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
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
</body>

</html>