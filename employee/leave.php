<?php
require_once '../includes/auth.php';
redirectIfNotLoggedIn();
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/toast_fn.php';

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';
$edit_mode = false;
$edit_leave_id = 0;

// Interns are not eligible for leave
$emp_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT employee_type FROM employees WHERE id = $user_id"));
$is_intern = isset($emp_info['employee_type']) && $emp_info['employee_type'] == 'intern';

// ========================================
// HANDLE EDIT LEAVE (Load data for editing)
// ========================================
if (isset($_GET['edit'])) {
    $edit_leave_id = (int)$_GET['edit'];
    $edit_query = mysqli_query($conn, "SELECT * FROM leaves WHERE id = $edit_leave_id AND employee_id = $user_id AND status = 'pending'");
    if (mysqli_num_rows($edit_query) > 0) {
        $edit_mode = true;
        $edit_leave = mysqli_fetch_assoc($edit_query);
    }
}

// ========================================
// HANDLE UPDATE LEAVE
// ========================================
if (isset($_POST['update_leave'])) {
    $leave_id = (int)$_POST['leave_id'];
    $leave_type = mysqli_real_escape_string($conn, $_POST['leave_type']);
    $half_day = mysqli_real_escape_string($conn, $_POST['half_day']);
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);

    if ($leave_type === 'HD' && $half_day === 'none') {
        $half_day = isset($_POST['half_day_choice']) ? mysqli_real_escape_string($conn, $_POST['half_day_choice']) : 'first_half';
    }

    // Calculate total days — Half Day (HD) has no balance deduction
    if ($leave_type === 'HD') {
        $total_days = 0;
        $end_date = $start_date;
    } elseif ($half_day != 'none') {
        $total_days = 0;
        $end_date = $start_date;
    } else {
        $total_days = (strtotime($end_date) - strtotime($start_date)) / 86400 + 1;
    }
    
    // Check if leave is still pending
    $check_query = mysqli_query($conn, "SELECT id FROM leaves WHERE id = $leave_id AND employee_id = $user_id AND status = 'pending'");
    if (mysqli_num_rows($check_query) > 0) {
        $update_query = "UPDATE leaves SET 
                            leave_type = '$leave_type', 
                            half_day = '$half_day',
                            start_date = '$start_date', 
                            end_date = '$end_date', 
                            total_days = $total_days,
                            reason = '$reason'
                         WHERE id = $leave_id";
        
        if (mysqli_query($conn, $update_query)) {
            // Handle new attachment if uploaded
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
                $allowed_attach_ext  = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
                $allowed_attach_mime = ['image/jpeg', 'image/png', 'application/pdf',
                                        'application/msword',
                                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                $attach_ext  = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
                $attach_mime = mime_content_type($_FILES['attachment']['tmp_name']);
                if (in_array($attach_ext, $allowed_attach_ext) && in_array($attach_mime, $allowed_attach_mime)) {
                    $target_dir = "../uploads/";
                    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

                    $old_attach = mysqli_fetch_assoc(mysqli_query($conn, "SELECT attachment FROM leaves WHERE id = $leave_id"));
                    if (!empty($old_attach['attachment']) && file_exists($target_dir . $old_attach['attachment'])) {
                        unlink($target_dir . $old_attach['attachment']);
                    }

                    $attachment = time() . '_' . basename($_FILES['attachment']['name']);
                    move_uploaded_file($_FILES['attachment']['tmp_name'], $target_dir . $attachment);
                    mysqli_query($conn, "UPDATE leaves SET attachment = '$attachment' WHERE id = $leave_id");
                }
            }
            
            $message = '<div class="bg-green-100 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm">✓ Leave application updated successfully!</div>';
            $edit_mode = false;
        } else {
            $error = '<div class="bg-red-100 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">✗ Error updating leave application.</div>';
        }
    }
}

