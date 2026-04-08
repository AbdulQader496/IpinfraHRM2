<?php
require_once 'db.php';

// Malaysia Statutory Calculations (ONLY for Malaysian employees)
function calculateEPF($salary, $is_employee = true, $is_malaysian = true) {
    if (!$is_malaysian) return 0;
    if ($is_employee) {
        return round($salary * 0.11, 2);
    } else {
        return round($salary * 0.13, 2);
    }
}

function calculateSOCSO($salary, $is_employee = true, $is_malaysian = true) {
    if (!$is_malaysian) return 0;
    if ($is_employee) {
        $amount = round($salary * 0.005, 2);
        return min($amount, 19.75);
    } else {
        $amount = round($salary * 0.0175, 2);
        return min($amount, 69.13);
    }
}

function calculateEIS($salary, $is_malaysian = true) {
    if (!$is_malaysian) return 0;
    return round($salary * 0.002, 2);
}

function calculatePCB($salary, $is_malaysian = true) {
    if (!$is_malaysian) return 0;
    if ($salary <= 5000) return 0;
    if ($salary <= 10000) return round($salary * 0.01, 2);
    if ($salary <= 20000) return round($salary * 0.03, 2);
    if ($salary <= 35000) return round($salary * 0.08, 2);
    return round($salary * 0.11, 2);
}

// Get employee nationality status
function isMalaysian($employee_id) {
    global $conn;
    $query = "SELECT nationality FROM employees WHERE id = $employee_id";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row['nationality'] == 'Malaysian';
}

function getLeaveBalance($employee_id) {
    global $conn;
    $query = "SELECT 
                annual_leave_entitlement, 
                used_annual_leave, 
                medical_leave_entitlement, 
                used_medical_leave 
              FROM employees 
              WHERE id = $employee_id";
    $result = mysqli_query($conn, $query);
    $balance = mysqli_fetch_assoc($result);
    
    return [
        'annual_leave_entitlement' => $balance['annual_leave_entitlement'],
        'used_annual_leave' => $balance['used_annual_leave'],
        'medical_leave_entitlement' => $balance['medical_leave_entitlement'],
        'used_medical_leave' => $balance['used_medical_leave'],
        'annual_remaining' => $balance['annual_leave_entitlement'] - $balance['used_annual_leave'],
        'medical_remaining' => $balance['medical_leave_entitlement'] - $balance['used_medical_leave']
    ];
}

function updateLeaveBalance($employee_id, $leave_type, $days) {
    global $conn;
    if ($leave_type == 'annual') {
        mysqli_query($conn, "UPDATE employees SET used_annual_leave = used_annual_leave + $days WHERE id = $employee_id");
    } elseif ($leave_type == 'medical') {
        mysqli_query($conn, "UPDATE employees SET used_medical_leave = used_medical_leave + $days WHERE id = $employee_id");
    }
}

function getEmployeeName($employee_id) {
    global $conn;
    $query = "SELECT name FROM employees WHERE id = $employee_id";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    return $row ? $row['name'] : 'Unknown';
}

function getEmployeeDetails($employee_id) {
    global $conn;
    $query = "SELECT * FROM employees WHERE id = $employee_id";
    $result = mysqli_query($conn, $query);
    return mysqli_fetch_assoc($result);
}

function isHoliday($date) {
    global $conn;
    $query = "SELECT * FROM holidays WHERE holiday_date = '$date'";
    $result = mysqli_query($conn, $query);
    return mysqli_num_rows($result) > 0;
}

function addNotification($employee_id, $title, $message) {
    global $conn;
    $title = mysqli_real_escape_string($conn, $title);
    $message = mysqli_real_escape_string($conn, $message);
    mysqli_query($conn, "INSERT INTO notifications (employee_id, title, message) VALUES ($employee_id, '$title', '$message')");
}

function getUnreadNotificationsCount($employee_id) {
    global $conn;
    $result = mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications WHERE employee_id = $employee_id AND is_read = 0");
    $row = mysqli_fetch_assoc($result);
    return $row['count'];
}
?>