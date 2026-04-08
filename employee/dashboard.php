<?php
require_once '../includes/auth.php';
redirectIfNotLoggedIn();
require_once '../includes/db.php';
$user_id = $_SESSION['user_id'];

$today = date('Y-m-d');
$current_time = date('H:i:s');

// Handle Clock In/Out directly from dashboard
if (isset($_POST['clock_in'])) {
    $status = ($current_time > '10:00:00') ? 'late' : 'present';
    mysqli_query($conn, "INSERT INTO attendance (employee_id, date, clock_in, status) VALUES ($user_id, '$today', '$current_time', '$status')");
    header('Location: dashboard.php');
    exit();
}

if (isset($_POST['clock_out'])) {
    mysqli_query($conn, "UPDATE attendance SET clock_out = '$current_time' WHERE employee_id = $user_id AND date = '$today'");
    header('Location: dashboard.php');
    exit();
}

// Get today's attendance
$attendance_query = mysqli_query($conn, "SELECT * FROM attendance WHERE employee_id = $user_id AND date = '$today'");
$attendance = mysqli_fetch_assoc($attendance_query);
$has_clocked_in = $attendance ? true : false;
$clocked_out = $attendance && $attendance['clock_out'] ? true : false;
$is_late = $attendance && $attendance['status'] == 'late';

