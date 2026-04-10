<?php
require_once '../includes/auth.php';
redirectIfNotAdmin();
require_once '../includes/db.php';
require_once '../includes/functions.php';

// ========================================
// LEAVE TYPES MANAGEMENT
// ========================================
if (isset($_POST['add_leave_type'])) {
    $leave_name = mysqli_real_escape_string($conn, $_POST['leave_name']);
    $leave_code = mysqli_real_escape_string($conn, $_POST['leave_code']);
    $days_per_year = $_POST['days_per_year'];
    $is_paid = isset($_POST['is_paid']) ? 1 : 0;
    $requires_attachment = isset($_POST['requires_attachment']) ? 1 : 0;
    $max_consecutive_days = $_POST['max_consecutive_days'];
    $color_code = $_POST['color_code'];
    
    mysqli_query($conn, "INSERT INTO leave_types (leave_name, leave_code, days_per_year, is_paid, requires_attachment, max_consecutive_days, color_code) 
                         VALUES ('$leave_name', '$leave_code', $days_per_year, $is_paid, $requires_attachment, $max_consecutive_days, '$color_code')");
    header('Location: manage_leave.php');
    exit();
}

if (isset($_POST['update_leave_type'])) {
    $id = $_POST['type_id'];
    $leave_name = mysqli_real_escape_string($conn, $_POST['leave_name']);
    $leave_code = mysqli_real_escape_string($conn, $_POST['leave_code']);
    $days_per_year = $_POST['days_per_year'];
    $is_paid = isset($_POST['is_paid']) ? 1 : 0;
    $requires_attachment = isset($_POST['requires_attachment']) ? 1 : 0;
    $max_consecutive_days = $_POST['max_consecutive_days'];
    $status = $_POST['status'];
    $color_code = $_POST['color_code'];
    
    mysqli_query($conn, "UPDATE leave_types SET 
        leave_name='$leave_name', leave_code='$leave_code', days_per_year=$days_per_year, 
        is_paid=$is_paid, requires_attachment=$requires_attachment, 
        max_consecutive_days=$max_consecutive_days, status='$status', color_code='$color_code' 
        WHERE id=$id");
    header('Location: manage_leave.php');
    exit();
}

if (isset($_GET['delete_type'])) {
    $id = $_GET['delete_type'];
    mysqli_query($conn, "DELETE FROM leave_types WHERE id=$id");
    header('Location: manage_leave.php');
    exit();
}

// ========================================
// LEAVE REQUESTS HANDLING
// ========================================
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = $_GET['id'];
    $action = $_GET['action'];
    $status = ($action == 'approve') ? 'approved' : 'rejected';
    
    $leave_query = mysqli_query($conn, "SELECT * FROM leaves WHERE id=$id");
    $leave = mysqli_fetch_assoc($leave_query);
    
    // Calculate days (including half day)
    if ($leave['half_day'] != 'none') {
        $days = 0.5;
    } else {
        $days = (strtotime($leave['end_date']) - strtotime($leave['start_date'])) / 86400 + 1;
    }
    
    mysqli_query($conn, "UPDATE leaves SET status='$status' WHERE id=$id");
    
    if ($status == 'approved') {
        $field = ($leave['leave_type'] == 'annual') ? 'used_annual_leave' : 'used_medical_leave';
        mysqli_query($conn, "UPDATE employees SET $field = $field + $days WHERE id = {$leave['employee_id']}");
        addNotification($leave['employee_id'], 'Leave Approved', 'Your ' . $leave['leave_type'] . ' leave has been approved.');
    } else {
        addNotification($leave['employee_id'], 'Leave Rejected', 'Your ' . $leave['leave_type'] . ' leave has been rejected.');
    }
    
    header('Location: manage_leave.php');
    exit();
}

// ========================================
// ADJUST LEAVE BALANCE - FULL CONTROL
// ========================================
if (isset($_POST['adjust_leave'])) {
    $employee_id = $_POST['employee_id'];
    $adjust_type = $_POST['adjust_type'];
    $adjust_field = $_POST['adjust_field'];
    $action_type = $_POST['action_type'];
    $adjust_amount = $_POST['adjust_amount'];
    
    if ($adjust_type == 'annual') {
        if ($adjust_field == 'entitlement') {
            if ($action_type == 'add') {
                mysqli_query($conn, "UPDATE employees SET annual_leave_entitlement = annual_leave_entitlement + $adjust_amount WHERE id = $employee_id");
            } else {
                mysqli_query($conn, "UPDATE employees SET annual_leave_entitlement = annual_leave_entitlement - $adjust_amount WHERE id = $employee_id");
            }
        } else {
            if ($action_type == 'add') {
                mysqli_query($conn, "UPDATE employees SET used_annual_leave = used_annual_leave + $adjust_amount WHERE id = $employee_id");
            } else {
                mysqli_query($conn, "UPDATE employees SET used_annual_leave = used_annual_leave - $adjust_amount WHERE id = $employee_id");
            }
        }
    } else {
        if ($adjust_field == 'entitlement') {
            if ($action_type == 'add') {
                mysqli_query($conn, "UPDATE employees SET medical_leave_entitlement = medical_leave_entitlement + $adjust_amount WHERE id = $employee_id");
            } else {
                mysqli_query($conn, "UPDATE employees SET medical_leave_entitlement = medical_leave_entitlement - $adjust_amount WHERE id = $employee_id");
            }
        } else {
            if ($action_type == 'add') {
                mysqli_query($conn, "UPDATE employees SET used_medical_leave = used_medical_leave + $adjust_amount WHERE id = $employee_id");
            } else {
                mysqli_query($conn, "UPDATE employees SET used_medical_leave = used_medical_leave - $adjust_amount WHERE id = $employee_id");
            }
        }
    }
    
    header('Location: manage_leave.php');
    exit();
}

// Get all employees with leave balances
$employees_balance = mysqli_query($conn, "SELECT 
    id, name, employee_id, department,
    annual_leave_entitlement, used_annual_leave,
    (annual_leave_entitlement - used_annual_leave) as annual_remaining,
    medical_leave_entitlement, used_medical_leave,
    (medical_leave_entitlement - used_medical_leave) as medical_remaining
    FROM employees WHERE role='employee' ORDER BY name");

// Get all leave applications
$leaves = mysqli_query($conn, "SELECT l.*, e.name, e.employee_id, e.department 
    FROM leaves l JOIN employees e ON l.employee_id = e.id ORDER BY applied_at DESC");

// ========================================
// GET LEAVE TYPES - THIS WAS MISSING!
// ========================================
$leave_types = mysqli_query($conn, "SELECT * FROM leave_types ORDER BY leave_name");
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
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen pb-20">
<!-- Premium Mobile Header -->
<div class="bg-gradient-to-r from-slate-900 via-indigo-900 to-slate-900 text-white sticky top-0 z-40 shadow-2xl">
    <div class="flex justify-between items-center px-4 py-4">
        <div class="flex items-center gap-3">
            <!-- MENU BUTTON - Left side -->
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
        <!-- No back button - just empty space or nothing -->
    </div>
</div>
    <!-- SIDEBAR -->
    <div id="sidebar" class="fixed top-0 left-0 h-full w-72 bg-gradient-to-b from-gray-900 to-gray-950 text-white z-50 transform -translate-x-full transition-transform duration-300 shadow-2xl overflow-y-auto">
        <div class="p-6 border-b border-gray-800">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center">
                    <span class="text-gray-900 font-bold text-xl">IN</span>
                </div>
                <div>
                    <h2 class="font-bold"><?php echo $_SESSION['user_name']; ?></h2>
                    <p class="text-xs text-gray-400">Administrator</p>
                </div>
            </div>
            <button onclick="toggleSidebar()" class="absolute top-4 right-4 text-white/60 hover:text-white">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <nav class="p-4">
            <a href="dashboard.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-gray-800/30 transition mb-1">
                <i class="fas fa-tachometer-alt w-5"></i> Dashboard
            </a>
            <a href="employees.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-gray-800/30 transition mb-1">
                <i class="fas fa-users w-5"></i> Employees
            </a>
            <a href="manage_leave.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-gray-800/30 transition mb-1">
                <i class="fas fa-calendar-check w-5"></i> Leave Management
            </a>
            <a href="manage_claim.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-gray-800/30 transition mb-1">
                <i class="fas fa-receipt w-5"></i> Claim Management
            </a>
            <a href="attendance.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-gray-800/30 transition mb-1">
                <i class="fas fa-fingerprint w-5"></i> Attendance
            </a>
            <a href="manage_assets.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-gray-800/30 transition mb-1">
                <i class="fas fa-boxes w-5"></i> Asset Management
            </a>
            <a href="manage_gallery.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-gray-800/30 transition mb-1">
                <i class="fas fa-images w-5"></i> Gallery Management
            </a>
            <a href="management.php" class="flex items-center gap-3 py-3 px-4 rounded-xl bg-gray-800/50 mb-1">
                <i class="fas fa-briefcase w-5"></i> Management
            </a>
            <a href="payroll.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-gray-800/30 transition mb-1">
                <i class="fas fa-file-invoice-dollar w-5"></i> Payroll
            </a>
            <a href="holidays.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-gray-800/30 transition mb-1">
                <i class="fas fa-calendar-alt w-5"></i> Holidays
            </a>
            <div class="border-t border-gray-800 my-4"></div>
            <a href="../logout.php" class="flex items-center gap-3 py-3 px-4 rounded-xl bg-red-600/20 text-red-300 hover:bg-red-600/30 transition">
                <i class="fas fa-sign-out-alt w-5"></i> Logout
            </a>
        </nav>
    </div>

    <div id="overlay" class="fixed inset-0 bg-black/50 z-40 hidden" onclick="toggleSidebar()"></div>

    <!-- Main Content -->
    <div class="px-4 py-6 pb-24 max-w-7xl mx-auto">
        <h1 class="text-xl font-bold text-gray-800 mb-2">Leave Management</h1>
        <p class="text-sm text-gray-500 mb-6">Manage leave requests, employee balances, and leave types</p>

        <!-- Tabs -->
        <div class="flex gap-2 mb-6 overflow-x-auto pb-2">
            <button onclick="showTab('requests')" id="tabRequests" class="tab-active px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap">
                <i class="fas fa-clock mr-1"></i> Leave Requests
            </button>
            <button onclick="showTab('balances')" id="tabBalances" class="tab-inactive px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap">
                <i class="fas fa-chart-line mr-1"></i> Leave Balances
            </button>
            <button onclick="showTab('types')" id="tabTypes" class="tab-inactive px-4 py-2 rounded-xl text-sm font-medium whitespace-nowrap">
                <i class="fas fa-tags mr-1"></i> Leave Types
            </button>
        </div>

        <!-- Leave Requests Tab -->
        <div id="requestsTab">
            <div class="bg-white rounded-xl shadow-xl overflow-hidden">
                <div class="bg-gray-50 px-4 py-3 border-b">
                    <p class="font-semibold text-gray-800"><i class="fas fa-clock mr-2 text-blue-600"></i> Pending & Recent Applications</p>
                </div>
                <div class="divide-y">
                    <?php if(mysqli_num_rows($leaves) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($leaves)): ?>
                        <div class="p-4">
                            <!-- Leave request display (same as before) -->
                            <div class="flex justify-between items-start mb-2 flex-wrap gap-2">
                                <div>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="font-semibold text-gray-800"><?php echo $row['name']; ?></p>
                                        <p class="text-xs text-gray-500"><?php echo $row['employee_id']; ?></p>
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600"><?php echo $row['department']; ?></span>
                                    </div>
                                    <p class="text-sm mt-1"><span class="text-gray-500">Type:</span> <?php echo ucfirst($row['leave_type']); ?></p>
                                    <p class="text-sm"><span class="text-gray-500">Dates:</span> <?php echo date('d M Y', strtotime($row['start_date'])); ?> - <?php echo date('d M Y', strtotime($row['end_date'])); ?></p>
                                    <?php 
                                    $days = (strtotime($row['end_date']) - strtotime($row['start_date'])) / 86400 + 1;
                                    if ($row['half_day'] != 'none') {
                                        $days = 0.5;
                                        echo '<p class="text-xs text-orange-600">Half Day (' . ($row['half_day'] == 'first_half' ? 'AM' : 'PM') . ')</p>';
                                    }
                                    ?>
                                    <p class="text-xs text-gray-500">Duration: <?php echo $days; ?> day(s)</p>
                                    <?php if($row['reason']): ?>
                                        <p class="text-xs text-gray-500 mt-1">Reason: <?php echo substr($row['reason'], 0, 100); ?></p>
                                    <?php endif; ?>
                                    <?php if($row['attachment']): ?>
                                        <a href="../uploads/<?php echo $row['attachment']; ?>" target="_blank" class="text-xs text-blue-600 mt-1 inline-block">
                                            <i class="fas fa-paperclip"></i> View Attachment
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="text-right">
                                    <span class="text-xs px-2 py-1 rounded-full <?php echo $row['status'] == 'approved' ? 'bg-green-100 text-green-700' : ($row['status'] == 'rejected' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'); ?>">
                                        <?php echo ucfirst($row['status']); ?>
                                    </span>
                                    <?php if ($row['status'] == 'pending'): ?>
                                        <div class="flex gap-2 mt-2">
                                            <a href="?action=approve&id=<?php echo $row['id']; ?>" class="bg-green-600 text-white px-3 py-1 rounded-lg text-xs">Approve</a>
                                            <a href="?action=reject&id=<?php echo $row['id']; ?>" class="bg-red-600 text-white px-3 py-1 rounded-lg text-xs">Reject</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="p-8 text-center text-gray-500">
                            <i class="fas fa-calendar-check text-4xl mb-2 block"></i>
                            <p class="text-sm">No leave applications</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
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
                <div class="bg-gray-50 px-4 py-3 border-b">
                    <p class="font-semibold text-gray-800"><i class="fas fa-chart-line mr-2 text-blue-600"></i> Employee Leave Balances</p>
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
                                    <button onclick='openEditTypeModal(<?php echo json_encode($type); ?>)' class="text-blue-600 mr-2">Edit</button>
                                    <a href="?delete_type=<?php echo $type['id']; ?>" onclick="return confirm('Delete this leave type?')" class="text-red-600">Delete</a>
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

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('overlay').classList.toggle('hidden');
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
    </script>
</body>
</html>