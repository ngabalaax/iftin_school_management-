<?php
// index.php

require_once 'config/database.php';
require_once 'config/session.php';  // Ensure session object is included

// Redirect to login if not logged in, otherwise to appropriate dashboard
if (!$session->isLoggedIn()) {
    header("Location: auth/login.php");
    exit();
} else {
    redirectBasedOnRole($session->getUserRole());
}

// Define the redirection logic
function redirectBasedOnRole($role) {
    $routes = [
        'manager' => 'manager/dashboard.php',
        'teacher' => 'teacher/dashboard.php',
        'student' => 'student/dashboard.php',
        'parent'  => 'parent/dashboard.php'
    ];
    header('Location: ' . ($routes[$role] ?? '/'));
    exit;
}
?>