// Get leave balance
$balance = mysqli_fetch_assoc(mysqli_query($conn, "SELECT 
    annual_leave_entitlement, 
    used_annual_leave,
    (annual_leave_entitlement - used_annual_leave) as annual_remaining,
    medical_leave_entitlement,
    used_medical_leave,
    (medical_leave_entitlement - used_medical_leave) as medical_remaining 
    FROM employees WHERE id = $user_id"));

// Get recent leaves
$recent_leaves = mysqli_query($conn, "SELECT * FROM leaves WHERE employee_id = $user_id ORDER BY applied_at DESC LIMIT 3");

// Get recent claims
$recent_claims = mysqli_query($conn, "SELECT * FROM claims WHERE employee_id = $user_id ORDER BY applied_at DESC LIMIT 3");

// Get this week's attendance summary
$week_days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$week_attendance = [];
foreach ($week_days as $day) {
    $date = date('Y-m-d', strtotime($day . ' this week'));
    $att = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM attendance WHERE employee_id = $user_id AND date = '$date'"));
    $week_attendance[$day] = $att;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Dashboard - IPINFRA HRM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .sidebar { transition: transform 0.3s ease-in-out; }
        .card-hover { transition: all 0.3s ease; }
        .card-hover:active { transform: scale(0.98); }
        .pulse {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        @media (max-width: 768px) {
            body { padding-bottom: 70px; }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen">
<!-- Mobile Header -->
<div class="bg-gradient-to-r from-blue-800 to-blue-900 text-white sticky top-0 z-30 shadow-lg">
    <div class="flex justify-between items-center px-4 py-3">
        <div class="flex items-center gap-2">
            <button onclick="history.back()" class="text-white text-xl">
                <i class="fas fa-arrow-left"></i>
            </button>
            <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                <span class="text-white font-bold text-sm">IN</span>
            </div>
            <div>
                <p class="text-xs text-blue-200">IPINFRA NETWORKS</p>
                <p class="text-xs font-bold">Dashboard</p>
            </div>
        </div>
        <button onclick="toggleSidebar()" class="text-white text-2xl">
            <i class="fas fa-bars"></i>
        </button>
    </div>
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
    <div class="px-4 py-6 pb-24 md:pb-6 max-w-7xl mx-auto">
        <!-- Welcome Card with Time -->
        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 rounded-2xl p-5 mb-6 text-white shadow-xl">
            <div class="flex justify-between items-start">
                <div>
                    <p class="text-sm opacity-90">Welcome back,</p>
                    <h1 class="text-2xl font-bold"><?php echo $_SESSION['user_name']; ?></h1>
                    <p class="text-sm opacity-80 mt-1"><?php echo date('l, d F Y'); ?></p>
                </div>
                <div class="text-right">
                    <div class="text-3xl font-bold clock-number" id="liveClock">--:--:--</div>
                    <p class="text-xs opacity-80 mt-1">Malaysia Time</p>
                </div>
            </div>
        </div>

        <!-- Clock In/Out Card - Prominent -->
        <div class="bg-white rounded-2xl shadow-xl p-5 mb-6">
            <div class="flex flex-col items-center text-center">
                <div class="mb-3">
                    <?php if (!$has_clocked_in): ?>
                        <div class="w-24 h-24 mx-auto bg-green-100 rounded-full flex items-center justify-center pulse">
                            <i class="fas fa-fingerprint text-4xl text-green-600"></i>
                        </div>
                    <?php elseif ($has_clocked_in && !$clocked_out): ?>
                        <div class="w-24 h-24 mx-auto bg-yellow-100 rounded-full flex items-center justify-center pulse">
                            <i class="fas fa-hourglass-half text-4xl text-yellow-600"></i>
                        </div>
                    <?php else: ?>
                        <div class="w-24 h-24 mx-auto bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-check-circle text-4xl text-blue-600"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!$has_clocked_in): ?>
                    <h3 class="text-xl font-bold text-gray-800">Not Clocked In Yet</h3>
                    <p class="text-sm text-gray-500 mb-4">Office hours: 9:30 AM - 6:00 PM</p>
                    <form method="POST">
                        <button type="submit" name="clock_in" class="bg-gradient-to-r from-green-500 to-green-600 text-white px-8 py-3 rounded-xl font-bold text-lg shadow-lg hover:shadow-xl transition">
                            <i class="fas fa-sign-in-alt mr-2"></i> Clock In Now
                        </button>
                    </form>
                <?php elseif ($has_clocked_in && !$clocked_out): ?>
                    <h3 class="text-xl font-bold <?php echo $is_late ? 'text-orange-600' : 'text-green-600'; ?>">
                        <?php echo $is_late ? '⚠️ Late Arrival' : '✅ Working'; ?>
                    </h3>
                    <p class="text-sm text-gray-500 mb-1">Clocked in at: <?php echo date('h:i A', strtotime($attendance['clock_in'])); ?></p>
                    <?php if ($is_late): ?>
                        <p class="text-xs text-orange-500 mb-4">Clocked in after 10:00 AM</p>
                    <?php else: ?>
                        <p class="text-xs text-gray-400 mb-4">On time (before 10:00 AM)</p>
                    <?php endif; ?>
                    <form method="POST">
                        <button type="submit" name="clock_out" class="bg-gradient-to-r from-red-500 to-red-600 text-white px-8 py-3 rounded-xl font-bold text-lg shadow-lg hover:shadow-xl transition">
                            <i class="fas fa-sign-out-alt mr-2"></i> Clock Out
                        </button>
                    </form>
                <?php else: ?>
                    <h3 class="text-xl font-bold text-blue-600">Day Completed ✓</h3>
                    <p class="text-sm text-gray-500 mb-1">Clocked in: <?php echo date('h:i A', strtotime($attendance['clock_in'])); ?></p>
                    <p class="text-sm text-gray-500 mb-4">Clocked out: <?php echo date('h:i A', strtotime($attendance['clock_out'])); ?></p>
                    <a href="clock.php" class="inline-block bg-gray-500 text-white px-6 py-2 rounded-xl text-sm">
                        <i class="fas fa-history mr-1"></i> View History
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 gap-4 mb-6">
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4">
                <div class="flex items-center justify-between mb-2">
                    <i class="fas fa-umbrella-beach text-2xl text-blue-600"></i>
                    <span class="text-xs text-blue-500">Remaining</span>
                </div>
                <p class="text-3xl font-bold text-blue-700"><?php echo $balance['annual_remaining']; ?></p>
                <p class="text-xs text-blue-600 mt-1">Annual Leave</p>
                <p class="text-xs text-gray-500">Used: <?php echo $balance['used_annual_leave']; ?>/<?php echo $balance['annual_leave_entitlement']; ?></p>
            </div>
            <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-4">
                <div class="flex items-center justify-between mb-2">
                    <i class="fas fa-hospital-user text-2xl text-green-600"></i>
                    <span class="text-xs text-green-500">Remaining</span>
                </div>
                <p class="text-3xl font-bold text-green-700"><?php echo $balance['medical_remaining']; ?></p>
                <p class="text-xs text-green-600 mt-1">Medical Leave</p>
                <p class="text-xs text-gray-500">Used: <?php echo $balance['used_medical_leave']; ?>/<?php echo $balance['medical_leave_entitlement']; ?></p>
            </div>
        </div>

        <!-- This Week Attendance Summary -->
        <div class="bg-white rounded-xl shadow-md p-4 mb-6">
            <h3 class="font-semibold text-gray-800 mb-3 flex items-center gap-2">
                <i class="fas fa-calendar-week text-blue-600"></i> This Week's Attendance
            </h3>
            <div class="grid grid-cols-5 gap-2">
                <?php foreach ($week_days as $day): 
                    $att = $week_attendance[$day];
                    $status = 'absent';
                    $color = 'bg-red-100 text-red-600';
                    if ($att && $att['clock_out']) {
                        $status = 'present';
                        $color = $att['status'] == 'late' ? 'bg-orange-100 text-orange-600' : 'bg-green-100 text-green-600';
                    } elseif ($att && $att['clock_in']) {
                        $status = 'active';
                        $color = 'bg-yellow-100 text-yellow-600';
                    }
                ?>
                <div class="text-center">
                    <p class="text-xs font-medium text-gray-500"><?php echo substr($day, 0, 3); ?></p>
                    <div class="<?php echo $color; ?> rounded-lg p-2 mt-1">
                        <i class="fas <?php echo $status == 'present' ? 'fa-check-circle' : ($status == 'active' ? 'fa-clock' : 'fa-times-circle'); ?> text-sm"></i>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-3">Quick Actions</h2>
            <div class="grid grid-cols-3 gap-3">
                <a href="leave.php" class="bg-white rounded-xl p-3 text-center shadow-md hover:shadow-lg transition card-hover">
                    <i class="fas fa-calendar-plus text-xl text-green-600 mb-1 block"></i>
                    <span class="text-xs font-medium">Apply Leave</span>
                </a>
                <a href="claim.php" class="bg-white rounded-xl p-3 text-center shadow-md hover:shadow-lg transition card-hover">
                    <i class="fas fa-receipt text-xl text-purple-600 mb-1 block"></i>
                    <span class="text-xs font-medium">Submit Claim</span>
                </a>
                <a href="payslip.php" class="bg-white rounded-xl p-3 text-center shadow-md hover:shadow-lg transition card-hover">
                    <i class="fas fa-file-pdf text-xl text-red-600 mb-1 block"></i>
                    <span class="text-xs font-medium">Payslip</span>
                </a>
            </div>
        </div>

        <!-- Recent Activity Tabs -->
        <div>
            <div class="flex gap-2 mb-3">
                <button onclick="showTab('leaves')" id="tabLeavesBtn" class="px-3 py-1 rounded-lg text-sm font-medium bg-blue-600 text-white">Leave Requests</button>
                <button onclick="showTab('claims')" id="tabClaimsBtn" class="px-3 py-1 rounded-lg text-sm font-medium bg-gray-200 text-gray-700">Claims</button>
            </div>
            
            <!-- Leaves Activity -->
            <div id="leavesTab" class="bg-white rounded-xl shadow-md overflow-hidden">
                <?php if(mysqli_num_rows($recent_leaves) > 0): ?>
                    <div class="divide-y">
                        <?php while($leave = mysqli_fetch_assoc($recent_leaves)): ?>
                        <div class="flex items-center gap-3 p-4">
                            <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                                <i class="fas fa-calendar-day text-blue-600"></i>
                            </div>
                            <div class="flex-1">
                                <p class="font-medium text-sm"><?php echo ucfirst($leave['leave_type']); ?> Leave</p>
                                <p class="text-xs text-gray-500"><?php echo date('d M Y', strtotime($leave['start_date'])); ?> - <?php echo date('d M Y', strtotime($leave['end_date'])); ?></p>
                            </div>
                            <span class="text-xs px-2 py-1 rounded-full <?php echo $leave['status'] == 'approved' ? 'bg-green-100 text-green-700' : ($leave['status'] == 'rejected' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'); ?>">
                                <?php echo ucfirst($leave['status']); ?>
                            </span>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="p-6 text-center text-gray-500">
                        <i class="fas fa-calendar-alt text-3xl mb-2 block"></i>
                        <p class="text-sm">No leave applications yet</p>
                        <a href="leave.php" class="text-blue-600 text-sm mt-2 inline-block">Apply for leave →</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Claims Activity -->
            <div id="claimsTab" class="bg-white rounded-xl shadow-md overflow-hidden hidden">
                <?php if(mysqli_num_rows($recent_claims) > 0): ?>
                    <div class="divide-y">
                        <?php while($claim = mysqli_fetch_assoc($recent_claims)): ?>
                        <div class="flex items-center gap-3 p-4">
                            <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center">
                                <i class="fas fa-receipt text-purple-600"></i>
                            </div>
                            <div class="flex-1">
                                <p class="font-medium text-sm"><?php echo ucfirst($claim['claim_type']); ?> Claim</p>
                                <p class="text-xs text-gray-500">RM <?php echo number_format($claim['amount'], 2); ?></p>
                            </div>
                            <span class="text-xs px-2 py-1 rounded-full <?php echo $claim['status'] == 'approved' ? 'bg-green-100 text-green-700' : ($claim['status'] == 'rejected' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'); ?>">
                                <?php echo ucfirst($claim['status']); ?>
                            </span>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="p-6 text-center text-gray-500">
                        <i class="fas fa-receipt text-3xl mb-2 block"></i>
                        <p class="text-sm">No claims submitted yet</p>
                        <a href="claim.php" class="text-blue-600 text-sm mt-2 inline-block">Submit a claim →</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 md:hidden shadow-lg z-20">
        <div class="flex justify-around py-2">
            <a href="dashboard.php" class="flex flex-col items-center py-1 px-3 text-blue-600">
                <i class="fas fa-home text-xl"></i>
                <span class="text-xs mt-1">Home</span>
            </a>
            <a href="leave.php" class="flex flex-col items-center py-1 px-3 text-gray-500">
                <i class="fas fa-calendar-alt text-xl"></i>
                <span class="text-xs mt-1">Leave</span>
            </a>
            <a href="claim.php" class="flex flex-col items-center py-1 px-3 text-gray-500">
                <i class="fas fa-receipt text-xl"></i>
                <span class="text-xs mt-1">Claim</span>
            </a>
            <a href="profile.php" class="flex flex-col items-center py-1 px-3 text-gray-500">
                <i class="fas fa-user text-xl"></i>
                <span class="text-xs mt-1">Profile</span>
            </a>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }
        
        function showTab(tab) {
            const leavesTab = document.getElementById('leavesTab');
            const claimsTab = document.getElementById('claimsTab');
            const leavesBtn = document.getElementById('tabLeavesBtn');
            const claimsBtn = document.getElementById('tabClaimsBtn');
            
            if (tab === 'leaves') {
                leavesTab.classList.remove('hidden');
                claimsTab.classList.add('hidden');
                leavesBtn.className = 'px-3 py-1 rounded-lg text-sm font-medium bg-blue-600 text-white';
                claimsBtn.className = 'px-3 py-1 rounded-lg text-sm font-medium bg-gray-200 text-gray-700';
            } else {
                leavesTab.classList.add('hidden');
                claimsTab.classList.remove('hidden');
                leavesBtn.className = 'px-3 py-1 rounded-lg text-sm font-medium bg-gray-200 text-gray-700';
                claimsBtn.className = 'px-3 py-1 rounded-lg text-sm font-medium bg-blue-600 text-white';
            }
        }
        
        function updateClock() {
            const now = new Date();
            document.getElementById('liveClock').textContent = now.toLocaleTimeString('en-MY', { hour12: false });
        }
        setInterval(updateClock, 1000);
        updateClock();
    </script>
</body>
</html>