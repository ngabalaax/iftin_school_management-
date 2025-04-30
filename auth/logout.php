<?php
require_once __DIR__ . '/../config/session.php';

// Destroy the session
$session->destroySession();

// Redirect to login page
header('Location: /auth/login.php');
exit();
?>