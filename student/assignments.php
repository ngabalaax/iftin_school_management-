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

// Get assignments with filters
$filter = $_GET['filter'] ?? 'upcoming'; // upcoming, past, all
$search = $_GET['search'] ?? '';

$query = "
    SELECT a.assignment_id, a.title, a.description, a.due_date, a.assignment_type, a.max_score, a.weight,
           s.subject_name, s.subject_code,
           DATEDIFF(a.due_date, CURDATE()) as days_remaining,
           g.score
    FROM assignments a
    JOIN class_subjects cs ON a.class_subject_id = cs.id
    JOIN subjects s ON cs.subject_id = s.subject_id
    LEFT JOIN grades g ON a.assignment_id = g.assignment_id AND g.student_id = ?
    WHERE cs.class_id = ?
";

$params = [$student['student_id'], $student['current_class_id']];

// Apply filters
if ($filter === 'upcoming') {
    $query .= " AND a.due_date >= CURDATE()";
} elseif ($filter === 'past') {
    $query .= " AND a.due_date < CURDATE()";
}

if (!empty($search)) {
    $query .= " AND (a.title LIKE ? OR s.subject_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
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
$stmt->execute([$student['current_class_id']]);
$subjects = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments | Iftin School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <style>
        .assignment-card {
            transition: transform 0.2s;
            margin-bottom: 1.5rem;
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
    
    <div class="main-content-teacher">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Assignments</h2>
                <div>
                    <span class="badge bg-primary"><?= htmlspecialchars($student['class_name']) ?></span>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="card-title">Manage Your Assignments</h5>
                            <p class="card-text">View and track all your assignments in one place.</p>
                        </div>
                        <div class="col-md-6">
                            <form class="row g-2">
                                <div class="col-md-6">
                                    <select name="filter" class="form-select" onchange="this.form.submit()">
                                        <option value="upcoming" <?= $filter === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                                        <option value="past" <?= $filter === 'past' ? 'selected' : '' ?>>Past</option>
                                        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Assignments</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <div class="input-group">
                                        <input type="text" name="search" class="form-control" placeholder="Search..." value="<?= htmlspecialchars($search) ?>">
                                        <button class="btn btn-outline-secondary" type="submit">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (empty($assignments)): ?>
                <div class="alert alert-info">No assignments found matching your criteria.</div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($assignments as $assignment): 
                        $isLate = $assignment['days_remaining'] < 0 && $assignment['score'] === null;
                        $isCompleted = $assignment['score'] !== null;
                        $dueDate = date("M j, Y", strtotime($assignment['due_date']));
                        ?>
                        <div class="col-md-6 col-lg-4">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>