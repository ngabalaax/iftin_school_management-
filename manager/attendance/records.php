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
// Get all classes for filter
$classes = $db->query("
    SELECT c.class_id, c.class_name 
    FROM classes c
    JOIN academic_years ay ON c.academic_year_id = ay.year_id
    WHERE ay.is_current = 1
    ORDER BY c.class_name
")->fetchAll();

// Default filter values
$filters = [
    'class_id' => $_GET['class_id'] ?? '',
    'date_from' => $_GET['date_from'] ?? date('Y-m-01'),
    'date_to' => $_GET['date_to'] ?? date('Y-m-d'),
    'status' => $_GET['status'] ?? ''
];

// Build base query
$query = "
    SELECT 
        a.attendance_id,
        a.date,
        a.status,
        a.notes,
        s.student_id,
        CONCAT(u.first_name, ' ', u.last_name) AS student_name,
        c.class_name,
        CONCAT(t.first_name, ' ', t.last_name) AS recorded_by
    FROM attendance a
    JOIN students s ON a.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    JOIN classes c ON a.class_id = c.class_id
    JOIN users t ON a.recorded_by = t.user_id
    WHERE a.date BETWEEN :date_from AND :date_to
";

$params = [
    ':date_from' => $filters['date_from'],
    ':date_to' => $filters['date_to']
];

// Add class filter if specified
if (!empty($filters['class_id']) && is_numeric($filters['class_id'])) {
    $query .= " AND a.class_id = :class_id";
    $params[':class_id'] = $filters['class_id'];
}

// Add status filter if specified
if (!empty($filters['status']) && in_array($filters['status'], ['present', 'absent', 'late', 'excused'])) {
    $query .= " AND a.status = :status";
    $params[':status'] = $filters['status'];
}

// Complete query with ordering
$query .= " ORDER BY a.date DESC, c.class_name, u.last_name, u.first_name";

// Get attendance records
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$attendanceRecords = $stmt->fetchAll();

// Handle attendance status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_attendance'])) {
    try {
        $db->beginTransaction();
        
        $attendanceId = (int)$_POST['attendance_id'];
        $newStatus = $_POST['status'];
        $notes = trim($_POST['notes']);
        
        // Validate status
        if (!in_array($newStatus, ['present', 'absent', 'late', 'excused'])) {
            throw new Exception("Invalid attendance status");
        }
        
        // Update attendance record
        $stmt = $db->prepare("
            UPDATE attendance SET 
            status = ?, 
            notes = ?, 
            recorded_by = ?
            WHERE attendance_id = ?
        ");
        $stmt->execute([
            $newStatus,
            $notes,
            $session->get('user_id'),
            $attendanceId
        ]);
        
        $db->commit();
        $_SESSION['flash_success'] = 'Attendance record updated successfully';
        header("Location: records.php?" . http_build_query($filters));
        exit();
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['flash_error'] = 'Error updating attendance: ' . $e->getMessage();
        header("Location: records.php?" . http_build_query($filters));
        exit();
    }
}

// Handle attendance deletion
if (isset($_GET['delete'])) {
    try {
        $attendanceId = (int)$_GET['delete'];
        
        $db->beginTransaction();
        $stmt = $db->prepare("DELETE FROM attendance WHERE attendance_id = ?");
        $stmt->execute([$attendanceId]);
        $db->commit();
        
        $_SESSION['flash_success'] = 'Attendance record deleted successfully';
        header("Location: records.php?" . http_build_query($filters));
        exit();
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['flash_error'] = 'Error deleting attendance record: ' . $e->getMessage();
        header("Location: records.php?" . http_build_query($filters));
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Records | Iftin School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .status-present { background-color: #d4edda !important; }
        .status-absent { background-color: #f8d7da !important; }
        .status-late { background-color: #fff3cd !important; }
        .status-excused { background-color: #e2e3e5 !important; }
        .badge-present { background-color: #28a745; }
        .badge-absent { background-color: #dc3545; }
        .badge-late { background-color: #ffc107; color: #000; }
        .badge-excused { background-color: #6c757d; }
    </style>
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
</head>
<body>    
<?php include './others/navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../others/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Attendance Records</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="overview.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-bar-chart"></i> View Overview
                        </a>
                    </div>
                </div>

                <?php if ($message = $_SESSION['flash_success'] ?? null): ?>
                    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
                    <?php unset($_SESSION['flash_success']); ?>
                <?php endif; ?>
                <?php if ($message = $_SESSION['flash_error'] ?? null): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($message) ?></div>
                    <?php unset($_SESSION['flash_error']); ?>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Filter Records</h5>
                    </div>
                    <div class="card-body">
                        <form method="get" class="row g-3">
                            <div class="col-md-3">
                                <label for="class_id" class="form-label">Class</label>
                                <select id="class_id" name="class_id" class="form-select">
                                    <option value="">All Classes</option>
                                    <?php foreach ($classes as $class): ?>
                                    <option value="<?= $class['class_id'] ?>" <?= $filters['class_id'] == $class['class_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($class['class_name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-2">
                                <label for="date_from" class="form-label">From Date</label>
                                <input type="date" class="form-control" id="date_from" name="date_from" 
                                       value="<?= htmlspecialchars($filters['date_from']) ?>">
                            </div>
                            
                            <div class="col-md-2">
                                <label for="date_to" class="form-label">To Date</label>
                                <input type="date" class="form-control" id="date_to" name="date_to" 
                                       value="<?= htmlspecialchars($filters['date_to']) ?>">
                            </div>
                            
                            <div class="col-md-3">
                                <label for="status" class="form-label">Status</label>
                                <select id="status" name="status" class="form-select">
                                    <option value="">All Statuses</option>
                                    <option value="present" <?= $filters['status'] === 'present' ? 'selected' : '' ?>>Present</option>
                                    <option value="absent" <?= $filters['status'] === 'absent' ? 'selected' : '' ?>>Absent</option>
                                    <option value="late" <?= $filters['status'] === 'late' ? 'selected' : '' ?>>Late</option>
                                    <option value="excused" <?= $filters['status'] === 'excused' ? 'selected' : '' ?>>Excused</option>
                                </select>
                            </div>
                            
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Attendance Records -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Attendance Records</h5>
                        <div>
                            <span class="badge badge-present me-2">Present</span>
                            <span class="badge badge-absent me-2">Absent</span>
                            <span class="badge badge-late me-2">Late</span>
                            <span class="badge badge-excused">Excused</span>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <?php if (empty($attendanceRecords)): ?>
                            <div class="alert alert-info">No attendance records found matching your criteria.</div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Student</th>
                                            <th>Class</th>
                                            <th>Status</th>
                                            <th>Notes</th>
                                            <th>Recorded By</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attendanceRecords as $record): ?>
                                        <tr class="status-<?= $record['status'] ?>">
                                            <td><?= date('M j, Y', strtotime($record['date'])) ?></td>
                                            <td><?= htmlspecialchars($record['student_name']) ?></td>
                                            <td><?= htmlspecialchars($record['class_name']) ?></td>
                                            <td>
                                                <span class="badge badge-<?= $record['status'] ?>">
                                                    <?= ucfirst($record['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($record['notes']) ?></td>
                                            <td><?= htmlspecialchars($record['recorded_by']) ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" 
                                                        data-bs-target="#editModal" 
                                                        data-id="<?= $record['attendance_id'] ?>"
                                                        data-status="<?= $record['status'] ?>"
                                                        data-notes="<?= htmlspecialchars($record['notes']) ?>">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </button>
                                                <a href="records.php?<?= http_build_query($filters) ?>&delete=<?= $record['attendance_id'] ?>" 
                                                   class="btn btn-sm btn-outline-danger"
                                                   onclick="return confirm('Are you sure you want to delete this attendance record?')">
                                                    <i class="bi bi-trash"></i> Delete
                                                </a>
                                            </td>
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

    <!-- Edit Modal -->
    <div class="modal fade" id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="attendance_id" id="modal_attendance_id">
                    <input type="hidden" name="update_attendance" value="1">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel">Edit Attendance Record</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="modal_status" class="form-label">Status</label>
                            <select class="form-select" id="modal_status" name="status" required>
                                <option value="present">Present</option>
                                <option value="absent">Absent</option>
                                <option value="late">Late</option>
                                <option value="excused">Excused</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="modal_notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="modal_notes" name="notes" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle edit modal
        const editModal = document.getElementById('editModal');
        if (editModal) {
            editModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const attendanceId = button.getAttribute('data-id');
                const status = button.getAttribute('data-status');
                const notes = button.getAttribute('data-notes');
                
                document.getElementById('modal_attendance_id').value = attendanceId;
                document.getElementById('modal_status').value = status;
                document.getElementById('modal_notes').value = notes;
            });
        }
        
        // Apply badge classes
        document.querySelectorAll('.badge-present').forEach(el => {
            el.classList.add('bg-success');
        });
        document.querySelectorAll('.badge-absent').forEach(el => {
            el.classList.add('bg-danger');
        });
        document.querySelectorAll('.badge-late').forEach(el => {
            el.classList.add('bg-warning', 'text-dark');
        });
        document.querySelectorAll('.badge-excused').forEach(el => {
            el.classList.add('bg-secondary');
        });
    </script>
</body>
</html>