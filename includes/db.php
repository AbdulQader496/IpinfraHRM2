<?php
// MySQL connection settings
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'hrmmanagement';

// Create connection
$conn = mysqli_connect($host, $username, $password, $database);

// Check connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to UTF-8
mysqli_set_charset($conn, "utf8");

// Set timezone to Malaysia
date_default_timezone_set('Asia/Kuala_Lumpur');

// Optional: Set error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
?>