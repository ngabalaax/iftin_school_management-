<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/session.php';

// Helper function to retrieve flash messages
function flashMessage($session, $key) {
    return $session->getFlash($key);
}
require_once __DIR__ . '/../../config/database.php';

// Manual role check if requireRole() isn't available
if (!$session->isLoggedIn()) {
    $redirect = $_SERVER['REQUEST_URI'];
    header("Location: /auth/login.php?redirect=" . urlencode($redirect));
    exit();
}

if ($session->get('user_role') !== 'manager') {
    header('HTTP/1.0 403 Forbidden');
    die('You do not have permission to access this page.');
}


// Get class details
$classStmt = $db->prepare("
    SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as teacher_name
    FROM classes c
    LEFT JOIN teachers t ON c.class_teacher_id = t.teacher_id
    LEFT JOIN users u ON t.user_id = u.user_id
    WHERE c.class_id = ?
");
$classStmt->execute([$classId]);
$class = $classStmt->fetch();
 

$classId = $_GET['class_id'] ?? null;

if (!$classId) {
    $session->set('flash_error', 'No class ID provided.');
    header("Location: manage.php");
    exit();
}



// Get all subjects
$subjects = $db->query("SELECT * FROM subjects ORDER BY subject_name")->fetchAll();

// Get current class subjects with teacher assignments
$classSubjects = $db->prepare("
    SELECT cs.*, s.subject_name, CONCAT(u.first_name, ' ', u.last_name) as teacher_name
    FROM class_subjects cs
    JOIN subjects s ON cs.subject_id = s.subject_id
    LEFT JOIN teachers t ON cs.teacher_id = t.teacher_id
    LEFT JOIN users u ON t.user_id = u.user_id
    WHERE cs.class_id = ?
    ORDER BY s.subject_name
");
$classSubjectsStmt->execute([$classId]);
$classSubjects = $classSubjectsStmt->fetchAll();

// Get all teachers
$teachers = $db->query("
    SELECT t.teacher_id, u.first_name, u.last_name 
    FROM teachers t
    JOIN users u ON t.user_id = u.user_id
    ORDER BY u.last_name, u.first_name
")->fetchAll();

// Handle subject assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_subject'])) {
        // Assign new subject to class
        $subjectId = (int)$_POST['subject_id'];
        $teacherId = (int)$_POST['teacher_id'];
        
        try {
            $db->beginTransaction();
            
            // Check if subject is already assigned
            $stmt = $db->prepare("SELECT 1 FROM class_subjects WHERE class_id = ? AND subject_id = ?");
            $stmt->execute([$classId, $subjectId]);
            
            if ($stmt->fetch()) {
                throw new Exception("This subject is already assigned to the class");
            }
            
            // Assign subject
            $stmt = $db->prepare("
                INSERT INTO class_subjects 
                (class_id, subject_id, teacher_id) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$classId, $subjectId, $teacherId]);
            
            $db->commit();
            $session->set('flash_success', 'Subject assigned successfully');
            header("Location: assign.php?class_id=$classId");
            exit();
        } catch (Exception $e) {
            $db->rollBack();
            $session->set('flash_error', $e->getMessage());
            header("Location: assign.php?class_id=$classId");
            exit();
        }
    } elseif (isset($_POST['update_assignments'])) {
        // Update existing subject assignments
        try {
            $db->beginTransaction();
            
            foreach ($_POST['assignments'] as $assignmentId => $teacherId) {
                $stmt = $db->prepare("
                    UPDATE class_subjects SET 
                    teacher_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([$teacherId ? (int)$teacherId : null, (int)$assignmentId]);
            }
            
            $db->commit();
            $session->set('flash_success', 'Subject assignments updated successfully');
            header("Location: assign.php?class_id=$classId");
            exit();
        } catch (Exception $e) {
            $db->rollBack();
            $session->set('flash_error', 'Error updating assignments: ' . $e->getMessage());
            header("Location: assign.php?class_id=$classId");
            exit();
        }
    } elseif (isset($_GET['delete_assignment'])) {
        // Remove subject from class
        $assignmentId = (int)$_GET['delete_assignment'];
        
        try {
            $db->beginTransaction();
            
            // Check if there are grades for this subject
            $stmt = $db->prepare("
                SELECT COUNT(*) 
                FROM grades g
                JOIN assignments a ON g.assignment_id = a.assignment_id
                WHERE a.class_subject_id = ?
            ");
            $stmt->execute([$assignmentId]);
            
            if ($stmt->fetchColumn() > 0) {
                throw new Exception("Cannot remove subject with existing grades");
            }
            
            // Delete assignments first
            $stmt = $db->prepare("DELETE FROM assignments WHERE class_subject_id = ?");
            $stmt->execute([$assignmentId]);
            
            // Then delete the class subject
            $stmt = $db->prepare("DELETE FROM class_subjects WHERE id = ?");
            $stmt->execute([$assignmentId]);
            
            $db->commit();
            $session->set('flash_success', 'Subject removed from class');
            header("Location: assign.php?class_id=$classId");
            exit();
        } catch (Exception $e) {
            $db->rollBack();
            $session->set('flash_error', $e->getMessage());
            header("Location: assign.php?class_id=$classId");
            exit();
        }
    }
}

// Get subjects not yet assigned to this class
$availableSubjects = [];
if ($classSubjects) {
    $assignedSubjectIds = array_column($classSubjects, 'subject_id');
    $availableSubjects = array_filter($subjects, function($subject) use ($assignedSubjectIds) {
        return !in_array($subject['subject_id'], $assignedSubjectIds);
    });
} else {
    $availableSubjects = $subjects;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Subjects | Iftin School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
<?php include './others/navbar.php'; ?>    
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '../others/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        Assign Subjects: <?= htmlspecialchars($class['class_name']) ?>
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="manage.php" class="btn btn-sm btn-outline-secondary">
                            Back to Manage
                        </a>
                    </div>
                </div>

                <?php if ($message = flashMessage($session, 'success')): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                <?php if ($message = flashMessage($session, 'error')): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Current Subjects</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($classSubjects)): ?>
                            <p class="text-muted">No subjects assigned to this class yet.</p>
                        <?php else: ?>
                            <form method="POST">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Subject</th>
                                            <th>Teacher</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($classSubjects as $cs): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($cs['subject_name']) ?></td>
                                            <td>
                                                <select name="assignments[<?= $cs['id'] ?>]" class="form-select form-select-sm">
                                                    <option value="">-- Not assigned --</option>
                                                    <?php foreach ($teachers as $teacher): ?>
                                                    <option value="<?= $teacher['teacher_id'] ?>" 
                                                        <?= $cs['teacher_id'] == $teacher['teacher_id'] ? 'selected' : '' ?>>
                                                        <?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <a href="?class_id=<?= $classId ?>&delete_assignment=<?= $cs['id'] ?>" 
                                                   class="btn btn-sm btn-outline-danger"
                                                   onclick="return confirm('Are you sure you want to remove this subject from the class?')">
                                                    <i class="bi bi-trash"></i> Remove
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <div class="text-end">
                                    <button type="submit" name="update_assignments" class="btn btn-primary">
                                        <i class="bi bi-save"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($availableSubjects)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5>Assign New Subject</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label for="subject_id" class="form-label">Subject *</label>
                                    <select class="form-select" id="subject_id" name="subject_id" required>
                                        <option value="">-- Select Subject --</option>
                                        <?php foreach ($availableSubjects as $subject): ?>
                                        <option value="<?= $subject['subject_id'] ?>">
                                            <?= htmlspecialchars($subject['subject_name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label for="teacher_id" class="form-label">Teacher *</label>
                                    <select class="form-select" id="teacher_id" name="teacher_id" required>
                                        <option value="">-- Select Teacher --</option>
                                        <?php foreach ($teachers as $teacher): ?>
                                        <option value="<?= $teacher['teacher_id'] ?>">
                                            <?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="submit" name="assign_subject" class="btn btn-primary w-100">
                                        <i class="bi bi-plus-circle"></i> Assign
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>