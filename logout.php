<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

// Safe bootstrap for remember-me support on older databases.
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

// Clear remember me token from database
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    mysqli_query($conn, "UPDATE employees SET remember_token = NULL WHERE id = $user_id");
    logAction('logout', 'User logged out', $user_id, 'employee');
}

// Clear cookies
setcookie('remember_token', '', time() - 3600, "/");
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

// Destroy session
session_destroy();
header('Location: index.php');
exit();
?>
