<?php
require_once 'db.php';

// Malaysia Statutory Calculations (ONLY for Malaysian employees)
function calculateEPF($salary, $is_employee = true, $is_malaysian = true) {
    if (!$is_malaysian) return 0;
    return $is_employee ? round($salary * 0.11, 2) : round($salary * 0.13, 2);
}

function calculateSOCSO($salary, $is_employee = true, $is_malaysian = true) {
    if (!$is_malaysian) return 0;
    if ($is_employee) {
        return min(round($salary * 0.005, 2), 19.75);
    } else {
        return min(round($salary * 0.0175, 2), 69.13);
    }
}

function calculateEIS($salary, $is_malaysian = true) {
    if (!$is_malaysian) return 0;
    return round($salary * 0.002, 2);
}

function calculatePCB($salary, $is_malaysian = true) {
    if (!$is_malaysian) return 0;
    if ($salary <= 5000)  return 0;
    if ($salary <= 10000) return round($salary * 0.01, 2);
    if ($salary <= 20000) return round($salary * 0.03, 2);
    if ($salary <= 35000) return round($salary * 0.08, 2);
    return round($salary * 0.11, 2);
}

function isMalaysian($employee_id) {
    global $conn;
    $id = intval($employee_id);
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nationality FROM employees WHERE id = $id"));
    return $row ? $row['nationality'] == 'Malaysian' : false;
}

function getLeaveBalance($employee_id) {
    global $conn;
    $id = intval($employee_id);
    $balance = mysqli_fetch_assoc(mysqli_query($conn, "SELECT
        annual_leave_entitlement, used_annual_leave,
        medical_leave_entitlement, used_medical_leave
        FROM employees WHERE id = $id"));
    if (!$balance) {
        return ['annual_leave_entitlement'=>0,'used_annual_leave'=>0,'medical_leave_entitlement'=>0,'used_medical_leave'=>0,'annual_remaining'=>0,'medical_remaining'=>0];
    }
    return [
        'annual_leave_entitlement'  => $balance['annual_leave_entitlement'],
        'used_annual_leave'         => $balance['used_annual_leave'],
        'medical_leave_entitlement' => $balance['medical_leave_entitlement'],
        'used_medical_leave'        => $balance['used_medical_leave'],
        'annual_remaining'          => $balance['annual_leave_entitlement'] - $balance['used_annual_leave'],
        'medical_remaining'         => $balance['medical_leave_entitlement'] - $balance['used_medical_leave'],
    ];
}

function updateLeaveBalance($employee_id, $leave_type, $days) {
    global $conn;
    $id   = intval($employee_id);
    $days = floatval($days);
    if ($leave_type == 'AL' || $leave_type == 'annual') {
        mysqli_query($conn, "UPDATE employees SET used_annual_leave = used_annual_leave + $days WHERE id = $id");
    } elseif ($leave_type == 'ML' || $leave_type == 'medical') {
        mysqli_query($conn, "UPDATE employees SET used_medical_leave = used_medical_leave + $days WHERE id = $id");
    }
    // UL/EML/HD/emergency: no balance to track
}

function getEmployeeName($employee_id) {
    global $conn;
    $id  = intval($employee_id);
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM employees WHERE id = $id"));
    return $row ? $row['name'] : 'Unknown';
}

function getEmployeeDetails($employee_id) {
    global $conn;
    $id = intval($employee_id);
    return mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM employees WHERE id = $id"));
}

function isHoliday($date) {
    global $conn;
    $date = mysqli_real_escape_string($conn, $date);
    return mysqli_num_rows(mysqli_query($conn, "SELECT id FROM holidays WHERE holiday_date = '$date'")) > 0;
}

function addNotification($employee_id, $title, $message) {
    global $conn;
    $id      = intval($employee_id);
    $title   = mysqli_real_escape_string($conn, $title);
    $message = mysqli_real_escape_string($conn, $message);
    mysqli_query($conn, "INSERT INTO notifications (employee_id, title, message) VALUES ($id, '$title', '$message')");
}

function getUnreadNotificationsCount($employee_id) {
    global $conn;
    $id  = intval($employee_id);
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications WHERE employee_id = $id AND is_read = 0"));
    return $row ? $row['count'] : 0;
}

function logAction($action, $description, $target_id = null, $target_type = null) {
    global $conn;
    try {
        // Ensure table exists (first-time setup)
        mysqli_query($conn, "CREATE TABLE IF NOT EXISTS audit_log (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT DEFAULT 0,
            action VARCHAR(50),
            description TEXT,
            target_type VARCHAR(50),
            target_id INT DEFAULT 0,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_action (action),
            INDEX idx_created (created_at)
        )");
        $user_id     = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
        $action_esc  = mysqli_real_escape_string($conn, $action);
        $description = mysqli_real_escape_string($conn, $description);
        $target_type = mysqli_real_escape_string($conn, $target_type ?? '');
        $target_id   = intval($target_id ?? 0);
        $ip          = mysqli_real_escape_string($conn, $_SERVER['REMOTE_ADDR'] ?? '');
        mysqli_query($conn, "INSERT INTO audit_log (user_id, action, description, target_type, target_id, ip_address)
            VALUES ($user_id, '$action_esc', '$description', '$target_type', $target_id, '$ip')");
    } catch (Exception $e) {
        // Never crash the calling page over a logging failure
        error_log('logAction failed: ' . $e->getMessage());
    }
}
?>