// ========================================
// HANDLE DELETE LEAVE
// ========================================
if (isset($_GET['delete'])) {
    $leave_id = (int)$_GET['delete'];
    
    // Check if leave is pending
    $check_query = mysqli_query($conn, "SELECT attachment FROM leaves WHERE id = $leave_id AND employee_id = $user_id AND status = 'pending'");
    if (mysqli_num_rows($check_query) > 0) {
        $leave_data = mysqli_fetch_assoc($check_query);
        // Delete attachment file if exists
        if (!empty($leave_data['attachment'])) {
            $file_path = "../uploads/" . $leave_data['attachment'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        mysqli_query($conn, "DELETE FROM leaves WHERE id = $leave_id");
        $message = '<div class="bg-green-100 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm">✓ Leave application deleted successfully!</div>';
    } else {
        $error = '<div class="bg-red-100 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">✗ Cannot delete leave that is already processed.</div>';
    }
}

// ========================================
// PAGINATION FOR LEAVE HISTORY
// ========================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$where = "WHERE employee_id = $user_id";
if (!empty($status_filter)) {
    $where .= " AND status = '$status_filter'";
}

$count_query = "SELECT COUNT(*) as total FROM leaves $where";
$count_result = mysqli_query($conn, $count_query);
$total_rows = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_rows / $per_page);
$offset = ($page - 1) * $per_page;

$history = mysqli_query($conn, "SELECT * FROM leaves $where ORDER BY applied_at DESC LIMIT $offset, $per_page");

// Get available leave types
$leave_types = mysqli_query($conn, "SELECT * FROM leave_types WHERE status = 'active' ORDER BY leave_name");

// Handle new leave submission
if (isset($_POST['apply_leave']) && !$edit_mode) {
    $leave_type = mysqli_real_escape_string($conn, $_POST['leave_type']);
    $lt_intern = mysqli_fetch_assoc(mysqli_query($conn, "SELECT leave_name FROM leave_types WHERE leave_code = '$leave_type'"));
    $lt_intern_name = $lt_intern ? strtolower($lt_intern['leave_name']) : strtolower($leave_type);
    if ($is_intern && !str_contains($lt_intern_name, 'unpaid')) {
        $error = '<div class="bg-red-100 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">✗ Interns can only apply for Unpaid Leave.</div>';
    } else {
        $half_day   = mysqli_real_escape_string($conn, $_POST['half_day']);
        $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
        $end_date   = mysqli_real_escape_string($conn, $_POST['end_date']);
        $reason     = mysqli_real_escape_string($conn, $_POST['reason']);

        // If HD type but half_day not set (JS failed), default to first_half
        if ($leave_type === 'HD' && $half_day === 'none') {
            $half_day = isset($_POST['half_day_choice']) ? mysqli_real_escape_string($conn, $_POST['half_day_choice']) : 'first_half';
        }

        // Half Day (HD) — no balance deduction, just one date
        if ($leave_type === 'HD' || $half_day != 'none') {
            $total_days = 0;
            $end_date   = $start_date;
        } else {
            // Validate end_date >= start_date
            if (strtotime($end_date) < strtotime($start_date)) {
                $error = '<div class="bg-red-100 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">✗ End date cannot be before start date.</div>';
                $total_days = 0;
            } else {
                $total_days = (strtotime($end_date) - strtotime($start_date)) / 86400 + 1;
            }
        }

        if (empty($error)) {
            $attachment = '';
            $type_query = mysqli_query($conn, "SELECT requires_attachment FROM leave_types WHERE leave_code = '$leave_type'");
            $type_data  = mysqli_fetch_assoc($type_query);
            $requires_attachment = $type_data ? $type_data['requires_attachment'] : 0;

            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
                $target_dir = "../uploads/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
                $attachment = time() . '_' . basename($_FILES['attachment']['name']);
                move_uploaded_file($_FILES['attachment']['tmp_name'], $target_dir . $attachment);
            } elseif ($requires_attachment) {
                $message = '<div class="bg-red-100 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">✗ This leave type requires an attachment (e.g., Medical Certificate).</div>';
            }

            if (empty($message)) {
                $query = "INSERT INTO leaves (employee_id, leave_type, half_day, start_date, end_date, total_days, reason, attachment)
                          VALUES ($user_id, '$leave_type', '$half_day', '$start_date', '$end_date', $total_days, '$reason', '$attachment')";
                if (mysqli_query($conn, $query)) {
                    $message = '<div class="bg-green-100 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm">✓ Leave application submitted successfully!</div>';
                } else {
                    $message = '<div class="bg-red-100 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">✗ Error submitting leave application.</div>';
                }
            }
        }
    }
}

$balance = getLeaveBalance($user_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
<title>Apply Leave - IPINFRA HRM</title>

<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
* { font-family: 'Inter', sans-serif; }
.sidebar { transition: transform 0.3s ease-in-out; }

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fadeIn { animation: fadeIn 0.3s ease-out; }
.animate-fadeInUp { animation: fadeInUp 0.4s ease-out; }

.balance-card { transition: all 0.2s ease; }
.balance-card:hover { transform: translateY(-3px); }

.form-input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    outline: none;
}

::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

