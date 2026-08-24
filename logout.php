<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

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