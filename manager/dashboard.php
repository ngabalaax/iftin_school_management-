<?php
require_once __DIR__ . '../../includes/auth_check.php';
include __DIR__ . '/others/sidebar.php';

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
try {
    // Get statistics for dashboard
    $stats = [
        'total_students' => $db->query("SELECT COUNT(*) FROM students")->fetchColumn(),
        'total_teachers' => $db->query("SELECT COUNT(*) FROM teachers")->fetchColumn(),
        'total_parents' => $db->query("SELECT COUNT(*) FROM parents")->fetchColumn(),
        'active_classes' => $db->query("SELECT COUNT(*) FROM classes WHERE academic_year_id = (SELECT year_id FROM academic_years WHERE is_current = 1)")->fetchColumn()
    ];
    // Get recent activity
    $recentActivity = $db->query("
        SELECT a.*, u.first_name, u.last_name 
        FROM audit_log a
        JOIN users u ON a.user_id = u.user_id
        ORDER BY created_at DESC 
        LIMIT 5
    ")->fetchAll();

    // Get attendance summary for the week
    $attendanceSummary = $db->query("
        SELECT 
            DATE(date) as day, 
            COUNT(CASE WHEN status = 'present' THEN 1 END) as present,
            COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent,
            COUNT(CASE WHEN status = 'late' THEN 1 END) as late,
            COUNT(*) as total
        FROM attendance 
        WHERE date BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND CURDATE()
        GROUP BY DATE(date)
        ORDER BY DATE(date)
    ")->fetchAll();

} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    flash('error', 'Unable to load dashboard data. Please try again.');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manager Dashboard | Iftin School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
</head>

<body>
    <?php include './others/navbar.php'; ?>
    <?php '/others/sidebar.php'; ?>
    <div class="main-content-teacher">
        <div class="container-fluid">
            <div class="row">

                <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                    <div
                        class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                        <h1 class="h2">Dashboard</h1>
                        <div class="btn-toolbar mb-2 mb-md-0">
                            <div class="btn-group me-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary">Export</button>
                            </div>
                        </div>
                    </div>
                    <!-- Summary Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card text-white bg-primary">
                                <div class="card-body">
                                    <h5 class="card-title">Students</h5>
                                    <h2 class="card-text"><?= $stats['total_students'] ?></h2>
                                    <a href="users/list.php?role=student" class="text-white">View All</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-white bg-success">
                                <div class="card-body">
                                    <h5 class="card-title">Teachers</h5>
                                    <h2 class="card-text"><?= $stats['total_teachers'] ?></h2>
                                    <a href="users/list.php?role=teacher" class="text-white">View All</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-white bg-info">
                                <div class="card-body">
                                    <h5 class="card-title">Parents</h5>
                                    <h2 class="card-text"><?= $stats['total_parents'] ?></h2>
                                    <a href="users/list.php?role=parent" class="text-white">View All</a>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card text-white bg-warning">
                                <div class="card-body">
                                    <h5 class="card-title">Classes</h5>
                                    <h2 class="card-text"><?= $stats['active_classes'] ?></h2>
                                    <a href="classes/manage.php" class="text-white">Manage</a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Attendance Overview -->
                    <div class="row mb-4">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Weekly Attendance Overview</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="attendanceChart" height="250"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h5>Attendance Summary</h5>
                                </div>
                                <div class="card-body">
                                    <canvas id="attendancePieChart" height="250"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>Recent Activity</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped table-sm">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentActivity as $activity): ?>
                                            <tr>
                                                <td><?= date('M j, H:i', strtotime($activity['created_at'])) ?></td>
                                                <td><?= htmlspecialchars($activity['first_name'] . ' ' . htmlspecialchars($activity['last_name'])) ?>
                                                </td>
                                                <td><?= htmlspecialchars($activity['action']) ?></td>
                                                <td><?= htmlspecialchars(substr($activity['table_name'] . ' #' . $activity['record_id'], 0, 30)) ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script>
        // Attendance Line Chart
        const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
        const attendanceChart = new Chart(attendanceCtx, {
            type: 'line',
            data: {
                labels: [<?php foreach ($attendanceSummary as $day): ?>"<?= date('D, M j', strtotime($day['day'])) ?>", <?php endforeach; ?>],
                datasets: [
                    {
                        label: 'Present',
                        data: [<?php foreach ($attendanceSummary as $day): ?><?= $day['present'] ?>, <?php endforeach; ?>],
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        tension: 0.1,
                        fill: true
                    },
                    {
                        label: 'Absent',
                        data: [<?php foreach ($attendanceSummary as $day): ?><?= $day['absent'] ?>, <?php endforeach; ?>],
                        borderColor: 'rgba(255, 99, 132, 1)',
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        tension: 0.1,
                        fill: true
                    },
                    {
                        label: 'Late',
                        data: [<?php foreach ($attendanceSummary as $day): ?><?= $day['late'] ?>, <?php endforeach; ?>],
                        borderColor: 'rgba(255, 206, 86, 1)',
                        backgroundColor: 'rgba(255, 206, 86, 0.2)',
                        tension: 0.1,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        suggestedMax: Math.max(...[<?php foreach ($attendanceSummary as $day): ?><?= $day['total'] ?>, <?php endforeach; ?>]) + 5
                    }
                }
            }
        });

        // Attendance Pie Chart
        const pieCtx = document.getElementById('attendancePieChart').getContext('2d');
        const pieChart = new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Absent', 'Late'],
                datasets: [{
                    data: [
                        <?= array_sum(array_column($attendanceSummary, 'present')) ?>,
                        <?= array_sum(array_column($attendanceSummary, 'absent')) ?>,
                        <?= array_sum(array_column($attendanceSummary, 'late')) ?>
                    ],
                    backgroundColor: [
                        'rgba(75, 192, 192, 0.7)',
                        'rgba(255, 99, 132, 0.7)',
                        'rgba(255, 206, 86, 0.7)'
                    ],
                    borderColor: [
                        'rgba(75, 192, 192, 1)',
                        'rgba(255, 99, 132, 1)',
                        'rgba(255, 206, 86, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                    }
                }
            }
        });
    </script>
</body>

</html>