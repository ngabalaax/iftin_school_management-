<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/session.php';

// Redirect if already logged in
if ($session->isLoggedIn()) {
    redirectByRole($session->getUserRole());
    exit;
}

$error = '';
$username = '';
$redirect = $_GET['redirect'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        validateCSRFToken($_POST['csrf_token'] ?? '');
    } catch (Exception $e) {
        $error = 'Invalid form submission.';
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please fill in both fields.';
    } else {
        try {
            $db = getDBConnection();
            $stmt = $db->prepare("SELECT user_id, username, password_hash, role, first_name, last_name, is_active FROM users WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['is_active']) {
                    $session->set('user_id', $user['user_id']);
                    $session->set('user_role', $user['role']);
                    $session->set('user_name', $user['first_name'] . ' ' . $user['last_name']);

                    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                    $stmt->execute([$user['user_id']]);

                    if (!empty($redirect) && strpos($redirect, 'auth/') === false) {
                        header("Location: $redirect");
                    } else {
                        redirectByRole($user['role']);
                    }
                    exit;
                } else {
                    $error = 'Account is inactive.';
                }
            } else {
                $error = 'Incorrect username or password.';
            }
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'Database error. Please try again.';
        }
    }
}

function redirectByRole($role) {
    $routes = [
        'manager' => '/../manager/dashboard.php',
        'teacher' => '/../teacher/dashboard.php',
        'student' => '/../student/dashboard.php',
        'parent'  => '/../parent/dashboard.php'
    ];
    header('Location: ' . ($routes[$role] ?? '/'));
    exit;
}
?>

<!-- HTML Part -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Iftin Login</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { background: #f8f9fa; height: 100vh; display: flex; align-items: center; }
        .login-container {
            max-width: 400px; margin: auto; padding: 30px;
            background: white; border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .school-logo img { max-width: 150px; }
    </style>
</head>
<body>
<div class="container">
    <div class="login-container">
        <div class="school-logo text-center mb-4">
            <img src="/assets/images/logo.png" alt="School Logo">
        </div>
        <h2 class="text-center mb-4">Login</h2>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" name="username" class="form-control" required value="<?= htmlspecialchars($username) ?>">
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required>
            </div>

            <div class="d-grid">
                <button type="submit" class="btn btn-primary">Login</button>
            </div>

            <div class="text-center mt-3">
                <a href="./forgot-password.php">Forgot password?</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
