<?php
/**
 * includes/auth_check.php
 * Authentication Check Middleware
 * Include this file at the top of any page that requires authentication
 */

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';


// Check if user is logged in
if (!$session->isLoggedIn()) {
    // Store the requested URL for redirect after login
    $redirect = $_SERVER['REQUEST_URI'];
    header("Location: auth/login.php?redirect=" . urlencode($redirect));
    exit();
}

// Get current user information
$current_user_id = $session->get('user_id');
$current_user_role = $session->get('user_role');

try {
    $db = getDBConnection();
    
    // Verify user still exists and is active
    $stmt = $db->prepare("SELECT is_active FROM users WHERE user_id = ?");
    $stmt->execute([$current_user_id]);
    $user = $stmt->fetch();
    
    if (!$user || !$user['is_active']) {
        $session->destroySession();
        header("Location: auth/login.php?error=account_inactive");
        exit();
    }
    
    // Role-specific verification
    switch ($current_user_role) {
        case 'student':
            $stmt = $db->prepare("SELECT 1 FROM students WHERE user_id = ?");
            break;
        case 'teacher':
            $stmt = $db->prepare("SELECT 1 FROM teachers WHERE user_id = ?");
            break;
        case 'parent':
            $stmt = $db->prepare("SELECT 1 FROM parents WHERE user_id = ?");
            break;
        case 'manager':
            // Managers are verified through the users table only
            break;
        default:
            $session->destroySession();
            header("Location: auth/login.php?error=invalid_role");
            exit();
    }
    
    if ($current_user_role !== 'manager') {
        $stmt->execute([$current_user_id]);
        if (!$stmt->fetch()) {
            $session->destroySession();
            header("Location: auth/login.php?error=invalid_role_data");
            exit();
        }
    }
    
    // Update last activity timestamp
    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
    $stmt->execute([$current_user_id]);
    
} catch (PDOException $e) {
    error_log("Auth check failed: " . $e->getMessage());
    // Don't reveal database errors to users
    die("An authentication error occurred. Please try again later.");
}
?>