@keyframes floatY {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}
.empty-state-svg { animation: floatY 3.2s ease-in-out infinite; }
</style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen pb-20">
<?php require_once '../includes/global_ui.php'; ?>
<?php require_once '../includes/toast.php'; ?>
<?php require_once '../includes/confirm_modal.php'; ?>

<!-- Premium Header -->
<div class="bg-gradient-to-r from-slate-900 via-indigo-900 to-slate-900 text-white sticky top-0 z-40 shadow-2xl backdrop-blur-sm">
    <div class="flex items-center justify-between px-5 py-4">
        <div class="flex items-center gap-3">
            <button onclick="toggleSidebar()" class="relative group">
                <div class="w-10 h-10 rounded-xl bg-white/10 backdrop-blur-sm flex items-center justify-center group-hover:bg-white/20 transition-all duration-300 group-hover:scale-105">
                    <i class="fas fa-bars text-lg"></i>
                </div>
            </button>
            
            <div class="relative">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 via-indigo-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-500/20 animate-pulse">
                    <span class="text-white font-bold text-sm">IN</span>
                </div>
                <div class="absolute -top-1 -right-1 w-3 h-3 bg-green-400 rounded-full border-2 border-slate-900"></div>
            </div>
            
            <div class="hidden sm:block">
                <p class="text-xs text-blue-200 font-medium tracking-wide">IPINFRA NETWORKS</p>
                <p class="text-sm font-bold tracking-tight">Employee Portal</p>
            </div>
        </div>
        
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-indigo-500 flex items-center justify-center shadow-lg">
                <span class="text-white text-xs font-bold"><?php echo substr($_SESSION['user_name'], 0, 1); ?></span>
            </div>
        </div>
    </div>
    <div class="h-0.5 bg-gradient-to-r from-transparent via-indigo-400 to-transparent"></div>
</div>

<!-- SIDEBAR -->
<div id="sidebar" class="fixed top-0 left-0 h-full w-72 bg-gradient-to-b from-blue-900 to-blue-950 text-white z-50 transform -translate-x-full transition-transform duration-300 shadow-2xl overflow-y-auto">
    <div class="p-6 border-b border-blue-800">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center">
                <span class="text-blue-900 font-bold text-xl">IN</span>
            </div>
            <div>
                <h2 class="font-bold"><?php echo $_SESSION['user_name']; ?></h2>
                <p class="text-xs text-blue-300"><?php echo $_SESSION['employee_id']; ?></p>
            </div>
        </div>
        <button onclick="toggleSidebar()" class="absolute top-4 right-4 text-white/60 hover:text-white">
            <i class="fas fa-times text-xl"></i>
        </button>
    </div>
    <nav class="p-4">
        <a href="dashboard.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1">
            <i class="fas fa-tachometer-alt w-5"></i> Dashboard
        </a>
        <a href="clock.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1">
            <i class="fas fa-clock w-5"></i> Clock In/Out
        </a>
        <a href="leave.php" class="flex items-center gap-3 py-3 px-4 rounded-xl bg-blue-800/50 mb-1">
            <i class="fas fa-calendar-alt w-5"></i> Apply Leave
        </a>
        <a href="claim.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1">
            <i class="fas fa-receipt w-5"></i> Apply Claim
        </a>
        <a href="gallery.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1">
            <i class="fas fa-images w-5"></i> Company Gallery
        </a>
        <a href="assets.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1">
            <i class="fas fa-boxes w-5"></i> Asset Tracker
        </a>
        <a href="management.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1">
            <i class="fas fa-briefcase w-5"></i> My Management
        </a>
        <a href="payslip.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1">
            <i class="fas fa-file-invoice-dollar w-5"></i> Payslip
        </a>
        <a href="calendar.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1">
            <i class="fas fa-calendar w-5"></i> Calendar
        </a>
        <a href="profile.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1">
            <i class="fas fa-user-circle w-5"></i> My Profile
        </a>
        <div class="border-t border-blue-800 my-4"></div>
        <a href="../logout.php" class="flex items-center gap-3 py-3 px-4 rounded-xl bg-red-600/20 text-red-300 hover:bg-red-600/30 transition">
            <i class="fas fa-sign-out-alt w-5"></i> Logout
        </a>
    </nav>
</div>

<div id="overlay" class="fixed inset-0 bg-black/50 z-40 hidden" onclick="toggleSidebar()"></div>

