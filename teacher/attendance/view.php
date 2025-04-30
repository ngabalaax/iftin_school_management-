<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/session.php';
require_once __DIR__ . '/../../config/database.php';

// Verify teacher access
if ($_SESSION['user_role'] !== 'teacher') {
    header('HTTP/1.0 403 Forbidden');
    die('Access denied');
}

// Get class ID from URL
$class_id = $_GET['class_id'] ?? null;

// Verify teacher has access to this class
$stmt = $db->prepare("
    SELECT 1 FROM class_subjects 
    WHERE class_id = ? AND teacher_id = ?
    LIMIT 1
");
$stmt->execute([$class_id, $_SESSION['teacher_id']]);
if (!$stmt->fetch()) {
    die('Access to this class denied');
}

// Get attendance records
$stmt = $db->prepare("
    SELECT a.date, 
           COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present,
           COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent,
           COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late
    FROM attendance a
    WHERE a.class_id = ?
    GROUP BY a.date
    ORDER BY a.date DESC
    LIMIT 30
");
$stmt->execute([$class_id]);
$attendance = $stmt->fetchAll();

// Get class name
$stmt = $db->prepare("SELECT class_name FROM classes WHERE class_id = ?");
$stmt->execute([$class_id]);
$class = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - <?= htmlspecialchars($class['class_name']) ?> | Iftin School</title>
    <style>
        .attendance-badge {
            width: 70px;
            text-align: center;
        }
    </style>
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
</head>

<body>
    <?php include '../navbar.php'; ?>
    <?php include '../sidebar.php'; ?>

    <div class="main-content-teacher">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Attendance - <?= htmlspecialchars($class['class_name']) ?></h2>
                <div>
                    <a href="take.php?class_id=<?= $class_id ?>" class="btn btn-primary">
                        <i class="fas fa-clipboard-check"></i> Take Attendance
                    </a>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body">
                    <?php if (empty($attendance)): ?>
                        <div class="alert alert-info">No attendance records found</div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Present</th>
                                        <th>Absent</th>
                                        <th>Late</th>
                                        <th>Rate</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance as $record):
                                        $total = $record['present'] + $record['absent'] + $record['late'];
                                        $rate = round(($record['present'] / $total) * 100);
                                        ?>
                                        <tr>
                                            <td><?= date('M j, Y', strtotime($record['date'])) ?></td>
                                            <td>
                                                <span class="badge bg-success attendance-badge">
                                                    <?= $record['present'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-danger attendance-badge">
                                                    <?= $record['absent'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-warning text-dark attendance-badge">
                                                    <?= $record['late'] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar bg-success" role="progressbar"
                                                        style="width: <?= $rate ?>%" aria-valuenow="<?= $rate ?>"
                                                        aria-valuemin="0" aria-valuemax="100">
                                                        <?= $rate ?>%
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <a href="detail.php?class_id=<?= $class_id ?>&date=<?= $record['date'] ?>"
                                                    class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-list"></i> Details
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
        </div>
    </div>

    <?php include __DIR__ . '/../../../includes/footer_assets.php'; ?>
</body>

</html>