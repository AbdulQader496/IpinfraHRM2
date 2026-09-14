<?php
session_start();
require_once 'db.php';
/** @var mysqli $conn */

function _restoreSessionFromCookie() {
    if (!isset($_COOKIE['remember_token'])) return;
    global $conn;
    try {
        $col_exists = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT COUNT(*) AS cnt
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'employees'
              AND COLUMN_NAME = 'remember_token'
        "));
        if ((int)($col_exists['cnt'] ?? 0) === 0) {
            mysqli_query($conn, "ALTER TABLE employees ADD COLUMN remember_token VARCHAR(64) NULL");
        }
        $token = mysqli_real_escape_string($conn, $_COOKIE['remember_token']);
        $user  = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT * FROM employees WHERE remember_token='$token' AND status='active'"));
        if ($user) {
            session_regenerate_id(true);
            $_SESSION['user_id']     = $user['id'];
            $_SESSION['user_name']   = $user['name'];
            $_SESSION['role']        = $user['role'];
            $_SESSION['employee_id'] = $user['employee_id'];
        }
    } catch (Exception $e) {
        // Column issue or DB error — just fall through to login redirect
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        _restoreSessionFromCookie();
    }
    if (!isLoggedIn()) {
        header('Location: ../index.php');
        exit();
    }
}

function redirectIfNotAdmin() {
    redirectIfNotLoggedIn();
    if (!isAdmin()) {
        header('Location: ../employee/dashboard.php');
        exit();
    }
}

// CSRF Protection helpers
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function validateCsrfToken($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}
?>
