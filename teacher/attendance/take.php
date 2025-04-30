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

if (!isset($_GET['class_id'])) {
    $_SESSION['error'] = 'Class ID not specified';
    header("Location: ../dashboard.php");
    exit();
}

$classId = (int)$_GET['class_id'];
$db = getDBConnection();

// Get teacher ID
$stmt = $db->prepare("
    SELECT teacher_id FROM teachers WHERE user_id = ?
");
$stmt->execute([$session->get('user_id')]);
$teacherId = $stmt->fetchColumn();

// Verify teacher is assigned to this class
$isValidClassStmt = $db->prepare("
    SELECT 1 FROM class_subjects 
    WHERE class_id = ? AND teacher_id = ?
");
$isValidClassStmt->execute([$classId, $teacherId]);
$isValidClass = $isValidClassStmt->fetchColumn();


if (!$isValidClass) {
    $_SESSION['error'] = 'You are not assigned to this class';
    header("Location: ../dashboard.php");
    exit();
}

// Get class details
$classStmt = $db->prepare("
    SELECT c.class_name, s.subject_name 
    FROM classes c
    JOIN class_subjects cs ON c.class_id = cs.class_id
    JOIN subjects s ON cs.subject_id = s.subject_id
    WHERE c.class_id = ? AND cs.teacher_id = ?
");
$classStmt->execute([$classId, $teacherId]);
$class = $classStmt->fetch();


// Get students in this class
$stmt = $db->prepare("
    SELECT s.student_id, u.first_name, u.last_name, 
           (SELECT status FROM attendance 
            WHERE student_id = s.student_id AND class_id = ? 
            AND date = CURDATE() LIMIT 1) as today_status
    FROM enrollments e
    JOIN students s ON e.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    WHERE e.class_id = ?
    ORDER BY u.last_name, u.first_name
");
$stmt->execute([$classId, $classId]);
$students = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        // Get current date
        $today = date('Y-m-d');
        
        // Process each student's attendance
        foreach ($students as $student) {
            $studentId = $student['student_id'];
            $status = $_POST['attendance'][$studentId] ?? 'absent';
            $notes = trim($_POST['notes'][$studentId] ?? '');
            
            // Check if record already exists for today
            $existing = $db->prepare("
                SELECT attendance_id FROM attendance 
                WHERE student_id = ? AND class_id = ? AND date = ?
            ");
            $stmt->execute([$studentId, $classId, $today]);
            $existing = $stmt->fetchColumn();
            
            if ($existing) {
                // Update existing record
                $stmt = $db->prepare("
                    UPDATE attendance SET 
                    status = ?, notes = ?, recorded_by = ?
                    WHERE attendance_id = ?
                ");
                $stmt->execute([
                    $status,
                    $notes,
                    $session->get('user_id'),
                    $existing
                ]);
            } else {
                // Create new record
                $stmt = $db->prepare("
                    INSERT INTO attendance 
                    (student_id, class_id, date, status, notes, recorded_by)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $studentId,
                    $classId,
                    $today,
                    $status,
                    $notes,
                    $session->get('user_id')
                ]);
            }
        }
        
        $db->commit();
        $_SESSION['success'] = 'Attendance recorded successfully!';
        header("Location: view.php?class_id=$classId");
        exit();
    } catch (PDOException $e) {
        $db->rollBack();
        $_SESSION['error'] = 'Error recording attendance: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Attendance | Iftin School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .attendance-option {
            cursor: pointer;
        }
        .attendance-option.selected {
            font-weight: bold;
            border-bottom: 2px solid #0d6efd;
        }
        .status-present { background-color: #d4edda !important; }
        .status-absent { background-color: #f8d7da !important; }
        .status-late { background-color: #fff3cd !important; }
        .status-excused { background-color: #e2e3e5 !important; }
    </style>
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
</head>
<body>    
    <div class="container-fluid">
        <div class="row">
            
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Take Attendance</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="../dashboard.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5>
                            <?= htmlspecialchars($class['class_name']) ?> - 
                            <?= htmlspecialchars($class['subject_name']) ?>
                        </h5>
                        <h6 class="text-muted"><?= date('l, F j, Y') ?></h6>
                    </div>
                    
                    <form method="POST">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th width="200">Status</th>
                                            <th>Notes</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($students as $student): 
                                            $status = $student['today_status'] ?: 'present';
                                        ?>
                                        <tr class="status-<?= $status ?>">
                                            <td>
                                                <?= htmlspecialchars($student['last_name'] . ', ' . $student['first_name']) ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-toggle" data-toggle="buttons">
                                                    <label class="btn btn-outline-success attendance-option <?= $status === 'present' ? 'selected active' : '' ?>">
                                                        <input type="radio" name="attendance[<?= $student['student_id'] ?>]" 
                                                               value="present" <?= $status === 'present' ? 'checked' : '' ?>> Present
                                                    </label>
                                                    <label class="btn btn-outline-danger attendance-option <?= $status === 'absent' ? 'selected active' : '' ?>">
                                                        <input type="radio" name="attendance[<?= $student['student_id'] ?>]" 
                                                               value="absent" <?= $status === 'absent' ? 'checked' : '' ?>> Absent
                                                    </label>
                                                    <label class="btn btn-outline-warning attendance-option <?= $status === 'late' ? 'selected active' : '' ?>">
                                                        <input type="radio" name="attendance[<?= $student['student_id'] ?>]" 
                                                               value="late" <?= $status === 'late' ? 'checked' : '' ?>> Late
                                                    </label>
                                                    <label class="btn btn-outline-secondary attendance-option <?= $status === 'excused' ? 'selected active' : '' ?>">
                                                        <input type="radio" name="attendance[<?= $student['student_id'] ?>]" 
                                                               value="excused" <?= $status === 'excused' ? 'checked' : '' ?>> Excused
                                                    </label>
                                                </div>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm" 
                                                       name="notes[<?= $student['student_id'] ?>]" 
                                                       value="<?= htmlspecialchars($student['notes'] ?? '') ?>" 
                                                       placeholder="Notes (optional)">
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer text-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Save Attendance
                            </button>
                        </div>
                    </form>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Highlight selected attendance option
        document.querySelectorAll('.attendance-option').forEach(option => {
            option.addEventListener('click', function() {
                // Remove selected class from all options in this group
                this.closest('.btn-group').querySelectorAll('.attendance-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                
                // Add selected class to clicked option
                this.classList.add('selected');
                
                // Find the radio input and check it
                const radio = this.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                }
            });
        });
    </script>
</body>
</html>