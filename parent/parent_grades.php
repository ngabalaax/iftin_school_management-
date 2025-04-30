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
    SELECT s.student_id, u.first_name, u.last_name, c.class_name
    FROM parent_student ps
    JOIN students s ON ps.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN classes c ON s.current_class_id = c.class_id
    WHERE ps.parent_id = (SELECT parent_id FROM parents WHERE user_id = ?)
    AND s.student_id = ?
");
$stmt->execute([$session->get('user_id'), $_GET['child_id']]);
$child = $stmt->fetch();

if (!$child) {
    die('Child not found or access denied');
}

// Get filters
$subjectFilter = $_GET['subject'] ?? '';
$typeFilter = $_GET['type'] ?? '';
$termFilter = $_GET['term'] ?? '';

// Base query
$query = "
    SELECT g.grade_id, g.score, g.comments, g.recorded_at,
           a.assignment_id, a.title, a.assignment_type, a.max_score, a.weight, a.due_date,
           s.subject_id, s.subject_name, s.subject_code,
           CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
           ROUND((g.score/a.max_score)*100) as percentage
    FROM grades g
    JOIN assignments a ON g.assignment_id = a.assignment_id
    JOIN class_subjects cs ON a.class_subject_id = cs.id
    JOIN subjects s ON cs.subject_id = s.subject_id
    JOIN teachers te ON cs.teacher_id = te.teacher_id
    JOIN users u ON te.user_id = u.user_id
    WHERE g.student_id = ?
";

$params = [$child['student_id']];

// Apply filters
if (!empty($subjectFilter)) {
    $query .= " AND s.subject_id = ?";
    $params[] = $subjectFilter;
}

if (!empty($typeFilter)) {
    $query .= " AND a.assignment_type = ?";
    $params[] = $typeFilter;
}

if (!empty($termFilter)) {
    // Assuming term is stored in assignments or calculated based on date
    $query .= " AND (a.due_date BETWEEN ? AND ?)";
    if ($termFilter === 'term1') {
        $params[] = '2023-09-01';
        $params[] = '2023-12-15';
    } elseif ($termFilter === 'term2') {
        $params[] = '2024-01-10';
        $params[] = '2024-04-30';
    } else {
        $params[] = '2024-05-01';
        $params[] = '2024-06-30';
    }
}

$query .= " ORDER BY a.due_date DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$grades = $stmt->fetchAll();

// Get subject averages
$stmt = $db->prepare("
    SELECT s.subject_id, s.subject_name,
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
$stmt->execute([$child['student_id']]);
$subjectAverages = $stmt->fetchAll();

// Get filter options
$stmt = $db->prepare("
    SELECT DISTINCT s.subject_id, s.subject_name
    FROM grades g
    JOIN assignments a ON g.assignment_id = a.assignment_id
    JOIN class_subjects cs ON a.class_subject_id = cs.id
    JOIN subjects s ON cs.subject_id = s.subject_id
    WHERE g.student_id = ?
    ORDER BY s.subject_name
");
$stmt->execute([$child['student_id']]);
$subjectOptions = $stmt->fetchAll();

$typeOptions = [
    ['type' => 'homework', 'name' => 'Homework'],
    ['type' => 'quiz', 'name' => 'Quiz'],
    ['type' => 'test', 'name' => 'Test'],
    ['type' => 'project', 'name' => 'Project'],
    ['type' => 'exam', 'name' => 'Exam']
];

$termOptions = [
    ['term' => 'term1', 'name' => 'Term 1'],
    ['term' => 'term2', 'name' => 'Term 2'],
    ['term' => 'term3', 'name' => 'Term 3']
];

// Helper function
function getLetterGrade($percentage)
{
    if ($percentage >= 90)
        return 'A';
    if ($percentage >= 80)
        return 'B';
    if ($percentage >= 70)
        return 'C';
    if ($percentage >= 60)
        return 'D';
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
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <style>
        .grade-badge {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            font-weight: bold;
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
    <div class="main-content-parent">
        <div class="container-fluid">
            <div class="row">
                <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                    <div
                        class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h2>Grades</h2>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <span class="me-2">Viewing:</span>
                            <strong><?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?></strong>
                            <span
                                class="badge bg-primary ms-2"><?= htmlspecialchars($child['class_name'] ?? 'No Class') ?></span>
                        </div>
                    </div>

                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-body">
                            <form class="row g-3">
                                <input type="hidden" name="child_id" value="<?= $child['student_id'] ?>">

                                <div class="col-md-4">
                                    <label class="form-label">Subject</label>
                                    <select name="subject" class="form-select" onchange="this.form.submit()">
                                        <option value="">All Subjects</option>
                                        <?php foreach ($subjectOptions as $subject): ?>
                                            <option value="<?= $subject['subject_id'] ?>"
                                                <?= $subjectFilter == $subject['subject_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($subject['subject_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Assignment Type</label>
                                    <select name="type" class="form-select" onchange="this.form.submit()">
                                        <option value="">All Types</option>
                                        <?php foreach ($typeOptions as $type): ?>
                                            <option value="<?= $type['type'] ?>" <?= $typeFilter == $type['type'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($type['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">Term</label>
                                    <select name="term" class="form-select" onchange="this.form.submit()">
                                        <option value="">All Terms</option>
                                        <?php foreach ($termOptions as $term): ?>
                                            <option value="<?= $term['term'] ?>" <?= $termFilter == $term['term'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($term['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Subject Performance -->
                    <div class="row mb-4">
                        <?php foreach ($subjectAverages as $subject):
                            $letterGrade = getLetterGrade($subject['average_percentage']);
                            ?>
                            <div class="col-md-4 col-lg-3 mb-3">
                                <div class="card subject-card h-100">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <h6 class="card-title mb-0"><?= htmlspecialchars($subject['subject_name']) ?>
                                            </h6>
                                            <span class="grade-badge grade-<?= $letterGrade ?>">
                                                <?= $letterGrade ?>
                                            </span>
                                        </div>
                                        <div class="progress mt-3" style="height: 10px;">
                                            <div class="progress-bar" role="progressbar" style="width: <?= $subject['average_percentage'] ?>%; 
                                                    background-color: <?=
                                                        $letterGrade === 'A' ? '#28a745' :
                                                        ($letterGrade === 'B' ? '#5cb85c' :
                                                            ($letterGrade === 'C' ? '#f0ad4e' : '#d9534f'))
                                                        ?>" aria-valuenow="<?= $subject['average_percentage'] ?>"
                                                aria-valuemin="0" aria-valuemax="100">
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-between mt-2 small">
                                            <span class="text-muted">
                                                <?= $subject['assignment_count'] ?> assignments
                                            </span>
                                            <span class="fw-bold">
                                                <?= round($subject['average_percentage']) ?>%
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Grade Details -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Grade Details</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($grades)): ?>
                                <div class="alert alert-info">No grades found matching your criteria</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Subject</th>
                                                <th>Assignment</th>
                                                <th>Type</th>
                                                <th>Score</th>
                                                <th>Grade</th>
                                                <th>Date</th>
                                                <th>Teacher</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($grades as $grade):
                                                $letterGrade = getLetterGrade($grade['percentage']);
                                                $dueDate = date("M j, Y", strtotime($grade['due_date']));
                                                ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($grade['subject_name']) ?></td>
                                                    <td><?= htmlspecialchars($grade['title']) ?></td>
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
                                                    <td><?= htmlspecialchars($grade['teacher_name']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>