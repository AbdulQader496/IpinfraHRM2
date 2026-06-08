<?php
require_once '../includes/auth.php';
redirectIfNotAdmin();
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/toast_fn.php';

// ========================================
// MANUAL ATTENDANCE ENTRY
// ========================================
if (isset($_POST['add_manual_attendance'])) {
    $employee_id = intval($_POST['manual_employee_id']);
    $attendance_date = mysqli_real_escape_string($conn, $_POST['attendance_date']);
    $clock_in = mysqli_real_escape_string($conn, $_POST['clock_in']);
    $clock_out = mysqli_real_escape_string($conn, $_POST['clock_out']);
    
    $check_query = mysqli_query($conn, "SELECT id FROM attendance WHERE employee_id = $employee_id AND date = '$attendance_date'");
    
    $status = 'present';
    if (!empty($clock_in) && strtotime($clock_in) > strtotime('10:00:00')) {
        $status = 'late';
    }
    
    if (mysqli_num_rows($check_query) > 0) {
        $update_query = "UPDATE attendance SET 
                         clock_in = " . ($clock_in ? "'$clock_in'" : "NULL") . ",
                         clock_out = " . ($clock_out ? "'$clock_out'" : "NULL") . ",
                         status = '$status'
                         WHERE employee_id = $employee_id AND date = '$attendance_date'";
        mysqli_query($conn, $update_query);
        $manual_message = '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded-xl text-sm animate-fadeIn">
                            <i class="fas fa-check-circle mr-2"></i> Attendance updated successfully!
                           </div>';
    } else {
        $insert_query = "INSERT INTO attendance (employee_id, date, clock_in, clock_out, status) 
                         VALUES ($employee_id, '$attendance_date', " . ($clock_in ? "'$clock_in'" : "NULL") . ", " . ($clock_out ? "'$clock_out'" : "NULL") . ", '$status')";
        mysqli_query($conn, $insert_query);
        $manual_message = '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded-xl text-sm animate-fadeIn">
                            <i class="fas fa-check-circle mr-2"></i> Attendance recorded successfully!
                           </div>';
    }
}

// ========================================
// DELETE ATTENDANCE RECORD
// ========================================
if (isset($_GET['delete_attendance'])) {
    $attendance_id = intval($_GET['delete_attendance']);
    $safe_date = preg_replace('/[^0-9\-]/', '', $_GET['date'] ?? date('Y-m-d'));
    mysqli_query($conn, "DELETE FROM attendance WHERE id = $attendance_id");
    header("Location: attendance.php?date=" . $safe_date);
    exit();
}

// ========================================
// EDIT ATTENDANCE
// ========================================
if (isset($_POST['edit_attendance'])) {
    $attendance_id = intval($_POST['attendance_id']);
    $clock_in = mysqli_real_escape_string($conn, $_POST['clock_in']);
    $clock_out = mysqli_real_escape_string($conn, $_POST['clock_out']);
    
    $status = 'present';
    if (!empty($clock_in) && strtotime($clock_in) > strtotime('10:00:00')) {
        $status = 'late';
    }
    
    $update_query = "UPDATE attendance SET 
                     clock_in = " . ($clock_in ? "'$clock_in'" : "NULL") . ",
                     clock_out = " . ($clock_out ? "'$clock_out'" : "NULL") . ",
                     status = '$status'
                     WHERE id = $attendance_id";
    $safe_date = preg_replace('/[^0-9\-]/', '', $_GET['date'] ?? date('Y-m-d'));
    mysqli_query($conn, $update_query);
    header("Location: attendance.php?date=" . $safe_date);
    exit();
}

