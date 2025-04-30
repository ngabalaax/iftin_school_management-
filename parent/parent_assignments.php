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

// Get filters
$filter = $_GET['filter'] ?? 'upcoming'; // upcoming, past, all
$subjectFilter = $_GET['subject'] ?? '';
$typeFilter = $_GET['type'] ?? '';

// Base query
$query = "
    SELECT a.assignment_id, a.title, a.description, a.assignment_type, 
           a.due_date, a.max_score, a.weight,
           s.subject_name, s.subject_code,
           DATEDIFF(a.due_date, CURDATE()) as days_remaining,
           g.score
    FROM assignments a
    JOIN class_subjects cs ON a.class_subject_id = cs.id
    JOIN subjects s ON cs.subject_id = s.subject_id
    LEFT JOIN grades g ON a.assignment_id = g.assignment_id AND g.student_id = ?
    WHERE cs.class_id = ?
";

$params = [$child['student_id'], $child['class_id']];

// Apply filters
if ($filter === 'upcoming') {
    $query .= " AND a.due_date >= CURDATE()";
} elseif ($filter === 'past') {
    $query .= " AND a.due_date < CURDATE()";
}

if (!empty($subjectFilter)) {
    $query .= " AND s.subject_id = ?";
    $params[] = $subjectFilter;
}

if (!empty($typeFilter)) {
    $query .= " AND a.assignment_type = ?";
    $params[] = $typeFilter;
}

$query .= " ORDER BY a.due_date " . ($filter === 'past' ? 'DESC' : 'ASC');

$stmt = $db->prepare($query);
$stmt->execute($params);
$assignments = $stmt->fetchAll();

// Get subject filter options
$stmt = $db->prepare("
    SELECT DISTINCT s.subject_id, s.subject_name
    FROM class_subjects cs
    JOIN subjects s ON cs.subject_id = s.subject_id
    WHERE cs.class_id = ?
    ORDER BY s.subject_name
");
$stmt->execute([$child['class_id']]);
$subjectOptions = $stmt->fetchAll();

$typeOptions = [
    ['type' => 'homework', 'name' => 'Homework'],
    ['type' => 'quiz', 'name' => 'Quiz'],
    ['type' => 'test', 'name' => 'Test'],
    ['type' => 'project', 'name' => 'Project'],
    ['type' => 'exam', 'name' => 'Exam']
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments | Iftin School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .assignment-card {
            margin-bottom: 1.5rem;
            transition: transform 0.2s;
        }
        .assignment-card:hover {
            transform: translateY(-3px);
        }
        .assignment-type {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .due-date {
            font-size: 0.9rem;
        }
        .late {
            color: #dc3545;
            font-weight: bold;
        }
        .completed {
            border-left: 4px solid #28a745;
        }
        .pending {
            border-left: 4px solid #ffc107;
        }
    </style>
</head>
<body>
    <?php include './navbar.php'; ?>
    <?php include './sidebar.php'; ?>
    <div class="main-content-parent">
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/parent_sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h2>Assignments</h2>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <span class="me-2">Viewing:</span>
                        <strong><?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?></strong>
                        <span class="badge bg-primary ms-2"><?= htmlspecialchars($child['class_name']) ?></span>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form class="row g-3">
                            <input type="hidden" name="child_id" value="<?= $child['student_id'] ?>">
                            
                            <div class="col-md-4">
                                <select name="filter" class="form-select" onchange="this.form.submit()">
                                    <option value="upcoming" <?= $filter === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                                    <option value="past" <?= $filter === 'past' ? 'selected' : '' ?>>Past</option>
                                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Assignments</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <select name="subject" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Subjects</option>
                                    <?php foreach ($subjectOptions as $subject): ?>
                                        <option value="<?= $subject['subject_id'] ?>" <?= $subjectFilter == $subject['subject_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($subject['subject_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <select name="type" class="form-select" onchange="this.form.submit()">
                                    <option value="">All Types</option>
                                    <?php foreach ($typeOptions as $type): ?>
                                        <option value="<?= $type['type'] ?>" <?= $typeFilter == $type['type'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($type['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Assignments List -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Assignments</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($assignments)): ?>
                            <div class="alert alert-info">No assignments found matching your criteria</div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($assignments as $assignment): 
                                    $isLate = $assignment['days_remaining'] < 0 && $assignment['score'] === null;
                                    $isCompleted = $assignment['score'] !== null;
                                    $dueDate = date("M j, Y", strtotime($assignment['due_date']));
                                ?>
                                    <div class="col-md-6">
                                        <div class="card assignment-card <?= $isCompleted ? 'completed' : 'pending' ?>">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h5 class="card-title mb-0"><?= htmlspecialchars($assignment['title']) ?></h5>
                                                    <span class="badge bg-secondary assignment-type">
                                                        <?= ucfirst($assignment['assignment_type']) ?>
                                                    </span>
                                                </div>
                                                <h6 class="card-subtitle mb-2 text-muted">
                                                    <?= htmlspecialchars($assignment['subject_name']) ?>
                                                </h6>
                                                
                                                <?php if (!empty($assignment['description'])): ?>
                                                    <p class="card-text mb-3"><?= nl2br(htmlspecialchars($assignment['description'])) ?></p>
                                                <?php endif; ?>
                                                
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="due-date <?= $isLate ? 'late' : '' ?>">
                                                        <i class="far fa-calendar-alt me-1"></i>
                                                        Due: <?= $dueDate ?>
                                                        <?php if ($assignment['days_remaining'] >= 0): ?>
                                                            (<?= $assignment['days_remaining'] ?> days left)
                                                        <?php else: ?>
                                                            (<?= abs($assignment['days_remaining']) ?> days ago)
                                                        <?php endif; ?>
                                                    </span>
                                                    
                                                    <?php if ($isCompleted): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check"></i> Submitted
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <?php if ($isCompleted): ?>
                                                    <div class="mt-3">
                                                        <div class="progress">
                                                            <div class="progress-bar bg-success" 
                                                                 role="progressbar" 
                                                                 style="width: <?= ($assignment['score']/$assignment['max_score'])*100 ?>%" 
                                                                 aria-valuenow="<?= $assignment['score'] ?>" 
                                                                 aria-valuemin="0" 
                                                                 aria-valuemax="<?= $assignment['max_score'] ?>">
                                                            </div>
                                                        </div>
                                                        <small class="text-muted">
                                                            Score: <?= $assignment['score'] ?>/<?= $assignment['max_score'] ?>
                                                            (<?= round(($assignment['score']/$assignment['max_score'])*100) ?>%)
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
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