<?php
require_once '../includes/auth.php';
redirectIfNotAdmin();
require_once '../includes/db.php';

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="employees_' . date('Y-m-d') . '.csv"');

// Open output stream
$output = fopen('php://output', 'w');

// Add CSV headers
fputcsv($output, [
    'ID', 
    'Employee ID', 
    'Name', 
    'Email', 
    'Department', 
    'Position', 
    'Basic Salary (RM)', 
    'Nationality', 
    'IC Number', 
    'Passport No', 
    'Phone', 
    'Bank Name', 
    'Bank Account', 
    'Join Date', 
    'Status', 
    'Annual Leave Entitlement', 
    'Medical Leave Entitlement',
    'Used Annual Leave',
    'Used Medical Leave'
]);

// Fetch all employees
$export_query = mysqli_query($conn, "SELECT * FROM employees WHERE role='employee' ORDER BY name");

while ($row = mysqli_fetch_assoc($export_query)) {
    fputcsv($output, [
        $row['id'],
        $row['employee_id'],
        $row['name'],
        $row['email'],
        $row['department'],
        $row['position'],
        $row['basic_salary'],
        $row['nationality'],
        $row['ic_number'],
        $row['passport_no'],
        $row['phone'],
        $row['bank_name'],
        $row['bank_account'],
        $row['join_date'],
        $row['status'],
        $row['annual_leave_entitlement'],
        $row['medical_leave_entitlement'],
        $row['used_annual_leave'],
        $row['used_medical_leave']
    ]);
}

fclose($output);
exit();
?>