// ========================================
// CSV EXPORT
// ========================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $export_month = isset($_GET['month']) ? preg_replace('/[^0-9\-]/', '', $_GET['month']) : null;

    if ($export_month && preg_match('/^\d{4}-\d{2}$/', $export_month)) {
        // Month export: all employees, all days in the month
        $month_start = $export_month . '-01';
        $month_end   = date('Y-m-t', strtotime($month_start));
        $filename    = 'attendance_' . $export_month . '.csv';

        $export_query = "SELECT e.employee_id, e.name, e.department,
                                a.date, a.clock_in, a.clock_out, a.status
                         FROM employees e
                         LEFT JOIN attendance a ON a.employee_id = e.id
                             AND a.date BETWEEN '$month_start' AND '$month_end'
                         WHERE e.role = 'employee'
                         ORDER BY e.name, a.date";
    } else {
        // Single-date export (current date or ?date=)
        $export_date = isset($_GET['date']) ? preg_replace('/[^0-9\-]/', '', $_GET['date']) : date('Y-m-d');
        $filename    = 'attendance_' . $export_date . '.csv';

        $export_query = "SELECT e.employee_id, e.name, e.department,
                                a.date, a.clock_in, a.clock_out, a.status
                         FROM employees e
                         LEFT JOIN attendance a ON a.employee_id = e.id
                             AND a.date = '$export_date'
                         WHERE e.role = 'employee'
                         ORDER BY e.name";
    }

    $export_result = mysqli_query($conn, $export_query);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['Employee ID', 'Name', 'Department', 'Date', 'Clock In', 'Clock Out', 'Hours Worked', 'Status']);

    while ($row = mysqli_fetch_assoc($export_result)) {
        $hours_worked = '';
        if (!empty($row['clock_in']) && !empty($row['clock_out'])) {
            $diff = strtotime($row['clock_out']) - strtotime($row['clock_in']);
            if ($diff > 0) {
                $h = floor($diff / 3600);
                $m = floor(($diff % 3600) / 60);
                $hours_worked = sprintf('%d:%02d', $h, $m);
            }
        }

        fputcsv($out, [
            $row['employee_id'],
            $row['name'],
            $row['department'] ?? '',
            $row['date'] ?? '',
            $row['clock_in'] ?? '',
            $row['clock_out'] ?? '',
            $hours_worked,
            $row['status'] ?? 'absent',
        ]);
    }

    fclose($out);
    exit();
}

// ========================================
// PAGINATION WITH LOAD MORE
// ========================================
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10; // 10 employees per page

$selected_date = $date;
$current_day_name = date('l', strtotime($date));
$is_weekend = ($current_day_name == 'Saturday' || $current_day_name == 'Sunday');

// Build WHERE clause
$employee_where = "WHERE e.role = 'employee'";
if (!empty($search)) {
    $employee_where .= " AND (e.name LIKE '%$search%' OR e.employee_id LIKE '%$search%')";
}

// Get total count
$count_query = "SELECT COUNT(*) as total FROM employees e $employee_where";
$count_result = mysqli_query($conn, $count_query);
$total_rows = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_rows / $per_page);
$offset = ($page - 1) * $per_page;

