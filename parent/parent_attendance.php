<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

if ($session->get('user_role') !== 'parent') {
    header('HTTP/1.0 403 Forbidden');
    die('Access denied');
}

// Verify parent has access to this child
$stmt = $db->prepare("
    SELECT s.student_id, u.first_name, u.last_name, c.class_name
    FROM parent_student ps
    JOIN students s ON ps.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN classes c ON s.current_class_id = c.class_id
    WHERE ps.parent_id = (SELECT parent_id FROM parents WHERE user_id = ?)
    AND s.student_id = ?
");
$stmt->execute([$session->get('user_id'), $_GET['child_id']]);
$child = $stmt->fetch();

if (!$child) {
    die('Child not found or access denied');
}

// Get attendance records
$stmt = $db->prepare("
    SELECT a.date, a.status, a.notes, 
           s.subject_name, 
           CONCAT(u.first_name, ' ', u.last_name) as teacher_name
    FROM attendance a
    LEFT JOIN class_subjects cs ON a.class_subject_id = cs.id
    LEFT JOIN subjects s ON cs.subject_id = s.subject_id
    LEFT JOIN users u ON a.recorded_by = u.user_id
    WHERE a.student_id = ?
    ORDER BY a.date DESC
");
$stmt->execute([$child['student_id']]);
$attendance = $stmt->fetchAll();

// Get attendance summary by month
$stmt = $db->prepare("
    SELECT 
        DATE_FORMAT(date, '%Y-%m') as month,
        COUNT(CASE WHEN status = 'present' THEN 1 END) as present,
        COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent,
        COUNT(CASE WHEN status = 'late' THEN 1 END) as late,
        COUNT(CASE WHEN status = 'excused' THEN 1 END) as excused,
        COUNT(*) as total
    FROM attendance
    WHERE student_id = ?
    GROUP BY DATE_FORMAT(date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 6
");
$stmt->execute([$child['student_id']]);
$monthlySummary = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance | Iftin School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <style>
        .status-badge {
            padding: 0.35em 0.65em;
            font-size: 0.75em;
        }

        .attendance-table th {
            white-space: nowrap;
        }

        .month-card {
            transition: all 0.2s;
        }

        .month-card:hover {
            transform: translateY(-3px);
        }
    </style>
</head>

<body>
    <?php include './navbar.php'; ?>
    <?php include './sidebar.php'; ?>
    <div class="main-content-parent">
        <div class="container-fluid">
            <div class="row">
                <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
                    <div
                        class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h2>Attendance Record</h2>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <span class="me-2">Viewing:</span>
                            <strong><?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?></strong>
                            <span
                                class="badge bg-primary ms-2"><?= htmlspecialchars($child['class_name'] ?? 'No Class') ?></span>
                        </div>
                    </div>

                    <!-- Monthly Summary -->
                    <div class="row mb-4">
                        <?php foreach ($monthlySummary as $month):
                            $monthName = date("F Y", strtotime($month['month'] . '-01'));
                            $presentPercent = $month['total'] > 0 ? round(($month['present'] / $month['total']) * 100) : 0;
                            ?>
                            <div class="col-md-4 col-lg-2 mb-3">
                                <div class="card month-card h-100">
                                    <div class="card-body text-center">
                                        <h6 class="card-title"><?= $monthName ?></h6>
                                        <div class="progress mb-2" style="height: 20px;">
                                            <div class="progress-bar bg-success" role="progressbar"
                                                style="width: <?= $presentPercent ?>%"
                                                aria-valuenow="<?= $presentPercent ?>" aria-valuemin="0"
                                                aria-valuemax="100">
                                                <?= $presentPercent ?>%
                                            </div>
                                        </div>
                                        <div class="d-flex justify-content-around small">
                                            <span class="text-success">
                                                <i class="fas fa-check"></i> <?= $month['present'] ?>
                                            </span>
                                            <span class="text-danger">
                                                <i class="fas fa-times"></i> <?= $month['absent'] ?>
                                            </span>
                                            <span class="text-warning">
                                                <i class="fas fa-clock"></i> <?= $month['late'] ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Detailed Records -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Detailed Attendance</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($attendance)): ?>
                                <div class="alert alert-info">No attendance records found</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover attendance-table">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Status</th>
                                                <th>Subject</th>
                                                <th>Teacher</th>
                                                <th>Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($attendance as $record): ?>
                                                <tr>
                                                    <td><?= date("M j, Y", strtotime($record['date'])) ?></td>
                                                    <td>
                                                        <?php
                                                        $badgeClass = '';
                                                        if ($record['status'] === 'present')
                                                            $badgeClass = 'bg-success';
                                                        if ($record['status'] === 'absent')
                                                            $badgeClass = 'bg-danger';
                                                        if ($record['status'] === 'late')
                                                            $badgeClass = 'bg-warning text-dark';
                                                        if ($record['status'] === 'excused')
                                                            $badgeClass = 'bg-info';
                                                        ?>
                                                        <span class="badge <?= $badgeClass ?> status-badge">
                                                            <?= ucfirst($record['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td><?= htmlspecialchars($record['subject_name'] ?? 'General') ?></td>
                                                    <td><?= htmlspecialchars($record['teacher_name'] ?? 'System') ?></td>
                                                    <td><?= htmlspecialchars($record['notes'] ?? '') ?></td>
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
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>