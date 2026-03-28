<?php
// backend/auth.php
require_once __DIR__ . '/bootstrap.php';

function ensure_session_started() {
    if (session_status() === PHP_SESSION_NONE) {
        @session_set_cookie_params(0, '/');
        session_start();
    }
}

function require_login() {
    ensure_session_started();
    if (!isset($_SESSION['user_id'])) {
        header('Location: ' . BASE_URL . '/backend/login.php');
        exit;
    }
}

function require_role($role) {
    require_login();
    if (($_SESSION['user_role'] ?? '') !== $role) {
        header('Location: ' . BASE_URL . '/backend/login.php');
        exit;
    }
}
