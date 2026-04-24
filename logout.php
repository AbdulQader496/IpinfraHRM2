<?php
session_start();
require_once 'includes/db.php';

// Clear remember me token from database
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    mysqli_query($conn, "UPDATE employees SET remember_token = NULL WHERE id = $user_id");
}

// Clear cookie
setcookie('remember_token', '', time() - 3600, "/");

// Destroy session
session_destroy();
header('Location: index.php');
exit();
?>