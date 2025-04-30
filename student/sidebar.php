<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/session.php';

// Verify student access
if ($session->get('user_role') !== 'student') {
    header('HTTP/1.0 403 Forbidden');
    die('You do not have permission to access this page.');
}
?>

<div class="sidebar-custom">
    <div class="sidebar-content">
        <!-- School Logo/Name -->
        <div class="text-center mb-4">
            <h5 class="mb-0">Iftin School</h5>
            <small class="text-muted">Student Portal</small>
        </div>

        <!-- Navigation Sections -->
        <ul class="nav-section">
            <li class="nav-heading">Main</li>
            <li>
                <a href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </li>
            
            <li class="nav-heading">Academics</li>
            <li>
                <a href="schedule.php">
                    <i class="fas fa-calendar-alt"></i> Class Schedule
                </a>
            </li>
            <li>
                <a href="subjects.php">
                    <i class="fas fa-book"></i> Subjects
                </a>
            </li>
            <li>
                <a href="assignments.php">
                    <i class="fas fa-tasks"></i> Assignments
                </a>
            </li>
            <li>
                <a href="grades.php">
                    <i class="fas fa-chart-bar"></i> Grades
                </a>
            </li>
            
            <li class="nav-heading">Attendance</li>
            <li>
                <a href="attendance.php">
                    <i class="fas fa-calendar-check"></i> Attendance Record
                </a>
            </li>
            
            <li class="nav-heading">Resources</li>
            <li>
                <a href="timetable.php">
                    <i class="fas fa-table"></i> Timetable
                </a>
            </li>
            <li>
                <a href="announcements.php">
                    <i class="fas fa-bullhorn"></i> Announcements
                </a>
            </li>
            <li>
                <a href="materials.php">
                    <i class="fas fa-file-alt"></i> Study Materials
                </a>
            </li>
        </ul>

        <!-- Bottom Links -->
        <div class="bottom-links">
            <a href="profile.php">
                <i class="fas fa-user-circle me-2"></i> My Profile
            </a>
            <a href="../auth/logout.php" class="logout">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </div>
    </div>
</div>