<!-- MAIN CONTENT -->
<div class="px-4 py-6 max-w-2xl mx-auto">
    
    <div class="text-center mb-6 animate-fadeInUp">
        <h1 class="text-2xl font-bold text-gray-800">🏖️ Leave Application</h1>
        <p class="text-sm text-gray-500 mt-1">Request time off and track your leave balance</p>
    </div>

    <!-- Balance Cards (hidden for interns - they have no paid leave entitlement) -->
    <?php if(!$is_intern): ?>
    <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="balance-card bg-gradient-to-br from-green-500 to-emerald-600 text-white p-5 rounded-2xl shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-green-100 opacity-80">Annual Leave</p>
                    <p class="text-4xl font-bold mt-1"><?php echo $balance['annual_remaining']; ?></p>
                    <p class="text-xs text-green-100 mt-1">Available days</p>
                </div>
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                    <i class="fas fa-umbrella-beach text-2xl"></i>
                </div>
            </div>
            <div class="mt-3">
                <div class="bg-white/20 rounded-full h-1.5">
                    <?php 
                    $total_annual = $balance['annual_leave_entitlement'];
                    $used_annual = $balance['used_annual_leave'];
                    $annual_percent = $total_annual > 0 ? ($used_annual / $total_annual) * 100 : 0;
                    ?>
                    <div class="bg-white rounded-full h-1.5" style="width: <?php echo min($annual_percent, 100); ?>%"></div>
                </div>
                <p class="text-xs text-green-100 mt-1">Used: <?php echo $used_annual; ?> / <?php echo $total_annual; ?> days</p>
            </div>
        </div>
        
        <div class="balance-card bg-gradient-to-br from-blue-500 to-indigo-600 text-white p-5 rounded-2xl shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-blue-100 opacity-80">Medical Leave</p>
                    <p class="text-4xl font-bold mt-1"><?php echo $balance['medical_remaining']; ?></p>
                    <p class="text-xs text-blue-100 mt-1">Available days</p>
                </div>
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                    <i class="fas fa-hospital-user text-2xl"></i>
                </div>
            </div>
            <div class="mt-3">
                <div class="bg-white/20 rounded-full h-1.5">
                    <?php 
                    $total_medical = $balance['medical_leave_entitlement'];
                    $used_medical = $balance['used_medical_leave'];
                    $medical_percent = $total_medical > 0 ? ($used_medical / $total_medical) * 100 : 0;
                    ?>
                    <div class="bg-white rounded-full h-1.5" style="width: <?php echo min($medical_percent, 100); ?>%"></div>
                </div>
                <p class="text-xs text-blue-100 mt-1">Used: <?php echo $used_medical; ?> / <?php echo $total_medical; ?> days</p>
            </div>
        </div>
    </div>
    <?php endif; ?><!-- end balance cards for non-interns -->

    <!-- Application / Edit Form -->
    <?php if($is_intern): ?>
    <div class="bg-blue-50 border border-blue-200 rounded-2xl px-5 py-3 mb-4 flex items-center gap-3 animate-fadeIn">
        <i class="fas fa-graduation-cap text-blue-500 text-lg"></i>
        <p class="text-sm text-blue-700 font-medium">Intern — only <strong>Unpaid Leave</strong> is applicable. It will be deducted from your salary.</p>
    </div>
    <?php endif; ?>
    <div class="bg-white rounded-2xl shadow-xl p-6 mb-6 animate-fadeInUp">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-md">
                <i class="fas <?php echo $edit_mode ? 'fa-edit' : 'fa-pen-alt'; ?> text-white"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-gray-800"><?php echo $edit_mode ? 'Edit Leave Request' : 'New Leave Request'; ?></h2>
                <p class="text-xs text-gray-500"><?php echo $edit_mode ? 'Update your leave details' : 'Fill in the details below'; ?></p>
            </div>
        </div>
        
        <?php echo $message; ?>
        <?php echo $error; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <?php if ($edit_mode): ?>
                <input type="hidden" name="leave_id" value="<?php echo $edit_leave['id']; ?>">
            <?php endif; ?>
            
            <?php
            $init_lt       = $edit_mode ? $edit_leave['leave_type'] : '';
            $init_half_day = $edit_mode ? $edit_leave['half_day']   : 'none';
            $init_is_hd    = ($init_lt === 'HD');
            ?>

            <!-- Leave Type -->
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Leave Type</label>
                <select name="leave_type" id="leave_type" required
                        class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 transition"
                        onchange="onLeaveTypeChange()">
                    <?php if ($is_intern): ?>
                        <option value="UL" selected>Unpaid Leave (salary deducted)</option>
                    <?php else: ?>
                        <option value="">-- Select Leave Type --</option>
                        <?php while ($type = mysqli_fetch_assoc($leave_types)):
                            $sel = ($edit_mode && $init_lt == $type['leave_code']) ? 'selected' : '';
                        ?>
                        <option value="<?php echo $type['leave_code']; ?>"
                                data-requires-attachment="<?php echo $type['requires_attachment']; ?>"
                                <?php echo $sel; ?>>
                            <?php echo $type['leave_name']; ?> (<?php echo $type['days_per_year']; ?> days/year)
                        </option>
                        <?php endwhile; ?>
                        <option value="HD" <?php echo $init_is_hd ? 'selected' : ''; ?>>
                            Half Day (no deduction — for manager info only)
                        </option>
                    <?php endif; ?>
                </select>
            </div>

            <!-- hidden field — always submitted -->
            <input type="hidden" name="half_day" id="half_day"
                   value="<?php echo htmlspecialchars($init_half_day); ?>">

            <!-- AM / PM session — only visible when Half Day is chosen -->
            <div id="halfDaySession" style="<?php echo $init_is_hd ? '' : 'display:none;'; ?>">
                <label class="block text-gray-700 text-sm font-semibold mb-2">Session</label>
                <div class="grid grid-cols-2 gap-3">
                    <label id="lbl_first" class="flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition
                        <?php echo ($init_half_day === 'first_half') ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-white hover:border-blue-300'; ?>">
                        <input type="radio" name="half_day_choice" value="first_half" class="accent-blue-600"
                               <?php echo ($init_half_day === 'first_half' || $init_is_hd && $init_half_day === 'none') ? 'checked' : ''; ?>
                               onchange="pickSession(this.value)">
                        <div>
                            <div class="font-semibold text-sm text-gray-800"><i class="fas fa-cloud-sun text-amber-500 mr-1"></i> Morning</div>
                            <div class="text-xs text-gray-400">9:00 AM – 1:00 PM</div>
                        </div>
                    </label>
                    <label id="lbl_second" class="flex items-center gap-3 p-3 rounded-xl border-2 cursor-pointer transition
                        <?php echo ($init_half_day === 'second_half') ? 'border-blue-500 bg-blue-50' : 'border-gray-200 bg-white hover:border-blue-300'; ?>">
                        <input type="radio" name="half_day_choice" value="second_half" class="accent-blue-600"
                               <?php echo ($init_half_day === 'second_half') ? 'checked' : ''; ?>
                               onchange="pickSession(this.value)">
                        <div>
                            <div class="font-semibold text-sm text-gray-800"><i class="fas fa-moon text-indigo-500 mr-1"></i> Afternoon</div>
                            <div class="text-xs text-gray-400">2:00 PM – 6:00 PM</div>
                        </div>
                    </label>
                </div>
                <p class="text-xs text-gray-400 mt-1.5"><i class="fas fa-info-circle mr-1"></i>Half Day leave does not deduct from your leave balance.</p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2" id="startDateLabel">
                        <?php echo $init_is_hd ? 'Leave Date' : 'Start Date'; ?>
                    </label>
                    <input type="date" name="start_date" id="start_date" required
                           value="<?php echo $edit_mode ? $edit_leave['start_date'] : ''; ?>"
                           class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 transition"
                           onchange="syncEndDate()">
                </div>
                <div id="endDateContainer" style="<?php echo $init_is_hd ? 'display:none;' : 'display:block;'; ?>">
                    <label class="block text-gray-700 text-sm font-semibold mb-2">End Date</label>
                    <input type="date" name="end_date" id="end_date"
                           value="<?php echo $edit_mode ? $edit_leave['end_date'] : ''; ?>"
                           class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 transition">
                </div>
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Reason <span class="text-gray-400 font-normal">(Optional)</span></label>
                <textarea name="reason" rows="3" placeholder="Please provide reason for leave..." class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 transition"><?php echo $edit_mode ? htmlspecialchars($edit_leave['reason']) : ''; ?></textarea>
            </div>
            
            <div id="attachmentContainer">
                <label class="block text-gray-700 text-sm font-semibold mb-2">
                    Attachment 
                    <span class="text-gray-400 font-normal" id="attachmentRequired">
                        <?php 
                        if ($edit_mode) {
                            $type_query = mysqli_query($conn, "SELECT requires_attachment FROM leave_types WHERE leave_code = '{$edit_leave['leave_type']}'");
                            $type_data = mysqli_fetch_assoc($type_query);
                            echo ($type_data && $type_data['requires_attachment']) ? '(Required)' : '(Optional)';
                        } else {
                            echo '(Optional)';
                        }
                        ?>
                    </span>
                </label>
                
                <?php if ($edit_mode && !empty($edit_leave['attachment'])): ?>
                    <div class="mb-2 p-2 bg-gray-100 rounded-lg flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-paperclip text-gray-500"></i>
                            <span class="text-sm text-gray-600">Current: <?php echo $edit_leave['attachment']; ?></span>
                        </div>
                        <a href="../uploads/<?php echo $edit_leave['attachment']; ?>" target="_blank" class="text-blue-500 text-sm">View</a>
                    </div>
                <?php endif; ?>
                
                <div class="relative">
                    <input type="file" name="attachment" id="fileInput" class="hidden">
                    <button type="button" onclick="document.getElementById('fileInput').click()" class="w-full border-2 border-dashed border-gray-300 rounded-xl p-3 text-center hover:border-blue-500 transition group">
                        <i class="fas fa-cloud-upload-alt text-gray-400 text-2xl group-hover:text-blue-500 transition"></i>
                        <p class="text-sm text-gray-500 mt-1 group-hover:text-blue-500 transition">Click to upload document (replaces existing if any)</p>
                        <p class="text-xs text-gray-400 mt-1" id="fileName">No file chosen</p>
                    </button>
                </div>
            </div>
            
            <button type="submit" name="<?php echo $edit_mode ? 'update_leave' : 'apply_leave'; ?>" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:shadow-xl transition-all transform hover:scale-105 text-white py-3 rounded-xl font-semibold flex items-center justify-center gap-2">
                <i class="fas <?php echo $edit_mode ? 'fa-save' : 'fa-paper-plane'; ?>"></i>
                <?php echo $edit_mode ? ' Update Leave Application' : ' Submit Leave Application'; ?>
            </button>
            
            <?php if ($edit_mode): ?>
            <div class="text-center">
                <a href="leave.php" class="text-sm text-gray-500 hover:text-blue-600 transition">Cancel Edit</a>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Leave History with Pagination & Edit/Delete Options -->
    <?php if($total_rows > 0): ?>
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-gradient-to-r from-gray-50 to-white px-5 py-4 border-b">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-2">
                    <i class="fas fa-history text-blue-500 text-xl"></i>
                    <h3 class="font-semibold text-gray-800">Leave History</h3>
                    <span class="text-xs text-gray-400">(<?php echo $total_rows; ?> total)</span>
                </div>
                
                <form method="GET" class="flex gap-2 flex-wrap">
                    <select name="status" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                    <select name="per_page" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5">
                        <option value="5"  <?php echo $per_page == 5  ? 'selected' : ''; ?>>5 / page</option>
                        <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10 / page</option>
                        <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25 / page</option>
                        <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50 / page</option>
                        <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>All</option>
                    </select>
                    <input type="hidden" name="page" value="1">
                    <button type="submit" class="bg-blue-600 text-white px-3 py-1.5 rounded-lg text-sm">Apply</button>
                    <?php if($status_filter || $per_page != 10): ?>
                        <a href="leave.php" class="bg-gray-200 text-gray-700 px-3 py-1.5 rounded-lg text-sm">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <div class="divide-y divide-gray-100">
            <?php while($leave = mysqli_fetch_assoc($history)):
                $is_hd_leave  = ($leave['leave_type'] === 'HD');
                $session_text = ($is_hd_leave && $leave['half_day'] != 'none') ? ' (' . ($leave['half_day'] == 'first_half' ? 'Morning' : 'Afternoon') . ')' : '';
                if ($is_hd_leave) {
                    $days = 0;
                    $type_label = 'Half Day' . $session_text;
                    $type_icon  = 'clock';
                } else {
                    $days = $leave['total_days'] ?: ((strtotime($leave['end_date']) - strtotime($leave['start_date'])) / 86400 + 1);
                    $lc = strtolower($leave['leave_type']);
                    $type_label = ucfirst($leave['leave_type']) . ' Leave' . $session_text;
                    $type_icon  = str_contains($lc, 'al') ? 'umbrella-beach' : (str_contains($lc, 'ml') ? 'hospital-user' : 'exclamation-triangle');
                }
                $status_color = $leave['status'] == 'approved' ? 'green' : ($leave['status'] == 'rejected' ? 'red' : 'yellow');
                $status_icon  = $leave['status'] == 'approved' ? 'check-circle' : ($leave['status'] == 'rejected' ? 'times-circle' : 'clock');
            ?>
            <div class="p-4 hover:bg-gray-50 transition">
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <i class="fas fa-<?php echo $type_icon; ?> text-<?php echo $status_color; ?>-500"></i>
                            <span class="font-medium text-gray-800"><?php echo htmlspecialchars($type_label); ?></span>
                            <span class="text-xs text-gray-400">• <?php echo $is_hd_leave ? 'Half Day' : $days . ' day(s)'; ?></span>
                        </div>
                        <p class="text-xs text-gray-500">
                            <i class="far fa-calendar-alt mr-1"></i> <?php echo date('d M Y', strtotime($leave['start_date'])); ?> - <?php echo date('d M Y', strtotime($leave['end_date'])); ?>
                        </p>
                        <?php if($leave['reason']): ?>
                            <p class="text-xs text-gray-400 mt-1"><?php echo substr($leave['reason'], 0, 60); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="text-right">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-<?php echo $status_color; ?>-100 text-<?php echo $status_color; ?>-700">
                            <i class="fas fa-<?php echo $status_icon; ?>"></i>
                            <?php echo ucfirst($leave['status']); ?>
                        </span>
                        
                        <?php if ($leave['status'] == 'pending'): ?>
                            <div class="flex gap-2 mt-2">
                                <a href="?edit=<?php echo $leave['id']; ?>" class="text-blue-600 hover:text-blue-800 text-sm" title="Edit">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="?delete=<?php echo $leave['id']; ?>" data-confirm="Delete this leave application? This action cannot be undone." data-confirm-title="Delete Leave" class="text-red-500 hover:text-red-700 text-sm" title="Delete">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        
        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <div class="bg-gray-50 px-4 py-3 border-t flex justify-between items-center flex-wrap gap-2">
            <p class="text-sm text-gray-500">
                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_rows); ?> of <?php echo $total_rows; ?> records
            </p>
            <div class="flex gap-1">
                <?php if($page > 1): ?>
                    <a href="?page=1&per_page=<?php echo $per_page; ?>&status=<?php echo $status_filter; ?>" class="px-3 py-1 bg-white border rounded-lg text-sm hover:bg-gray-100">First</a>
                    <a href="?page=<?php echo $page-1; ?>&per_page=<?php echo $per_page; ?>&status=<?php echo $status_filter; ?>" class="px-3 py-1 bg-white border rounded-lg text-sm hover:bg-gray-100">← Prev</a>
                <?php endif; ?>
                
                <span class="px-3 py-1 bg-blue-600 text-white rounded-lg text-sm"><?php echo $page; ?></span>
                
                <?php if($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&per_page=<?php echo $per_page; ?>&status=<?php echo $status_filter; ?>" class="px-3 py-1 bg-white border rounded-lg text-sm hover:bg-gray-100">Next →</a>
                    <a href="?page=<?php echo $total_pages; ?>&per_page=<?php echo $per_page; ?>&status=<?php echo $status_filter; ?>" class="px-3 py-1 bg-white border rounded-lg text-sm hover:bg-gray-100">Last</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="bg-white rounded-2xl shadow-xl py-14 px-6 text-center">
        <div class="flex justify-center mb-6">
            <svg class="empty-state-svg" width="140" height="140" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg" style="filter: drop-shadow(0 8px 24px rgba(59,130,246,0.15));">
                <!-- Calendar base -->
                <rect x="14" y="26" width="92" height="80" rx="10" fill="#eff6ff"/>
                <rect x="14" y="26" width="92" height="80" rx="10" stroke="#3b82f6" stroke-width="2.5" fill="none"/>
                <!-- Calendar top bar -->
                <rect x="14" y="26" width="92" height="26" rx="10" fill="#3b82f6"/>
                <rect x="14" y="40" width="92" height="12" fill="#3b82f6"/>
                <!-- Ring pegs -->
                <rect x="36" y="18" width="6" height="18" rx="3" fill="#1d4ed8"/>
                <rect x="78" y="18" width="6" height="18" rx="3" fill="#1d4ed8"/>
                <!-- Month dots / grid -->
                <circle cx="34" cy="66" r="4" fill="#3b82f6" opacity="0.6"/>
                <circle cx="50" cy="66" r="4" fill="#3b82f6" opacity="0.6"/>
                <circle cx="66" cy="66" r="4" fill="#3b82f6" opacity="0.6"/>
                <circle cx="82" cy="66" r="4" fill="#3b82f6" opacity="0.3"/>
                <circle cx="34" cy="82" r="4" fill="#3b82f6" opacity="0.3"/>
                <circle cx="50" cy="82" r="4" fill="#3b82f6" opacity="0.3"/>
                <circle cx="66" cy="82" r="4" fill="#3b82f6" opacity="0.3"/>
                <circle cx="82" cy="82" r="4" fill="#3b82f6" opacity="0.3"/>
                <!-- Palm tree / beach icon in top-right (annual leave hint) -->
                <circle cx="94" cy="20" r="4" fill="#93c5fd" opacity="0.8"/>
                <circle cx="105" cy="30" r="2.5" fill="#3b82f6" opacity="0.4"/>
                <circle cx="86" cy="12" r="2" fill="#1d4ed8" opacity="0.5"/>
                <!-- Highlighted day -->
                <circle cx="50" cy="66" r="7" fill="none" stroke="#3b82f6" stroke-width="2"/>
            </svg>
        </div>
        <h3 class="text-lg font-bold text-gray-700 mb-1">No Leave Records</h3>
        <p class="text-sm text-gray-400 mb-4 max-w-xs mx-auto">
            <?php if($status_filter): ?>
                No <strong class="text-blue-600"><?php echo $status_filter; ?></strong> leave applications found.
            <?php else: ?>
                Your leave history will appear here once you submit your first application.
            <?php endif; ?>
        </p>
        <?php if($status_filter): ?>
            <a href="leave.php" class="inline-flex items-center gap-2 text-sm font-semibold text-blue-600 hover:text-blue-800 border border-blue-200 hover:border-blue-400 px-4 py-2 rounded-xl transition">
                <i class="fas fa-times-circle text-xs"></i> Clear Filter
            </a>
        <?php else: ?>
            <p class="text-xs text-gray-400 italic">Tip: Use the form above to apply for annual, medical or unpaid leave.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('-translate-x-full');
    document.getElementById('overlay').classList.toggle('hidden');
}

