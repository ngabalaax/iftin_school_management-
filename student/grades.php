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

// Get grades with filters
$filter = $_GET['filter'] ?? 'all'; // all, recent, subject
$subjectFilter = $_GET['subject'] ?? '';

$query = "
    SELECT g.grade_id, g.score, g.comments, g.recorded_at,
           a.assignment_id, a.title, a.assignment_type, a.max_score, a.weight, a.due_date,
           s.subject_id, s.subject_name, s.subject_code,
           CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
           ROUND((g.score/a.max_score)*100) as percentage
    FROM grades g
    JOIN assignments a ON g.assignment_id = a.assignment_id
    JOIN class_subjects cs ON a.class_subject_id = cs.id
    JOIN subjects s ON cs.subject_id = s.subject_id
    JOIN teachers te ON cs.teacher_id = te.teacher_id
    JOIN users t ON te.user_id = t.user_id
    WHERE g.student_id = ?
";

$params = [$student['student_id']];

if ($filter === 'recent') {
    $query .= " AND a.due_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
} elseif (!empty($subjectFilter)) {
    $query .= " AND s.subject_id = ?";
    $params[] = $subjectFilter;
}

$query .= " ORDER BY a.due_date DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$grades = $stmt->fetchAll();

// Calculate overall performance
$stmt = $db->prepare("
    SELECT s.subject_name, 
           COUNT(g.grade_id) as assignment_count,
           AVG(g.score/a.max_score)*100 as average_percentage
    FROM grades g
    JOIN assignments a ON g.assignment_id = a.assignment_id
    JOIN class_subjects cs ON a.class_subject_id = cs.id
    JOIN subjects s ON cs.subject_id = s.subject_id
    WHERE g.student_id = ?
    GROUP BY s.subject_id
    ORDER BY s.subject_name
");
$stmt->execute([$student['student_id']]);
$subjectPerformance = $stmt->fetchAll();

// Get subject filter options
$stmt = $db->prepare("
    SELECT DISTINCT s.subject_id, s.subject_name
    FROM class_subjects cs
    JOIN subjects s ON cs.subject_id = s.subject_id
    WHERE cs.class_id = ?
    ORDER BY s.subject_name
");
$stmt->execute([$student['current_class_id']]);
$subjects = $stmt->fetchAll();

// Helper function to get letter grade
function getLetterGrade($percentage) {
    if ($percentage >= 90) return 'A';
    if ($percentage >= 80) return 'B';
    if ($percentage >= 70) return 'C';
    if ($percentage >= 60) return 'D';
    return 'F';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Grades | Iftin School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <style>
        .grade-card {
            margin-bottom: 1.5rem;
            transition: transform 0.2s;
        }
        .grade-card:hover {
            transform: translateY(-3px);
        }
        .grade-badge {
            font-size: 1.1rem;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        .grade-A {
            background-color: #28a745;
            color: white;
        }
        .grade-B {
            background-color: #5cb85c;
            color: white;
        }
        .grade-C {
            background-color: #f0ad4e;
            color: white;
        }
        .grade-D {
            background-color: #d9534f;
            color: white;
        }
        .grade-F {
            background-color: #dc3545;
            color: white;
        }
        .subject-card {
            border-left: 4px solid #0d6efd;
        }
        .performance-chart {
            height: 300px;
        }
    </style>
</head>
<body>
    <?php include './navbar.php'; ?>
    <?php include './sidebar.php'; ?>
    
    <div class="main-content-teacher">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Grades</h2>
                <div>
                    <span class="badge bg-primary"><?= htmlspecialchars($student['class_name']) ?></span>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="card-title">Your Academic Performance</h5>
                            <p class="card-text">Track your grades and progress in all subjects.</p>
                        </div>
                        <div class="col-md-6">
                            <form class="row g-2">
                                <div class="col-md-6">
                                    <select name="filter" class="form-select" onchange="this.form.submit()">
                                        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Grades</option>
                                        <option value="recent" <?= $filter === 'recent' ? 'selected' : '' ?>>Recent (Last 30 Days)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <select name="subject" class="form-select" onchange="this.form.submit()">
                                        <option value="">All Subjects</option>
                                        <?php foreach ($subjects as $subject): ?>
                                            <option value="<?= $subject['subject_id'] ?>" <?= $subjectFilter == $subject['subject_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($subject['subject_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-8">
                    <?php if (empty($grades)): ?>
                        <div class="alert alert-info">No grades found matching your criteria.</div>
                    <?php else: ?>
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Assignment Grades</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Assignment</th>
                                                <th>Subject</th>
                                                <th>Type</th>
                                                <th>Score</th>
                                                <th>Grade</th>
                                                <th>Date</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($grades as $grade): 
                                                $letterGrade = getLetterGrade($grade['percentage']);
                                                $dueDate = date("M j, Y", strtotime($grade['due_date']));
                                                ?>
                                                <tr>
                                                    <td>
                                                        <strong><?= htmlspecialchars($grade['title']) ?></strong>
                                                        <?php if (!empty($grade['comments'])): ?>
                                                            <small class="d-block text-muted"><?= htmlspecialchars($grade['comments']) ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($grade['subject_name']) ?></td>
                                                    <td><?= ucfirst($grade['assignment_type']) ?></td>
                                                    <td>
                                                        <?= $grade['score'] ?>/<?= $grade['max_score'] ?>
                                                        (<?= $grade['percentage'] ?>%)
                                                    </td>
                                                    <td>
                                                        <span class="grade-badge grade-<?= $letterGrade ?>">
                                                            <?= $letterGrade ?>
                                                        </span>
                                                    </td>
                                                    <td><?= $dueDate ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">Overall Performance</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($subjectPerformance)): ?>
                                <div class="alert alert-warning">No performance data available yet.</div>
                            <?php else: ?>
                                <div class="performance-chart mb-4">
                                    <canvas id="performanceChart"></canvas>
                                </div>
                                
                                <div class="list-group">
                                    <?php foreach ($subjectPerformance as $subject): 
                                        $letterGrade = getLetterGrade($subject['average_percentage']);
                                        ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span><?= htmlspecialchars($subject['subject_name']) ?></span>
                                                <span class="badge bg-primary rounded-pill">
                                                    <?= round($subject['average_percentage']) ?>%
                                                </span>
                                            </div>
                                            <div class="progress mt-2" style="height: 8px;">
                                                <div class="progress-bar bg-<?= 
                                                    $letterGrade === 'A' ? 'success' : 
                                                    ($letterGrade === 'B' ? 'info' : 
                                                    ($letterGrade === 'C' ? 'warning' : 'danger')) 
                                                ?>" 
                                                role="progressbar" 
                                                style="width: <?= $subject['average_percentage'] ?>%" 
                                                aria-valuenow="<?= $subject['average_percentage'] ?>" 
                                                aria-valuemin="0" 
                                                aria-valuemax="100"></div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Performance chart
        const ctx = document.getElementById('performanceChart');
        <?php if (!empty($subjectPerformance)): ?>
            const subjectNames = <?= json_encode(array_column($subjectPerformance, 'subject_name')) ?>;
            const subjectAverages = <?= json_encode(array_column($subjectPerformance, 'average_percentage')) ?>;
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: subjectNames,
                    datasets: [{
                        label: 'Average Score (%)',
                        data: subjectAverages,
                        backgroundColor: [
                            '#28a745', '#17a2b8', '#ffc107', '#dc3545', '#6c757d',
                            '#007bff', '#6610f2', '#6f42c1', '#e83e8c', '#fd7e14'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        <?php endif; ?>
    </script>
</body>
</html>