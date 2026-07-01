<?php
session_start();
require_once 'db.php';

function _restoreSessionFromCookie() {
    if (!isset($_COOKIE['remember_token'])) return;
    global $conn;
    try {
        // Ensure column exists (safe to run every time)
        mysqli_query($conn, "ALTER TABLE employees ADD COLUMN IF NOT EXISTS remember_token VARCHAR(64) NULL");
        $token = mysqli_real_escape_string($conn, $_COOKIE['remember_token']);
        $user  = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT * FROM employees WHERE remember_token='$token' AND status='active'"));
        if ($user) {
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
?>
