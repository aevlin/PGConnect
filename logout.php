<?php
// logout.php
require_once __DIR__ . '/backend/bootstrap.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Clear all session data
$_SESSION = [];

// Destroy session on server
session_destroy();

// Optional: clear session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

// Redirect to login page
header('Location: ' . base_url('backend/login.php'));
exit;
