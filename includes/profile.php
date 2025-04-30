<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';

// Get current user data
$stmt = $db->prepare("
    SELECT u.*, 
           CASE u.role
               WHEN 'teacher' THEN t.employee_id
               WHEN 'student' THEN s.admission_number
               WHEN 'parent' THEN p.occupation
               ELSE NULL
           END as role_specific_field
    FROM users u
    LEFT JOIN teachers t ON u.user_id = t.user_id AND u.role = 'teacher'
    LEFT JOIN students s ON u.user_id = s.user_id AND u.role = 'student'
    LEFT JOIN parents p ON u.user_id = p.user_id AND u.role = 'parent'
    WHERE u.user_id = ?
");
$stmt->execute([$session->get('user_id')]);
$user = $stmt->fetch();

if (!$user) {
    die('User not found');
}

$errors = [];
$success = false;

// In the profile picture upload section, replace with this code:
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_picture'])) {
    if (isset($_FILES['profile_picture'])) {
        $file = $_FILES['profile_picture'];
        
        // Validate file
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($file['type'], $allowedTypes)) {
            $errors[] = 'Only JPG, PNG, and GIF files are allowed.';
        } elseif ($file['size'] > $maxSize) {
            $errors[] = 'File size must be less than 2MB.';
        } else {
            // Create uploads directory if it doesn't exist
            $uploadDir = __DIR__ . '/../uploads/profile_pictures/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Generate unique filename
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $user['user_id'] . '_' . time() . '.' . $ext;
            $uploadPath = $uploadDir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                // Delete old profile picture if it exists and isn't the default
                if (!empty($user['profile_picture']) && $user['profile_picture'] !== 'default.png') {
                    $oldFilePath = $uploadDir . $user['profile_picture'];
                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                }
                
                // Update database
                $stmt = $db->prepare("UPDATE users SET profile_picture = ? WHERE user_id = ?");
                if ($stmt->execute([$filename, $user['user_id']])) {
                    $session->set('profile_picture', $filename);
                    $user['profile_picture'] = $filename;
                    $success = true;
                } else {
                    $errors[] = 'Failed to update profile picture in database.';
                }
            } else {
                $errors[] = 'Failed to upload file. Check directory permissions.';
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate current password
    if (!password_verify($current_password, $user['password_hash'])) {
        $errors[] = 'Current password is incorrect.';
    }

    // Validate new password
    if (strlen($new_password) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    }

    if ($new_password !== $confirm_password) {
        $errors[] = 'New passwords do not match.';
    }

    if (empty($errors)) {
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
        if ($stmt->execute([$new_hash, $user['user_id']])) {
            $success = true;
        } else {
            $errors[] = 'Failed to update password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Iftin School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <style>
        .profile-picture {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 5px solid white;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .profile-section {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 2rem;
        }

        .role-badge {
            font-size: 0.8rem;
            padding: 0.35rem 0.65rem;
            text-transform: capitalize;
        }

        .badge-manager {
            background-color: #6f42c1;
        }

        .badge-teacher {
            background-color: #20c997;
        }

        .badge-student {
            background-color: #fd7e14;
        }

        .badge-parent {
            background-color: #6610f2;
        }
    </style>
</head>

<body>
    <?php include './navbar.php'; ?>
    <div class="main-content-teacher">
        <div class="main-content-<?= strtolower($user['role']) ?>">
            <div class="container-fluid py-4">
                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        Profile updated successfully!
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php foreach ($errors as $error): ?>
                            <div><?= htmlspecialchars($error) ?></div>
                        <?php endforeach; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-4">
                        <div class="profile-section text-center mb-4">
                            <div class="position-relative d-inline-block mb-3">
                                <img src="../uploads/profile_pictures/<?= htmlspecialchars($user['profile_picture'] ?? 'default.png') ?>"
                                    class="profile-picture" alt="Profile Picture">
                                <button class="btn btn-sm btn-primary position-absolute bottom-0 end-0 rounded-circle"
                                    data-bs-toggle="modal" data-bs-target="#pictureModal">
                                    <i class="fas fa-camera"></i>
                                </button>
                            </div>

                            <h4><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></h4>
                            <span class="badge role-badge badge-<?= strtolower($user['role']) ?>">
                                <?= ucfirst($user['role']) ?>
                            </span>

                            <div class="mt-3">
                                <?php if ($user['role'] === 'teacher'): ?>
                                    <p class="mb-1"><i class="fas fa-id-card me-2"></i>
                                        <?= htmlspecialchars($user['role_specific_field']) ?></p>
                                <?php elseif ($user['role'] === 'student'): ?>
                                    <p class="mb-1"><i class="fas fa-id-card me-2"></i>
                                        <?= htmlspecialchars($user['role_specific_field']) ?></p>
                                <?php elseif ($user['role'] === 'parent'): ?>
                                    <p class="mb-1"><i class="fas fa-briefcase me-2"></i>
                                        <?= htmlspecialchars($user['role_specific_field']) ?></p>
                                <?php endif; ?>
                                <p class="mb-1"><i class="fas fa-envelope me-2"></i>
                                    <?= htmlspecialchars($user['email']) ?></p>
                                <?php if (!empty($user['phone'])): ?>
                                    <p class="mb-1"><i class="fas fa-phone me-2"></i>
                                        <?= htmlspecialchars($user['phone']) ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-8">
                        <div class="profile-section mb-4">
                            <h5 class="mb-4">Account Information</h5>

                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">First Name</label>
                                    <input type="text" class="form-control"
                                        value="<?= htmlspecialchars($user['first_name']) ?>" readonly>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Last Name</label>
                                    <input type="text" class="form-control"
                                        value="<?= htmlspecialchars($user['last_name']) ?>" readonly>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>"
                                    readonly>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control"
                                    value="<?= htmlspecialchars($user['username']) ?>" readonly>
                            </div>

                            <?php if (!empty($user['phone'])): ?>
                                <div class="mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>"
                                        readonly>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($user['address'])): ?>
                                <div class="mb-3">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" rows="2"
                                        readonly><?= htmlspecialchars($user['address']) ?></textarea>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Change Password Form -->
                        <div class="profile-section">
                            <h5 class="mb-4">Change Password</h5>

                            <form method="POST">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password"
                                        name="current_password" required>
                                </div>

                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password"
                                        required>
                                    <div class="form-text">At least 8 characters</div>
                                </div>

                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password"
                                        name="confirm_password" required>
                                </div>

                                <button type="submit" name="change_password" class="btn btn-primary">
                                    Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Profile Picture Modal -->
    <div class="modal fade" id="pictureModal" tabindex="-1" aria-labelledby="pictureModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="pictureModalLabel">Update Profile Picture</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="profile_picture" class="form-label">Select new profile picture</label>
                            <input class="form-control" type="file" id="profile_picture" name="profile_picture"
                                accept="image/*" required>
                            <div class="form-text">JPG, PNG or GIF (Max 2MB)</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_picture" class="btn btn-primary">Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preview image before upload
        document.getElementById('profile_picture')?.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function (e) {
                    document.querySelector('.profile-picture').src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>

</html>