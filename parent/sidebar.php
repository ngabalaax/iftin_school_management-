<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/session.php';

// Verify parent access
if ($session->get('user_role') !== 'parent') {
    header('HTTP/1.0 403 Forbidden');
    die('You do not have permission to access this page.');
}

// Get parent's children for the dropdown
require_once __DIR__ . '/../config/database.php';
$stmt = $db->prepare("
    SELECT s.student_id, u.first_name, u.last_name 
    FROM parent_student ps
    JOIN students s ON ps.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    WHERE ps.parent_id = (SELECT parent_id FROM parents WHERE user_id = ?)
    ORDER BY u.first_name
");
$stmt->execute([$session->get('user_id')]);
$children = $stmt->fetchAll();

// Get current child ID from query string
$currentChildId = $_GET['child_id'] ?? ($children[0]['student_id'] ?? null);
?>

<div class="sidebar-custom">
    <div class="sidebar-content">
        <!-- School Header -->
        <div class="text-center mb-4">
            <h5 class="mb-0">Iftin School</h5>
            <small class="text-muted">Parent Portal</small>
        </div>

        <!-- Child Selector Dropdown -->
        <?php if (!empty($children)): ?>
            <div class="mb-4">
                <label class="form-label small text-muted mb-1">Viewing Child</label>
                <select class="form-select form-select-sm" id="childSelector">
                    <?php foreach ($children as $child): ?>
                        <option value="<?= $child['student_id'] ?>" 
                            <?= $child['student_id'] == $currentChildId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($child['first_name'] . ' ' . $child['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <!-- Navigation Sections -->
        <ul class="nav-section">
            <li class="nav-heading">Dashboard</li>
            <li>
                <a href="dashboard.php?child_id=<?= $currentChildId ?>" 
                   class="<?= basename($_SERVER['PHP_SELF']) == 'parent_dashboard.php' ? 'active' : '' ?>">
                    <i class="fas fa-tachometer-alt"></i> Overview
                </a>
            </li>
            
            <li class="nav-heading">Academics</li>
            <li>
                <a href="parent_attendance.php?child_id=<?= $currentChildId ?>"
                   class="<?= basename($_SERVER['PHP_SELF']) == 'parent_attendance.php' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-check"></i> Attendance
                </a>
            </li>
            <li>
                <a href="parent_grades.php?child_id=<?= $currentChildId ?>"
                   class="<?= basename($_SERVER['PHP_SELF']) == 'parent_grades.php' ? 'active' : '' ?>">
                    <i class="fas fa-chart-bar"></i> Grades
                </a>
            </li>
            <li>
                <a href="parent_schedule.php?child_id=<?= $currentChildId ?>"
                   class="<?= basename($_SERVER['PHP_SELF']) == 'parent_schedule.php' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt"></i> Schedule
                </a>
            </li>
            <li>
                <a href="parent_assignments.php?child_id=<?= $currentChildId ?>"
                   class="<?= basename($_SERVER['PHP_SELF']) == 'parent_assignments.php' ? 'active' : '' ?>">
                    <i class="fas fa-tasks"></i> Assignments
                </a>
            </li>
            
            <li class="nav-heading">Communication</li>
            <li>
                <a href="parent_announcements.php?child_id=<?= $currentChildId ?>"
                   class="<?= basename($_SERVER['PHP_SELF']) == 'parent_announcements.php' ? 'active' : '' ?>">
                    <i class="fas fa-bullhorn"></i> Announcements
                </a>
            </li>
            <li>
                <a href="parent_messages.php?child_id=<?= $currentChildId ?>"
                   class="<?= basename($_SERVER['PHP_SELF']) == 'parent_messages.php' ? 'active' : '' ?>">
                    <i class="fas fa-envelope"></i> Messages
                </a>
            </li>
        </ul>

        <!-- Bottom Links -->
        <div class="bottom-links">
            <a href="parent_profile.php">
                <i class="fas fa-user-circle me-2"></i> My Profile
            </a>
            <a href="../actions/logout.php" class="logout">
                <i class="fas fa-sign-out-alt me-2"></i> Logout
            </a>
        </div>
    </div>
</div>

<script>
// Handle child selection change
document.getElementById('childSelector')?.addEventListener('change', function() {
    const childId = this.value;
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('child_id', childId);
    window.location.href = currentUrl.toString();
});
</script>