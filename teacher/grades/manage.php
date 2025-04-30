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

// Get teacher ID
$stmt = $db->prepare("
    SELECT teacher_id FROM teachers WHERE user_id = ?
");
$stmt->execute([$session->get('user_id')]);
$teacherId = $stmt->fetchColumn();

// Get assignment to grade (if specified)
$assignmentId = $_GET['assignment_id'] ?? null;
$assignment = null;
$classSubjects = [];

if ($assignmentId) {
    $assignment = $db->prepare("
        SELECT a.*, c.class_name, s.subject_name, s.subject_id, cs.class_id
        FROM assignments a
        JOIN class_subjects cs ON a.class_subject_id = cs.id
        JOIN classes c ON cs.class_id = c.class_id
        JOIN subjects s ON cs.subject_id = s.subject_id
        WHERE a.assignment_id = ? AND cs.teacher_id = ?
    ");
    $assignment->execute([$assignmentId, $teacherId]);
    $assignment = $assignment->fetch();

    if (!$assignment) {
        $session->set('flash_error', 'Assignment not found or you are not authorized');
        $errorMessage = $session->get('flash_error'); // Retrieve flash error message directly
        header("Location: manage.php");
        exit();
    }

    // Get students with their grades for this assignment
    $students = $db->prepare("
        SELECT s.student_id, u.first_name, u.last_name, g.score, g.comments, g.grade_id
        FROM enrollments e
        JOIN students s ON e.student_id = s.student_id
        JOIN users u ON s.user_id = u.user_id
        LEFT JOIN grades g ON s.student_id = g.student_id AND g.assignment_id = ?
        WHERE e.class_id = ?
        ORDER BY u.last_name, u.first_name
    ");
    $students->execute([$assignmentId, $assignment['class_id']]);
    $students = $students->fetchAll();
}

// Get all assignments that need grading
$assignments = $db->prepare("
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
$assignments->execute([$teacherId]);
$assignments = $assignments->fetchAll();

// Handle grade submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();

        $assignmentId = (int) $_POST['assignment_id'];

        foreach ($_POST['grades'] as $studentId => $gradeData) {
            $score = !empty($gradeData['score']) ? (float) $gradeData['score'] : null;
            $comments = trim($gradeData['comments'] ?? '');

            if ($score !== null) {
                // Validate score doesn't exceed max score
                if ($score > $assignment['max_score']) {
                    throw new Exception("Score cannot exceed maximum score of {$assignment['max_score']}");
                }

                // Check if grade already exists
                $gradeIdStmt = $db->prepare("
                SELECT grade_id FROM grades 
                WHERE assignment_id = ? AND student_id = ?
               ");
                $gradeIdStmt->execute([$assignmentId, $studentId]);
                $gradeId = $gradeIdStmt->fetchColumn();


                if ($gradeId) {
                    // Update existing grade
                    $stmt = $db->prepare("
                        UPDATE grades SET 
                        score = ?, comments = ?, recorded_by = ?, recorded_at = NOW()
                        WHERE grade_id = ?
                    ");
                    $stmt->execute([
                        $score,
                        $comments,
                        $session->get('user_id'),
                        $gradeId
                    ]);
                } else {
                    // Create new grade
                    $stmt = $db->prepare("
                        INSERT INTO grades 
                        (student_id, assignment_id, score, comments, recorded_by)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $studentId,
                        $assignmentId,
                        $score,
                        $comments,
                        $session->get('user_id')
                    ]);
                }
            }
        }

        $db->commit();
        $session->set('flash_success', 'Grades saved successfully!');
        header("Location: manage.php?assignment_id=$assignmentId");
        exit();
    } catch (Exception $e) {
        $db->rollBack();
        $session->set('flash_error', 'Error saving grades: ' . $e->getMessage());
        header("Location: manage.php?assignment_id=$assignmentId");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Grades | Iftin School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
</head>

<body>
    <?php include '../navbar.php'; ?>
    <?php include '../sidebar.php'; ?>
    <div class="main-content-teacher">
        <div class="container-fluid">
            <div class="row">
                <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                    <div
                        class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2">Manage Grades</h1>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <a href="../dashboard.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>

                    <?php if ($message = $session->get('flash_success')): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>
                    <?php if ($message = $session->get('flash_error')): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5>Your Assignments</h5>
                                </div>
                                <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                                    <div class="list-group">
                                        <?php foreach ($assignments as $assgn):
                                            $gradedPercent = round(($assgn['graded_count'] / $assgn['total_students']) * 100);
                                            ?>
                                            <a href="?assignment_id=<?= $assgn['assignment_id'] ?>"
                                                class="list-group-item list-group-item-action <?= $assignmentId == $assgn['assignment_id'] ? 'active' : '' ?>">
                                                <div class="d-flex w-100 justify-content-between">
                                                    <h6 class="mb-1"><?= htmlspecialchars($assgn['title']) ?></h6>
                                                    <small><?= date('M j', strtotime($assgn['due_date'])) ?></small>
                                                </div>
                                                <p class="mb-1">
                                                    <small>
                                                        <?= htmlspecialchars($assgn['class_name']) ?> -
                                                        <?= htmlspecialchars($assgn['subject_name']) ?>
                                                    </small>
                                                </p>
                                                <div class="progress mt-2" style="height: 5px;">
                                                    <div class="progress-bar <?= $gradedPercent == 100 ? 'bg-success' : 'bg-info' ?>"
                                                        role="progressbar" style="width: <?= $gradedPercent ?>%;"
                                                        aria-valuenow="<?= $gradedPercent ?>" aria-valuemin="0"
                                                        aria-valuemax="100">
                                                    </div>
                                                </div>
                                                <small><?= $assgn['graded_count'] ?> of <?= $assgn['total_students'] ?>
                                                    graded</small>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-8">
                            <?php if ($assignment): ?>
                                <div class="card">
                                    <div class="card-header">
                                        <h5>
                                            <?= htmlspecialchars($assignment['title']) ?>
                                            <small class="text-muted">
                                                (Max Score: <?= $assignment['max_score'] ?> | Weight:
                                                <?= $assignment['weight'] ?>%)
                                            </small>
                                        </h5>
                                        <h6 class="text-muted">
                                            <?= htmlspecialchars($assignment['class_name']) ?> -
                                            <?= htmlspecialchars($assignment['subject_name']) ?>
                                        </h6>
                                    </div>

                                    <form method="POST">
                                        <input type="hidden" name="assignment_id" value="<?= $assignmentId ?>">

                                        <div class="card-body">
                                            <div class="table-responsive">
                                                <table class="table table-striped table-hover">
                                                    <thead>
                                                        <tr>
                                                            <th>Student</th>
                                                            <th width="120">Score</th>
                                                            <th>Comments</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($students as $student): ?>
                                                            <tr>
                                                                <td>
                                                                    <?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name']) ?>
                                                                </td>
                                                                <td>
                                                                    <input type="number" class="form-control form-control-sm"
                                                                        name="grades[<?= $student['student_id'] ?>][score]"
                                                                        value="<?= htmlspecialchars($student['score'] ?? '') ?>"
                                                                        min="0" max="<?= $assignment['max_score'] ?>"
                                                                        step="0.01"
                                                                        placeholder="0-<?= $assignment['max_score'] ?>">
                                                                </td>
                                                                <td>
                                                                    <input type="text" class="form-control form-control-sm"
                                                                        name="grades[<?= $student['student_id'] ?>][comments]"
                                                                        value="<?= htmlspecialchars($student['comments'] ?? '') ?>"
                                                                        placeholder="Comments (optional)">
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="card-footer text-end">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-save"></i> Save Grades
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div class="card">
                                    <div class="card-body text-center py-5">
                                        <i class="bi bi-journal-text" style="font-size: 3rem; color: #6c757d;"></i>
                                        <h5 class="mt-3">Select an assignment to grade</h5>
                                        <p class="text-muted">Choose from the list on the left to view and enter grades</p>
                                    </div>
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