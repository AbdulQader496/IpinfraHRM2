<?php
session_start();
require_once 'db.php';
/** @var mysqli $conn */

function _restoreSessionFromCookie() {
    if (!isset($_COOKIE['remember_token'])) return;
    global $conn;
    try {
        // Schema migration — run once at deployment, not on every request:
        // mysqli_query($conn, "ALTER TABLE employees ADD COLUMN IF NOT EXISTS remember_token VARCHAR(64) NULL");
        $token = mysqli_real_escape_string($conn, $_COOKIE['remember_token']);
        // status='active' alone doesn't catch an approved resignation -- see the matching
        // check in index.php for why.
        $user  = mysqli_fetch_assoc(mysqli_query($conn,
            "SELECT * FROM employees WHERE remember_token='$token' AND status='active'
             AND NOT EXISTS (
                 SELECT 1 FROM employee_resignations er
                 WHERE er.employee_id = employees.id AND er.status = 'approved' AND er.last_working_date < CURDATE()
             )"));
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

// Once $_SESSION['user_id']/role are set at login, nothing re-checked them against the DB
// for the rest of that PHP session's lifetime -- a demoted admin, a resigned/terminated
// employee, or a since-deactivated account kept full access until they happened to log
// out. Re-verified once per request here (this function already runs at the top of every
// protected page) rather than only at login/cookie-restore time.
function _revalidateSession() {
    if (!isLoggedIn()) return;
    global $conn;
    $id = intval($_SESSION['user_id']);
    $row = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT status, role FROM employees WHERE id=$id AND status='active'
         AND NOT EXISTS (
             SELECT 1 FROM employee_resignations er
             WHERE er.employee_id = employees.id AND er.status = 'approved' AND er.last_working_date < CURDATE()
         )"));
    if (!$row) {
        // Account deactivated, resigned past their last day, or deleted since login --
        // end the session instead of trusting the stale cached role.
        $_SESSION = [];
        session_destroy();
        header('Location: ../index.php');
        exit();
    }
    // Keep the cached role in sync if an admin changed it mid-session, so isAdmin()
    // reflects reality instead of what was true when this session started.
    $_SESSION['role'] = $row['role'];
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        _restoreSessionFromCookie();
    }
    if (!isLoggedIn()) {
        header('Location: ../index.php');
        exit();
    }
    _revalidateSession();
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