// Get employees with attendance for selected date
$attendance = mysqli_query($conn, "SELECT a.*, e.id as emp_id, e.name, e.employee_id, e.department, e.nationality 
    FROM employees e 
    LEFT JOIN attendance a ON a.employee_id = e.id AND a.date = '$date'
    $employee_where
    ORDER BY e.name 
    LIMIT $offset, $per_page");

// Calculate stats
if ($is_weekend) {
    $completed_count = 0;
    $in_progress_count = 0;
    $late_count = 0;
    $absent_count = 0;
    $weekend_count = $total_rows;
} else {
    $stats_query = mysqli_query($conn, "SELECT 
        COUNT(CASE WHEN a.clock_in IS NOT NULL AND a.clock_out IS NOT NULL THEN 1 END) as completed,
        COUNT(CASE WHEN a.clock_in IS NOT NULL AND a.clock_out IS NULL THEN 1 END) as in_progress,
        COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late,
        COUNT(CASE WHEN a.clock_in IS NULL THEN 1 END) as absent
        FROM employees e 
        LEFT JOIN attendance a ON a.employee_id = e.id AND a.date = '$date'
        WHERE e.role = 'employee'");
    $stats = mysqli_fetch_assoc($stats_query);
    
    $completed_count = $stats['completed'] ?? 0;
    $in_progress_count = $stats['in_progress'] ?? 0;
    $late_count = $stats['late'] ?? 0;
    $absent_count = $stats['absent'] ?? 0;
    $weekend_count = 0;
}

// Get date details
$day_name = date('l', strtotime($date));
$formatted_date = date('d F Y', strtotime($date));

// Get week dates
$week_start = date('Y-m-d', strtotime('monday this week', strtotime($date)));
$week_dates = [];
for ($i = 0; $i < 7; $i++) {
    $week_dates[] = date('Y-m-d', strtotime("$week_start +$i days"));
}

// Store attendance data
$attendance_data = [];
while ($row = mysqli_fetch_assoc($attendance)) {
    $attendance_data[] = $row;
}

// Get all employees for dropdown
$all_employees = mysqli_query($conn, "SELECT id, name, employee_id FROM employees WHERE role='employee' AND status='active' ORDER BY name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Attendance - IPINFRA HRM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .sidebar { transition: transform 0.3s ease-in-out; }
        .status-badge { transition: all 0.2s; }
        .stat-card { transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-2px); }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fadeIn { animation: fadeIn 0.3s ease-out; }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .loading-spinner {
            animation: spin 1s linear infinite;
        }
        @media (max-width: 640px) {
            .attendance-table th, .attendance-table td { padding: 10px 6px; font-size: 12px; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 min-h-screen pb-20">
<?php require_once '../includes/global_ui.php'; ?>
<?php require_once '../includes/toast.php'; ?>
<?php require_once '../includes/confirm_modal.php'; ?>

<!-- Premium Mobile Header -->
<div class="bg-[#060912] text-white sticky top-0 z-40 shadow-2xl">
    <div class="flex justify-between items-center px-4 py-4">
        <div class="flex items-center gap-3">
            <button onclick="toggleSidebar()" class="text-white/80 hover:text-white p-2 rounded-full hover:bg-white/10">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg">
                <img src="../uploads/1775551018_4xzREYTcMvK7ReGODviudjeDBIofOQ78mr5DsN9g.jpg" alt="IPINFRA" style="width:28px;height:28px;object-fit:contain;border-radius:4px;background:#fff;">
            </div>
            <div>
                <p class="text-xs text-blue-200 font-medium">IPINFRA NETWORKS</p>
                <p class="text-sm font-bold tracking-wide">Admin Portal</p>
            </div>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="history.back()" class="text-white/80 hover:text-white p-2 rounded-full hover:bg-white/10">
                <i class="fas fa-arrow-left text-lg"></i>
            </button>
        </div>
    </div>
</div>

<?php require_once '../includes/admin_sidebar.php'; ?>

<!-- Main Content -->
<div class="px-4 py-6 pb-24 max-w-7xl mx-auto">
    
    <!-- Title Section -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-800">Attendance Report</h1>
        <p class="text-sm text-slate-500 mt-1">Monitor employee clock in/out times and attendance status</p>
    </div>

    <!-- Weekend Notice -->
    <?php if($is_weekend): ?>
    <div class="bg-purple-100 border-l-4 border-purple-500 rounded-xl p-4 mb-4">
        <div class="flex items-center gap-3">
            <i class="fas fa-calendar-week text-purple-600 text-xl"></i>
            <div>
                <p class="font-semibold text-purple-800">Weekend - <?php echo $current_day_name; ?></p>
                <p class="text-sm text-purple-700">No attendance required on weekends. All employees are marked as Weekend.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Date Navigation -->
    <div class="bg-white/80 backdrop-blur rounded-2xl shadow-lg p-5 mb-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <button onclick="changeDate(-1)" class="w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center hover:bg-slate-200 transition shadow-sm">
                    <i class="fas fa-chevron-left text-slate-600"></i>
                </button>
                <div class="text-center min-w-[180px]">
                    <p class="text-xl font-bold text-slate-800"><?php echo $day_name; ?></p>
                    <p class="text-sm text-slate-500"><?php echo $formatted_date; ?></p>
                </div>
                <button onclick="changeDate(1)" class="w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center hover:bg-slate-200 transition shadow-sm">
                    <i class="fas fa-chevron-right text-slate-600"></i>
                </button>
            </div>
            <div class="flex flex-wrap gap-3 items-center">
                <button onclick="goToday()" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-medium shadow-md transition">
                    <i class="fas fa-calendar-day mr-2"></i> Today
                </button>
                <div class="relative">
                    <i class="fas fa-calendar-alt absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                    <input type="date" id="datePicker" value="<?php echo $date; ?>" class="pl-9 pr-3 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="goToDate()">
                </div>
                <!-- CSV Export Controls -->
                <div class="flex items-center gap-2">
                    <a href="?export=csv&date=<?php echo urlencode($date); ?>" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-medium shadow-md transition flex items-center gap-2">
                        <i class="fas fa-file-csv"></i> Export Day
                    </a>
                    <div class="flex items-center gap-1">
                        <input type="month" id="exportMonth" value="<?php echo date('Y-m', strtotime($date)); ?>" class="px-3 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500">
                        <button onclick="exportMonthCSV()" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-sm font-medium shadow-md transition flex items-center gap-2">
                            <i class="fas fa-file-export"></i> Export Month
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Week View -->
    <div class="bg-white/80 backdrop-blur rounded-2xl shadow-lg p-4 mb-6 overflow-x-auto">
        <div class="flex min-w-max gap-2">
            <?php foreach ($week_dates as $i => $week_date): 
                $week_day = date('D', strtotime($week_date));
                $week_day_num = date('d', strtotime($week_date));
                $is_today = ($week_date == date('Y-m-d'));
                $is_selected = ($week_date == $date);
                $is_weekend_day = (date('l', strtotime($week_date)) == 'Saturday' || date('l', strtotime($week_date)) == 'Sunday');
                $weekend_class = $is_weekend_day ? 'text-purple-600' : '';
            ?>
            <a href="?date=<?php echo $week_date; ?>" class="text-center px-5 py-2 rounded-xl transition-all <?php echo $is_selected ? 'bg-blue-600 text-white shadow-md' : ($is_today ? 'bg-slate-200 text-slate-800' : 'text-slate-600 hover:bg-slate-100'); ?>">
                <p class="text-xs font-medium <?php echo $weekend_class; ?>"><?php echo $week_day; ?></p>
                <p class="text-xl font-bold"><?php echo $week_day_num; ?></p>
                <?php if($is_weekend_day): ?>
                    <p class="text-xs text-purple-500 mt-0.5">Weekend</p>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Stats Cards -->
    <?php if($is_weekend): ?>
    <div class="grid grid-cols-1 gap-3 mb-6">
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-2xl p-4 text-white shadow-lg stat-card text-center">
            <i class="fas fa-calendar-week text-2xl opacity-80"></i>
            <p class="text-3xl font-bold"><?php echo $weekend_count; ?></p>
            <p class="text-sm font-medium mt-1">Weekend (No Attendance)</p>
        </div>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-4 gap-3 mb-6">
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-2xl p-3 text-white shadow-lg stat-card text-center">
            <i class="fas fa-check-circle text-xl opacity-80"></i>
            <p class="text-2xl font-bold"><?php echo $completed_count; ?></p>
            <p class="text-xs">Completed</p>
        </div>
        <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-2xl p-3 text-white shadow-lg stat-card text-center">
            <i class="fas fa-hourglass-half text-xl opacity-80"></i>
            <p class="text-2xl font-bold"><?php echo $in_progress_count; ?></p>
            <p class="text-xs">In Progress</p>
        </div>
        <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-2xl p-3 text-white shadow-lg stat-card text-center">
            <i class="fas fa-clock text-xl opacity-80"></i>
            <p class="text-2xl font-bold"><?php echo $late_count; ?></p>
            <p class="text-xs">Late</p>
        </div>
        <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-2xl p-3 text-white shadow-lg stat-card text-center">
            <i class="fas fa-times-circle text-xl opacity-80"></i>
            <p class="text-2xl font-bold"><?php echo $absent_count; ?></p>
            <p class="text-xs">Absent</p>
        </div>
    </div>
    <?php endif; ?>

    <!-- Search Bar -->
    <div class="bg-white rounded-xl shadow-md p-4 mb-4">
        <form method="GET" class="flex flex-col md:flex-row gap-3" id="searchForm">
            <input type="hidden" name="date" value="<?php echo $date; ?>">
            <div class="flex-1 relative">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                <input type="text" name="search" id="searchInput" value="<?php echo htmlspecialchars($search); ?>" 
                       placeholder="Search by employee name or ID..." 
                       class="w-full pl-9 pr-3 py-2 border border-gray-200 rounded-lg text-sm">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm hover:bg-blue-700">Filter</button>
                <a href="?date=<?php echo $date; ?>" class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm text-center hover:bg-gray-300">Reset</a>
            </div>
        </form>
    </div>

    <!-- Results Summary -->
    <div class="flex justify-between items-center mb-4">
        <p class="text-sm text-gray-500">
            Showing <?php echo count($attendance_data); ?> of <?php echo $total_rows; ?> employees
        </p>
    </div>

    <!-- Attendance Table -->
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-gradient-to-r from-slate-100 to-gray-100 px-5 py-4 border-b">
            <p class="font-semibold text-slate-700">
                <i class="fas fa-fingerprint mr-2 text-blue-600"></i> 
                Attendance Details - <?php echo $formatted_date; ?> <span class="text-slate-500 font-normal">(<?php echo $day_name; ?>)</span>
                <?php if($is_weekend): ?>
                    <span class="ml-2 text-xs bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full">Weekend</span>
                <?php endif; ?>
            </p>
        </div>
        
        <div class="overflow-x-auto">
            <table class="w-full attendance-table">
                <thead>
                    <tr class="bg-slate-50 border-b">
                        <th class="p-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Employee</th>
                        <th class="p-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">ID</th>
                        <?php if(!$is_weekend): ?>
                        <th class="p-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Clock In</th>
                        <th class="p-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Clock Out</th>
                        <th class="p-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Duration</th>
                        <?php endif; ?>
                        <th class="p-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                        <th class="p-4 text-center text-xs font-semibold text-slate-500 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody id="attendanceTableBody">
                    <?php if(count($attendance_data) > 0): ?>
                        <?php foreach ($attendance_data as $row): 
                            $row_status = 'absent';
                            $attendance_id = $row['id'] ?? null;
                            
                            if ($is_weekend) {
                                $row_status = 'weekend';
                            } elseif ($row['clock_in'] && $row['clock_out']) {
                                $row_status = 'completed';
                            } elseif ($row['clock_in'] && !$row['clock_out']) {
                                $row_status = 'in_progress';
                            } elseif ($row['status'] == 'late') {
                                $row_status = 'late';
                            }
                        ?>
                        <tr class="hover:bg-slate-50 transition">
                            <td class="p-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center">
                                        <i class="fas fa-user text-blue-600 text-sm"></i>
                                    </div>
                                    <span class="font-medium text-slate-800 text-sm"><?php echo $row['name']; ?></span>
                                </div>
                            </td>
                            <td class="p-4 text-sm text-slate-500 font-mono"><?php echo $row['employee_id']; ?></td>
                            
                            <?php if(!$is_weekend): ?>
                            <td class="p-4">
                                <?php if ($row['clock_in']): ?>
                                    <div class="flex flex-col">
                                        <span class="text-sm font-semibold <?php echo (strtotime($row['clock_in']) > strtotime('10:00:00')) ? 'text-orange-600' : 'text-green-600'; ?>">
                                            <i class="fas fa-sign-in-alt mr-1 text-xs"></i> <?php echo date('h:i A', strtotime($row['clock_in'])); ?>
                                        </span>
                                        <?php if (strtotime($row['clock_in']) > strtotime('10:00:00')): ?>
                                            <span class="text-xs text-orange-500 mt-0.5">(Late)</span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-sm text-slate-400">-- : --</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <?php if ($row['clock_out']): ?>
                                    <span class="text-sm font-medium text-red-600">
                                        <i class="fas fa-sign-out-alt mr-1 text-xs"></i> <?php echo date('h:i A', strtotime($row['clock_out'])); ?>
                                    </span>
                                <?php elseif ($row['clock_in'] && !$row['clock_out']): ?>
                                    <span class="text-sm text-orange-500">
                                        <i class="fas fa-hourglass-end mr-1"></i> Not Clocked Out
                                    </span>
                                <?php else: ?>
                                    <span class="text-sm text-slate-400">-- : --</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <?php if ($row['clock_in'] && $row['clock_out']): 
                                    $start = new DateTime($row['clock_in']);
                                    $end = new DateTime($row['clock_out']);
                                    $diff = $start->diff($end);
                                    $hours = $diff->h;
                                    $minutes = $diff->i;
                                ?>
                                    <span class="text-sm font-medium text-slate-700"><?php echo $hours; ?>h <?php echo $minutes; ?>m</span>
                                <?php elseif ($row['clock_in'] && !$row['clock_out']): ?>
                                    <span class="text-sm text-orange-600">
                                        <i class="fas fa-spinner fa-pulse mr-1"></i> In Progress
                                    </span>
                                <?php else: ?>
                                    <span class="text-sm text-slate-400">-</span>
                                <?php endif; ?>
                            </td>
                            <?php endif; ?>
                            
                            <td class="p-4">
                                <?php if($is_weekend): ?>
                                    <span class="status-badge px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-700">
                                        <i class="fas fa-calendar-week mr-1"></i> Weekend
                                    </span>
                                <?php elseif ($row['clock_in'] && $row['clock_out']): ?>
                                    <span class="status-badge px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                        <i class="fas fa-check-circle mr-1"></i> Completed
                                    </span>
                                <?php elseif ($row['clock_in'] && !$row['clock_out']): ?>
                                    <span class="status-badge px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">
                                        <i class="fas fa-hourglass-half mr-1"></i> In Progress
                                    </span>
                                <?php elseif ($row['status'] == 'late'): ?>
                                    <span class="status-badge px-3 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-700">
                                        <i class="fas fa-clock mr-1"></i> Late
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                        <i class="fas fa-times-circle mr-1"></i> Absent
                                    </span>
                                <?php endif; ?>
                            </td>
                            
                            <td class="p-4 text-center">
                                <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>, '<?php echo $date; ?>')" 
                                        class="text-blue-600 hover:text-blue-800 transition" title="Edit Attendance">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if($attendance_id): ?>
                                <a href="?delete_attendance=<?php echo $attendance_id; ?>&date=<?php echo $date; ?>" 
                                   data-confirm="Delete this attendance record?" data-confirm-title="Delete Record"
                                   class="text-red-500 hover:text-red-700 transition ml-2" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                             </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo $is_weekend ? '4' : '7'; ?>" class="p-12 text-center text-slate-500">
                                <i class="fas fa-users text-5xl mb-3 block text-slate-300"></i>
                                <p class="text-sm">No employees found</p>
                              </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Load More Button -->
    <?php if($page < $total_pages): ?>
    <div class="mt-6 text-center">
        <button id="loadMoreBtn" onclick="loadMore()" class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-8 py-3 rounded-xl font-semibold hover:shadow-xl transition transform hover:scale-105 flex items-center justify-center gap-2 mx-auto">
            <i class="fas fa-spinner hidden" id="loadMoreSpinner"></i>
            <i class="fas fa-arrow-down"></i>
            <span>Load More Employees</span>
        </button>
        <p class="text-xs text-gray-400 mt-2">Showing <?php echo $page * $per_page; ?> of <?php echo $total_rows; ?> employees</p>
    </div>
    <?php endif; ?>

    <!-- Footer Note -->
    <div class="mt-6 text-center">
        <p class="text-xs text-slate-400">
            <i class="fas fa-clock mr-1"></i> Office hours: 9:30 AM - 6:00 PM (Late after 10:00 AM)
        </p>
    </div>

    <!-- Manual Attendance Entry Form (Bottom) -->
    <div class="bg-white rounded-2xl shadow-xl p-6 mt-8">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 bg-gradient-to-br from-green-500 to-emerald-600 rounded-xl flex items-center justify-center shadow-md">
                <i class="fas fa-pen-alt text-white"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-gray-800">Manual Attendance Entry</h2>
                <p class="text-xs text-gray-500">Record attendance for employees who forgot to clock in/out</p>
            </div>
        </div>
        
        <?php if(isset($manual_message)) echo $manual_message; ?>
        
        <form method="POST" action="" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="lg:col-span-2">
                <label class="block text-gray-700 text-sm font-semibold mb-2">Select Employee</label>
                <select name="manual_employee_id" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                    <option value="">-- Select Employee --</option>
                    <?php 
                    mysqli_data_seek($all_employees, 0);
                    while($emp = mysqli_fetch_assoc($all_employees)):
                    ?>
                        <option value="<?php echo $emp['id']; ?>"><?php echo $emp['name']; ?> (<?php echo $emp['employee_id']; ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Date</label>
                <input type="date" name="attendance_date" value="<?php echo $date; ?>" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Clock In Time</label>
                <input type="time" name="clock_in" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                <p class="text-xs text-gray-400 mt-1">Format: 09:00 (24-hour)</p>
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Clock Out Time</label>
                <input type="time" name="clock_out" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                <p class="text-xs text-gray-400 mt-1">Format: 18:00 (24-hour)</p>
            </div>
            
            <div class="lg:col-span-5">
                <button type="submit" name="add_manual_attendance" class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white py-2.5 rounded-xl font-semibold hover:shadow-lg transition transform hover:scale-105">
                    <i class="fas fa-save mr-2"></i> Save Attendance
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Attendance Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl">
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-5 rounded-t-2xl">
            <div class="flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-bold text-white">Edit Attendance</h2>
                    <p class="text-xs text-blue-100 mt-1" id="editEmployeeName"></p>
                </div>
                <button onclick="closeEditModal()" class="w-10 h-10 rounded-full bg-white/20 hover:bg-white/30 transition-all flex items-center justify-center">
                    <i class="fas fa-times text-white text-xl"></i>
                </button>
            </div>
        </div>
        <form method="POST" class="p-6 space-y-4" action="">
            <input type="hidden" name="attendance_id" id="editAttendanceId">
            <input type="hidden" name="date" value="<?php echo $date; ?>">
            
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Clock In Time</label>
                <input type="time" name="clock_in" id="editClockIn" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none">
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Clock Out Time</label>
                <input type="time" name="clock_out" id="editClockOut" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none">
            </div>
            
            <div class="flex gap-3 pt-3">
                <button type="submit" name="edit_attendance" class="flex-1 bg-blue-600 text-white py-2.5 rounded-xl font-semibold hover:bg-blue-700 transition">
                    Update Attendance
                </button>
                <button type="button" onclick="closeEditModal()" class="flex-1 bg-gray-200 text-gray-700 py-2.5 rounded-xl font-semibold hover:bg-gray-300 transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Mobile Bottom Navigation -->
<div class="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur border-t border-slate-200 md:hidden shadow-lg z-20">
    <div class="flex justify-around py-2">
        <a href="dashboard.php" class="flex flex-col items-center py-1 px-3 text-slate-500 hover:text-blue-600 transition">
            <i class="fas fa-home text-xl"></i>
            <span class="text-xs mt-1">Home</span>
        </a>
        <a href="employees.php" class="flex flex-col items-center py-1 px-3 text-slate-500 hover:text-blue-600 transition">
            <i class="fas fa-users text-xl"></i>
            <span class="text-xs mt-1">Staff</span>
        </a>
        <a href="attendance.php" class="flex flex-col items-center py-1 px-3 text-blue-600">
            <i class="fas fa-fingerprint text-xl"></i>
            <span class="text-xs mt-1">Attendance</span>
        </a>
        <a href="payroll.php" class="flex flex-col items-center py-1 px-3 text-slate-500 hover:text-blue-600 transition">
            <i class="fas fa-file-invoice-dollar text-xl"></i>
            <span class="text-xs mt-1">Payroll</span>
        </a>
    </div>
</div>

<script>
    let currentPage = <?php echo $page; ?>;
    const totalPages = <?php echo $total_pages; ?>;
    const currentDate = '<?php echo $date; ?>';
    const currentSearch = '<?php echo addslashes($search); ?>';
    
    function changeDate(days) {
        let currentDate = new Date('<?php echo $date; ?>');
        currentDate.setDate(currentDate.getDate() + days);
        let year = currentDate.getFullYear();
        let month = String(currentDate.getMonth() + 1).padStart(2, '0');
        let day = String(currentDate.getDate()).padStart(2, '0');
        window.location.href = `?date=${year}-${month}-${day}&search=${encodeURIComponent(currentSearch)}`;
    }
    
    function goToday() {
        let today = new Date();
        let year = today.getFullYear();
        let month = String(today.getMonth() + 1).padStart(2, '0');
        let day = String(today.getDate()).padStart(2, '0');
        window.location.href = `?date=${year}-${month}-${day}&search=${encodeURIComponent(currentSearch)}`;
    }
    
    function goToDate() {
        let date = document.getElementById('datePicker').value;
        window.location.href = `?date=${date}&search=${encodeURIComponent(currentSearch)}`;
    }

    function exportMonthCSV() {
        let month = document.getElementById('exportMonth').value;
        if (!month) { alert('Please select a month first.'); return; }
        window.location.href = `?export=csv&month=${encodeURIComponent(month)}`;
    }
    
    function loadMore() {
        const nextPage = currentPage + 1;
        const spinner = document.getElementById('loadMoreSpinner');
        const btnText = document.querySelector('#loadMoreBtn span:not(.hidden)');
        const btn = document.getElementById('loadMoreBtn');
        
        // Show loading state
        spinner.classList.remove('hidden');
        btn.disabled = true;
        
        fetch(`load_more_attendance.php?page=${nextPage}&date=${currentDate}&search=${encodeURIComponent(currentSearch)}`)
            .then(response => response.text())
            .then(data => {
                // Append new rows to table
                document.getElementById('attendanceTableBody').insertAdjacentHTML('beforeend', data);
                currentPage = nextPage;
                
                // Hide load more button if reached last page
                if (currentPage >= totalPages) {
                    btn.style.display = 'none';
                }
                
                // Update the showing count
                const showingCount = document.querySelector('.text-sm.text-gray-500');
                if (showingCount) {
                    const newCount = Math.min(currentPage * 10, <?php echo $total_rows; ?>);
                    showingCount.innerHTML = `Showing ${newCount} of <?php echo $total_rows; ?> employees`;
                }
                
                spinner.classList.add('hidden');
                btn.disabled = false;
            })
            .catch(error => {
                console.error('Error:', error);
                spinner.classList.add('hidden');
                btn.disabled = false;
            });
    }
    
    function openEditModal(employee, date) {
        document.getElementById('editAttendanceId').value = employee.id;
        document.getElementById('editEmployeeName').innerHTML = employee.name + ' (' + employee.employee_id + ')';
        
        if (employee.clock_in) {
            document.getElementById('editClockIn').value = employee.clock_in.substring(0, 5);
        } else {
            document.getElementById('editClockIn').value = '';
        }
        
        if (employee.clock_out) {
            document.getElementById('editClockOut').value = employee.clock_out.substring(0, 5);
        } else {
            document.getElementById('editClockOut').value = '';
        }
        
        document.getElementById('editModal').classList.remove('hidden');
    }
    
    function closeEditModal() {
        document.getElementById('editModal').classList.add('hidden');
    }
</script>
</body>
</html>
