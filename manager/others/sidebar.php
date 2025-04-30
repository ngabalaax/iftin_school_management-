<?php
// manager/others/sidebar.php   mamage sidebar
require_once __DIR__ . '/../../config/session.php';
?>
<link rel="stylesheet" href="../../../assets/css/sidebar.css">

<div class="sidebar-custom">
     <!-- Logo/School Name -->
     <div class="text-center mb-4">
            <h4 class="text-primary">Iftin School</h4>
        </div>
    <div class="sidebar-content">
        <ul class="nav-section">
            <li><a href="../manager/dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
            <li><a href="../manager/users/list.php?role=student"><i class="bi bi-people"></i> Students</a></li>
            <li><a href="../manager/users/list.php?role=teacher"><i class="bi bi-person-badge"></i> Teachers</a></li>
            <li><a href="../manager/users/list.php?role=parent"><i class="bi bi-person-vcard"></i> Parents</a></li>
            <li><a href="../manager/classes/manage.php"><i class="bi bi-book"></i> Classes</a></li>
            <li><a href="../manager/attendance/overview.php"><i class="bi bi-calendar-check"></i> Attendance</a></li>
            <li><a href="../manager/system/config.php"><i class="bi bi-gear"></i> System Settings</a></li>
        </ul>

        <div class="nav-heading">Quick Actions</div>
        <ul class="nav-section">
            <li><a href="../manager/users/create.php"><i class="bi bi-plus-circle"></i> Add New User</a></li>
            <li><a href="../manager/classes/assign.php"><i class="bi bi-person-plus"></i> Assign Teachers</a></li>
        </ul>

        <div class="bottom-links">
            <a href="../../includes/profile.php"><i class="bi bi-person-circle"></i> Profile</a>
            <a href="../../auth/logout.php" class="logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </div>
</div>