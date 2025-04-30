<?php
// No need for session_start() here as it's included after dashboard starts session
require_once __DIR__ . '/../config/session.php';
?>
<link rel="stylesheet" href="../assets/css/sidebar.css">
<div class="sidebar-custom">
    <div class="sidebar-content">
        <!-- Logo/School Name -->
        <div class="text-center mb-4">
            <h4 class="text-primary">Iftin School</h4>
        </div>

        <!-- Main Navigation -->
        <ul class="nav-section">
            <li class="nav-heading">Teaching</li>
            <li>
                <a href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            <li>
                <a href="attendance/take.php">
                    <i class="fas fa-clipboard-check"></i> Take Attendance
                </a>
            </li>
            <li>
                <a href="attendance/view.php">
                    <i class="fas fa-history"></i> View Attendance
                </a>
            </li>
            <li>
                <a href="classes/view.php">
                    <i class="fas fa-users"></i> My Classes
                </a>
            </li>
        </ul>

        <!-- Grading Section -->
        <ul class="nav-section">
            <li class="nav-heading">Grading</li>
            <li>
                <a href="grades/assignments.php">
                    <i class="fas fa-tasks"></i> Assignments
                </a>
            </li>
            <li>
                <a href="grades/manage.php">
                    <i class="fas fa-graduation-cap"></i> Manage Grades
                </a>
            </li>
        </ul>

        <!-- Bottom Links -->
        <div class="bottom-links">
            <a href="../includes/profile.php">
                <i class="fas fa-user-circle"></i> My Profile
            </a>
            <a href="../auth/logout.php" class="logout">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
</div>