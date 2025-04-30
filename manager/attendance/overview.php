<?php
require_once __DIR__ . '/../../includes/auth_check.php';
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



// Default to current month
$month = $_GET['month'] ?? date('Y-m');
$classId = $_GET['class_id'] ?? '';

// Validate month format
if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

// Get attendance summary
$query = "
    SELECT 
        DATE(date) as day, 
        COUNT(CASE WHEN status = 'present' THEN 1 END) as present,
        COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent,
        COUNT(CASE WHEN status = 'late' THEN 1 END) as late,
        COUNT(*) as total,
        ROUND(COUNT(CASE WHEN status = 'present' THEN 1 END) / COUNT(*) * 100, 2) as percentage
    FROM attendance 
    WHERE date BETWEEN :start_date AND :end_date
";

$params = [
    ':start_date' => date('Y-m-01', strtotime($month)),
    ':end_date' => date('Y-m-t', strtotime($month))
];

// Add class filter if specified
if (!empty($classId) && is_numeric($classId)) {
    $query .= " AND class_id = :class_id";
    $params[':class_id'] = $classId;
}

$query .= " GROUP BY DATE(date) ORDER BY DATE(date)";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$attendanceData = $stmt->fetchAll();

// Get monthly summary for pie chart
$stmt = $db->prepare("
    SELECT 
        COUNT(CASE WHEN status = 'present' THEN 1 END) as present,
        COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent,
        COUNT(CASE WHEN status = 'late' THEN 1 END) as late,
        COUNT(*) as total
    FROM attendance 
    WHERE date BETWEEN :start_date AND :end_date
");
$stmt->execute($params);
$monthlySummary = $stmt->fetch();

// Get class list for filter
$classes = $db->query("
    SELECT c.class_id, c.class_name 
    FROM classes c
    JOIN academic_years ay ON c.academic_year_id = ay.year_id
    WHERE ay.is_current = 1
    ORDER BY c.class_name
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Overview | Iftin School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
</head>
<body>    
<?php include './others/navbar.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../others/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Attendance Overview</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="records.php" class="btn btn-sm btn-outline-secondary">
                            View Detailed Records
                        </a>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="get" class="row g-3">
                            <div class="col-md-4">
                                <label for="month" class="form-label">Month</label>
                                <input type="month" class="form-control" id="month" name="month" 
                                       value="<?= htmlspecialchars($month) ?>" max="<?= date('Y-m') ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="class_id" class="form-label">Class (Optional)</label>
                                <select id="class_id" name="class_id" class="form-select">
                                    <option value="">All Classes</option>
                                    <?php foreach ($classes as $class): ?>
                                    <option value="<?= $class['class_id'] ?>" <?= $classId == $class['class_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($class['class_name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <a href="overview.php" class="btn btn-outline-secondary w-100">Reset</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-white bg-success">
                            <div class="card-body">
                                <h5 class="card-title">Present</h5>
                                <h2 class="card-text"><?= $monthlySummary['present'] ?></h2>
                                <p class="card-text"><?= round($monthlySummary['present'] / $monthlySummary['total'] * 100, 2) ?>%</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-danger">
                            <div class="card-body">
                                <h5 class="card-title">Absent</h5>
                                <h2 class="card-text"><?= $monthlySummary['absent'] ?></h2>
                                <p class="card-text"><?= round($monthlySummary['absent'] / $monthlySummary['total'] * 100, 2) ?>%</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-white bg-warning">
                            <div class="card-body">
                                <h5 class="card-title">Late</h5>
                                <h2 class="card-text"><?= $monthlySummary['late'] ?></h2>
                                <p class="card-text"><?= round($monthlySummary['late'] / $monthlySummary['total'] * 100, 2) ?>%</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts -->
                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5>Daily Attendance for <?= date('F Y', strtotime($month)) ?></h5>
                            </div>
                            <div class="card-body">
                                <canvas id="attendanceChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5>Monthly Summary</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="attendancePieChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Data Table -->
                <div class="card">
                    <div class="card-header">
                        <h5>Daily Breakdown</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Present</th>
                                        <th>Absent</th>
                                        <th>Late</th>
                                        <th>Total</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendanceData as $day): ?>
                                    <tr>
                                        <td><?= date('D, M j', strtotime($day['day'])) ?></td>
                                        <td><?= $day['present'] ?></td>
                                        <td><?= $day['absent'] ?></td>
                                        <td><?= $day['late'] ?></td>
                                        <td><?= $day['total'] ?></td>
                                        <td><?= $day['percentage'] ?>%</td>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script>
        // Daily Attendance Chart
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        const attendanceChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [<?php foreach ($attendanceData as $day): ?>"<?= date('j', strtotime($day['day'])) ?>", <?php endforeach; ?>],
                datasets: [
                    {
                        label: 'Present',
                        data: [<?php foreach ($attendanceData as $day): ?><?= $day['present'] ?>, <?php endforeach; ?>],
                        backgroundColor: 'rgba(75, 192, 192, 0.7)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Absent',
                        data: [<?php foreach ($attendanceData as $day): ?><?= $day['absent'] ?>, <?php endforeach; ?>],
                        backgroundColor: 'rgba(255, 99, 132, 0.7)',
                        borderColor: 'rgba(255, 99, 132, 1)',
                        borderWidth: 1
                    },
                    {
                        label: 'Late',
                        data: [<?php foreach ($attendanceData as $day): ?><?= $day['late'] ?>, <?php endforeach; ?>],
                        backgroundColor: 'rgba(255, 206, 86, 0.7)',
                        borderColor: 'rgba(255, 206, 86, 1)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    x: {
                        stacked: true,
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true
                    }
                },
                plugins: {
                    tooltip: {
                        callbacks: {
                            afterBody: function(context) {
                                const total = context[0].raw + context[1].raw + context[2].raw;
                                const percentage = Math.round((context[0].raw / total) * 100);
                                return `Attendance Rate: ${percentage}%`;
                            }
                        }
                    }
                }
            }
        });

        // Monthly Summary Pie Chart
        const pieCtx = document.getElementById('attendancePieChart').getContext('2d');
        const pieChart = new Chart(pieCtx, {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Absent', 'Late'],
                datasets: [{
                    data: [
                        <?= $monthlySummary['present'] ?>,
                        <?= $monthlySummary['absent'] ?>,
                        <?= $monthlySummary['late'] ?>
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
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const value = context.raw;
                                const percentage = Math.round((value / total) * 100);
                                return `${context.label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>