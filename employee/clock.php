<?php
require_once '../includes/auth.php';
redirectIfNotLoggedIn();
require_once '../includes/db.php';

$user_id = $_SESSION['user_id'];
$today = date('Y-m-d');
$current_time = date('H:i:s');
$current_day = date('l');
$current_hour = date('H');

// ========================================
// WEEKEND CHECK
// ========================================
$is_weekend = ($current_day == 'Saturday' || $current_day == 'Sunday');

// ========================================
// OFFICE HOURS & LATE DETECTION
// ========================================
$grace_period_end = '10:00:00';
$is_late = ($current_time > $grace_period_end);
$auto_clockout_time = '21:00:00'; // 9:00 PM

// Get today's attendance
$query = "SELECT * FROM attendance WHERE employee_id = $user_id AND date = '$today'";
$result = mysqli_query($conn, $query);
$attendance = mysqli_fetch_assoc($result);

// ========================================
// CLOCK IN (Only if not weekend)
// ========================================
if (isset($_POST['clock_in']) && !$attendance) {
    if ($is_weekend) {
        $error = "Cannot clock in on weekends (Saturday & Sunday)!";
    } else {
        $status = $is_late ? 'late' : 'present';
        mysqli_query($conn, "INSERT INTO attendance (employee_id, date, clock_in, status) VALUES ($user_id, '$today', '$current_time', '$status')");
        header('Location: clock.php');
        exit();
    }
}

// ========================================
// CLOCK OUT (Only if not weekend)
// ========================================
if (isset($_POST['clock_out']) && $attendance && !$attendance['clock_out']) {
    if ($is_weekend) {
        $error = "Cannot clock out on weekends!";
    } else {
        mysqli_query($conn, "UPDATE attendance SET clock_out = '$current_time' WHERE id = " . $attendance['id']);
        header('Location: clock.php');
        exit();
    }
}

// ========================================
// PAGINATION FOR ATTENDANCE HISTORY
// ========================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$month_filter = isset($_GET['month']) ? $_GET['month'] : '';

// Build WHERE clause for history
$history_where = "WHERE employee_id = $user_id";
if (!empty($month_filter)) {
    $history_where .= " AND date LIKE '$month_filter%'";
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM attendance $history_where";
$count_result = mysqli_query($conn, $count_query);
$total_rows = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_rows / $per_page);
$offset = ($page - 1) * $per_page;

// Get paginated attendance history
$attendance_history = mysqli_query($conn, "SELECT * FROM attendance $history_where ORDER BY date DESC LIMIT $offset, $per_page");