document.getElementById('fileInput')?.addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name || 'No file chosen';
    document.getElementById('fileName').textContent = fileName;
});

function onLeaveTypeChange() {
    const select   = document.getElementById('leave_type');
    const val      = select ? select.value : '';
    const isHD     = (val === 'HD');
    const session  = document.getElementById('halfDaySession');
    const hiddenHD = document.getElementById('half_day');

    // Show/hide AM/PM session block
    if (session) session.style.display = isHD ? '' : 'none';

    if (isHD) {
        // Default to first_half when HD first selected
        const checked = document.querySelector('input[name="half_day_choice"]:checked');
        hiddenHD.value = checked ? checked.value : 'first_half';
        if (!checked) {
            const r = document.querySelector('input[name="half_day_choice"][value="first_half"]');
            if (r) { r.checked = true; highlightSession('first_half'); }
        }
    } else {
        hiddenHD.value = 'none';
    }

    // Attachment required/optional label
    const opt = select ? select.options[select.selectedIndex] : null;
    const span = document.getElementById('attachmentRequired');
    if (span) {
        const req = opt ? opt.getAttribute('data-requires-attachment') : '0';
        span.innerHTML = req == '1' ? '(Required)' : '(Optional)';
        span.classList.toggle('text-red-500', req == '1');
    }

    syncEndDate();
}

