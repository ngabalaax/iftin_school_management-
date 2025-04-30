<?php
// config/session.php
session_start();

class SessionManager {
    public function set($key, $value) {
        $_SESSION[$key] = $value;
    }

    public function get($key) {
        return $_SESSION[$key] ?? null;
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function getUserRole() {
        return $_SESSION['user_role'] ?? null;
    }

    public function destroy() {
        session_destroy();
        $_SESSION = [];
    }
}

$session = new SessionManager();

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        throw new Exception("Invalid CSRF token");
    }
}