// Get this week's attendance summary
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_attendance = mysqli_query($conn, "SELECT * FROM attendance WHERE employee_id = $user_id AND date >= '$week_start' ORDER BY date ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Clock In/Out - IPINFRA HRM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .clock-number { font-family: 'Courier New', monospace; font-weight: 700; }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        .pulse { animation: pulse 2s infinite; }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen pb-20">

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
        <a href="clock.php" class="flex items-center gap-3 py-3 px-4 rounded-xl bg-blue-800/50 mb-1">
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
    
    <!-- Office Hours & Weekend Info -->
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl p-3 mb-4 text-center">
        <p class="text-xs text-blue-700">
            <i class="fas fa-clock mr-1"></i> Office Hours: 9:30 AM - 6:00 PM | Grace period until 10:00 AM
        </p>
        <?php if($is_weekend): ?>
            <p class="text-xs text-red-600 mt-1">
                <i class="fas fa-calendar-times mr-1"></i> Today is <?php echo $current_day; ?> (Weekend - No clock in/out required)
            </p>
        <?php endif; ?>
    </div>

    <!-- Time Card -->
    <div class="bg-white rounded-2xl shadow-xl p-6 mb-6 text-center">
        <p class="text-gray-500 text-sm mb-2">Current Time</p>
        <div class="text-5xl font-bold text-gray-800 clock-number mb-2" id="clock">--:--:--</div>
        <p class="text-gray-500 text-sm"><?php echo date('l, d F Y'); ?></p>
    </div>

    <!-- Action Button -->
    <div class="bg-white rounded-2xl shadow-xl p-8 mb-6 text-center">
        <?php if(isset($error)): ?>
            <div class="bg-red-100 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4 text-sm">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <?php if (!$attendance): ?>
            <div class="mb-4">
                <div class="w-32 h-32 mx-auto bg-green-100 rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-fingerprint text-5xl text-green-600"></i>
                </div>
                <p class="text-gray-600 text-sm">You haven't clocked in today</p>
                <?php if ($is_weekend): ?>
                    <p class="text-xs text-green-600 mt-1">
                        <i class="fas fa-check-circle"></i> Weekend - No clock in required
                    </p>
                <?php elseif ($is_late && date('H:i:s') > '10:00:00'): ?>
                    <p class="text-xs text-yellow-600 mt-1">
                        <i class="fas fa-exclamation-triangle"></i> You will be marked as LATE (after 10:00 AM)
                    </p>
                <?php else: ?>
                    <p class="text-xs text-gray-400 mt-1">Office hours: 9:30 AM - 6:00 PM</p>
                <?php endif; ?>
            </div>
            <?php if (!$is_weekend): ?>
                <form method="POST">
                    <button type="submit" name="clock_in" class="w-full bg-gradient-to-r from-green-500 to-green-600 text-white py-4 rounded-xl font-bold text-lg shadow-lg hover:shadow-xl transition">
                        <i class="fas fa-sign-in-alt mr-2"></i> Clock In
                    </button>
                </form>
            <?php endif; ?>
            
        <?php elseif ($attendance && !$attendance['clock_out']): ?>
            <div class="mb-4">
                <div class="w-32 h-32 mx-auto <?php echo $attendance['status'] == 'late' ? 'bg-yellow-100' : 'bg-green-100'; ?> rounded-full flex items-center justify-center mb-4 pulse">
                    <i class="fas <?php echo $attendance['status'] == 'late' ? 'fa-hourglass-half text-yellow-600' : 'fa-check-circle text-green-600'; ?> text-5xl"></i>
                </div>
                <p class="text-gray-600 text-sm">You are currently working</p>
                <p class="text-xs text-gray-400 mt-1">Clocked in at: <?php echo date('h:i A', strtotime($attendance['clock_in'])); ?></p>
                <?php if ($attendance['status'] == 'late'): ?>
                    <p class="text-xs text-yellow-600 mt-1">
                        <i class="fas fa-hourglass-half"></i> Status: LATE (Clocked in after 10:00 AM)
                    </p>
                <?php endif; ?>
                <?php if ($current_hour >= 21): ?>
                    <p class="text-xs text-red-500 mt-1">
                        <i class="fas fa-clock"></i> Past 9:00 PM - Please clock out now!
                    </p>
                <?php endif; ?>
            </div>
            <?php if (!$is_weekend): ?>
                <form method="POST">
                    <button type="submit" name="clock_out" class="w-full bg-gradient-to-r from-red-500 to-red-600 text-white py-4 rounded-xl font-bold text-lg shadow-lg hover:shadow-xl transition">
                        <i class="fas fa-sign-out-alt mr-2"></i> Clock Out
                    </button>
                </form>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="mb-4">
                <div class="w-32 h-32 mx-auto bg-blue-100 rounded-full flex items-center justify-center mb-4">
                    <i class="fas fa-check-circle text-5xl text-blue-600"></i>
                </div>
                <p class="text-gray-600 text-sm">Day completed!</p>
                <p class="text-xs text-gray-400 mt-1">
                    In: <?php echo date('h:i A', strtotime($attendance['clock_in'])); ?> | 
                    Out: <?php echo date('h:i A', strtotime($attendance['clock_out'])); ?>
                </p>
                <?php if ($attendance['status'] == 'late'): ?>
                    <p class="text-xs text-yellow-600 mt-1">Status: LATE</p>
                <?php endif; ?>
            </div>
            <a href="dashboard.php" class="block w-full bg-gradient-to-r from-gray-500 to-gray-600 text-white py-4 rounded-xl font-bold text-lg text-center shadow-lg hover:shadow-xl transition">
                <i class="fas fa-home mr-2"></i> Back to Dashboard
            </a>
        <?php endif; ?>
    </div>

    <!-- This Week Summary -->
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden mb-6">
        <div class="bg-gray-50 px-4 py-3 border-b">
            <h3 class="font-semibold text-gray-800"><i class="fas fa-calendar-week mr-2 text-blue-600"></i> This Week's Attendance</h3>
        </div>
        <div class="divide-y">
            <?php 
            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            foreach ($days as $day):
                $date = date('Y-m-d', strtotime($day . ' this week'));
                $att = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM attendance WHERE employee_id = $user_id AND date = '$date'"));
                $is_weekend_day = ($day == 'Saturday' || $day == 'Sunday');
                
                if ($att && $att['clock_out']) {
                    $status = 'Completed';
                    $statusColor = 'text-green-600';
                    $bgColor = 'bg-green-50';
                } elseif ($att && $att['clock_in']) {
                    $status = 'In Progress';
                    $statusColor = 'text-yellow-600';
                    $bgColor = 'bg-yellow-50';
                } elseif ($is_weekend_day) {
                    $status = 'Weekend';
                    $statusColor = 'text-gray-400';
                    $bgColor = 'bg-gray-50';
                } else {
                    $status = 'Absent';
                    $statusColor = 'text-red-500';
                    $bgColor = 'bg-red-50';
                }
                
                if ($att && $att['status'] == 'late') {
                    $status = 'Late';
                    $statusColor = 'text-orange-600';
                    $bgColor = 'bg-orange-50';
                }
            ?>
            <div class="flex justify-between items-center p-4 <?php echo $bgColor; ?>">
                <div>
                    <p class="font-medium"><?php echo $day; ?></p>
                    <p class="text-xs text-gray-400"><?php echo date('d/m', strtotime($date)); ?></p>
                </div>
                <div class="text-right">
                    <p class="font-medium <?php echo $statusColor; ?>"><?php echo $status; ?></p>
                    <?php if ($att && $att['clock_in']): ?>
                        <p class="text-xs text-gray-400"><?php echo date('h:i A', strtotime($att['clock_in'])); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Attendance History with Pagination -->
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-gradient-to-r from-gray-50 to-white px-5 py-4 border-b">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-2">
                    <i class="fas fa-history text-blue-500 text-xl"></i>
                    <h3 class="font-semibold text-gray-800">Attendance History</h3>
                    <span class="text-xs text-gray-400">(<?php echo $total_rows; ?> records)</span>
                </div>
                
                <!-- Month Filter -->
                <form method="GET" class="flex gap-2">
                    <input type="month" name="month" value="<?php echo $month_filter; ?>" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5">
                    <button type="submit" class="bg-blue-600 text-white px-3 py-1.5 rounded-lg text-sm">Filter</button>
                    <?php if($month_filter): ?>
                        <a href="clock.php" class="bg-gray-200 text-gray-700 px-3 py-1.5 rounded-lg text-sm">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <?php if(mysqli_num_rows($attendance_history) > 0): ?>
            <div class="divide-y divide-gray-100">
                <?php while($att = mysqli_fetch_assoc($attendance_history)): 
                    $att_date = date('d M Y', strtotime($att['date']));
                    $att_day = date('l', strtotime($att['date']));
                    $is_att_weekend = ($att_day == 'Saturday' || $att_day == 'Sunday');
                ?>
                <div class="p-4 hover:bg-gray-50 transition">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="font-medium text-gray-800"><?php echo $att_date; ?></p>
                            <p class="text-xs text-gray-400"><?php echo $att_day; ?></p>
                        </div>
                        <div class="text-right">
                            <?php if($att['clock_in'] && $att['clock_out']): ?>
                                <p class="text-sm font-medium text-green-600">
                                    <i class="fas fa-check-circle"></i> Completed
                                </p>
                                <p class="text-xs text-gray-400">
                                    <?php echo date('h:i A', strtotime($att['clock_in'])); ?> - <?php echo date('h:i A', strtotime($att['clock_out'])); ?>
                                </p>
                            <?php elseif($att['clock_in'] && !$att['clock_out']): ?>
                                <p class="text-sm font-medium text-yellow-600">
                                    <i class="fas fa-hourglass-half"></i> In Progress
                                </p>
                                <p class="text-xs text-gray-400">
                                    In: <?php echo date('h:i A', strtotime($att['clock_in'])); ?>
                                </p>
                                <?php if(date('H', strtotime($att['date'])) >= 21): ?>
                                    <p class="text-xs text-red-500">Not clocked out (past 9PM)</p>
                                <?php endif; ?>
                            <?php elseif($is_att_weekend): ?>
                                <p class="text-sm font-medium text-gray-400">
                                    <i class="fas fa-calendar-times"></i> Weekend
                                </p>
                            <?php else: ?>
                                <p class="text-sm font-medium text-red-600">
                                    <i class="fas fa-times-circle"></i> Absent
                                </p>
                            <?php endif; ?>
                            <?php if($att['status'] == 'late'): ?>
                                <p class="text-xs text-orange-500">Late arrival</p>
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
                        <a href="?page=1&per_page=<?php echo $per_page; ?>&month=<?php echo $month_filter; ?>" class="px-3 py-1 bg-white border rounded-lg text-sm hover:bg-gray-100">First</a>
                        <a href="?page=<?php echo $page-1; ?>&per_page=<?php echo $per_page; ?>&month=<?php echo $month_filter; ?>" class="px-3 py-1 bg-white border rounded-lg text-sm hover:bg-gray-100">← Prev</a>
                    <?php endif; ?>
                    
                    <span class="px-3 py-1 bg-blue-600 text-white rounded-lg text-sm"><?php echo $page; ?></span>
                    
                    <?php if($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&per_page=<?php echo $per_page; ?>&month=<?php echo $month_filter; ?>" class="px-3 py-1 bg-white border rounded-lg text-sm hover:bg-gray-100">Next →</a>
                        <a href="?page=<?php echo $total_pages; ?>&per_page=<?php echo $per_page; ?>&month=<?php echo $month_filter; ?>" class="px-3 py-1 bg-white border rounded-lg text-sm hover:bg-gray-100">Last</a>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500">Show:</span>
                    <select onchange="window.location.href=this.value" class="text-sm border rounded px-2 py-1">
                        <option value="?per_page=10&page=1&month=<?php echo $month_filter; ?>" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="?per_page=25&page=1&month=<?php echo $month_filter; ?>" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                        <option value="?per_page=50&page=1&month=<?php echo $month_filter; ?>" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                    </select>
                </div>
            </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="p-8 text-center">
                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-calendar-alt text-2xl text-gray-400"></i>
                </div>
                <p class="text-gray-500 font-medium">No attendance records found</p>
                <?php if($month_filter): ?>
                    <a href="clock.php" class="text-blue-600 text-sm mt-2 inline-block">Clear filter</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Mobile Bottom Navigation -->
<div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 md:hidden shadow-lg z-20">
    <div class="flex justify-around py-2">
        <a href="dashboard.php" class="flex flex-col items-center py-1 px-3 text-gray-500">
            <i class="fas fa-home text-xl"></i>
            <span class="text-xs mt-1">Home</span>
        </a>
        <a href="clock.php" class="flex flex-col items-center py-1 px-3 text-blue-600">
            <i class="fas fa-clock text-xl"></i>
            <span class="text-xs mt-1">Clock</span>
        </a>
        <a href="leave.php" class="flex flex-col items-center py-1 px-3 text-gray-500">
            <i class="fas fa-calendar-alt text-xl"></i>
            <span class="text-xs mt-1">Leave</span>
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
    
    function updateClock() {
        const now = new Date();
        document.getElementById('clock').textContent = now.toLocaleTimeString('en-MY', { hour12: false });
    }
    setInterval(updateClock, 1000);
    updateClock();
</script>
</body>
</html>