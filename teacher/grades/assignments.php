<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';

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

// First get teacher_id from user_id
$stmt = $db->prepare("SELECT teacher_id FROM teachers WHERE user_id = ?");
$stmt->execute([$session->get('user_id')]);
$teacher = $stmt->fetch();

if (!$teacher) {
    die('Teacher record not found');
}

$teacher_id = $teacher['teacher_id'];

// Get teacher's assignments
$stmt = $db->prepare("
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
    GROUP BY a.assignment_id
    ORDER BY a.due_date DESC
");
$stmt->execute([$teacher_id]);
$assignments = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assignments | Iftin School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/sidebar.css" rel="stylesheet">
    <style>
        .main-content-teacher {
            margin-left: 220px;
            padding: 20px;
        }
        .progress {
            min-width: 100px;
        }
    </style>
</head>
<body>
    <?php include '../navbar.php'; ?>
    <?php include '../sidebar.php'; ?>
    
    <div class="main-content-teacher">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">My Assignments</h2>
                <a href="create.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> New Assignment
                </a>
            </div>

            <?php if (empty($assignments)): ?>
                <div class="alert alert-info">No assignments found</div>
            <?php else: ?>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Title</th>
                                        <th>Class</th>
                                        <th>Subject</th>
                                        <th>Due Date</th>
                                        <th>Progress</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assignments as $assignment): 
                                        $progress = $assignment['total_students'] > 0 
                                            ? round(($assignment['graded_count'] / $assignment['total_students']) * 100) 
                                            : 0;
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($assignment['title']) ?></td>
                                        <td><?= htmlspecialchars($assignment['class_name']) ?></td>
                                        <td><?= htmlspecialchars($assignment['subject_name']) ?></td>
                                        <td><?= date('M j, Y', strtotime($assignment['due_date'])) ?></td>
                                        <td>
                                            <div class="progress" style="height: 20px;">
                                                <div class="progress-bar" role="progressbar" 
                                                     style="width: <?= $progress ?>%" 
                                                     aria-valuenow="<?= $progress ?>" 
                                                     aria-valuemin="0" 
                                                     aria-valuemax="100">
                                                    <?= $assignment['graded_count'] ?>/<?= $assignment['total_students'] ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="manage.php?assignment_id=<?= $assignment['assignment_id'] ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-edit"></i> Grade
                                            </a>
                                            <a href="view.php?assignment_id=<?= $assignment['assignment_id'] ?>" 
                                               class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-eye"></i> View
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