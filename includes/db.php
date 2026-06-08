<?php
$host = getenv('DB_HOST') ?: 'db';
$port = (int) (getenv('DB_PORT') ?: 3306);
$username = getenv('DB_USER') ?: 'hrm_user';
$password = getenv('DB_PASSWORD') ?: 'hrm_password';
$database = getenv('DB_NAME') ?: 'hrmmanagement';
$timezone = getenv('APP_TIMEZONE') ?: 'Asia/Kuala_Lumpur';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$conn = mysqli_connect($host, $username, $password, $database, $port);

if (!$conn) {
    die('Connection failed: ' . mysqli_connect_error());
}

mysqli_set_charset($conn, 'utf8');
date_default_timezone_set($timezone);
?>
