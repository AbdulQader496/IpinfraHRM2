<?php
require_once '../includes/auth.php';
redirectIfNotAdmin();
require_once '../includes/db.php';
require_once '../includes/toast_fn.php';

// ========================================
// HANDLE ANNOUNCEMENTS (ADD, EDIT, DELETE)
// ========================================

// Add Announcement
if (isset($_POST['add_announcement'])) {
    $title             = mysqli_real_escape_string($conn, $_POST['title']);
    $message           = mysqli_real_escape_string($conn, $_POST['message']);
    $announcement_type = mysqli_real_escape_string($conn, $_POST['announcement_type']);
    $target_role       = mysqli_real_escape_string($conn, $_POST['target_role']);
    $start_date        = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date          = !empty($_POST['end_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['end_date']) . "'" : 'NULL';
    $created_by        = intval($_SESSION['user_id']);

    mysqli_query($conn, "INSERT INTO announcements (title, message, announcement_type, target_role, start_date, end_date, created_by)
        VALUES ('$title', '$message', '$announcement_type', '$target_role', '$start_date', $end_date, $created_by)");
    header('Location: dashboard.php');
    exit();
}

// Edit Announcement
if (isset($_POST['edit_announcement'])) {
    $id                = intval($_POST['announcement_id']);
    $title             = mysqli_real_escape_string($conn, $_POST['title']);
    $message           = mysqli_real_escape_string($conn, $_POST['message']);
    $announcement_type = mysqli_real_escape_string($conn, $_POST['announcement_type']);
    $target_role       = mysqli_real_escape_string($conn, $_POST['target_role']);
    $start_date        = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date          = !empty($_POST['end_date']) ? "'" . mysqli_real_escape_string($conn, $_POST['end_date']) . "'" : 'NULL';
    $is_active         = isset($_POST['is_active']) ? 1 : 0;

    mysqli_query($conn, "UPDATE announcements SET
        title='$title', message='$message', announcement_type='$announcement_type',
        target_role='$target_role', start_date='$start_date', end_date=$end_date,
        is_active=$is_active WHERE id=$id");
    header('Location: dashboard.php');
    exit();
}

// Delete Announcement
if (isset($_GET['delete_announcement'])) {
    $id = intval($_GET['delete_announcement']);
    mysqli_query($conn, "DELETE FROM announcements WHERE id=$id");
    header('Location: dashboard.php');
    exit();
}

// Get statistics
$total_employees  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM employees WHERE role='employee'"))['count'];
$active_employees = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM employees WHERE role='employee' AND status='active'"))['count'];
$regular_count    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM employees WHERE role='employee' AND status='active' AND (employee_type='regular' OR employee_type IS NULL)"))['count'];
$intern_count     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM employees WHERE role='employee' AND status='active' AND employee_type='intern'"))['count'];
$pending_leaves = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM leaves WHERE status='pending'"))['count'];
$pending_claims = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM claims WHERE status='pending'"))['count'];
$today_attendance = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM attendance WHERE date=CURDATE() AND clock_in IS NOT NULL"))['count'];
$total_assets = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(quantity) as count FROM assets"))['count'];
$total_payroll = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(net_salary) as total FROM payroll WHERE month_year = DATE_FORMAT(CURDATE(), '%Y-%m')"))['total'];

// ── Analytics: Monthly attendance rate (last 6 months) ──────────────
$attendance_months  = [];
$attendance_rates   = [];
for ($i = 5; $i >= 0; $i--) {
    $month_label = date('M Y', strtotime("-$i months"));
    $month_key   = date('Y-m', strtotime("-$i months"));
    $attendance_months[] = $month_label;

    $total_working_days = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(DISTINCT date) as cnt FROM attendance
         WHERE DATE_FORMAT(date,'%Y-%m') = '$month_key'"))['cnt'];

    $present_count = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) as cnt FROM attendance
         WHERE DATE_FORMAT(date,'%Y-%m') = '$month_key'
         AND clock_in IS NOT NULL"))['cnt'];

    if ($total_working_days > 0 && $active_employees > 0) {
        $rate = round(($present_count / ($active_employees * $total_working_days)) * 100, 1);
    } else {
        $rate = 0;
    }
    $attendance_rates[] = min($rate, 100);
}

// ── Analytics: Leave type distribution (current year, approved) ──────
$leave_types  = ['Annual', 'Medical', 'Unpaid', 'Emergency'];
$leave_counts = [];
foreach (['annual', 'medical', 'unpaid', 'emergency'] as $lt) {
    $row = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COUNT(*) as cnt FROM leaves
         WHERE leave_type='$lt' AND status='approved'
         AND YEAR(start_date) = YEAR(CURDATE())"));
    $leave_counts[] = (int)$row['cnt'];
}

// ── Analytics: Monthly payroll totals (last 6 months) ────────────────
$payroll_months = [];
$payroll_totals = [];
for ($i = 5; $i >= 0; $i--) {
    $payroll_months[] = date('M Y', strtotime("-$i months"));
    $my = date('Y-%m', strtotime("-$i months")); // DATE_FORMAT pattern
    $my_val = date('Y-m', strtotime("-$i months"));
    $row = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT COALESCE(SUM(net_salary),0) as total FROM payroll
         WHERE month_year='$my_val'"));
    $payroll_totals[] = (float)$row['total'];
}

// ── Analytics: Department headcount ──────────────────────────────────
$dept_result = mysqli_query($conn,
    "SELECT department, COUNT(*) as cnt FROM employees
     WHERE role='employee' AND status='active'
     GROUP BY department ORDER BY cnt DESC LIMIT 6");
$dept_labels  = [];
$dept_counts  = [];
while ($dr = mysqli_fetch_assoc($dept_result)) {
    $dept_labels[] = $dr['department'] ?: 'Unassigned';
    $dept_counts[]  = (int)$dr['cnt'];
}

// Get recent employees
$recent_employees = mysqli_query($conn, "SELECT * FROM employees WHERE role='employee' ORDER BY id DESC LIMIT 5");

// ── Employee of the Month (admin-selected) ────────────────────────────
@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS employee_of_month (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    month_year VARCHAR(7) NOT NULL,
    note VARCHAR(255),
    selected_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_month (month_year),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (selected_by) REFERENCES employees(id) ON DELETE SET NULL
)");

if (isset($_GET['clear_eom'])) {
    mysqli_query($conn, "DELETE FROM employee_of_month WHERE month_year = DATE_FORMAT(CURDATE(),'%Y-%m')");
    header('Location: dashboard.php');
    exit();
}

if (isset($_POST['set_eom'])) {
    $eom_emp = intval($_POST['eom_employee_id']);
    $eom_note = mysqli_real_escape_string($conn, $_POST['eom_note'] ?? '');
    $eom_month = date('Y-m');
    $sel_by = intval($_SESSION['user_id']);
    mysqli_query($conn, "INSERT INTO employee_of_month (employee_id, month_year, note, selected_by)
        VALUES ($eom_emp, '$eom_month', '$eom_note', $sel_by)
        ON DUPLICATE KEY UPDATE employee_id=$eom_emp, note='$eom_note', selected_by=$sel_by, created_at=NOW()");
    header('Location: dashboard.php');
    exit();
}

$eom_current = null;
try {
    $eom_current = mysqli_fetch_assoc(mysqli_query($conn,
        "SELECT m.*, e.name, e.department, e.profile_pic
         FROM employee_of_month m
         JOIN employees e ON m.employee_id = e.id
         WHERE m.month_year = DATE_FORMAT(CURDATE(),'%Y-%m')
         LIMIT 1"));
} catch (Exception $e) { }

// Get announcements
$announcements = mysqli_query($conn, "SELECT * FROM announcements WHERE is_active = 1 AND (end_date IS NULL OR end_date >= CURDATE()) ORDER BY 
    CASE announcement_type 
        WHEN 'urgent' THEN 1 
        WHEN 'holiday' THEN 2 
        WHEN 'event' THEN 3 
        ELSE 4 
    END, created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Admin Dashboard - IPINFRA HRM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { font-family: 'Inter', sans-serif; }
        .sidebar { transition: transform 0.3s ease-in-out; }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideInLeft {
            from { opacity: 0; transform: translateX(-30px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes slideInRight {
            from { opacity: 0; transform: translateX(30px); }
            to { opacity: 1; transform: translateX(0); }
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .animate-fadeInUp { animation: fadeInUp 0.5s ease-out; }
        .animate-slideLeft { animation: slideInLeft 0.5s ease-out; }
        .animate-slideRight { animation: slideInRight 0.5s ease-out; }
        
        .stat-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -12px rgba(0, 0, 0, 0.15);
        }
        
        .quick-action {
            transition: all 0.2s ease;
        }
        .quick-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.15);
        }
        
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 10px; }
        
        .welcome-card {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            position: relative;
            overflow: hidden;
        }
        .welcome-card::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 4s ease-in-out infinite;
        }
        
        .notification-badge {
            animation: pulse 2s ease-in-out infinite;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 min-h-screen pb-20">
<?php require_once '../includes/global_ui.php'; ?>

<!-- Premium Mobile Header -->
<div class="bg-[#060912] text-white sticky top-0 z-40 shadow-2xl">
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
        <div class="flex items-center gap-3">
            <!-- Notification Bell -->
            <div class="relative" id="notifWrapper">
                <button onclick="toggleNotif()" class="relative p-2 rounded-full hover:bg-white/10 transition">
                    <i class="fas fa-bell text-white/80 text-lg"></i>
                    <?php if($pending_leaves + $pending_claims > 0): ?>
                        <span class="absolute -top-0.5 -right-0.5 w-5 h-5 bg-red-500 rounded-full text-[10px] font-bold flex items-center justify-center"><?php echo $pending_leaves + $pending_claims; ?></span>
                    <?php endif; ?>
                </button>
                <!-- Dropdown -->
                <div id="notifDropdown" class="hidden absolute right-0 top-12 w-72 bg-white rounded-2xl shadow-2xl z-50 overflow-hidden">
                    <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-4 py-3">
                        <p class="text-white font-semibold text-sm">Pending Actions</p>
                        <p class="text-indigo-100 text-xs"><?php echo $pending_leaves + $pending_claims; ?> item(s) need attention</p>
                    </div>
                    <div class="divide-y divide-gray-100 max-h-72 overflow-y-auto">
                        <?php if($pending_leaves > 0): ?>
                        <a href="manage_leave.php" class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition">
                            <div class="w-9 h-9 bg-yellow-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-calendar-check text-yellow-600"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-800"><?php echo $pending_leaves; ?> Leave Request<?php echo $pending_leaves > 1 ? 's' : ''; ?></p>
                                <p class="text-xs text-gray-400">Awaiting approval</p>
                            </div>
                            <i class="fas fa-chevron-right text-gray-300 ml-auto text-xs"></i>
                        </a>
                        <?php endif; ?>
                        <?php if($pending_claims > 0): ?>
                        <a href="manage_claim.php" class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition">
                            <div class="w-9 h-9 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-receipt text-green-600"></i>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-800"><?php echo $pending_claims; ?> Claim<?php echo $pending_claims > 1 ? 's' : ''; ?></p>
                                <p class="text-xs text-gray-400">Awaiting approval</p>
                            </div>
                            <i class="fas fa-chevron-right text-gray-300 ml-auto text-xs"></i>
                        </a>
                        <?php endif; ?>
                        <?php if($pending_leaves + $pending_claims == 0): ?>
                        <div class="px-4 py-6 text-center">
                            <i class="fas fa-check-circle text-green-400 text-2xl mb-2"></i>
                            <p class="text-sm text-gray-500">All caught up!</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/admin_sidebar.php'; ?>

<!-- Main Content -->
<div class="px-4 py-6 pb-24 md:pb-6 max-w-7xl mx-auto">
    
    <!-- Welcome Card -->
    <div class="welcome-card rounded-2xl p-6 mb-8 text-white shadow-2xl animate-slideLeft">
        <div class="relative z-10">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div>
                    <p class="text-sm text-blue-200">Welcome back,</p>
                    <h1 class="text-3xl font-bold mt-1"><?php echo $_SESSION['user_name']; ?></h1>
                    <p class="text-sm text-blue-200 mt-2">
                        <i class="fas fa-calendar-alt mr-2"></i><?php echo date('l, d F Y'); ?>
                    </p>
                </div>
                <div class="text-right">
                    <div class="w-16 h-16 bg-white/20 rounded-2xl flex items-center justify-center backdrop-blur-sm">
                        <i class="fas fa-chart-line text-3xl"></i>
                    </div>
                    <p class="text-xs text-blue-200 mt-2">Dashboard Overview</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Premium Stats Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
        <div class="stat-card bg-white rounded-2xl p-5 shadow-lg border-l-4 border-blue-500 animate-fadeInUp">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 font-medium">Total Staff</p>
                    <p class="text-3xl font-bold text-gray-800 mt-2 count-up" data-target="<?php echo $active_employees; ?>">0</p>
                    <p class="text-xs text-gray-400 mt-1 flex items-center gap-2 flex-wrap">
                        <span><i class="fas fa-user-tie text-blue-500 mr-1"></i><span class="count-up" data-target="<?php echo $regular_count; ?>">0</span> regular</span>
                        <span><i class="fas fa-graduation-cap text-purple-500 mr-1"></i><span class="count-up" data-target="<?php echo $intern_count; ?>">0</span> intern</span>
                    </p>
                </div>
                <div class="w-14 h-14 bg-blue-100 rounded-2xl flex items-center justify-center">
                    <i class="fas fa-users text-blue-600 text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card bg-white rounded-2xl p-5 shadow-lg border-l-4 border-yellow-500 animate-fadeInUp">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 font-medium">Pending Leaves</p>
                    <p class="text-3xl font-bold text-gray-800 mt-2 count-up" data-target="<?php echo $pending_leaves; ?>">0</p>
                    <p class="text-xs text-gray-400 mt-1">Awaiting approval</p>
                </div>
                <div class="w-14 h-14 bg-yellow-100 rounded-2xl flex items-center justify-center">
                    <i class="fas fa-calendar-check text-yellow-600 text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card bg-white rounded-2xl p-5 shadow-lg border-l-4 border-purple-500 animate-fadeInUp">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 font-medium">Pending Claims</p>
                    <p class="text-3xl font-bold text-gray-800 mt-2 count-up" data-target="<?php echo $pending_claims; ?>">0</p>
                    <p class="text-xs text-gray-400 mt-1">Need review</p>
                </div>
                <div class="w-14 h-14 bg-purple-100 rounded-2xl flex items-center justify-center">
                    <i class="fas fa-receipt text-purple-600 text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card bg-gradient-to-r from-green-600 to-emerald-600 rounded-2xl p-5 shadow-lg text-white animate-fadeInUp">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-green-100 font-medium">Today's Attendance</p>
                    <p class="text-3xl font-bold mt-2 count-up" data-target="<?php echo $today_attendance; ?>">0</p>
                    <p class="text-xs text-green-100 mt-1">Clocked in today</p>
                </div>
                <div class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center">
                    <i class="fas fa-fingerprint text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="mb-8 animate-fadeInUp">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-bold text-gray-800">Quick Actions</h2>
            <span class="text-xs text-gray-400">Frequently used</span>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="employees.php" class="quick-action bg-white rounded-2xl p-4 text-center shadow-md hover:shadow-xl transition-all group">
                <div class="w-12 h-12 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center mx-auto mb-2 shadow-md group-hover:scale-110 transition">
                    <i class="fas fa-user-plus text-white text-xl"></i>
                </div>
                <span class="text-sm font-semibold text-gray-700">Add Employee</span>
            </a>
            <a href="manage_leave.php" class="quick-action bg-white rounded-2xl p-4 text-center shadow-md hover:shadow-xl transition-all group">
                <div class="w-12 h-12 bg-gradient-to-br from-yellow-500 to-orange-500 rounded-xl flex items-center justify-center mx-auto mb-2 shadow-md group-hover:scale-110 transition">
                    <i class="fas fa-check-circle text-white text-xl"></i>
                </div>
                <span class="text-sm font-semibold text-gray-700">Approve Leaves</span>
                <?php if($pending_leaves > 0): ?>
                    <span class="ml-1 bg-red-500 text-white text-xs px-1.5 py-0.5 rounded-full"><?php echo $pending_leaves; ?></span>
                <?php endif; ?>
            </a>
            <a href="manage_claim.php" class="quick-action bg-white rounded-2xl p-4 text-center shadow-md hover:shadow-xl transition-all group">
                <div class="w-12 h-12 bg-gradient-to-br from-purple-500 to-pink-500 rounded-xl flex items-center justify-center mx-auto mb-2 shadow-md group-hover:scale-110 transition">
                    <i class="fas fa-money-bill-wave text-white text-xl"></i>
                </div>
                <span class="text-sm font-semibold text-gray-700">Approve Claims</span>
                <?php if($pending_claims > 0): ?>
                    <span class="ml-1 bg-red-500 text-white text-xs px-1.5 py-0.5 rounded-full"><?php echo $pending_claims; ?></span>
                <?php endif; ?>
            </a>
            <a href="payroll.php" class="quick-action bg-white rounded-2xl p-4 text-center shadow-md hover:shadow-xl transition-all group">
                <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-rose-500 rounded-xl flex items-center justify-center mx-auto mb-2 shadow-md group-hover:scale-110 transition">
                    <i class="fas fa-calculator text-white text-xl"></i>
                </div>
                <span class="text-sm font-semibold text-gray-700">Run Payroll</span>
            </a>
        </div>
    </div>

    <!-- Announcements (full width) -->
    <div class="mb-8">
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden animate-slideRight">
            <div class="bg-gradient-to-r from-gray-50 to-white px-5 py-4 border-b flex justify-between items-center">
                <div class="flex items-center gap-2">
                    <i class="fas fa-bullhorn text-purple-600 text-xl"></i>
                    <h3 class="font-semibold text-gray-800">Announcements</h3>
                </div>
                <button onclick="document.getElementById('addAnnouncementModal').classList.remove('hidden')" class="bg-purple-600 text-white px-3 py-1.5 rounded-lg text-sm hover:bg-purple-700 transition flex items-center gap-1">
                    <i class="fas fa-plus text-xs"></i> Add
                </button>
            </div>

            <?php if(mysqli_num_rows($announcements) > 0): ?>
                <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                    <?php
                    // Reset the result pointer since it was already iterated below
                    mysqli_data_seek($announcements, 0);
                    while($ann = mysqli_fetch_assoc($announcements)):
                        $type_colors = [
                            'general' => 'bg-blue-100 text-blue-700',
                            'urgent' => 'bg-red-100 text-red-700',
                            'holiday' => 'bg-green-100 text-green-700',
                            'event' => 'bg-purple-100 text-purple-700'
                        ];
                        $type_icons = [
                            'general' => 'fa-info-circle',
                            'urgent' => 'fa-exclamation-triangle',
                            'holiday' => 'fa-gift',
                            'event' => 'fa-calendar-day'
                        ];
                        $type_color = $type_colors[$ann['announcement_type']] ?? 'bg-gray-100 text-gray-700';
                        $type_icon = $type_icons[$ann['announcement_type']] ?? 'fa-bullhorn';
                    ?>
                    <div class="p-4 hover:bg-gray-50 transition">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium <?php echo $type_color; ?>">
                                        <i class="fas <?php echo $type_icon; ?> text-xs"></i>
                                        <?php echo ucfirst($ann['announcement_type']); ?>
                                    </span>
                                    <span class="text-xs text-gray-400">
                                        <i class="far fa-clock mr-1"></i><?php echo date('d M Y', strtotime($ann['created_at'])); ?>
                                    </span>
                                </div>
                                <h4 class="font-semibold text-gray-800"><?php echo htmlspecialchars($ann['title']); ?></h4>
                                <p class="text-sm text-gray-600 mt-1"><?php echo nl2br(htmlspecialchars($ann['message'])); ?></p>
                                <?php if($ann['end_date']): ?>
                                    <p class="text-xs text-gray-400 mt-2">
                                        <i class="far fa-calendar-alt mr-1"></i>
                                        Valid until: <?php echo date('d M Y', strtotime($ann['end_date'])); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div class="flex gap-1 ml-2">
                                <button onclick='openEditAnnouncementModal(<?php echo json_encode($ann); ?>)' class="text-blue-500 hover:text-blue-700 p-1">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="?delete_announcement=<?php echo $ann['id']; ?>" data-confirm="Delete this announcement? This cannot be undone." data-confirm-title="Delete Announcement" class="text-red-500 hover:text-red-700 p-1">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="p-8 text-center">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-bullhorn text-2xl text-gray-400"></i>
                    </div>
                    <p class="text-gray-500 font-medium">No announcements yet</p>
                    <p class="text-xs text-gray-400 mt-1">Click "Add" to create an announcement</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Employee of the Month -->
    <div class="mb-8">
        <div class="bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 rounded-2xl shadow-xl overflow-hidden relative">
            <div class="absolute top-0 right-0 w-48 h-48 bg-white/10 rounded-full -translate-y-1/2 translate-x-1/2 pointer-events-none"></div>
            <div class="absolute bottom-0 left-0 w-32 h-32 bg-white/10 rounded-full translate-y-1/2 -translate-x-1/2 pointer-events-none"></div>
            <div class="relative z-10 p-5">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-trophy text-yellow-300 text-xl"></i>
                        <div>
                            <p class="font-bold text-white text-sm uppercase tracking-widest">Employee of the Month</p>
                            <p class="text-white/60 text-xs"><?php echo date('F Y'); ?></p>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <?php if($eom_current): ?>
                        <a href="?clear_eom=1" data-confirm="Remove this month's Employee of the Month?" data-confirm-title="Remove Selection" class="bg-white/20 hover:bg-red-500/60 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition backdrop-blur-sm">
                            <i class="fas fa-trash mr-1"></i> Remove
                        </a>
                        <?php endif; ?>
                        <button onclick="document.getElementById('eomModal').classList.remove('hidden')" class="bg-white/20 hover:bg-white/30 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition backdrop-blur-sm">
                            <i class="fas fa-edit mr-1"></i> <?php echo $eom_current ? 'Change' : 'Select'; ?>
                        </button>
                    </div>
                </div>
                <?php if($eom_current): ?>
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center overflow-hidden border-2 border-white/40 shadow-lg flex-shrink-0">
                        <?php if(!empty($eom_current['profile_pic']) && file_exists('../uploads/profiles/'.$eom_current['profile_pic'])): ?>
                            <img src="../uploads/profiles/<?php echo htmlspecialchars($eom_current['profile_pic']); ?>" class="w-full h-full object-cover">
                        <?php else: ?>
                            <span class="text-2xl font-bold text-white"><?php echo strtoupper(substr($eom_current['name'],0,1)); ?></span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <a href="view_employee.php?id=<?php echo $eom_current['employee_id']; ?>" class="text-xl font-bold text-white hover:underline block"><?php echo htmlspecialchars($eom_current['name']); ?></a>
                        <p class="text-white/80 text-sm"><?php echo htmlspecialchars($eom_current['department'] ?: 'No department'); ?></p>
                        <?php if(!empty($eom_current['note'])): ?>
                            <p class="text-white/70 text-xs mt-1 italic">"<?php echo htmlspecialchars($eom_current['note']); ?>"</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-user-circle text-white/30 text-5xl mb-3 block"></i>
                    <p class="text-white/70 text-sm">No employee selected for this month yet</p>
                    <p class="text-white/50 text-xs mt-1">Click "Select" to choose an employee</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════ -->
    <!-- ANALYTICS SECTION                                  -->
    <!-- ═══════════════════════════════════════════════════ -->
    <div class="mb-8 animate-fadeInUp">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h2 class="text-xl font-bold text-gray-800">Analytics</h2>
                <p class="text-xs text-gray-400 mt-0.5">Live data — last 6 months</p>
            </div>
            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-indigo-50 text-indigo-600 rounded-full text-xs font-semibold">
                <i class="fas fa-chart-line text-xs"></i> Real-time
            </span>
        </div>

        <!-- Row 1: Attendance trend + Leave distribution -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">

            <!-- Attendance Line Chart -->
            <div class="bg-white rounded-2xl shadow-xl p-5 relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-indigo-50/60 to-purple-50/30 pointer-events-none rounded-2xl"></div>
                <div class="relative z-10">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="font-semibold text-gray-800 text-sm">Attendance Trend</h3>
                            <p class="text-xs text-gray-400 mt-0.5">Monthly presence rate (%)</p>
                        </div>
                        <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-xl flex items-center justify-center shadow-md">
                            <i class="fas fa-chart-line text-white text-sm"></i>
                        </div>
                    </div>
                    <div class="h-52">
                        <canvas id="attendanceChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Leave Doughnut Chart -->
            <div class="bg-white rounded-2xl shadow-xl p-5 relative overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-br from-orange-50/40 to-rose-50/30 pointer-events-none rounded-2xl"></div>
                <div class="relative z-10">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="font-semibold text-gray-800 text-sm">Leave Distribution</h3>
                            <p class="text-xs text-gray-400 mt-0.5">By type — current year (approved)</p>
                        </div>
                        <div class="w-10 h-10 bg-gradient-to-br from-orange-500 to-rose-500 rounded-xl flex items-center justify-center shadow-md">
                            <i class="fas fa-chart-pie text-white text-sm"></i>
                        </div>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="relative h-44 w-44 flex-shrink-0 mx-auto">
                            <canvas id="leaveChart"></canvas>
                            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                <span class="text-2xl font-bold text-gray-800" id="leaveTotalDisplay">0</span>
                                <span class="text-xs text-gray-400">Total</span>
                            </div>
                        </div>
                        <div class="flex-1 space-y-2 text-xs hidden sm:block">
                            <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-blue-500 flex-shrink-0"></span><span class="text-gray-600">Annual</span><span class="ml-auto font-semibold text-gray-800"><?php echo $leave_counts[0]; ?></span></div>
                            <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-rose-500 flex-shrink-0"></span><span class="text-gray-600">Medical</span><span class="ml-auto font-semibold text-gray-800"><?php echo $leave_counts[1]; ?></span></div>
                            <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-amber-400 flex-shrink-0"></span><span class="text-gray-600">Unpaid</span><span class="ml-auto font-semibold text-gray-800"><?php echo $leave_counts[2]; ?></span></div>
                            <div class="flex items-center gap-2"><span class="w-3 h-3 rounded-full bg-violet-500 flex-shrink-0"></span><span class="text-gray-600">Emergency</span><span class="ml-auto font-semibold text-gray-800"><?php echo $leave_counts[3]; ?></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 2: Payroll Bar Chart (full width) -->
        <div class="bg-white rounded-2xl shadow-xl p-5 relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-br from-emerald-50/50 to-teal-50/30 pointer-events-none rounded-2xl"></div>
            <div class="relative z-10">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-semibold text-gray-800 text-sm">Monthly Payroll</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Net salary totals — last 6 months (RM)</p>
                    </div>
                    <div class="w-10 h-10 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-money-bill-wave text-white text-sm"></i>
                    </div>
                </div>
                <div class="h-56">
                    <canvas id="payrollChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Summary Cards Row -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl p-5 text-white shadow-xl">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-blue-100">Total Assets</p>
                    <p class="text-3xl font-bold mt-1 count-up" data-target="<?php echo $total_assets ?? 0; ?>">0</p>
                    <p class="text-xs text-blue-100 mt-1">Company inventory items</p>
                </div>
                <div class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center">
                    <i class="fas fa-boxes text-2xl"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-r from-green-600 to-emerald-600 rounded-2xl p-5 text-white shadow-xl">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-green-100">This Month's Payroll</p>
                    <p class="text-3xl font-bold mt-1">RM <?php echo number_format($total_payroll ?? 0, 2); ?></p>
                    <p class="text-xs text-green-100 mt-1">Total salary payout</p>
                </div>
                <div class="w-14 h-14 bg-white/20 rounded-2xl flex items-center justify-center">
                    <i class="fas fa-file-invoice-dollar text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Hires -->
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden mt-6 animate-slideLeft">
        <div class="bg-gradient-to-r from-gray-50 to-white px-5 py-4 border-b">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fas fa-users text-blue-500 text-xl"></i>
                    <h3 class="font-semibold text-gray-800">Recent Hires</h3>
                </div>
                <a href="employees.php" class="text-xs text-blue-600 hover:text-blue-800">View all →</a>
            </div>
        </div>

        <?php if(mysqli_num_rows($recent_employees) > 0): ?>
            <div class="divide-y divide-gray-100">
                <?php while($emp = mysqli_fetch_assoc($recent_employees)): ?>
                <div class="flex items-center gap-3 p-4 hover:bg-gray-50 transition">
                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white font-bold shadow-sm">
                        <?php echo strtoupper(substr($emp['name'], 0, 1)); ?>
                    </div>
                    <div class="flex-1">
                        <p class="font-medium text-gray-800"><?php echo $emp['name']; ?></p>
                        <p class="text-xs text-gray-500"><?php echo $emp['employee_id']; ?> • <?php echo $emp['department']; ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-400">Joined</p>
                        <p class="text-xs font-medium text-gray-600"><?php echo date('d M Y', strtotime($emp['join_date'])); ?></p>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="p-8 text-center">
                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-users text-2xl text-gray-400"></i>
                </div>
                <p class="text-gray-500 font-medium">No employees yet</p>
                <p class="text-xs text-gray-400 mt-1">Click "Add Employee" to get started</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Employee of the Month Modal -->
<div id="eomModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl">
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 p-5 rounded-t-2xl">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-bold text-white">Select Employee of the Month</h2>
                    <p class="text-xs text-indigo-100 mt-1"><?php echo date('F Y'); ?></p>
                </div>
                <button onclick="document.getElementById('eomModal').classList.add('hidden')" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Employee</label>
                <select name="eom_employee_id" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-purple-500 focus:outline-none">
                    <option value="">— Select Employee —</option>
                    <?php
                    $eom_list = mysqli_query($conn, "SELECT id, name, department FROM employees WHERE role='employee' AND status='active' ORDER BY name");
                    while($el = mysqli_fetch_assoc($eom_list)):
                        $sel = ($eom_current && $eom_current['employee_id'] == $el['id']) ? 'selected' : '';
                    ?>
                        <option value="<?php echo $el['id']; ?>" <?php echo $sel; ?>><?php echo htmlspecialchars($el['name']); ?><?php echo $el['department'] ? ' — '.$el['department'] : ''; ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Reason / Note <span class="text-gray-400 font-normal">(optional)</span></label>
                <input type="text" name="eom_note" value="<?php echo htmlspecialchars($eom_current['note'] ?? ''); ?>" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-purple-500 focus:outline-none" placeholder="e.g., Outstanding performance in client projects">
            </div>
            <button type="submit" name="set_eom" class="w-full bg-gradient-to-r from-indigo-600 to-purple-600 text-white py-3 rounded-xl font-semibold hover:shadow-lg transition">
                <i class="fas fa-trophy mr-2"></i> Confirm Selection
            </button>
        </form>
    </div>
</div>

<!-- ======================================== -->
<!-- ADD ANNOUNCEMENT MODAL -->
<!-- ======================================== -->
<div id="addAnnouncementModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl">
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 p-5 rounded-t-2xl">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-bold text-white">Create Announcement</h2>
                    <p class="text-xs text-purple-100 mt-1">Share important updates with employees</p>
                </div>
                <button onclick="document.getElementById('addAnnouncementModal').classList.add('hidden')" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        <form method="POST" class="p-5 space-y-4">
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Announcement Type</label>
                <select name="announcement_type" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-purple-500">
                    <option value="general">📢 General</option>
                    <option value="urgent">⚠️ Urgent</option>
                    <option value="holiday">🎉 Holiday</option>
                    <option value="event">🎪 Event</option>
                </select>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Title</label>
                <input type="text" name="title" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-purple-500" placeholder="e.g., Upcoming Holidays">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Message</label>
                <textarea name="message" rows="3" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-purple-500" placeholder="Enter announcement details..."></textarea>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Target Audience</label>
                <select name="target_role" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-purple-500">
                    <option value="all">👥 All Users</option>
                    <option value="employee">👤 Employees Only</option>
                    <option value="admin">👑 Admins Only</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Start Date</label>
                    <input type="date" name="start_date" value="<?php echo date('Y-m-d'); ?>" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-purple-500">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">End Date (Optional)</label>
                    <input type="date" name="end_date" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-purple-500">
                </div>
            </div>
            <button type="submit" name="add_announcement" class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 text-white py-3 rounded-xl font-semibold hover:shadow-lg transition transform hover:scale-105">
                <i class="fas fa-paper-plane mr-2"></i> Publish Announcement
            </button>
        </form>
    </div>
</div>

<!-- ======================================== -->
<!-- EDIT ANNOUNCEMENT MODAL -->
<!-- ======================================== -->
<div id="editAnnouncementModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl">
        <div class="bg-gradient-to-r from-purple-600 to-indigo-600 p-5 rounded-t-2xl">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-bold text-white">Edit Announcement</h2>
                    <p class="text-xs text-purple-100 mt-1">Update announcement details</p>
                </div>
                <button onclick="document.getElementById('editAnnouncementModal').classList.add('hidden')" class="text-white hover:text-gray-200">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>
        <form method="POST" class="p-5 space-y-4" id="editAnnouncementForm">
            <input type="hidden" name="announcement_id" id="edit_announcement_id">
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Announcement Type</label>
                <select name="announcement_type" id="edit_announcement_type" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-purple-500">
                    <option value="general">📢 General</option>
                    <option value="urgent">⚠️ Urgent</option>
                    <option value="holiday">🎉 Holiday</option>
                    <option value="event">🎪 Event</option>
                </select>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Title</label>
                <input type="text" name="title" id="edit_announcement_title" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-purple-500">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Message</label>
                <textarea name="message" id="edit_announcement_message" rows="3" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-purple-500"></textarea>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Target Audience</label>
                <select name="target_role" id="edit_announcement_target" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-purple-500">
                    <option value="all">👥 All Users</option>
                    <option value="employee">👤 Employees Only</option>
                    <option value="admin">👑 Admins Only</option>
                </select>
            </div>
            <div class="grid grid-cols-3 gap-3">
                <div class="col-span-1">
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Start Date</label>
                    <input type="date" name="start_date" id="edit_announcement_start" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-purple-500">
                </div>
                <div class="col-span-1">
                    <label class="block text-gray-700 text-sm font-semibold mb-2">End Date</label>
                    <input type="date" name="end_date" id="edit_announcement_end" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-purple-500">
                </div>
                <div class="col-span-1">
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Status</label>
                    <select name="is_active" id="edit_announcement_status" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-purple-500">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
            </div>
            <button type="submit" name="edit_announcement" class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 text-white py-3 rounded-xl font-semibold hover:shadow-lg transition transform hover:scale-105">
                <i class="fas fa-save mr-2"></i> Update Announcement
            </button>
        </form>
    </div>
</div>

<!-- Mobile Bottom Navigation -->
<div class="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-lg border-t border-gray-200 md:hidden shadow-2xl z-30">
    <div class="flex justify-around py-3">
        <a href="dashboard.php" class="flex flex-col items-center text-blue-600 relative">
            <i class="fas fa-home text-xl"></i>
            <span class="text-xs mt-1 font-semibold">Home</span>
            <div class="absolute -top-1 right-0 w-2 h-2 bg-blue-600 rounded-full"></div>
        </a>
        <a href="employees.php" class="flex flex-col items-center text-gray-500 hover:text-blue-600 transition group">
            <i class="fas fa-users text-xl group-hover:scale-110 transition"></i>
            <span class="text-xs mt-1">Staff</span>
        </a>
        <a href="manage_leave.php" class="flex flex-col items-center text-gray-500 hover:text-blue-600 transition group">
            <i class="fas fa-calendar-alt text-xl group-hover:scale-110 transition"></i>
            <span class="text-xs mt-1">Leaves</span>
            <?php if($pending_leaves > 0): ?>
                <div class="absolute -top-1 right-0 w-2 h-2 bg-red-500 rounded-full animate-pulse"></div>
            <?php endif; ?>
        </a>
        <a href="payroll.php" class="flex flex-col items-center text-gray-500 hover:text-blue-600 transition group">
            <i class="fas fa-file-invoice-dollar text-xl group-hover:scale-110 transition"></i>
            <span class="text-xs mt-1">Payroll</span>
        </a>
    </div>
</div>

<script>
// ──────────────────────────────────────────────────
// CHART DATA (from PHP)
// ──────────────────────────────────────────────────
const attendanceLabels = <?php echo json_encode($attendance_months); ?>;
const attendanceRates  = <?php echo json_encode($attendance_rates); ?>;
const leaveLabels      = <?php echo json_encode($leave_types); ?>;
const leaveCounts      = <?php echo json_encode($leave_counts); ?>;
const payrollLabels    = <?php echo json_encode($payroll_months); ?>;
const payrollTotals    = <?php echo json_encode($payroll_totals); ?>;

// ──────────────────────────────────────────────────
// 1. ATTENDANCE LINE CHART
// ──────────────────────────────────────────────────
(function () {
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    const grad = ctx.createLinearGradient(0, 0, 0, 200);
    grad.addColorStop(0, 'rgba(99, 102, 241, 0.35)');
    grad.addColorStop(1, 'rgba(99, 102, 241, 0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: attendanceLabels,
            datasets: [{
                label: 'Attendance %',
                data: attendanceRates,
                borderColor: '#6366f1',
                borderWidth: 2.5,
                pointBackgroundColor: '#6366f1',
                pointRadius: 4,
                pointHoverRadius: 6,
                fill: true,
                backgroundColor: grad,
                tension: 0.45
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e1b4b',
                    titleColor: '#a5b4fc',
                    bodyColor: '#e0e7ff',
                    padding: 10,
                    callbacks: {
                        label: ctx => ' ' + ctx.parsed.y + '%'
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#9ca3af', font: { size: 10 } },
                    border: { display: false }
                },
                y: {
                    display: true,
                    grid: { color: 'rgba(99,102,241,0.07)', drawBorder: false },
                    ticks: {
                        color: '#9ca3af',
                        font: { size: 10 },
                        callback: v => v + '%'
                    },
                    border: { display: false },
                    min: 0,
                    max: 100
                }
            }
        }
    });
})();

// ──────────────────────────────────────────────────
// 2. LEAVE DOUGHNUT CHART
// ──────────────────────────────────────────────────
(function () {
    const ctx = document.getElementById('leaveChart').getContext('2d');
    const total = leaveCounts.reduce((a, b) => a + b, 0);
    document.getElementById('leaveTotalDisplay').textContent = total;

    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: leaveLabels,
            datasets: [{
                data: leaveCounts.every(v => v === 0) ? [1, 1, 1, 1] : leaveCounts,
                backgroundColor: ['#3b82f6', '#f43f5e', '#fbbf24', '#8b5cf6'],
                hoverBackgroundColor: ['#2563eb', '#e11d48', '#f59e0b', '#7c3aed'],
                borderWidth: 3,
                borderColor: '#ffffff',
                hoverBorderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
                legend: {
                    display: true,
                    position: 'bottom',
                    labels: {
                        boxWidth: 10,
                        padding: 10,
                        color: '#6b7280',
                        font: { size: 10 }
                    }
                },
                tooltip: {
                    backgroundColor: '#1f2937',
                    titleColor: '#f9fafb',
                    bodyColor: '#d1d5db',
                    padding: 10,
                    callbacks: {
                        label: ctx => ' ' + ctx.label + ': ' + (leaveCounts.every(v => v === 0) ? 0 : ctx.parsed) + ' leave(s)'
                    }
                }
            }
        }
    });
})();

// ──────────────────────────────────────────────────
// 3. PAYROLL BAR CHART
// ──────────────────────────────────────────────────
(function () {
    const ctx = document.getElementById('payrollChart').getContext('2d');
    const gradBar = ctx.createLinearGradient(0, 0, 0, 220);
    gradBar.addColorStop(0, 'rgba(16, 185, 129, 0.9)');
    gradBar.addColorStop(1, 'rgba(5, 150, 105, 0.5)');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: payrollLabels,
            datasets: [{
                label: 'Net Salary (RM)',
                data: payrollTotals,
                backgroundColor: gradBar,
                borderRadius: 10,
                borderRadiusTopLeft: 10,
                borderRadiusTopRight: 10,
                borderSkipped: false,
                maxBarThickness: 48
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#064e3b',
                    titleColor: '#6ee7b7',
                    bodyColor: '#d1fae5',
                    padding: 10,
                    callbacks: {
                        label: ctx => ' RM ' + Number(ctx.parsed.y).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false },
                    ticks: { color: '#9ca3af', font: { size: 10 } },
                    border: { display: false }
                },
                y: {
                    grid: { color: 'rgba(16,185,129,0.08)', drawBorder: false },
                    ticks: {
                        color: '#9ca3af',
                        font: { size: 10 },
                        callback: v => 'RM ' + (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v)
                    },
                    border: { display: false }
                }
            }
        }
    });
})();
</script>

<script>
    function toggleNotif() {
        const dd = document.getElementById('notifDropdown');
        dd.classList.toggle('hidden');
    }
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        const wrapper = document.getElementById('notifWrapper');
        if (wrapper && !wrapper.contains(e.target)) {
            document.getElementById('notifDropdown').classList.add('hidden');
        }
    });
    
    function openEditAnnouncementModal(announcement) {
        document.getElementById('edit_announcement_id').value = announcement.id;
        document.getElementById('edit_announcement_type').value = announcement.announcement_type;
        document.getElementById('edit_announcement_title').value = announcement.title;
        document.getElementById('edit_announcement_message').value = announcement.message;
        document.getElementById('edit_announcement_target').value = announcement.target_role;
        document.getElementById('edit_announcement_start').value = announcement.start_date;
        document.getElementById('edit_announcement_end').value = announcement.end_date || '';
        document.getElementById('edit_announcement_status').value = announcement.is_active;
        document.getElementById('editAnnouncementModal').classList.remove('hidden');
    }
</script>
</body>
</html>