<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';

// Verify teacher access
if ($_SESSION['user_role'] !== 'teacher') {
    header('HTTP/1.0 403 Forbidden');
    die('Access denied');
}

// Get teacher's classes
$stmt = $db->prepare("
    SELECT cs.id as class_subject_id, c.class_name, s.subject_name 
    FROM class_subjects cs
    JOIN classes c ON cs.class_id = c.class_id
    JOIN subjects s ON cs.subject_id = s.subject_id
    WHERE cs.teacher_id = (
        SELECT teacher_id FROM teachers WHERE user_id = ?
    )
    ORDER BY c.class_name, s.subject_name
");
$stmt->execute([$_SESSION['user_id']]);
$classes = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Validate inputs
    if (empty($_POST['title'])) {
        $errors['title'] = 'Assignment title is required';
    }
    if (empty($_POST['class_subject_id'])) {
        $errors['class_subject_id'] = 'Class/subject selection is required';
    }
    if (empty($_POST['due_date'])) {
        $errors['due_date'] = 'Due date is required';
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            $stmt = $db->prepare("
                INSERT INTO assignments 
                (title, description, due_date, class_subject_id, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $_POST['title'],
                $_POST['description'] ?? null,
                $_POST['due_date'],
                $_POST['class_subject_id']
            ]);
            
            $assignment_id = $db->lastInsertId();
            
            // Create grade entries for all students in the class
            $stmt = $db->prepare("
                INSERT INTO grades (assignment_id, student_id, status)
                SELECT ?, e.student_id, 'pending'
                FROM enrollments e
                JOIN class_subjects cs ON e.class_id = cs.class_id
                WHERE cs.id = ?
            ");
            $stmt->execute([$assignment_id, $_POST['class_subject_id']]);
            
            $db->commit();
            
            $_SESSION['success_message'] = 'Assignment created successfully!';
            header("Location: manage.php?assignment_id=$assignment_id");
            exit();
        } catch (PDOException $e) {
            $db->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Assignment | Iftin School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/sidebar.css" rel="stylesheet">
    <style>
        .main-content-teacher {
            margin-left: 220px;
            padding: 20px;
        }
        .form-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .form-section {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 25px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include '../navbar.php'; ?>
    <?php include '../sidebar.php'; ?>
    
    <div class="main-content-teacher">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Create New Assignment</h2>
                <a href="assignments.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Assignments
                </a>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <strong>Please fix the following errors:</strong>
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <form method="POST" class="form-section">
                    <div class="mb-3">
                        <label for="title" class="form-label">Assignment Title *</label>
                        <input type="text" class="form-control" id="title" name="title" 
                               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" 
                                  rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="class_subject_id" class="form-label">Class/Subject *</label>
                            <select class="form-select" id="class_subject_id" name="class_subject_id" required>
                                <option value="">Select Class/Subject</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?= $class['class_subject_id'] ?>"
                                        <?= isset($_POST['class_subject_id']) && $_POST['class_subject_id'] == $class['class_subject_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($class['class_name']) ?> - <?= htmlspecialchars($class['subject_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="due_date" class="form-label">Due Date *</label>
                            <input type="date" class="form-control" id="due_date" name="due_date" 
                                   value="<?= htmlspecialchars($_POST['due_date'] ?? '') ?>" required
                                   min="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="reset" class="btn btn-outline-secondary me-md-2">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Create Assignment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set minimum date to today
        document.getElementById('due_date').min = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>