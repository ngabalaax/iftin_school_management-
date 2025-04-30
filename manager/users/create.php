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

$errors = [];
$userData = [
    'username' => '',
    'email' => '',
    'first_name' => '',
    'last_name' => '',
    'role' => 'student',
    'phone' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userData = array_map('trim', $_POST);
    $requiredFields = ['username', 'email', 'first_name', 'last_name', 'role', 'password'];
    
    // Validate required fields
    foreach ($requiredFields as $field) {
        if (empty($userData[$field])) {
            $errors[$field] = "This field is required";
        }
    }
    
    // Validate email
    if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format";
    }
    
    // Validate password strength
    if (strlen($userData['password']) < 8) {
        $errors['password'] = "Password must be at least 8 characters";
    }
    
    // Check if username or email exists
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$userData['username'], $userData['email']]);
    if ($stmt->fetch()) {
        $errors['username'] = "Username or email already exists";
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Insert into users table
            $stmt = $db->prepare("
                INSERT INTO users 
                (username, password_hash, email, first_name, last_name, role, phone, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $passwordHash = password_hash($userData['password'], PASSWORD_DEFAULT);
            $stmt->execute([
                $userData['username'],
                $passwordHash,
                $userData['email'],
                $userData['first_name'],
                $userData['last_name'],
                $userData['role'],
                $userData['phone'] ?? null
            ]);
            
            $userId = $db->lastInsertId();
            
            // Insert into role-specific table if needed
            switch ($userData['role']) {
                case 'student':
                    $stmt = $db->prepare("
                        INSERT INTO students 
                        (user_id, admission_number, date_of_birth, gender) 
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $userId,
                        'STU' . str_pad($userId, 5, '0', STR_PAD_LEFT),
                        $userData['dob'] ?? null,
                        $userData['gender'] ?? 'male'
                    ]);
                    break;
                    
                case 'teacher':
                    $stmt = $db->prepare("
                        INSERT INTO teachers 
                        (user_id, employee_id, qualification, specialization, hire_date) 
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $userId,
                        'TEA' . str_pad($userId, 5, '0', STR_PAD_LEFT),
                        $userData['qualification'] ?? null,
                        $userData['specialization'] ?? null,
                        date('Y-m-d')
                    ]);
                    break;
                    
                case 'parent':
                    $stmt = $db->prepare("INSERT INTO parents (user_id, occupation) VALUES (?, ?)");
                    $stmt->execute([$userId, $userData['occupation'] ?? null]);
                    break;
            }
            
            $db->commit();
            $_SESSION['flash_message'] = ['type' => 'success', 'message' => 'User created successfully!'];
            header("Location: list.php");
            exit();
            
        } catch (PDOException $e) {
            $db->rollBack();
            error_log("User creation error: " . $e->getMessage());
            $errors['system'] = "An error occurred while creating the user. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User | Iftin School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../../assets/css/sidebar.css" rel="stylesheet">
</head>
<body>
    
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../others/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Create New User</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="list.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left"></i> Back to Users
                        </a>
                    </div>
                </div>

                <?php if (!empty($errors['system'])): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($errors['system']) ?></div>
                <?php endif; ?>

                <form method="POST" class="needs-validation" novalidate>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="username" class="form-label">Username *</label>
                            <input type="text" class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>" 
                                   id="username" name="username" value="<?= htmlspecialchars($userData['username']) ?>" required>
                            <?php if (isset($errors['username'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['username']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" 
                                   id="email" name="email" value="<?= htmlspecialchars($userData['email']) ?>" required>
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['email']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="first_name" class="form-label">First Name *</label>
                            <input type="text" class="form-control <?= isset($errors['first_name']) ? 'is-invalid' : '' ?>" 
                                   id="first_name" name="first_name" value="<?= htmlspecialchars($userData['first_name']) ?>" required>
                            <?php if (isset($errors['first_name'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['first_name']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="last_name" class="form-label">Last Name *</label>
                            <input type="text" class="form-control <?= isset($errors['last_name']) ? 'is-invalid' : '' ?>" 
                                   id="last_name" name="last_name" value="<?= htmlspecialchars($userData['last_name']) ?>" required>
                            <?php if (isset($errors['last_name'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['last_name']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password *</label>
                            <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" 
                                   id="password" name="password" required>
                            <?php if (isset($errors['password'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['password']) ?></div>
                            <?php endif; ?>
                            <small class="text-muted">Minimum 8 characters</small>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="role" class="form-label">Role *</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="student" <?= $userData['role'] === 'student' ? 'selected' : '' ?>>Student</option>
                                <option value="teacher" <?= $userData['role'] === 'teacher' ? 'selected' : '' ?>>Teacher</option>
                                <option value="parent" <?= $userData['role'] === 'parent' ? 'selected' : '' ?>>Parent</option>
                                <option value="manager" <?= $userData['role'] === 'manager' ? 'selected' : '' ?>>Manager</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?= htmlspecialchars($userData['phone']) ?>">
                        </div>
                        
                        <!-- Role-specific fields (shown/hidden based on role selection) -->
                        <div id="studentFields" class="role-fields" style="display: <?= $userData['role'] === 'student' ? 'block' : 'none' ?>;">
                            <div class="col-md-6">
                                <label for="dob" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="dob" name="dob" 
                                       value="<?= htmlspecialchars($userData['dob'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="gender" class="form-label">Gender</label>
                                <select class="form-select" id="gender" name="gender">
                                    <option value="male" <?= ($userData['gender'] ?? 'male') === 'male' ? 'selected' : '' ?>>Male</option>
                                    <option value="female" <?= ($userData['gender'] ?? 'male') === 'female' ? 'selected' : '' ?>>Female</option>
                                    <option value="other" <?= ($userData['gender'] ?? 'male') === 'other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div id="teacherFields" class="role-fields" style="display: <?= $userData['role'] === 'teacher' ? 'block' : 'none' ?>;">
                            <div class="col-md-6">
                                <label for="qualification" class="form-label">Qualification</label>
                                <input type="text" class="form-control" id="qualification" name="qualification" 
                                       value="<?= htmlspecialchars($userData['qualification'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="specialization" class="form-label">Specialization</label>
                                <input type="text" class="form-control" id="specialization" name="specialization" 
                                       value="<?= htmlspecialchars($userData['specialization'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div id="parentFields" class="role-fields" style="display: <?= $userData['role'] === 'parent' ? 'block' : 'none' ?>;">
                            <div class="col-md-6">
                                <label for="occupation" class="form-label">Occupation</label>
                                <input type="text" class="form-control" id="occupation" name="occupation" 
                                       value="<?= htmlspecialchars($userData['occupation'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <button class="btn btn-primary" type="submit">Create User</button>
                            <a href="list.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show/hide role-specific fields
        document.getElementById('role').addEventListener('change', function() {
            document.querySelectorAll('.role-fields').forEach(field => {
                field.style.display = 'none';
            });
            
            const selectedFields = document.getElementById(this.value + 'Fields');
            if (selectedFields) {
                selectedFields.style.display = 'block';
            }
        });
        
        // Form validation
        (function() {
            'use strict';
            const forms = document.querySelectorAll('.needs-validation');
            
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>
</html>