function pickSession(val) {
    document.getElementById('half_day').value = val;
    highlightSession(val);
}

function highlightSession(val) {
    ['first', 'second'].forEach(function(key) {
        const lbl = document.getElementById('lbl_' + key);
        if (!lbl) return;
        const active = (val === key + '_half');
        lbl.classList.toggle('border-blue-500', active);
        lbl.classList.toggle('bg-blue-50',      active);
        lbl.classList.toggle('border-gray-200', !active);
        lbl.classList.toggle('bg-white',        !active);
    });
}

function syncEndDate() {
    const isHD            = (document.getElementById('leave_type')?.value === 'HD');
    const endDateContainer = document.getElementById('endDateContainer');
    const endDateInput    = document.getElementById('end_date');
    const startLabel      = document.getElementById('startDateLabel');

    if (isHD) {
        if (endDateContainer) endDateContainer.style.display = 'none';
        if (endDateInput) {
            endDateInput.value    = document.getElementById('start_date').value;
            endDateInput.required = false;
        }
        if (startLabel) startLabel.textContent = 'Leave Date';
    } else {
        if (endDateContainer) endDateContainer.style.display = 'block';
        if (endDateInput) endDateInput.required = true;
        if (startLabel) startLabel.textContent = 'Start Date';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    onLeaveTypeChange();
    syncEndDate();
});
</script>

</body>
</html>