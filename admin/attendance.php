<?php
require_once '../includes/auth.php';
redirectIfNotAdmin();
require_once '../includes/db.php';

// Get date from URL or default to today
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$selected_date = $date;

// Get attendance for selected date with employee details
$attendance = mysqli_query($conn, "SELECT a.*, e.name, e.employee_id, e.department, e.nationality 
    FROM attendance a 
    RIGHT JOIN employees e ON a.employee_id = e.id AND a.date = '$date'
    WHERE e.role = 'employee'
    ORDER BY e.name");

// Calculate stats
$total_employees = mysqli_num_rows($attendance);
$present_count = 0;
$late_count = 0;
$absent_count = 0;

$attendance_data = [];
while ($row = mysqli_fetch_assoc($attendance)) {
    $attendance_data[] = $row;
    if ($row['clock_in']) {
        $present_count++;
        if ($row['status'] == 'late') {
            $late_count++;
        }
    } else {
        $absent_count++;
    }
}

// Get date details
$day_name = date('l', strtotime($date));
$formatted_date = date('d F Y', strtotime($date));

// Get week dates for navigation
$week_start = date('Y-m-d', strtotime('monday this week', strtotime($date)));
$week_dates = [];
for ($i = 0; $i < 7; $i++) {
    $week_dates[] = date('Y-m-d', strtotime("$week_start +$i days"));
}
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
        @media (max-width: 640px) {
            .attendance-table th, .attendance-table td { padding: 10px 6px; font-size: 12px; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 min-h-screen pb-20">

    <!-- Mobile Header -->
    <div class="bg-gradient-to-r from-slate-900 to-gray-800 text-white sticky top-0 z-30 shadow-xl">
        <div class="flex justify-between items-center px-4 py-3">
            <div class="flex items-center gap-2">
                <button onclick="history.back()" class="text-white text-xl mr-2 hover:scale-110 transition">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div class="w-9 h-9 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur">
                    <span class="text-white font-bold text-sm">IN</span>
                </div>
                <div>
                    <p class="text-xs text-blue-200">IPINFRA NETWORKS</p>
                    <p class="text-xs font-semibold">Attendance</p>
                </div>
            </div>
            <button onclick="toggleSidebar()" class="text-white text-2xl hover:scale-110 transition">
                <i class="fas fa-bars"></i>
            </button>
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
        
        <!-- Title Section -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-800">Attendance Report</h1>
            <p class="text-sm text-slate-500 mt-1">Monitor employee clock in/out times and attendance status</p>
        </div>

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
                <div class="flex gap-3">
                    <button onclick="goToday()" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-sm font-medium shadow-md transition">
                        <i class="fas fa-calendar-day mr-2"></i> Today
                    </button>
                    <div class="relative">
                        <i class="fas fa-calendar-alt absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                        <input type="date" id="datePicker" value="<?php echo $date; ?>" class="pl-9 pr-3 py-2 border border-slate-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="goToDate()">
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
                ?>
                <a href="?date=<?php echo $week_date; ?>" class="text-center px-5 py-2 rounded-xl transition-all <?php echo $is_selected ? 'bg-blue-600 text-white shadow-md' : ($is_today ? 'bg-slate-200 text-slate-800' : 'text-slate-600 hover:bg-slate-100'); ?>">
                    <p class="text-xs font-medium"><?php echo $week_day; ?></p>
                    <p class="text-xl font-bold"><?php echo $week_day_num; ?></p>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-2xl p-4 text-white shadow-lg stat-card">
                <div class="flex items-center justify-between mb-2">
                    <i class="fas fa-check-circle text-2xl opacity-80"></i>
                    <span class="text-xs opacity-80">Today</span>
                </div>
                <p class="text-3xl font-bold"><?php echo $present_count; ?></p>
                <p class="text-sm font-medium mt-1">Present</p>
            </div>
            <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-2xl p-4 text-white shadow-lg stat-card">
                <div class="flex items-center justify-between mb-2">
                    <i class="fas fa-hourglass-half text-2xl opacity-80"></i>
                    <span class="text-xs opacity-80">Today</span>
                </div>
                <p class="text-3xl font-bold"><?php echo $late_count; ?></p>
                <p class="text-sm font-medium mt-1">Late</p>
            </div>
            <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-2xl p-4 text-white shadow-lg stat-card">
                <div class="flex items-center justify-between mb-2">
                    <i class="fas fa-times-circle text-2xl opacity-80"></i>
                    <span class="text-xs opacity-80">Today</span>
                </div>
                <p class="text-3xl font-bold"><?php echo $absent_count; ?></p>
                <p class="text-sm font-medium mt-1">Absent</p>
            </div>
        </div>

        <!-- Attendance Table -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <div class="bg-gradient-to-r from-slate-100 to-gray-100 px-5 py-4 border-b">
                <p class="font-semibold text-slate-700">
                    <i class="fas fa-fingerprint mr-2 text-blue-600"></i> 
                    Attendance Details - <?php echo $formatted_date; ?> <span class="text-slate-500 font-normal">(<?php echo $day_name; ?>)</span>
                </p>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full attendance-table">
                    <thead>
                        <tr class="bg-slate-50 border-b">
                            <th class="p-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Employee</th>
                            <th class="p-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">ID</th>
                            <th class="p-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Clock In</th>
                            <th class="p-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Clock Out</th>
                            <th class="p-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Duration</th>
                            <th class="p-4 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($attendance_data as $row): ?>
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
                                    <span class="text-sm text-yellow-600"><i class="fas fa-spinner fa-pulse mr-1"></i> In Progress</span>
                                <?php else: ?>
                                    <span class="text-sm text-slate-400">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="p-4">
                                <?php if ($row['clock_in']): ?>
                                    <?php if ($row['status'] == 'late' || strtotime($row['clock_in']) > strtotime('10:00:00')): ?>
                                        <span class="status-badge px-3 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-700">
                                            <i class="fas fa-hourglass-half mr-1"></i> Late
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                            <i class="fas fa-check-circle mr-1"></i> Present
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="status-badge px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">
                                        <i class="fas fa-times-circle mr-1"></i> Absent
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (empty($attendance_data)): ?>
            <div class="p-12 text-center text-slate-500">
                <i class="fas fa-users text-5xl mb-3 block text-slate-300"></i>
                <p class="text-sm">No employees found</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Footer Note -->
        <div class="mt-6 text-center">
            <p class="text-xs text-slate-400">
                <i class="fas fa-clock mr-1"></i> Office hours: 9:30 AM - 6:00 PM
            </p>
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
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('overlay').classList.toggle('hidden');
        }
        
        function changeDate(days) {
            let currentDate = new Date('<?php echo $date; ?>');
            currentDate.setDate(currentDate.getDate() + days);
            let year = currentDate.getFullYear();
            let month = String(currentDate.getMonth() + 1).padStart(2, '0');
            let day = String(currentDate.getDate()).padStart(2, '0');
            window.location.href = `?date=${year}-${month}-${day}`;
        }
        
        function goToday() {
            let today = new Date();
            let year = today.getFullYear();
            let month = String(today.getMonth() + 1).padStart(2, '0');
            let day = String(today.getDate()).padStart(2, '0');
            window.location.href = `?date=${year}-${month}-${day}`;
        }
        
        function goToDate() {
            let date = document.getElementById('datePicker').value;
            window.location.href = `?date=${date}`;
        }
    </script>
</body>
</html>