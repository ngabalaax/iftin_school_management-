<?php
// No need for session_start() here as it's included after dashboard starts session
require_once __DIR__ . '/../../config/session.php';
?>
<link rel="stylesheet" href="../../assets/css/sidebar.css">
<nav class="navbar-teacher">
    <div class="user-menu dropdown">
        <a class="d-flex align-items-center text-decoration-none dropdown-toggle" 
           href="#" 
           id="teacherDropdown" 
           data-bs-toggle="dropdown" 
           aria-expanded="false">
            <img src="../assets/image/<?php echo $_SESSION['user_profile_pic'] ?? 'default.jpeg'; ?>" 
                 class="user-avatar" 
                 alt="User profile">
            <div class="user-info">
                <span class="user-name"><?php echo $_SESSION['user_name'] ?? 'User'; ?></span>
                <span class="user-role"><?php echo $_SESSION['user_role'] ?? 'manager'; ?></span>
            </div>
        </a>
        <ul class="dropdown-menu dropdown-menu-teacher dropdown-menu-end" 
            aria-labelledby="teacherDropdown">
            <li>
                <a class="dropdown-item-teacher" href="../includes/profile.php">
                    <i class="fas fa-user"></i> My Profile
                </a>
            </li>
            <li><hr class="dropdown-divider-teacher"></li>
            <li>
                <a class="dropdown-item-teacher text-danger" href="../auth/logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </li>
        </ul>
    </div>
</nav>