<?php
require_once '../includes/auth.php';
redirectIfNotAdmin();
require_once '../includes/db.php';
/** @var mysqli $conn */
require_once '../includes/functions.php';
require_once '../includes/toast_fn.php';

// ========================================
// PAGINATION & FILTERS
// ========================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
if ($per_page < 1) $per_page = 10;
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$allowed_statuses = ['pending', 'approved', 'rejected'];
$status_filter = isset($_GET['status']) && in_array($_GET['status'], $allowed_statuses) ? $_GET['status'] : (isset($_GET['status']) ? '' : 'pending');
$type_filter = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : '';
$date_from = isset($_GET['date_from']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date_to']) ? $_GET['date_to'] : '';

// Build WHERE clause
$where = "WHERE 1=1";
if (!empty($search)) {
    $where .= " AND (e.name LIKE '%$search%' OR e.employee_id LIKE '%$search%')";
}
if (!empty($status_filter)) {
    $where .= " AND l.status = '$status_filter'";
}
if (!empty($type_filter)) {
    $where .= " AND l.leave_type = '$type_filter'";
}
if (!empty($date_from)) {
    $where .= " AND l.start_date >= '$date_from'";
}
if (!empty($date_to)) {
    $where .= " AND l.end_date <= '$date_to'";
}

// ========================================
// CSV EXPORT
// ========================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = 'leave_export_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    // BOM for Excel UTF-8 compatibility
    fputs($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Employee ID', 'Name', 'Department', 'Leave Type', 'Start Date', 'End Date', 'Total Days', 'Status', 'Applied Date']);

    $export_result = mysqli_query($conn,
        "SELECT e.employee_id, e.name, e.department, l.leave_type, l.start_date, l.end_date,
                CASE WHEN l.half_day IS NOT NULL AND l.half_day != 'none' THEN 0.5
                     ELSE (DATEDIFF(l.end_date, l.start_date) + 1) END AS total_days,
                l.status, l.applied_at
         FROM leaves l
         JOIN employees e ON l.employee_id = e.id
         $where
         ORDER BY l.applied_at DESC"
    );

    while ($row = mysqli_fetch_assoc($export_result)) {
        fputcsv($out, [
            $row['employee_id'],
            $row['name'],
            $row['department'],
            ucfirst($row['leave_type']),
            $row['start_date'],
            $row['end_date'],
            $row['total_days'],
            ucfirst($row['status']),
            $row['applied_at'],
        ]);
    }
    fclose($out);
    exit();
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM leaves l JOIN employees e ON l.employee_id = e.id $where";
$count_result = mysqli_query($conn, $count_query);
$total_rows = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_rows / $per_page);
$offset = ($page - 1) * $per_page;

// Get paginated leave applications
$leaves = mysqli_query($conn, "SELECT l.*, e.name, e.employee_id, e.department 
    FROM leaves l 
    JOIN employees e ON l.employee_id = e.id 
    $where 
    ORDER BY l.applied_at DESC 
    LIMIT $offset, $per_page");

// ========================================
// LEAVE TYPES MANAGEMENT
// ========================================
if (isset($_POST['add_leave_type'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        showToast('Invalid request.', 'error');
        header('Location: manage_leave.php'); exit();
    }
    $leave_name           = mysqli_real_escape_string($conn, $_POST['leave_name']);
    $leave_code           = mysqli_real_escape_string($conn, $_POST['leave_code']);
    $days_per_year        = intval($_POST['days_per_year']);
    $is_paid              = isset($_POST['is_paid']) ? 1 : 0;
    $requires_attachment  = isset($_POST['requires_attachment']) ? 1 : 0;
    $max_consecutive_days = intval($_POST['max_consecutive_days']);
    $color_code           = mysqli_real_escape_string($conn, $_POST['color_code']);

    // leaves.leave_type is a hard ENUM('annual','medical','emergency','unpaid'). A leave_code
    // outside that set would silently save as a blank leave_type when an employee applies —
    // reject it here instead of letting the same bug resurface for a new custom type.
    if (!in_array($leave_code, ['annual', 'medical', 'emergency', 'unpaid'], true)) {
        showToast('Leave code must be one of: annual, medical, emergency, unpaid (the system only supports these types right now).', 'error');
        header('Location: manage_leave.php'); exit();
    }

    mysqli_query($conn, "INSERT INTO leave_types (leave_name, leave_code, days_per_year, is_paid, requires_attachment, max_consecutive_days, color_code)
        VALUES ('$leave_name', '$leave_code', $days_per_year, $is_paid, $requires_attachment, $max_consecutive_days, '$color_code')");
    header('Location: manage_leave.php');
    exit();
}

if (isset($_POST['update_leave_type'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        showToast('Invalid request.', 'error');
        header('Location: manage_leave.php'); exit();
    }
    $id                   = intval($_POST['type_id']);
    $leave_name           = mysqli_real_escape_string($conn, $_POST['leave_name']);
    $leave_code           = mysqli_real_escape_string($conn, $_POST['leave_code']);
    $days_per_year        = intval($_POST['days_per_year']);
    $is_paid              = isset($_POST['is_paid']) ? 1 : 0;
    $requires_attachment  = isset($_POST['requires_attachment']) ? 1 : 0;
    $max_consecutive_days = intval($_POST['max_consecutive_days']);
    $status               = mysqli_real_escape_string($conn, $_POST['status']);
    $color_code           = mysqli_real_escape_string($conn, $_POST['color_code']);

    if (!in_array($leave_code, ['annual', 'medical', 'emergency', 'unpaid'], true)) {
        showToast('Leave code must be one of: annual, medical, emergency, unpaid (the system only supports these types right now).', 'error');
        header('Location: manage_leave.php'); exit();
    }

    mysqli_query($conn, "UPDATE leave_types SET
        leave_name='$leave_name', leave_code='$leave_code', days_per_year=$days_per_year,
        is_paid=$is_paid, requires_attachment=$requires_attachment,
        max_consecutive_days=$max_consecutive_days, status='$status', color_code='$color_code'
        WHERE id=$id");
    header('Location: manage_leave.php');
    exit();
}

if (isset($_POST['delete_type'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        showToast('Invalid request.', 'error');
        header('Location: manage_leave.php'); exit();
    }
    $id = intval($_POST['delete_type']);
    mysqli_query($conn, "DELETE FROM leave_types WHERE id=$id");
    header('Location: manage_leave.php');
    exit();
}

// ========================================
// BULK APPROVE / REJECT LEAVES
// ========================================
if (isset($_POST['bulk_leave_action']) && !empty($_POST['ids'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        showToast('Invalid request.', 'error');
        header('Location: manage_leave.php'); exit();
    }
    $bulk_action = $_POST['bulk_leave_action'];
    $bulk_status = ($bulk_action === 'approve') ? 'approved' : 'rejected';
    $ids = array_map('intval', $_POST['ids']);
    $ids_safe = implode(',', $ids);

    $bulk_rows = mysqli_query($conn, "SELECT * FROM leaves WHERE id IN ($ids_safe) AND status='pending'");
    $affected = 0;
    while ($br = mysqli_fetch_assoc($bulk_rows)) {
        $emp_check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT employee_type FROM employees WHERE id = {$br['employee_id']}"));
        if (isset($emp_check['employee_type']) && $emp_check['employee_type'] == 'intern' && !in_array($br['leave_type'], ['medical', 'unpaid'])) {
            continue; // interns can only have medical or unpaid leave approved
        }
        $days = (isset($br['half_day']) && $br['half_day'] != 'none') ? 0.5
              : (strtotime($br['end_date']) - strtotime($br['start_date'])) / 86400 + 1;

        mysqli_begin_transaction($conn);
        // Atomic guard: only proceed if this row was still 'pending' at update time —
        // prevents double-deducting balances if another request processed it concurrently.
        mysqli_query($conn, "UPDATE leaves SET status='$bulk_status' WHERE id={$br['id']} AND status='pending'");
        if (mysqli_affected_rows($conn) === 0) {
            mysqli_commit($conn);
            continue;
        }
        if ($bulk_status === 'approved') {
            if ($br['leave_type'] == 'annual') {
                mysqli_query($conn, "UPDATE employees SET used_annual_leave = used_annual_leave + $days WHERE id = {$br['employee_id']}");
            } elseif ($br['leave_type'] == 'medical') {
                mysqli_query($conn, "UPDATE employees SET used_medical_leave = used_medical_leave + $days WHERE id = {$br['employee_id']}");
            }
            mysqli_commit($conn);
            addNotification($br['employee_id'], 'Leave Approved', 'Your ' . $br['leave_type'] . ' leave has been approved.');
        } else {
            mysqli_commit($conn);
            addNotification($br['employee_id'], 'Leave Rejected', 'Your ' . $br['leave_type'] . ' leave has been rejected.');
        }
        logAction($bulk_status === 'approved' ? 'approve' : 'reject', 'Bulk ' . $bulk_status . ' leave request', $br['id'], 'leave');
        $affected++;
    }

    if ($affected > 0) {
        if ($bulk_status === 'approved') {
            showToast("$affected leave application(s) approved.", 'success');
        } else {
            showToast("$affected leave application(s) rejected.", 'warning');
        }
    }

    header('Location: manage_leave.php');
    exit();
}

// ========================================
// LEAVE REQUESTS HANDLING
// ========================================
if (isset($_POST['action']) && isset($_POST['id'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        showToast('Invalid request.', 'error');
        header('Location: manage_leave.php'); exit();
    }
    $id     = intval($_POST['id']);
    $action = $_POST['action'];
    $status = ($action == 'approve') ? 'approved' : 'rejected';

    $leave = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM leaves WHERE id=$id AND status='pending'"));
    if ($leave) {
        $emp_type = mysqli_fetch_assoc(mysqli_query($conn, "SELECT employee_type FROM employees WHERE id = {$leave['employee_id']}"));
        if (isset($emp_type['employee_type']) && $emp_type['employee_type'] == 'intern' && !in_array($leave['leave_type'], ['medical', 'unpaid']) && $action == 'approve') {
            // redirect back with error - interns can only have medical or unpaid leave
            showToast('Interns can only be approved for Medical or Unpaid Leave.', 'error');
            header('Location: manage_leave.php'); exit();
        }
        $days = (isset($leave['half_day']) && $leave['half_day'] != 'none') ? 0.5
              : (strtotime($leave['end_date']) - strtotime($leave['start_date'])) / 86400 + 1;

        mysqli_begin_transaction($conn);
        // Atomic guard: only proceed if this row was still 'pending' at update time —
        // prevents double-deducting balances if another request (e.g. two admin tabs) processed it concurrently.
        mysqli_query($conn, "UPDATE leaves SET status='$status' WHERE id=$id AND status='pending'");
        if (mysqli_affected_rows($conn) === 0) {
            mysqli_commit($conn);
            showToast('This leave request was already processed.', 'warning');
            header('Location: manage_leave.php'); exit();
        }
        if ($status == 'approved') {
            if ($leave['leave_type'] == 'annual') {
                mysqli_query($conn, "UPDATE employees SET used_annual_leave = used_annual_leave + $days WHERE id = {$leave['employee_id']}");
            } elseif ($leave['leave_type'] == 'medical') {
                mysqli_query($conn, "UPDATE employees SET used_medical_leave = used_medical_leave + $days WHERE id = {$leave['employee_id']}");
            }
            mysqli_commit($conn);
            addNotification($leave['employee_id'], 'Leave Approved', 'Your ' . $leave['leave_type'] . ' leave has been approved.');
            showToast('Leave application approved successfully.', 'success');
        } else {
            mysqli_commit($conn);
            addNotification($leave['employee_id'], 'Leave Rejected', 'Your ' . $leave['leave_type'] . ' leave has been rejected.');
            showToast('Leave application rejected.', 'warning');
        }
        logAction($status === 'approved' ? 'approve' : 'reject', ucfirst($status) . ' ' . $leave['leave_type'] . ' leave request', $id, 'leave');
    }
    header('Location: manage_leave.php');
    exit();
}

// ========================================
// UNDO (REVERT) LEAVE DECISION
// ========================================
if (isset($_POST['undo_leave'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        showToast('Security error.', 'error');
        header('Location: manage_leave.php'); exit();
    }
    $id = intval($_POST['undo_leave']);
    $leave = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM leaves WHERE id=$id AND status IN ('approved','rejected')"));
    if ($leave) {
        $days = (isset($leave['half_day']) && $leave['half_day'] != 'none') ? 0.5
              : (strtotime($leave['end_date']) - strtotime($leave['start_date'])) / 86400 + 1;
        $prev_status = $leave['status'];
        mysqli_begin_transaction($conn);
        // Atomic guard: only proceed if the row was still in the status we just read —
        // prevents double-restoring the balance if another request reverted it concurrently.
        mysqli_query($conn, "UPDATE leaves SET status='pending' WHERE id=$id AND status='$prev_status'");
        if (mysqli_affected_rows($conn) === 0) {
            mysqli_commit($conn);
            showToast('This leave request was already changed elsewhere.', 'warning');
            header('Location: manage_leave.php'); exit();
        }
        if ($prev_status == 'approved') {
            if ($leave['leave_type'] == 'annual') {
                mysqli_query($conn, "UPDATE employees SET used_annual_leave = GREATEST(0, used_annual_leave - $days) WHERE id = {$leave['employee_id']}");
            } elseif ($leave['leave_type'] == 'medical') {
                mysqli_query($conn, "UPDATE employees SET used_medical_leave = GREATEST(0, used_medical_leave - $days) WHERE id = {$leave['employee_id']}");
            }
        }
        mysqli_commit($conn);
        logAction('update', 'Reverted ' . $prev_status . ' leave request to pending', $id, 'leave');
        showToast('Leave reverted to pending.', 'success');
    }
    header('Location: manage_leave.php'); exit();
}

// ========================================
// DELETE LEAVE (admin cleanup of old records — any status)
// ========================================
// If the leave being removed was approved, its days were already added to
// used_annual_leave / used_medical_leave at approval time -- give them back here,
// the same way "Undo" already does, so deleting a record never leaves a balance
// inflated for leave that no longer has any trace in the leaves table.
if (isset($_POST['delete_leave'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        showToast('Security error.', 'error');
        header('Location: manage_leave.php'); exit();
    }
    $id = intval($_POST['delete_leave']);
    // Read the row under a lock inside the transaction: otherwise a concurrent approve/undo
    // could change status between this read and the DELETE, and the balance restore below
    // would act on a stale status (restoring days never deducted, or missing ones that were).
    mysqli_begin_transaction($conn);
    $leave = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM leaves WHERE id=$id FOR UPDATE"));
    if ($leave) {
        mysqli_query($conn, "DELETE FROM leaves WHERE id=$id");
        if (mysqli_affected_rows($conn) > 0) {
            if ($leave['status'] == 'approved') {
                $days = (isset($leave['half_day']) && $leave['half_day'] != 'none') ? 0.5
                      : (strtotime($leave['end_date']) - strtotime($leave['start_date'])) / 86400 + 1;
                if ($leave['leave_type'] == 'annual') {
                    mysqli_query($conn, "UPDATE employees SET used_annual_leave = GREATEST(0, used_annual_leave - $days) WHERE id = {$leave['employee_id']}");
                } elseif ($leave['leave_type'] == 'medical') {
                    mysqli_query($conn, "UPDATE employees SET used_medical_leave = GREATEST(0, used_medical_leave - $days) WHERE id = {$leave['employee_id']}");
                }
            }
            mysqli_commit($conn);
            if (!empty($leave['attachment']) && file_exists("../uploads/" . $leave['attachment'])) {
                unlink("../uploads/" . $leave['attachment']);
            }
            logAction('delete', 'Deleted ' . $leave['leave_type'] . ' leave record', $id, 'leave');
            showToast('Leave record deleted.', 'info');
        } else {
            mysqli_rollback($conn);
        }
    } else {
        mysqli_rollback($conn);
    }
    header('Location: manage_leave.php'); exit();
}

// ========================================
// ADJUST LEAVE BALANCE
// ========================================
if (isset($_POST['adjust_leave'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        showToast('Invalid request.', 'error');
        header('Location: manage_leave.php?tab=balances'); exit();
    }
    $employee_id   = intval($_POST['employee_id']);
    $adjust_type   = $_POST['adjust_type'];
    $adjust_field  = $_POST['adjust_field'];
    $action_type   = $_POST['action_type'];
    $adjust_amount = floatval($_POST['adjust_amount']);
    $op            = ($action_type == 'add') ? '+' : '-';

    // A blank/non-numeric amount silently becomes 0 via floatval(), which would run a
    // no-op update ("field = field + 0") and still show success — surface that instead.
    if ($adjust_amount <= 0) {
        showToast('Enter an amount greater than 0.', 'error');
        header('Location: manage_leave.php?tab=balances'); exit();
    }

    if (!in_array($adjust_type, ['annual', 'medical'], true)) {
        showToast('Invalid leave type.', 'error');
        header('Location: manage_leave.php?tab=balances'); exit();
    }

    $field_map = ['annual_entitlement' => 'annual_leave_entitlement', 'annual_used' => 'used_annual_leave', 'medical_entitlement' => 'medical_leave_entitlement', 'medical_used' => 'used_medical_leave'];
    $key = $adjust_type . '_' . $adjust_field;
    if (!isset($field_map[$key])) { showToast('Invalid field.', 'error'); header('Location: manage_leave.php?tab=balances'); exit(); }
    $field = $field_map[$key];

    // Clamp used-leave subtraction at 0 (matches the leave-approval undo logic elsewhere)
    // instead of letting it go negative.
    if ($op === '-') {
        mysqli_query($conn, "UPDATE employees SET $field = GREATEST(0, $field - $adjust_amount) WHERE id = $employee_id AND role='employee'");
    } else {
        mysqli_query($conn, "UPDATE employees SET $field = $field + $adjust_amount WHERE id = $employee_id AND role='employee'");
    }

    // employee_id came from a hidden field populated by JS off the row's data — confirm
    // it actually matched a real employee instead of silently reporting success either way.
    $exists = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM employees WHERE id=$employee_id AND role='employee'"));
    if (!$exists) {
        showToast('Could not find that employee — please refresh and try again.', 'error');
        header('Location: manage_leave.php?tab=balances'); exit();
    }

    logAction('update', "Adjusted $field: $action_type $adjust_amount", $employee_id, 'employee');
    showToast('Leave balance updated.', 'success');
    header('Location: manage_leave.php?tab=balances'); exit();
}

// ========================================
// RESET ALL LEAVE BALANCES (Year Reset)
// ========================================
if (isset($_POST['reset_leave_balances']) && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    mysqli_query($conn, "UPDATE employees SET used_annual_leave = 0, used_medical_leave = 0 WHERE role = 'employee'");
    logAction('update', 'Reset all employee leave balances (new year)', null, 'employee');
    showToast('All leave balances reset to 0 for the new year.', 'success');
    header('Location: manage_leave.php?tab=balances'); exit();
}

// Get all employees with leave balances
$employees_balance = mysqli_query($conn, "SELECT 
    id, name, employee_id, department,
    annual_leave_entitlement, used_annual_leave,
    (annual_leave_entitlement - used_annual_leave) as annual_remaining,
    medical_leave_entitlement, used_medical_leave,
    (medical_leave_entitlement - used_medical_leave) as medical_remaining
    FROM employees WHERE role='employee' ORDER BY name");

// Get leave types
$leave_types = mysqli_query($conn, "SELECT * FROM leave_types ORDER BY leave_name");

// Get unique leave types for filter dropdown
$leave_type_options = mysqli_query($conn, "SELECT DISTINCT leave_type FROM leaves");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Leave Management - IPINFRA HRM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .sidebar { transition: transform 0.3s ease-in-out; }
        .tab-active { background-color: #2563eb; color: white; }
        .tab-inactive { background-color: #e5e7eb; color: #4b5563; }
        .filter-active { background-color: #2563eb; color: white; border-color: #2563eb; }

        /* Bulk action bar */
        #leaveBulkBar {
            transition: transform 0.3s ease, opacity 0.3s ease;
        }
        #leaveBulkBar.hidden-bar {
            transform: translateY(100%);
            opacity: 0;
            pointer-events: none;
        }
        .bulk-check {
            width: 18px; height: 18px;
            accent-color: #2563eb;
            cursor: pointer;
            flex-shrink: 0;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen pb-20">
<?php require_once '../includes/global_ui.php'; ?>
<?php require_once '../includes/toast.php'; ?>
<?php require_once '../includes/confirm_modal.php'; ?>

<!-- Premium Mobile Header -->
<div class="bg-gradient-to-r from-slate-900 via-indigo-900 to-slate-900 text-white sticky top-0 z-40 shadow-2xl">
    <div class="flex justify-between items-center px-4 py-4">
        <div class="flex items-center gap-3">
            <button onclick="toggleSidebar()" class="text-white/80 hover:text-white p-2 rounded-full hover:bg-white/10">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg">
                <span class="text-white font-bold text-sm">IN</span>
            </div>
            <div>
                <p class="text-xs text-blue-200 font-medium">IPINFRA NETWORKS</p>
                <p class="text-sm font-bold tracking-wide">Admin Portal</p>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/admin_sidebar.php'; ?>

<!-- Main Content -->
<div class="px-4 py-6 pb-24 max-w-7xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-2">Leave Management</h1>
    <p class="text-sm text-gray-500 mb-6">Manage leave requests, employee balances, and leave types</p>

    <!-- Stats Summary -->
    <?php
    $total_leaves = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM leaves"))['count'];
    $pending_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM leaves WHERE status='pending'"))['count'];
    $approved_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM leaves WHERE status='approved'"))['count'];
    $rejected_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM leaves WHERE status='rejected'"))['count'];
    ?>
    <div class="grid grid-cols-4 gap-3 mb-6">
        <div class="bg-blue-100 rounded-xl p-3 text-center">
            <p class="text-2xl font-bold text-blue-700"><?php echo $total_leaves; ?></p>
            <p class="text-xs text-blue-600">Total Requests</p>
        </div>
        <div class="bg-yellow-100 rounded-xl p-3 text-center">
            <p class="text-2xl font-bold text-yellow-700"><?php echo $pending_count; ?></p>
            <p class="text-xs text-yellow-600">Pending</p>
        </div>
        <div class="bg-green-100 rounded-xl p-3 text-center">
            <p class="text-2xl font-bold text-green-700"><?php echo $approved_count; ?></p>
            <p class="text-xs text-green-600">Approved</p>
        </div>
        <div class="bg-red-100 rounded-xl p-3 text-center">
            <p class="text-2xl font-bold text-red-700"><?php echo $rejected_count; ?></p>
            <p class="text-xs text-red-600">Rejected</p>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="bg-white rounded-xl shadow-md p-4 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-3">
            <div class="md:col-span-2">
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by name or ID..." 
                           class="w-full pl-9 pr-3 py-2 border border-gray-200 rounded-lg text-sm">
                </div>
            </div>
            <div>
                <select name="status" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            <div>
                <select name="type" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                    <option value="">All Types</option>
                    <option value="annual" <?php echo $type_filter == 'annual' ? 'selected' : ''; ?>>Annual Leave</option>
                    <option value="medical" <?php echo $type_filter == 'medical' ? 'selected' : ''; ?>>Medical Leave</option>
                    <option value="emergency" <?php echo $type_filter == 'emergency' ? 'selected' : ''; ?>>Emergency Leave</option>
                    <option value="unpaid" <?php echo $type_filter == 'unpaid' ? 'selected' : ''; ?>>Unpaid Leave</option>
                </select>
            </div>
            <div>
                <input type="date" name="date_from" value="<?php echo $date_from; ?>" placeholder="From" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
            </div>
            <div>
                <input type="date" name="date_to" value="<?php echo $date_to; ?>" placeholder="To" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-blue-600 text-white px-3 py-2 rounded-lg text-sm hover:bg-blue-700">Filter</button>
                <a href="manage_leave.php?status=pending" class="flex-1 bg-gray-200 text-gray-700 px-3 py-2 rounded-lg text-sm text-center hover:bg-gray-300">Reset</a>
            </div>
        </form>
        <!-- Export CSV — preserves active filters -->
        <div class="mt-3 flex justify-end">
            <a href="?export=csv&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($status_filter); ?>&type=<?php echo urlencode($type_filter); ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>"
               class="inline-flex items-center gap-2 bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition">
                <i class="fas fa-file-csv"></i> Export CSV
            </a>
        </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-2 mb-6 overflow-x-auto pb-2">
        <button onclick="showTab('requests')" id="tabRequests" class="tab-active px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap">
            <i class="fas fa-clock mr-1"></i> Leave Requests
            <?php if($pending_count > 0): ?>
                <span class="ml-1 bg-red-500 text-white text-xs px-1.5 py-0.5 rounded-full"><?php echo $pending_count; ?></span>
            <?php endif; ?>
        </button>
        <button onclick="showTab('balances')" id="tabBalances" class="tab-inactive px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap">
            <i class="fas fa-chart-line mr-1"></i> Leave Balances
        </button>
        <button onclick="showTab('types')" id="tabTypes" class="tab-inactive px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap">
            <i class="fas fa-tags mr-1"></i> Leave Types
        </button>
    </div>

    <!-- ======================================== -->
    <!-- LEAVE REQUESTS TAB (PAGINATED) -->
    <!-- ======================================== -->
    <div id="requestsTab">
        <!-- Bulk form -->
        <form id="leaveBulkForm" method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="bulk_leave_action" id="leaveBulkActionInput" value="">

        <div class="bg-white rounded-xl shadow-xl overflow-hidden">
            <div class="bg-gray-50 px-4 py-3 border-b flex justify-between items-center flex-wrap gap-2">
                <div class="flex items-center gap-3">
                    <p class="font-semibold text-gray-800">
                        <i class="fas fa-clock mr-2 text-blue-600"></i>
                        Leave Applications
                        <span class="text-sm font-normal text-gray-500">(<?php echo $total_rows; ?> total)</span>
                    </p>
                    <span id="leaveSelectedCount" class="text-xs text-blue-600 font-medium hidden">0 selected</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500">Show:</span>
                    <select name="per_page" onchange="window.location.href=this.value" class="text-sm border rounded px-2 py-1">
                        <option value="?per_page=10&page=1&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="?per_page=25&page=1&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                        <option value="?per_page=50&page=1&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="?per_page=100&page=1&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                </div>
            </div>

            <?php if(mysqli_num_rows($leaves) > 0): ?>
                <!-- Select All row -->
                <div class="bg-blue-50 px-4 py-2 border-b flex items-center gap-2">
                    <input type="checkbox" id="leaveSelectAll" class="bulk-check" onchange="leaveToggleSelectAll(this)">
                    <label for="leaveSelectAll" class="text-sm font-medium text-blue-700 cursor-pointer select-none">Select All Pending</label>
                </div>
                <div class="divide-y">
                    <?php while ($row = mysqli_fetch_assoc($leaves)): ?>
                    <div class="p-4 hover:bg-gray-50 transition">
                        <div class="flex justify-between items-start flex-wrap gap-3">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 flex-wrap mb-2">
                                    <?php if ($row['status'] === 'pending'): ?>
                                    <input type="checkbox" name="ids[]" value="<?php echo $row['id']; ?>" class="bulk-check" onchange="leaveUpdateBulkBar()">
                                    <?php endif; ?>
                                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($row['name']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo $row['employee_id']; ?></p>
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600"><?php echo $row['department']; ?></span>
                                </div>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
                                    <p><span class="text-gray-500">Type:</span> <span class="font-medium"><?php echo !empty($row['leave_type']) ? ucfirst($row['leave_type']) : '<span class="text-red-500 text-xs">Not specified</span>'; ?></span></p>
                                    <p><span class="text-gray-500">Duration:</span> 
                                        <?php 
                                        $days = (strtotime($row['end_date']) - strtotime($row['start_date'])) / 86400 + 1;
                                        if ($row['half_day'] != 'none') {
                                            $days = 0.5;
                                            echo '<span class="text-orange-600">Half Day (' . ($row['half_day'] == 'first_half' ? 'AM' : 'PM') . ')</span>';
                                        } else {
                                            echo $days . ' day(s)';
                                        }
                                        ?>
                                    </p>
                                    <p class="col-span-2"><span class="text-gray-500">Dates:</span> <?php echo date('d M Y', strtotime($row['start_date'])); ?> - <?php echo date('d M Y', strtotime($row['end_date'])); ?></p>
                                </div>
                                <p class="text-xs text-gray-500 mt-2">Reason: <?php echo $row['reason'] ? substr(htmlspecialchars($row['reason']), 0, 100) : '<span class="italic text-gray-400">No reason provided</span>'; ?></p>
                                <?php if($row['attachment']): ?>
                                    <a href="../uploads/<?php echo htmlspecialchars($row['attachment']); ?>" target="_blank" class="text-xs text-blue-600 mt-1 inline-block">
                                        <i class="fas fa-paperclip"></i> View Attachment
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="text-right">
                                <?php
                                $leave_status_class = ($row['status'] == 'approved') ? 'bg-green-100 text-green-700'
                                    : (($row['status'] == 'rejected') ? 'bg-red-100 text-red-700'
                                    : 'bg-amber-100 text-amber-800');
                                ?>
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold <?php echo $leave_status_class; ?>">
                                    <i class="fas <?php echo $row['status'] == 'approved' ? 'fa-check-circle' : ($row['status'] == 'rejected' ? 'fa-times-circle' : 'fa-clock'); ?>"></i>
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                                <?php if ($row['status'] !== 'pending'): ?>
                                    <div class="mt-2">
                                        <form id="undo_leave_<?php echo $row['id']; ?>" method="POST" onsubmit="return false;">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="undo_leave" value="<?php echo $row['id']; ?>">
                                            <button type="button"
                                                onclick="confirmAction('Revert to Pending?','This will reset the leave back to pending so it can be reviewed again. Leave balances will be restored if it was approved.',function(){document.getElementById('undo_leave_<?php echo $row['id']; ?>').submit();})"
                                                class="inline-flex items-center gap-1.5 text-xs text-gray-500 hover:text-indigo-600 bg-gray-100 hover:bg-indigo-50 border border-gray-200 hover:border-indigo-200 px-2.5 py-1.5 rounded-lg transition font-medium">
                                                <i class="fas fa-rotate-left text-[10px]"></i> Undo
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                                <div class="mt-2">
                                    <form method="POST" data-confirm="Delete this leave record permanently? <?php echo $row['status'] == 'approved' ? 'Its days will be given back to the employee\'s balance. ' : ''; ?>This cannot be undone." data-confirm-title="Delete Leave">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="delete_leave" value="<?php echo $row['id']; ?>">
                                        <button type="submit"
                                            class="inline-flex items-center gap-1.5 text-xs text-red-500 hover:text-red-700 bg-red-50 hover:bg-red-100 border border-red-200 hover:border-red-300 px-2.5 py-1.5 rounded-lg transition font-medium">
                                            <i class="fas fa-trash text-[10px]"></i> Delete
                                        </button>
                                    </form>
                                </div>
                                <?php if ($row['status'] == 'pending'): ?>
                                    <?php
                                    $modal_days = (isset($row['half_day']) && $row['half_day'] != 'none') ? 0.5
                                        : (strtotime($row['end_date']) - strtotime($row['start_date'])) / 86400 + 1;
                                    $leave_modal_data = [
                                        'id'          => $row['id'],
                                        'name'        => $row['name'],
                                        'employee_id' => $row['employee_id'],
                                        'department'  => $row['department'],
                                        'leave_type'  => $row['leave_type'],
                                        'start_date'  => $row['start_date'],
                                        'end_date'    => $row['end_date'],
                                        'total_days'  => $modal_days,
                                        'reason'      => $row['reason'] ?? '',
                                        'attachment'  => $row['attachment'] ?? '',
                                    ];
                                    ?>
                                    <div class="flex gap-2 mt-2">
                                        <button type="button"
                                            onclick="openLeaveDetails(<?php echo htmlspecialchars(json_encode($leave_modal_data), ENT_QUOTES); ?>)"
                                            class="bg-blue-600 text-white px-3 py-1 rounded-lg text-xs hover:bg-blue-700 flex items-center gap-1">
                                            <i class="fas fa-eye"></i> Review
                                        </button>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>

                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <div class="bg-gray-50 px-4 py-3 border-t flex justify-between items-center">
                    <p class="text-sm text-gray-500">
                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_rows); ?> of <?php echo $total_rows; ?> records
                    </p>
                    <div class="flex gap-1">
                        <?php if($page > 1): ?>
                            <a href="?page=1&per_page=<?php echo $per_page; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="px-3 py-1 bg-white border rounded-lg text-sm hover:bg-gray-100">First</a>
                            <a href="?page=<?php echo $page-1; ?>&per_page=<?php echo $per_page; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="px-3 py-1 bg-white border rounded-lg text-sm hover:bg-gray-100">Previous</a>
                        <?php endif; ?>
                        
                        <span class="px-3 py-1 bg-blue-600 text-white rounded-lg text-sm"><?php echo $page; ?></span>
                        
                        <?php if($page < $total_pages): ?>
                            <a href="?page=<?php echo $page+1; ?>&per_page=<?php echo $per_page; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="px-3 py-1 bg-white border rounded-lg text-sm hover:bg-gray-100">Next</a>
                            <a href="?page=<?php echo $total_pages; ?>&per_page=<?php echo $per_page; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="px-3 py-1 bg-white border rounded-lg text-sm hover:bg-gray-100">Last</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="p-8 text-center text-gray-500">
                    <i class="fas fa-calendar-check text-4xl mb-2 block"></i>
                    <p class="text-sm">No leave applications found</p>
                </div>
            <?php endif; ?>
        </div><!-- end white card -->
        </form><!-- end leaveBulkForm -->
    </div>

    <!-- Leave Balances Tab -->
    <div id="balancesTab" class="hidden">
        <?php
        $total_annual = 0;
        $total_used_annual = 0;
        $total_medical = 0;
        $total_used_medical = 0;
        mysqli_data_seek($employees_balance, 0);
        while($emp = mysqli_fetch_assoc($employees_balance)) {
            $total_annual += $emp['annual_leave_entitlement'];
            $total_used_annual += $emp['used_annual_leave'];
            $total_medical += $emp['medical_leave_entitlement'];
            $total_used_medical += $emp['used_medical_leave'];
        }
        mysqli_data_seek($employees_balance, 0);
        ?>
        
        <div class="grid grid-cols-2 gap-3 mb-6">
            <div class="bg-blue-100 rounded-xl p-3 text-center">
                <p class="text-xs text-blue-600">Total Annual Leave</p>
                <p class="text-2xl font-bold text-blue-700"><?php echo $total_annual; ?></p>
                <p class="text-xs text-blue-500">Used: <?php echo $total_used_annual; ?></p>
            </div>
            <div class="bg-green-100 rounded-xl p-3 text-center">
                <p class="text-xs text-green-600">Total Medical Leave</p>
                <p class="text-2xl font-bold text-green-700"><?php echo $total_medical; ?></p>
                <p class="text-xs text-green-500">Used: <?php echo $total_used_medical; ?></p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-xl overflow-hidden">
            <div class="bg-gray-50 px-4 py-3 border-b flex items-center justify-between flex-wrap gap-2">
                <p class="font-semibold text-gray-800"><i class="fas fa-chart-line mr-2 text-blue-600"></i> Employee Leave Balances</p>
                <form method="POST" class="inline">
                    <?php echo csrfField(); ?>
                    <button type="submit" name="reset_leave_balances"
                        data-confirm="Reset ALL employee used leave to 0? Do this at the start of a new year only." data-confirm-title="Year-End Leave Reset"
                        class="flex items-center gap-2 bg-orange-100 hover:bg-orange-200 text-orange-700 px-4 py-1.5 rounded-lg text-sm font-semibold transition">
                        <i class="fas fa-calendar-plus"></i> Reset All (New Year)
                    </button>
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="p-3 text-left text-xs font-semibold text-gray-600">Employee</th>
                            <th class="p-3 text-left text-xs font-semibold text-gray-600">Annual</th>
                            <th class="p-3 text-left text-xs font-semibold text-gray-600">Medical</th>
                            <th class="p-3 text-left text-xs font-semibold text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php while ($emp = mysqli_fetch_assoc($employees_balance)): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="p-3">
                                <p class="font-medium text-gray-800"><?php echo $emp['name']; ?></p>
                                <p class="text-xs text-gray-500"><?php echo $emp['employee_id']; ?> • <?php echo $emp['department']; ?></p>
                            </td>
                            <td class="p-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-16 bg-gray-200 rounded-full h-2">
                                        <?php 
                                        $annual_percent = ($emp['annual_leave_entitlement'] > 0) ? ($emp['used_annual_leave'] / $emp['annual_leave_entitlement']) * 100 : 0;
                                        ?>
                                        <div class="bg-blue-600 h-2 rounded-full" style="width: <?php echo min($annual_percent, 100); ?>%"></div>
                                    </div>
                                    <span class="text-sm font-medium"><?php echo $emp['annual_remaining']; ?>/<?php echo $emp['annual_leave_entitlement']; ?></span>
                                </div>
                                <p class="text-xs text-gray-400 mt-1">Used: <?php echo $emp['used_annual_leave']; ?></p>
                            </td>
                            <td class="p-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-16 bg-gray-200 rounded-full h-2">
                                        <?php 
                                        $medical_percent = ($emp['medical_leave_entitlement'] > 0) ? ($emp['used_medical_leave'] / $emp['medical_leave_entitlement']) * 100 : 0;
                                        ?>
                                        <div class="bg-green-600 h-2 rounded-full" style="width: <?php echo min($medical_percent, 100); ?>%"></div>
                                    </div>
                                    <span class="text-sm font-medium"><?php echo $emp['medical_remaining']; ?>/<?php echo $emp['medical_leave_entitlement']; ?></span>
                                </div>
                                <p class="text-xs text-gray-400 mt-1">Used: <?php echo $emp['used_medical_leave']; ?></p>
                            </td>
                            <td class="p-3">
                                <button onclick="openAdjustModal(<?php echo htmlspecialchars(json_encode($emp)); ?>)" class="text-blue-600 text-sm bg-blue-50 px-3 py-1 rounded-lg">
                                    <i class="fas fa-sliders-h"></i> Adjust
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Leave Types Tab -->
    <div id="typesTab" class="hidden">
        <div class="bg-white rounded-xl shadow-xl overflow-hidden">
            <div class="bg-gray-50 px-4 py-3 border-b flex justify-between items-center">
                <p class="font-semibold text-gray-800"><i class="fas fa-tags mr-2 text-blue-600"></i> Leave Types Configuration</p>
                <button onclick="document.getElementById('addTypeModal').classList.remove('hidden')" class="bg-green-600 text-white px-3 py-1 rounded-lg text-sm">
                    <i class="fas fa-plus"></i> Add Type
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="p-3 text-left text-xs font-semibold text-gray-600">Leave Type</th>
                            <th class="p-3 text-left text-xs font-semibold text-gray-600">Code</th>
                            <th class="p-3 text-left text-xs font-semibold text-gray-600">Days/Year</th>
                            <th class="p-3 text-left text-xs font-semibold text-gray-600">Paid</th>
                            <th class="p-3 text-left text-xs font-semibold text-gray-600">Attachment</th>
                            <th class="p-3 text-left text-xs font-semibold text-gray-600">Status</th>
                            <th class="p-3 text-left text-xs font-semibold text-gray-600">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <?php while($type = mysqli_fetch_assoc($leave_types)): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="p-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-3 h-3 rounded-full" style="background: <?php echo $type['color_code']; ?>"></div>
                                    <span class="font-medium"><?php echo $type['leave_name']; ?></span>
                                </div>
                            </td>
                            <td class="p-3"><?php echo $type['leave_code']; ?></td>
                            <td class="p-3"><?php echo $type['days_per_year']; ?></td>
                            <td class="p-3"><?php echo $type['is_paid'] ? 'Yes' : 'No'; ?></td>
                            <td class="p-3"><?php echo $type['requires_attachment'] ? 'Required' : 'Not Required'; ?></td>
                            <td class="p-3">
                                <span class="px-2 py-1 rounded-full text-xs <?php echo $type['status'] == 'active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700'; ?>">
                                    <?php echo ucfirst($type['status']); ?>
                                </span>
                            </td>
                            <td class="p-3">
                                <button onclick='openEditTypeModal(<?php echo htmlspecialchars(json_encode($type), ENT_QUOTES); ?>)' class="text-blue-600 mr-2">Edit</button>
                                <form id="del_type_<?php echo $type['id']; ?>" method="POST" style="display:inline" onsubmit="return false;">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="delete_type" value="<?php echo $type['id']; ?>">
                                    <button type="button" onclick="confirmAction('Delete Leave Type?','Employees will no longer be able to apply for this leave type.',function(){document.getElementById('del_type_<?php echo $type['id']; ?>').submit();})" class="text-red-600 hover:text-red-800 transition">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Adjust Leave Modal -->
<div id="adjustModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl">
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-4 rounded-t-2xl">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-lg font-bold text-white">Adjust Leave Balance</h2>
                    <p class="text-xs text-blue-100 mt-1">Modify entitlement or taken leave</p>
                </div>
                <button onclick="closeAdjustModal()" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <?php echo csrfField(); ?>
            <input type="hidden" name="employee_id" id="adj_employee_id">
            <div class="bg-blue-50 p-3 rounded-xl">
                <p class="font-medium text-gray-800" id="adj_employee_name"></p>
                <p class="text-xs text-gray-500" id="adj_employee_id_display"></p>
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Leave Type</label>
                <select name="adjust_type" id="adj_type" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500">
                    <option value="annual">Annual Leave</option>
                    <option value="medical">Medical Leave</option>
                </select>
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">What to Adjust?</label>
                <select name="adjust_field" id="adj_field" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500">
                    <option value="entitlement">📊 Total Entitlement (Days per Year)</option>
                    <option value="used">📉 Leave Taken / Used</option>
                </select>
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Action</label>
                <select name="action_type" id="adj_action" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500">
                    <option value="add">➕ Add Days (+)</option>
                    <option value="subtract">➖ Deduct Days (-)</option>
                </select>
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Number of Days</label>
                <input type="number" name="adjust_amount" id="adj_amount" required min="0.5" step="0.5" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500" placeholder="Enter number of days">
            </div>
            
            <div class="bg-gradient-to-r from-yellow-50 to-amber-50 p-4 rounded-xl text-xs text-amber-800 space-y-1">
                <p><i class="fas fa-info-circle mr-1"></i> <strong>Understanding the options:</strong></p>
                <p>• <strong>Total Entitlement</strong> - Changes the total days employee gets per year</p>
                <p>• <strong>Leave Taken / Used</strong> - Adjusts how many days already consumed</p>
                <p>• Adding to entitlement INCREASES available balance</p>
                <p>• Adding to used leave DECREASES available balance</p>
            </div>
            
            <button type="submit" name="adjust_leave" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white py-3 rounded-xl font-semibold hover:shadow-lg transition transform hover:scale-105">
                <i class="fas fa-save mr-2"></i> Apply Adjustment
            </button>
        </form>
    </div>
</div>

<!-- Add Leave Type Modal -->
<div id="addTypeModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full">
        <div class="p-4 border-b flex justify-between items-center">
            <h2 class="text-lg font-bold">Add Leave Type</h2>
            <button onclick="document.getElementById('addTypeModal').classList.add('hidden')" class="text-gray-500">&times;</button>
        </div>
        <form method="POST" class="p-4 space-y-3">
            <?php echo csrfField(); ?>
            <div class="grid grid-cols-2 gap-3">
                <input type="text" name="leave_name" placeholder="Leave Name" required class="px-4 py-3 border rounded-xl">
                <input type="text" name="leave_code" placeholder="Code (e.g., AL)" required class="px-4 py-3 border rounded-xl">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <input type="number" name="days_per_year" placeholder="Days per Year" required class="px-4 py-3 border rounded-xl">
                <input type="color" name="color_code" value="#3B82F6" class="px-4 py-2 border rounded-xl h-12">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_paid" value="1" checked> Paid Leave
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="requires_attachment" value="1"> Requires Attachment
                </label>
            </div>
            <input type="number" name="max_consecutive_days" placeholder="Max Consecutive Days" value="30" class="px-4 py-3 border rounded-xl">
            <button type="submit" name="add_leave_type" class="w-full bg-blue-600 text-white py-3 rounded-xl font-semibold">Add Leave Type</button>
        </form>
    </div>
</div>

<!-- Edit Leave Type Modal -->
<div id="editTypeModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full">
        <div class="p-4 border-b flex justify-between items-center">
            <h2 class="text-lg font-bold">Edit Leave Type</h2>
            <button onclick="document.getElementById('editTypeModal').classList.add('hidden')" class="text-gray-500">&times;</button>
        </div>
        <form method="POST" class="p-4 space-y-3">
            <?php echo csrfField(); ?>
            <input type="hidden" name="type_id" id="edit_type_id">
            <div class="grid grid-cols-2 gap-3">
                <input type="text" name="leave_name" id="edit_leave_name" placeholder="Leave Name" required class="px-4 py-3 border rounded-xl">
                <input type="text" name="leave_code" id="edit_leave_code" placeholder="Code" required class="px-4 py-3 border rounded-xl">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <input type="number" name="days_per_year" id="edit_days_per_year" placeholder="Days per Year" required class="px-4 py-3 border rounded-xl">
                <input type="color" name="color_code" id="edit_color_code" class="px-4 py-2 border rounded-xl h-12">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="is_paid" id="edit_is_paid" value="1"> Paid Leave
                </label>
                <label class="flex items-center gap-2">
                    <input type="checkbox" name="requires_attachment" id="edit_requires_attachment" value="1"> Requires Attachment
                </label>
            </div>
            <input type="number" name="max_consecutive_days" id="edit_max_consecutive_days" placeholder="Max Consecutive Days" class="px-4 py-3 border rounded-xl">
            <select name="status" id="edit_status" class="px-4 py-3 border rounded-xl">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <button type="submit" name="update_leave_type" class="w-full bg-green-600 text-white py-3 rounded-xl font-semibold">Update Leave Type</button>
        </form>
    </div>
</div>

<!-- Leave Bulk Action Bar (sticky at bottom) -->
<div id="leaveBulkBar" class="fixed bottom-0 left-0 right-0 z-30 hidden-bar">
    <div class="bg-gradient-to-r from-blue-700 to-indigo-700 text-white px-4 py-3 shadow-2xl flex items-center justify-between flex-wrap gap-2">
        <span class="text-sm font-semibold" id="leaveBulkCountLabel">0 selected</span>
        <div class="flex items-center gap-2">
            <button type="button" onclick="leaveSubmitBulk('approve')"
                class="bg-green-500 hover:bg-green-400 text-white px-4 py-1.5 rounded-xl text-sm font-semibold flex items-center gap-1 transition">
                <i class="fas fa-check"></i> Approve All
            </button>
            <button type="button" onclick="leaveSubmitBulk('reject')"
                class="bg-red-500 hover:bg-red-400 text-white px-4 py-1.5 rounded-xl text-sm font-semibold flex items-center gap-1 transition">
                <i class="fas fa-times"></i> Reject All
            </button>
            <button type="button" onclick="leaveClearSelection()"
                class="text-white/70 hover:text-white text-xs underline ml-2 transition">
                Clear
            </button>
        </div>
    </div>
</div>

<script>
    // ---- Leave Bulk selection ----
    function leaveGetCheckedBoxes() {
        return Array.from(document.querySelectorAll('#leaveBulkForm .bulk-check[name="ids[]"]:checked'));
    }

    function leaveUpdateBulkBar() {
        const checked = leaveGetCheckedBoxes();
        const count = checked.length;
        const bar = document.getElementById('leaveBulkBar');
        const countLabel = document.getElementById('leaveBulkCountLabel');
        const headerCount = document.getElementById('leaveSelectedCount');

        countLabel.textContent = count + ' selected';
        if (headerCount) {
            headerCount.textContent = count + ' selected';
            headerCount.classList.toggle('hidden', count === 0);
        }

        if (count > 0) {
            bar.classList.remove('hidden-bar');
        } else {
            bar.classList.add('hidden-bar');
        }

        const allBoxes = document.querySelectorAll('#leaveBulkForm .bulk-check[name="ids[]"]');
        const selectAll = document.getElementById('leaveSelectAll');
        if (selectAll) {
            selectAll.checked = allBoxes.length > 0 && count === allBoxes.length;
            selectAll.indeterminate = count > 0 && count < allBoxes.length;
        }
    }

    function leaveToggleSelectAll(cb) {
        const boxes = document.querySelectorAll('#leaveBulkForm .bulk-check[name="ids[]"]');
        boxes.forEach(b => b.checked = cb.checked);
        leaveUpdateBulkBar();
    }

    function leaveSubmitBulk(action) {
        const checked = leaveGetCheckedBoxes();
        if (checked.length === 0) return;
        const label = action === 'approve' ? 'Approve' : 'Reject';
        confirmAction(
            label + ' ' + checked.length + ' Leave Application' + (checked.length > 1 ? 's' : '') + '?',
            'Are you sure you want to <strong>' + label.toLowerCase() + '</strong> ' + checked.length + ' selected leave application' + (checked.length > 1 ? 's' : '') + '? Employees will be notified.',
            function() {
                document.getElementById('leaveBulkActionInput').value = action;
                document.getElementById('leaveBulkForm').submit();
            }
        );
    }

    function leaveClearSelection() {
        document.querySelectorAll('#leaveBulkForm .bulk-check').forEach(b => b.checked = false);
        leaveUpdateBulkBar();
    }

    function showTab(tab) {
        const requestsTab = document.getElementById('requestsTab');
        const balancesTab = document.getElementById('balancesTab');
        const typesTab = document.getElementById('typesTab');
        const tabRequests = document.getElementById('tabRequests');
        const tabBalances = document.getElementById('tabBalances');
        const tabTypes = document.getElementById('tabTypes');
        
        if (tab === 'requests') {
            requestsTab.classList.remove('hidden');
            balancesTab.classList.add('hidden');
            typesTab.classList.add('hidden');
            tabRequests.className = 'tab-active px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap';
            tabBalances.className = 'tab-inactive px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap';
            tabTypes.className = 'tab-inactive px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap';
        } else if (tab === 'balances') {
            requestsTab.classList.add('hidden');
            balancesTab.classList.remove('hidden');
            typesTab.classList.add('hidden');
            tabRequests.className = 'tab-inactive px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap';
            tabBalances.className = 'tab-active px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap';
            tabTypes.className = 'tab-inactive px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap';
        } else {
            requestsTab.classList.add('hidden');
            balancesTab.classList.add('hidden');
            typesTab.classList.remove('hidden');
            tabRequests.className = 'tab-inactive px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap';
            tabBalances.className = 'tab-inactive px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap';
            tabTypes.className = 'tab-active px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap';
        }
    }
    
    function openAdjustModal(employee) {
        document.getElementById('adj_employee_id').value = employee.id;
        document.getElementById('adj_employee_name').innerHTML = employee.name;
        document.getElementById('adj_employee_id_display').innerHTML = employee.employee_id;
        document.getElementById('adj_amount').value = '';
        document.getElementById('adjustModal').classList.remove('hidden');
    }
    
    function closeAdjustModal() {
        document.getElementById('adjustModal').classList.add('hidden');
    }
    
    function openEditTypeModal(type) {
        document.getElementById('edit_type_id').value = type.id;
        document.getElementById('edit_leave_name').value = type.leave_name;
        document.getElementById('edit_leave_code').value = type.leave_code;
        document.getElementById('edit_days_per_year').value = type.days_per_year;
        document.getElementById('edit_color_code').value = type.color_code;
        document.getElementById('edit_is_paid').checked = type.is_paid == 1;
        document.getElementById('edit_requires_attachment').checked = type.requires_attachment == 1;
        document.getElementById('edit_max_consecutive_days').value = type.max_consecutive_days;
        document.getElementById('edit_status').value = type.status;
        document.getElementById('editTypeModal').classList.remove('hidden');
    }

    // ---- Leave Details Modal ----
    function openLeaveDetails(data) {
        // Avatar initial
        var initial = (data.name || '?').charAt(0).toUpperCase();
        document.getElementById('ld_avatar').textContent = initial;

        // Header
        document.getElementById('ld_name').textContent = data.name;
        document.getElementById('ld_meta').textContent = data.employee_id + ' · ' + data.department;

        // Leave type badge
        var typeColors = {
            annual:    'bg-blue-100 text-blue-700',
            medical:   'bg-green-100 text-green-700',
            emergency: 'bg-orange-100 text-orange-700',
            unpaid:    'bg-gray-100 text-gray-700'
        };
        var badgeEl = document.getElementById('ld_type_badge');
        badgeEl.textContent = data.leave_type.charAt(0).toUpperCase() + data.leave_type.slice(1) + ' Leave';
        badgeEl.className = 'inline-block px-3 py-1 rounded-full text-xs font-semibold ' + (typeColors[data.leave_type] || 'bg-gray-100 text-gray-700');

        // Dates & days
        var fmt = function(d) {
            if (!d) return '';
            var parts = d.split('-');
            var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            return parts[2] + ' ' + months[parseInt(parts[1], 10) - 1] + ' ' + parts[0];
        };
        document.getElementById('ld_dates').textContent = fmt(data.start_date) + ' — ' + fmt(data.end_date);
        document.getElementById('ld_days').textContent = data.total_days + (data.total_days == 1 ? ' day' : ' days');

        // Reason
        document.getElementById('ld_reason').textContent = data.reason || 'No reason provided.';

        // Attachment
        var attRow = document.getElementById('ld_attachment_row');
        var attLink = document.getElementById('ld_attachment_link');
        if (data.attachment) {
            attLink.href = '../uploads/' + data.attachment;
            attRow.classList.remove('hidden');
        } else {
            attRow.classList.add('hidden');
        }

        // Action buttons
        document.getElementById('ld_approve_id').value = data.id;
        document.getElementById('ld_reject_id').value  = data.id;

        // Show modal with animation
        var modal = document.getElementById('leaveDetailsModal');
        var panel = document.getElementById('leaveDetailsPanel');
        modal.classList.remove('hidden');
        // Trigger scale-in animation
        requestAnimationFrame(function() {
            panel.classList.remove('scale-95', 'opacity-0');
            panel.classList.add('scale-100', 'opacity-100');
        });
    }

    function closeLeaveDetails() {
        var modal = document.getElementById('leaveDetailsModal');
        var panel = document.getElementById('leaveDetailsPanel');
        panel.classList.remove('scale-100', 'opacity-100');
        panel.classList.add('scale-95', 'opacity-0');
        setTimeout(function() { modal.classList.add('hidden'); }, 200);
    }

    // Actions on the Balances/Types tabs redirect back here with ?tab=...
    // so the page reopens on the same tab instead of resetting to Requests.
    (function() {
        var wantedTab = new URLSearchParams(location.search).get('tab');
        if (wantedTab === 'balances' || wantedTab === 'types') {
            showTab(wantedTab);
        }
    })();
</script>

<!-- ========================================= -->
<!-- LEAVE DETAILS MODAL                        -->
<!-- ========================================= -->
<div id="leaveDetailsModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-end sm:items-center justify-center p-0 sm:p-4">
    <div id="leaveDetailsPanel"
         class="bg-white w-full sm:max-w-md sm:rounded-2xl rounded-t-2xl shadow-2xl transform transition-all duration-200 scale-95 opacity-0 overflow-hidden">

        <!-- Modal Header -->
        <div class="bg-gradient-to-r from-indigo-600 to-blue-600 px-5 py-4">
            <div class="flex items-center justify-between mb-3">
                <p class="text-xs text-blue-200 font-semibold uppercase tracking-widest">Leave Request Review</p>
                <button onclick="closeLeaveDetails()" class="text-white/70 hover:text-white transition">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            <div class="flex items-center gap-3">
                <div id="ld_avatar"
                     class="w-12 h-12 rounded-xl bg-white/20 text-white font-bold text-xl flex items-center justify-center flex-shrink-0 shadow-inner">
                    ?
                </div>
                <div>
                    <p id="ld_name" class="text-white font-bold text-base leading-tight"></p>
                    <p id="ld_meta" class="text-blue-200 text-xs mt-0.5"></p>
                </div>
            </div>
        </div>

        <!-- Modal Body -->
        <div class="px-5 py-4 space-y-4">

            <!-- Leave type badge -->
            <div>
                <span id="ld_type_badge" class="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700"></span>
            </div>

            <!-- Date range & total days -->
            <div class="grid grid-cols-2 gap-3">
                <div class="bg-gray-50 rounded-xl p-3">
                    <p class="text-xs text-gray-400 mb-1 font-medium">Date Range</p>
                    <p id="ld_dates" class="text-sm font-semibold text-gray-800 leading-snug"></p>
                </div>
                <div class="bg-indigo-50 rounded-xl p-3 text-center">
                    <p class="text-xs text-indigo-400 mb-1 font-medium">Duration</p>
                    <p id="ld_days" class="text-xl font-bold text-indigo-700"></p>
                </div>
            </div>

            <!-- Reason -->
            <div>
                <p class="text-xs text-gray-400 font-medium mb-1">Reason</p>
                <div class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-sm text-gray-700 leading-relaxed min-h-[60px]">
                    <p id="ld_reason"></p>
                </div>
            </div>

            <!-- Attachment -->
            <div id="ld_attachment_row" class="hidden">
                <p class="text-xs text-gray-400 font-medium mb-1">Attachment</p>
                <a id="ld_attachment_link" href="#" target="_blank"
                   class="inline-flex items-center gap-2 text-sm text-blue-600 hover:text-blue-800 bg-blue-50 px-3 py-2 rounded-lg transition">
                    <i class="fas fa-paperclip"></i> View Attachment
                </a>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="px-5 pb-5 flex gap-3">
            <form id="ld_approve_form" method="POST" style="flex:1;display:flex;" onsubmit="return false;">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="id" id="ld_approve_id" value="">
                <button type="button"
                   onclick="confirmAction('Approve Leave Request?', 'This will approve the leave and notify the employee. The leave balance will be updated.', function(){ document.getElementById('ld_approve_form').submit(); })"
                   class="flex-1 bg-gradient-to-r from-green-500 to-emerald-600 text-white text-center py-3 rounded-xl font-semibold text-sm shadow hover:shadow-lg hover:from-green-600 hover:to-emerald-700 transition flex items-center justify-center gap-2">
                    <i class="fas fa-check-circle"></i> Approve
                </button>
            </form>
            <form id="ld_reject_form" method="POST" style="flex:1;display:flex;" onsubmit="return false;">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="id" id="ld_reject_id" value="">
                <button type="button"
                   onclick="confirmAction('Reject Leave Request?', 'This will reject the leave request and notify the employee.', function(){ document.getElementById('ld_reject_form').submit(); })"
                   class="flex-1 bg-gradient-to-r from-red-500 to-rose-600 text-white text-center py-3 rounded-xl font-semibold text-sm shadow hover:shadow-lg hover:from-red-600 hover:to-rose-700 transition flex items-center justify-center gap-2">
                    <i class="fas fa-times-circle"></i> Reject
                </button>
            </form>
        </div>
    </div>
</div>

</body>
</html>