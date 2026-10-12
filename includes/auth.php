<?php
// auth.php – Authentication and authorization middleware
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Absolute login URL (relative redirects break in subfolders like /admin/)
if (!function_exists('auth_login_url')) {
    function auth_login_url() {
        return defined('BASE_URL') ? rtrim(BASE_URL, '/') . '/login.php' : '/hotel-system/login.php';
    }
}

// Check if user is logged in
function require_login() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        header("Location: " . auth_login_url());
        exit();
    }
}

// Check for specific role access
function require_role($required_role) {
    require_login();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
        http_response_code(403);
        echo "<div class='container mt-5 alert alert-danger'>Access denied: You are not authorized to view this page.</div>";
        exit();
    }
}

function require_clerk() { require_role('clerk'); }
function require_admin() { require_role('admin'); }
function require_manager() { require_role('manager'); }

function get_user_id() { require_login(); return $_SESSION['user_id']; }
function get_user_role() { require_login(); return $_SESSION['role']; }
function has_any_role($allowed_roles) { require_login(); return in_array($_SESSION['role'], $allowed_roles); }
?>