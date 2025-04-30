<?php
require_once __DIR__ . '/../../../includes/auth_check.php';
require_once __DIR__ . '/../../../config/session.php';
require_once __DIR__ . '/../../../config/database.php';

$session->requireRole('manager');

if (!isset($_GET['id'])) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'User ID not specified'];
    header("Location: list.php");
    exit();
}

$userId = (int)$_GET['id'];
$db = getDBConnection();

// Get user data for confirmation
$stmt = $db->prepare("SELECT username, first_name, last_name, role FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'User not found'];
    header("Location: list.php");
    exit();
}

// Handle deletion confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->beginTransaction();
        
        // Delete from role-specific table first
        switch ($user['role']) {
            case 'student':
                $stmt = $db->prepare("DELETE FROM students WHERE user_id = ?");
                $stmt->execute([$userId]);
                break;
            case 'teacher':
                $stmt = $db->prepare("DELETE FROM teachers WHERE user_id = ?");
                $stmt->execute([$userId]);
                break;
            case 'parent':
                $stmt = $db->prepare("DELETE FROM parents WHERE user_id = ?");
                $stmt->execute([$userId]);
                break;
        }
        
        // Delete from users table
        $stmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        $db->commit();
        $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'User deleted successfully!'];
        header("Location: list.php");
        exit();
        
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("User deletion error: " . $e->getMessage());
        $_SESSION['flash_message'] = ['type' => 'error', 'message' => 'An error occurred while deleting the user. Please try again.'];
        header("Location: list.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete User | Iftin School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../../../includes/navbar.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../../../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Delete User</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="list.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Users
                        </a>
                    </div>
                </div>

                <div class="alert alert-danger">
                    <h4 class="alert-heading">Warning!</h4>
                    <p>You are about to permanently delete the following user:</p>
                    <hr>
                    <p class="mb-0">
                        <strong>Name:</strong> <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?><br>
                        <strong>Username:</strong> <?= htmlspecialchars($user['username']) ?><br>
                        <strong>Role:</strong> <?= ucfirst($user['role']) ?>
                    </p>
                    <hr>
                    <p class="mb-0"><strong>This action cannot be undone!</strong></p>
                </div>

                <form method="POST">
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="confirmDelete" required>
                        <label class="form-check-label" for="confirmDelete">I understand that this action is permanent</label>
                    </div>
                    
                    <button type="submit" class="btn btn-danger">Confirm Delete</button>
                    <a href="list.php" class="btn btn-outline-secondary">Cancel</a>
                </form>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!document.getElementById('confirmDelete').checked) {
                e.preventDefault();
                alert('Please confirm that you understand this action is permanent');
            }
        });
    </script>
</body>
</html>