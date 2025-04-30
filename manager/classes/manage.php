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

if ($session->get('user_role') !== 'manager') {
    header('HTTP/1.0 403 Forbidden');
    die('You do not have permission to access this page.');
}



// Handle class deletion
if (isset($_GET['delete'])) {
    $classId = (int)$_GET['delete'];
    
    try {
        $db->beginTransaction();
        
        // Check if class has students
        $stmt = $db->prepare("SELECT COUNT(*) FROM enrollments WHERE class_id = ?");
        $stmt->execute([$classId]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("Cannot delete class with enrolled students");
        }
        
        // Delete class subjects first
        $stmt = $db->prepare("DELETE FROM class_subjects WHERE class_id = ?");
        $stmt->execute([$classId]);
        
        // Then delete the class
        $stmt = $db->prepare("DELETE FROM classes WHERE class_id = ?");
        $stmt->execute([$classId]);
        
        $db->commit();
        $_SESSION['flash_success'] = 'Class deleted successfully';
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['flash_error'] = $e->getMessage();
    }
    
    header("Location: manage.php");
    exit();
}

// Get current academic year
$currentYear = $db->query("SELECT year_id FROM academic_years WHERE is_current = 1")->fetchColumn();

// Get all classes for current academic year
$classes = $db->query("
    SELECT c.*, CONCAT(u.first_name, ' ', u.last_name) as teacher_name
    FROM classes c
    LEFT JOIN teachers t ON c.class_teacher_id = t.teacher_id
    LEFT JOIN users u ON t.user_id = u.user_id
    WHERE c.academic_year_id = $currentYear
    ORDER BY c.class_name
")->fetchAll();

// Get all available teachers
$teachers = $db->query("
    SELECT t.teacher_id, u.first_name, u.last_name 
    FROM teachers t
    JOIN users u ON t.user_id = u.user_id
    ORDER BY u.last_name, u.first_name
")->fetchAll();

// Handle class creation/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $classData = [
        'class_name' => trim($_POST['class_name']),
        'class_teacher_id' => $_POST['class_teacher_id'] ? (int)$_POST['class_teacher_id'] : null,
        'room_number' => trim($_POST['room_number']),
        'capacity' => (int)$_POST['capacity']
    ];
    
    $errors = [];
    
    // Validate
    if (empty($classData['class_name'])) {
        $errors['class_name'] = "Class name is required";
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            if (isset($_POST['class_id']) && $_POST['class_id']) {
                // Update existing class
                $stmt = $db->prepare("
                    UPDATE classes SET 
                    class_name = ?, class_teacher_id = ?, room_number = ?, capacity = ?
                    WHERE class_id = ?
                ");
                $stmt->execute([
                    $classData['class_name'],
                    $classData['class_teacher_id'],
                    $classData['room_number'],
                    $classData['capacity'],
                    (int)$_POST['class_id']
                ]);
                
                $_SESSION['flash_success'] = 'Class updated successfully';
            } else {
                // Create new class
                $stmt = $db->prepare("
                    INSERT INTO classes 
                    (class_name, academic_year_id, class_teacher_id, room_number, capacity)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $classData['class_name'],
                    $currentYear,
                    $classData['class_teacher_id'],
                    $classData['room_number'],
                    $classData['capacity']
                ]);
                
                $_SESSION['flash_success'] = 'Class created successfully';
            }
            
            $db->commit();
            header("Location: manage.php");
            exit();
        } catch (PDOException $e) {
            $db->rollBack();
            $_SESSION['flash_error'] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get class data for editing if ID is provided
$editClass = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("
        SELECT * FROM classes WHERE class_id = ?
    ");
    $stmt->execute([(int)$_GET['edit']]);
    $editClass = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Classes | Iftin School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../../assets/css/sidebar.css" rel="stylesheet">
</head>
<body>
<?php include './others/navbar.php'; ?>
<div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../others/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Manage Classes</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#classModal">
                            <i class="bi bi-plus-circle"></i> Add New Class
                        </button>
                    </div>
                </div>

                <?php if (isset($_SESSION['flash_success'])): ?>
                    <?php $message = $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?>
                    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['flash_error'])): ?>
                    <?php $message = $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
                <?php endif; ?>

                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Class Name</th>
                                <th>Class Teacher</th>
                                <th>Room</th>
                                <th>Capacity</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($classes as $class): ?>
                            <tr>
                                <td><?= htmlspecialchars($class['class_name']) ?></td>
                                <td><?= $class['teacher_name'] ?? 'Not assigned' ?></td>
                                <td><?= htmlspecialchars($class['room_number']) ?></td>
                                <td><?= $class['capacity'] ?></td>
                                <td>
                                    <a href="?edit=<?= $class['class_id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    <a href="assign.php?class_id=<?= $class['class_id'] ?>" class="btn btn-sm btn-outline-info">
                                        <i class="bi bi-book"></i> Subjects
                                    </a>
                                    <a href="?delete=<?= $class['class_id'] ?>" class="btn btn-sm btn-outline-danger" 
                                       onclick="return confirm('Are you sure you want to delete this class?')">
                                        <i class="bi bi-trash"></i> Delete
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <!-- Class Modal -->
    <div class="modal fade" id="classModal" tabindex="-1" aria-labelledby="classModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="classModalLabel">
                            <?= $editClass ? 'Edit Class' : 'Add New Class' ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="class_id" value="<?= $editClass['class_id'] ?? '' ?>">
                        
                        <div class="mb-3">
                            <label for="class_name" class="form-label">Class Name *</label>
                            <input type="text" class="form-control" id="class_name" name="class_name" 
                                   value="<?= htmlspecialchars($editClass['class_name'] ?? '') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="class_teacher_id" class="form-label">Class Teacher</label>
                            <select class="form-select" id="class_teacher_id" name="class_teacher_id">
                                <option value="">-- Select Teacher --</option>
                                <?php foreach ($teachers as $teacher): ?>
                                <option value="<?= $teacher['teacher_id'] ?>" 
                                    <?= ($editClass['class_teacher_id'] ?? '') == $teacher['teacher_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="room_number" class="form-label">Room Number</label>
                            <input type="text" class="form-control" id="room_number" name="room_number" 
                                   value="<?= htmlspecialchars($editClass['room_number'] ?? '') ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="capacity" class="form-label">Capacity</label>
                            <input type="number" class="form-control" id="capacity" name="capacity" 
                                   value="<?= $editClass['capacity'] ?? 30 ?>" min="1">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">
                            <?= $editClass ? 'Update Class' : 'Create Class' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show modal if editing or if there are errors
        <?php if (isset($_GET['edit']) || !empty($errors)): ?>
            document.addEventListener('DOMContentLoaded', function() {
                var modal = new bootstrap.Modal(document.getElementById('classModal'));
                modal.show();
            });
        <?php endif; ?>
    </script>
</body>
</html>