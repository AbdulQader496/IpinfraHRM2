<?php
require_once 'db.php';
/** @var mysqli $conn */

// Malaysia Statutory Calculations (ONLY for Malaysian employees)
function calculateEPF(float $salary, bool $is_employee = true, bool $is_malaysian = true) {
    if (!$is_malaysian) return 0;
    if ($is_employee) return round($salary * 0.11, 2);
    // Employer rate: 13% for wages ≤ RM5,000; 12% for wages > RM5,000 (EPF Third Schedule)
    return $salary <= 5000 ? round($salary * 0.13, 2) : round($salary * 0.12, 2);
}

function calculateSOCSO(float $salary, bool $is_employee = true, bool $is_malaysian = true) {
    if (!$is_malaysian) return 0;
    $insurable = min($salary, 5000);
    if ($is_employee) {
        return min(round($insurable * 0.005, 2), 19.75);
    } else {
        return min(round($insurable * 0.0175, 2), 69.13);
    }
}

function calculateEIS(float $salary, bool $is_malaysian = true) {
    if (!$is_malaysian) return 0;
    $insurable = min($salary, 4000);
    return round($insurable * 0.002, 2);
}

function calculatePCB(float $salary, bool $is_malaysian = true) {
    return 0; // PCB not applicable — employees handle own tax filing
}

function isMalaysian(int $employee_id) {
    global $conn;
    $id = intval($employee_id);
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nationality FROM employees WHERE id = $id"));
    return $row ? $row['nationality'] == 'Malaysian' : false;
}

function getLeaveBalance(int $employee_id) {
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
        'annual_remaining'          => max(0, $balance['annual_leave_entitlement'] - $balance['used_annual_leave']),
        'medical_remaining'         => max(0, $balance['medical_leave_entitlement'] - $balance['used_medical_leave']),
    ];
}

// updateLeaveBalance() used to live here but nothing ever called it -- admin/manage_leave.php
// does its own inline UPDATEs (with GREATEST(0, ...) floors and atomic status guards) instead,
// and this version had neither, so it was a trap for whoever next wired it up. Removed;
// leave-balance changes belong in manage_leave.php's approve/undo/delete handlers.

function getEmployeeName(int $employee_id) {
    global $conn;
    $id  = intval($employee_id);
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name FROM employees WHERE id = $id"));
    return $row ? $row['name'] : 'Unknown';
}

function getEmployeeDetails(int $employee_id) {
    global $conn;
    $id = intval($employee_id);
    return mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM employees WHERE id = $id"));
}

function isHoliday(string $date) {
    global $conn;
    $date = mysqli_real_escape_string($conn, $date);
    return mysqli_num_rows(mysqli_query($conn, "SELECT id FROM holidays WHERE holiday_date = '$date'")) > 0;
}

function addNotification(int $employee_id, string $title, string $message) {
    global $conn;
    $id      = intval($employee_id);
    $title   = mysqli_real_escape_string($conn, $title);
    $message = mysqli_real_escape_string($conn, $message);
    mysqli_query($conn, "INSERT INTO notifications (employee_id, title, message) VALUES ($id, '$title', '$message')");
}

function getUnreadNotificationsCount(int $employee_id) {
    global $conn;
    $id  = intval($employee_id);
    $row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM notifications WHERE employee_id = $id AND is_read = 0"));
    return $row ? $row['count'] : 0;
}

function logAction(string $action, string $description, ?int $target_id = null, ?string $target_type = null) {
    global $conn;
    $user_id     = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;
    $action      = mysqli_real_escape_string($conn, $action);
    $description = mysqli_real_escape_string($conn, $description);
    $target_type = mysqli_real_escape_string($conn, $target_type ?? '');
    $target_id   = intval($target_id ?? 0);
    $ip          = mysqli_real_escape_string($conn, $_SERVER['REMOTE_ADDR'] ?? '');
    try {
        mysqli_query($conn, "INSERT INTO audit_log (user_id, action, description, target_type, target_id, ip_address)
            VALUES ($user_id, '$action', '$description', '$target_type', $target_id, '$ip')");
    } catch (Exception $e) { /* audit_log table may not exist yet */ }
}
?>
