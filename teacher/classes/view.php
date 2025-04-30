<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';

// Verify teacher access
if ($_SESSION['user_role'] !== 'teacher') {
    header('HTTP/1.0 403 Forbidden');
    die('Access denied');
}

// Get teacher ID
$stmt = $db->prepare("SELECT teacher_id FROM teachers WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$teacher = $stmt->fetch();

if (!$teacher) {
    die('Teacher record not found');
}

// Get teacher's classes with subject count and student count
$query = "
    SELECT 
        c.class_id,
        c.class_name,
        s.section_name,
        COUNT(DISTINCT cs.subject_id) as subject_count,
        COUNT(DISTINCT e.student_id) as student_count
    FROM classes c
    JOIN sections s ON c.section_id = s.section_id
    JOIN class_subjects cs ON c.class_id = cs.class_id
    LEFT JOIN enrollments e ON c.class_id = e.class_id
    WHERE cs.teacher_id = ?
    GROUP BY c.class_id
    ORDER BY c.class_name, s.section_name
";
$stmt->execute([$teacher['teacher_id']]);
$classes = $stmt->fetchAll();

// Get upcoming assignments for all classes
$stmt = $db->prepare("
    SELECT 
        a.assignment_id,
        a.title,
        a.due_date,
        c.class_id,
        c.class_name,
        s.subject_name
    FROM assignments a
    JOIN class_subjects cs ON a.class_subject_id = cs.id
    JOIN classes c ON cs.class_id = c.class_id
    JOIN subjects s ON cs.subject_id = s.subject_id
    WHERE cs.teacher_id = ?
    AND a.due_date >= CURDATE()
    ORDER BY a.due_date ASC
    LIMIT 5
");
$stmt->execute([$teacher['teacher_id']]);
$upcoming_assignments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Classes | Iftin School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/sidebar.css" rel="stylesheet">
    <style>
        .main-content-teacher {
            margin-left: 220px;
            padding: 20px;
        }
        .class-card {
            transition: transform 0.2s, box-shadow 0.2s;
            height: 100%;
        }
        .class-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .badge-subject {
            background-color: #6f42c1;
        }
        .badge-student {
            background-color: #20c997;
        }
        .upcoming-item {
            border-left: 3px solid #0d6efd;
        }
    </style>
</head>
<body>
    <?php include '../navbar.php'; ?>
    <?php include '../sidebar.php'; ?>
    
    <div class="main-content-teacher">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">My Classes</h2>
                <div class="btn-group">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" 
                            data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item active" href="#">All Classes</a></li>
                        <li><a class="dropdown-item" href="#">With Upcoming Assignments</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="#">By Subject</a></li>
                    </ul>
                </div>
            </div>

            <?php if (empty($classes)): ?>
                <div class="alert alert-info">
                    You don't have any assigned classes yet. Please contact the administrator.
                </div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4">
                    <?php foreach ($classes as $class): ?>
                    <div class="col">
                        <div class="card class-card shadow-sm">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="card-title mb-0">
                                        <?= htmlspecialchars($class['class_name']) ?>
                                        <?php if (!empty($class['section_name'])): ?>
                                            <small class="text-muted"><?= htmlspecialchars($class['section_name']) ?></small>
                                        <?php endif; ?>
                                    </h5>
                                    <div>
                                        <span class="badge badge-subject me-1">
                                            <i class="fas fa-book"></i> <?= $class['subject_count'] ?>
                                        </span>
                                        <span class="badge badge-student">
                                            <i class="fas fa-users"></i> <?= $class['student_count'] ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <a href="details.php?class_id=<?= $class['class_id'] ?>" 
                                       class="btn btn-sm btn-outline-primary me-2">
                                        <i class="fas fa-list"></i> View Details
                                    </a>
                                    <a href="../attendance/take.php?class_id=<?= $class['class_id'] ?>" 
                                       class="btn btn-sm btn-outline-success">
                                        <i class="fas fa-clipboard-check"></i> Take Attendance
                                    </a>
                                </div>
                                
                                <?php 
                                // Get assignments due soon for this class
                                $class_assignments = array_filter($upcoming_assignments, 
                                    fn($a) => $a['class_id'] == $class['class_id']);
                                ?>
                                
                                <?php if (!empty($class_assignments)): ?>
                                    <div class="border-top pt-2">
                                        <small class="text-muted">Upcoming:</small>
                                        <ul class="list-unstyled mb-0">
                                            <?php foreach ($class_assignments as $assignment): ?>
                                            <li class="upcoming-item ps-2 mb-1">
                                                <small>
                                                    <i class="fas fa-tasks text-primary me-1"></i>
                                                    <?= htmlspecialchars($assignment['title']) ?>
                                                    <span class="text-muted ms-2">
                                                        <?= date('M j', strtotime($assignment['due_date'])) ?>
                                                    </span>
                                                </small>
                                            </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Upcoming Assignments Section -->
            <?php if (!empty($upcoming_assignments)): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Upcoming Assignments</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Due Date</th>
                                        <th>Assignment</th>
                                        <th>Class</th>
                                        <th>Subject</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcoming_assignments as $assignment): ?>
                                    <tr>
                                        <td>
                                            <?= date('M j, Y', strtotime($assignment['due_date'])) ?>
                                            <?php if (date('Y-m-d', strtotime($assignment['due_date'])) == date('Y-m-d')): ?>
                                                <span class="badge bg-warning text-dark ms-2">Today</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($assignment['title']) ?></td>
                                        <td><?= htmlspecialchars($assignment['class_name']) ?></td>
                                        <td><?= htmlspecialchars($assignment['subject_name']) ?></td>
                                        <td>
                                            <a href="../grades/manage.php?assignment_id=<?= $assignment['assignment_id'] ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit"></i> Grade
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>