<?php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ .'/../others/sidebar.php';
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

// Get role filter if specified
$roleFilter = $_GET['role'] ?? '';
$validRoles = ['manager', 'teacher', 'student', 'parent'];

// Base query
$query = "
    SELECT u.user_id, u.username, u.email, u.first_name, u.last_name, u.role, u.is_active, 
           u.last_login, u.created_at,
           CASE 
               WHEN u.role = 'student' THEN s.admission_number
               WHEN u.role = 'teacher' THEN t.employee_id
               ELSE NULL
           END as identifier
    FROM users u
    LEFT JOIN students s ON u.user_id = s.user_id
    LEFT JOIN teachers t ON u.user_id = t.user_id
    WHERE 1=1
";

$params = [];

// Add role filter if valid
if (in_array($roleFilter, $validRoles)) {
    $query .= " AND u.role = :role";
    $params[':role'] = $roleFilter;
}

// Add search filter if specified
if (!empty($_GET['search'])) {
    $query .= " AND (u.username LIKE :search OR u.email LIKE :search OR u.first_name LIKE :search OR u.last_name LIKE :search)";
    $params[':search'] = '%' . $_GET['search'] . '%';
}

// Ordering
$query .= " ORDER BY u.created_at DESC";

// Pagination
$page = max(1, $_GET['page'] ?? 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get total count
$countStmt = $db->prepare(str_replace('SELECT u.user_id', 'SELECT COUNT(*) as total', $query));
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value);
}
$countStmt->execute();
$total = $countStmt->fetchColumn();

// Add pagination to main query
$query .= " LIMIT :limit OFFSET :offset";
$params[':limit'] = $perPage;
$params[':offset'] = $offset;

// Execute main query
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$users = $stmt->fetchAll();

$totalPages = ceil($total / $perPage);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | Iftin School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../../assets/css/sidebar.css" rel="stylesheet">
</head>
<body>
   
    
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../others/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">User Management</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="create.php" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-plus-circle"></i> Add New User
                        </a>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="get" class="row g-3">
                            <div class="col-md-4">
                                <label for="role" class="form-label">Filter by Role</label>
                                <select id="role" name="role" class="form-select">
                                    <option value="">All Roles</option>
                                    <option value="manager" <?= $roleFilter === 'manager' ? 'selected' : '' ?>>Managers</option>
                                    <option value="teacher" <?= $roleFilter === 'teacher' ? 'selected' : '' ?>>Teachers</option>
                                    <option value="student" <?= $roleFilter === 'student' ? 'selected' : '' ?>>Students</option>
                                    <option value="parent" <?= $roleFilter === 'parent' ? 'selected' : '' ?>>Parents</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" placeholder="Search by name, email or username">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- User List -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Identifier</th>
                                        <th>Status</th>
                                        <th>Last Login</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?= $user['user_id'] ?></td>
                                        <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                                        <td><?= htmlspecialchars($user['username']) ?></td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= 
                                                $user['role'] === 'manager' ? 'primary' : 
                                                ($user['role'] === 'teacher' ? 'success' : 
                                                ($user['role'] === 'student' ? 'info' : 'warning'))
                                            ?>">
                                                <?= ucfirst($user['role']) ?>
                                            </span>
                                        </td>
                                        <td><?= $user['identifier'] ?? 'N/A' ?></td>
                                        <td>
                                            <span class="badge bg-<?= $user['is_active'] ? 'success' : 'danger' ?>">
                                                <?= $user['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td><?= $user['last_login'] ? date('M j, Y H:i', strtotime($user['last_login'])) : 'Never' ?></td>
                                        <td>
                                            <a href="edit.php?id=<?= $user['user_id'] ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                            <a href="delete.php?id=<?= $user['user_id'] ?>" class="btn btn-sm btn-outline-danger" 
                                               onclick="return confirm('Are you sure you want to delete this user?')">Delete</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <nav aria-label="Page navigation">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Previous</a>
                                </li>
                                
                                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                                </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>