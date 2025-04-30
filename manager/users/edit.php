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

if (!isset($_GET['id'])) {
    $_SESSION['flash_error'] = 'User ID not specified';
    header("Location: list.php");
    exit();
}

$userId = (int)$_GET['id'];
$db = getDBConnection();

// Get user data
$stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch();

if (!$userData) {
    $_SESSION['flash_error'] = 'User not found';
    header("Location: list.php");
    exit();
}

// Get role-specific data
switch ($userData['role']) {
    case 'student':
        $stmt = $db->prepare("SELECT * FROM students WHERE user_id = ?");
        $stmt->execute([$userId]);
        $roleData = $stmt->fetch();
        break;
    case 'teacher':
        $stmt = $db->prepare("SELECT * FROM teachers WHERE user_id = ?");
        $stmt->execute([$userId]);
        $roleData = $stmt->fetch();
        break;
    case 'parent':
        $stmt = $db->prepare("SELECT * FROM parents WHERE user_id = ?");
        $stmt->execute([$userId]);
        $roleData = $stmt->fetch();
        break;
    default:
        $roleData = [];
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postData = array_map('trim', $_POST);
    $requiredFields = ['username', 'email', 'first_name', 'last_name', 'role'];
    
    // Validate required fields
    foreach ($requiredFields as $field) {
        if (empty($postData[$field])) {
            $errors[$field] = "This field is required";
        }
    }
    
    // Validate email
    if (!filter_var($postData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Invalid email format";
    }
    
    // Check if username or email exists for another user
    $stmt = $db->prepare("SELECT user_id FROM users WHERE (username = ? OR email = ?) AND user_id != ?");
    $stmt->execute([$postData['username'], $postData['email'], $userId]);
    if ($stmt->fetch()) {
        $errors['username'] = "Username or email already exists for another user";
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Update users table
            $stmt = $db->prepare("
                UPDATE users SET 
                username = ?, email = ?, first_name = ?, last_name = ?, role = ?, phone = ?
                WHERE user_id = ?
            ");
            $stmt->execute([
                $postData['username'],
                $postData['email'],
                $postData['first_name'],
                $postData['last_name'],
                $postData['role'],
                $postData['phone'] ?? null,
                $userId
            ]);
            
            // Update password if provided
            if (!empty($postData['password'])) {
                if (strlen($postData['password']) < 8) {
                    $errors['password'] = "Password must be at least 8 characters";
                    throw new Exception("Password too short");
                }
                
                $passwordHash = password_hash($postData['password'], PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                $stmt->execute([$passwordHash, $userId]);
            }
            
            // Update role-specific data
            switch ($postData['role']) {
                case 'student':
                    $stmt = $db->prepare("
                        UPDATE students SET 
                        date_of_birth = ?, gender = ?
                        WHERE user_id = ?
                    ");
                    $stmt->execute([
                        $postData['dob'] ?? null,
                        $postData['gender'] ?? 'male',
                        $userId
                    ]);
                    break;
                    
                case 'teacher':
                    $stmt = $db->prepare("
                        UPDATE teachers SET 
                        qualification = ?, specialization = ?
                        WHERE user_id = ?
                    ");
                    $stmt->execute([
                        $postData['qualification'] ?? null,
                        $postData['specialization'] ?? null,
                        $userId
                    ]);
                    break;
                    
                case 'parent':
                    $stmt = $db->prepare("UPDATE parents SET occupation = ? WHERE user_id = ?");
                    $stmt->execute([$postData['occupation'] ?? null, $userId]);
                    break;
            }
            
            $db->commit();
            $_SESSION['flash_success'] = 'User updated successfully!';
            header("Location: list.php");
            exit();
            
        } catch (Exception $e) {
            $db->rollBack();
            error_log("User update error: " . $e->getMessage());
            $errors['system'] = "An error occurred while updating the user. Please try again.";
        }
    }
    
    // Merge POST data with existing data for display
    $userData = array_merge($userData, $postData);
    if ($roleData) {
        $roleData = array_merge($roleData, $postData);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User | Iftin School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/sidebar.css">
</head>
<body>
    
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/../others/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Edit User</h1>
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
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" 
                                   id="password" name="password">
                            <?php if (isset($errors['password'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['password']) ?></div>
                            <?php endif; ?>
                            <small class="text-muted">Leave blank to keep current password</small>
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
                                   value="<?= htmlspecialchars($userData['phone'] ?? '') ?>">
                        </div>
                        
                        <!-- Role-specific fields -->
                        <div id="studentFields" class="role-fields" style="display: <?= $userData['role'] === 'student' ? 'block' : 'none' ?>;">
                            <div class="col-md-6">
                                <label for="dob" class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" id="dob" name="dob" 
                                       value="<?= htmlspecialchars($roleData['date_of_birth'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="gender" class="form-label">Gender</label>
                                <select class="form-select" id="gender" name="gender">
                                    <option value="male" <?= ($roleData['gender'] ?? 'male') === 'male' ? 'selected' : '' ?>>Male</option>
                                    <option value="female" <?= ($roleData['gender'] ?? 'male') === 'female' ? 'selected' : '' ?>>Female</option>
                                    <option value="other" <?= ($roleData['gender'] ?? 'male') === 'other' ? 'selected' : '' ?>>Other</option>
                                </select>
                            </div>
                        </div>
                        
                        <div id="teacherFields" class="role-fields" style="display: <?= $userData['role'] === 'teacher' ? 'block' : 'none' ?>;">
                            <div class="col-md-6">
                                <label for="qualification" class="form-label">Qualification</label>
                                <input type="text" class="form-control" id="qualification" name="qualification" 
                                       value="<?= htmlspecialchars($roleData['qualification'] ?? '') ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="specialization" class="form-label">Specialization</label>
                                <input type="text" class="form-control" id="specialization" name="specialization" 
                                       value="<?= htmlspecialchars($roleData['specialization'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div id="parentFields" class="role-fields" style="display: <?= $userData['role'] === 'parent' ? 'block' : 'none' ?>;">
                            <div class="col-md-6">
                                <label for="occupation" class="form-label">Occupation</label>
                                <input type="text" class="form-control" id="occupation" name="occupation" 
                                       value="<?= htmlspecialchars($roleData['occupation'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <button class="btn btn-primary" type="submit">Update User</button>
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