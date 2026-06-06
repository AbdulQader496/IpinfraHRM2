<?php
require_once '../includes/auth.php';
redirectIfNotLoggedIn();
require_once '../includes/db.php';

$user_id = $_SESSION['user_id'];
$year  = isset($_GET['year'])  ? intval($_GET['year'])  : (int)date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : (int)date('m');

$holidays = mysqli_query($conn, "SELECT * FROM holidays WHERE YEAR(holiday_date) = $year");
$holiday_dates = [];
while ($row = mysqli_fetch_assoc($holidays)) {
    $holiday_dates[$row['holiday_date']] = $row['holiday_name'];
}

$leaves = mysqli_query($conn, "SELECT * FROM leaves WHERE employee_id = $user_id AND status = 'approved'");
$leave_dates = [];
while ($row = mysqli_fetch_assoc($leaves)) {
    $start = new DateTime($row['start_date']);
    $end = new DateTime($row['end_date']);
    $interval = DateInterval::createFromDateString('1 day');
    $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
    foreach ($period as $date) {
        $leave_dates[$date->format('Y-m-d')] = ucfirst($row['leave_type']);
    }
}

// Month navigation already resolved above
$current_month = date('F Y', strtotime("$year-$month-01"));
$prev_month = date('m', strtotime("$year-$month-01 -1 month"));
$prev_year = date('Y', strtotime("$year-$month-01 -1 month"));
$next_month = date('m', strtotime("$year-$month-01 +1 month"));
$next_year = date('Y', strtotime("$year-$month-01 +1 month"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Calendar - IPINFRA HRM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .sidebar { transition: transform 0.3s ease-in-out; }
        .calendar-day {
            aspect-ratio: 1 / 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 500;
            border-radius: 12px;
            transition: all 0.2s;
        }
        .calendar-day:hover {
            transform: scale(0.95);
            background-color: #f3f4f6;
        }
        @media (max-width: 480px) {
            .calendar-day { font-size: 12px; }
            .calendar-day span:first-child { font-size: 14px; font-weight: 600; }
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen pb-20">
    
<!-- Premium Header -->
<div class="bg-gradient-to-r from-slate-900 via-indigo-900 to-slate-900 text-white sticky top-0 z-40 shadow-2xl backdrop-blur-sm">
    <div class="flex items-center justify-between px-5 py-4">
        <div class="flex items-center gap-3">
            <!-- Menu Button -->
            <button onclick="toggleSidebar()" class="relative group">
                <div class="w-10 h-10 rounded-xl bg-white/10 backdrop-blur-sm flex items-center justify-center group-hover:bg-white/20 transition-all duration-300 group-hover:scale-105">
                    <i class="fas fa-bars text-lg"></i>
                </div>
            </button>
            
            <!-- Logo -->
            <div class="relative">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 via-indigo-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-500/20 animate-pulse">
                    <span class="text-white font-bold text-sm">IN</span>
                </div>
                <div class="absolute -top-1 -right-1 w-3 h-3 bg-green-400 rounded-full border-2 border-slate-900"></div>
            </div>
            
            <!-- Brand -->
            <div class="hidden sm:block">
                <p class="text-xs text-blue-200 font-medium tracking-wide">IPINFRA NETWORKS</p>
                <p class="text-sm font-bold tracking-tight">Employee Portal</p>
            </div>
        </div>
        
        <!-- Right side - Empty for now, can add profile/user later -->
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-indigo-500 flex items-center justify-center shadow-lg">
                <span class="text-white text-xs font-bold"><?php echo substr($_SESSION['user_name'], 0, 1); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Subtle bottom border glow -->
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
        <a href="leave.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1">
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
        <a href="management.php" class="flex items-center gap-3 py-3 px-4 rounded-xl bg-blue-800/50 mb-1">
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

<!-- Main Content -->
<div class="px-4 py-6 max-w-lg mx-auto">
    
    <!-- Header -->
    <div class="text-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">📅 Calendar</h1>
        <p class="text-sm text-gray-500 mt-1">View holidays and your approved leaves</p>
    </div>

    <!-- Month Navigation -->
    <div class="bg-white rounded-2xl shadow-xl p-4 mb-6">
        <div class="flex justify-between items-center">
            <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center hover:bg-gray-200 transition">
                <i class="fas fa-chevron-left text-gray-600"></i>
            </a>
            <h2 class="text-xl font-bold text-gray-800"><?php echo $current_month; ?></h2>
            <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center hover:bg-gray-200 transition">
                <i class="fas fa-chevron-right text-gray-600"></i>
            </a>
        </div>
    </div>

    <!-- Calendar Grid -->
    <div class="overflow-x-auto">
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden min-w-[320px]">
        <!-- Weekday Headers -->
        <div class="grid grid-cols-7 bg-gradient-to-r from-gray-50 to-gray-100 border-b">
            <?php 
            $weekdays = ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT'];
            foreach ($weekdays as $i => $day):
                $color = ($i == 0) ? 'text-red-500' : (($i == 6) ? 'text-blue-500' : 'text-gray-600');
            ?>
            <div class="py-3 text-center text-xs font-semibold <?php echo $color; ?>"><?php echo $day; ?></div>
            <?php endforeach; ?>
        </div>
        
        <!-- Calendar Days -->
        <div class="grid grid-cols-7">
            <?php
            $first_day = strtotime("$year-$month-01");
            $days_in_month = date('t', $first_day);
            $start_weekday = date('w', $first_day);
            
            for ($i = 0; $i < $start_weekday; $i++) {
                echo '<div class="calendar-day bg-gray-50/50"></div>';
            }
            
            for ($day = 1; $day <= $days_in_month; $day++) {
                $date = sprintf("%04d-%02d-%02d", $year, $month, $day);
                $bg_color = '';
                $badge = '';
                $badge_color = '';
                $tooltip = '';
                
                if (isset($holiday_dates[$date])) {
                    $bg_color = 'bg-red-50';
                    $badge = 'H';
                    $badge_color = 'bg-red-500';
                    $tooltip = $holiday_dates[$date];
                } elseif (isset($leave_dates[$date])) {
                    $bg_color = 'bg-green-50';
                    $badge = 'L';
                    $badge_color = 'bg-green-500';
                    $tooltip = $leave_dates[$date] . ' Leave';
                }
                
                $weekday = date('w', strtotime($date));
                $text_color = ($weekday == 0) ? 'text-red-500' : (($weekday == 6) ? 'text-blue-500' : 'text-gray-700');
                
                echo "<div class='calendar-day $bg_color p-2 text-center relative' title='$tooltip'>";
                echo "<span class='$text_color font-semibold'>$day</span>";
                if ($badge) {
                    echo "<span class='absolute top-1 right-1 w-4 h-4 $badge_color text-white text-[8px] rounded-full flex items-center justify-center font-bold'>$badge</span>";
                }
                echo "</div>";
            }
            
            // Fill remaining cells
            $total_cells = $start_weekday + $days_in_month;
            $remaining = 42 - $total_cells;
            for ($i = 0; $i < $remaining; $i++) {
                echo '<div class="calendar-day bg-gray-50/50"></div>';
            }
            ?>
        </div>
    </div>
    </div><!-- /overflow-x-auto -->

    <!-- Legend -->
    <div class="flex justify-center gap-6 mt-6">
        <div class="flex items-center gap-2">
            <div class="w-3 h-3 bg-red-500 rounded-full"></div>
            <span class="text-xs text-gray-600">Public Holiday</span>
        </div>
        <div class="flex items-center gap-2">
            <div class="w-3 h-3 bg-green-500 rounded-full"></div>
            <span class="text-xs text-gray-600">Approved Leave</span>
        </div>
    </div>
</div>

<!-- Mobile Bottom Navigation -->
<div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 md:hidden shadow-lg z-20">
    <div class="flex justify-around py-2">
        <a href="dashboard.php" class="flex flex-col items-center py-1 px-3 text-gray-500">
            <i class="fas fa-home text-xl"></i>
            <span class="text-xs mt-1">Home</span>
        </a>
        <a href="clock.php" class="flex flex-col items-center py-1 px-3 text-gray-500">
            <i class="fas fa-clock text-xl"></i>
            <span class="text-xs mt-1">Clock</span>
        </a>
        <a href="calendar.php" class="flex flex-col items-center py-1 px-3 text-blue-600">
            <i class="fas fa-calendar-alt text-xl"></i>
            <span class="text-xs mt-1">Calendar</span>
        </a>
        <a href="profile.php" class="flex flex-col items-center py-1 px-3 text-gray-500">
            <i class="fas fa-user text-xl"></i>
            <span class="text-xs mt-1">Profile</span>
        </a>
    </div>
</div>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('-translate-x-full');
    document.getElementById('overlay').classList.toggle('hidden');
}
</script>

</body>
</html>