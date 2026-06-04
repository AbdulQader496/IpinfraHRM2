<?php
require_once '../includes/auth.php';
redirectIfNotLoggedIn();
require_once '../includes/db.php';
require_once '../includes/toast_fn.php';
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

// Get leave balance and employee type
$balance = mysqli_fetch_assoc(mysqli_query($conn, "SELECT
    annual_leave_entitlement,
    used_annual_leave,
    (annual_leave_entitlement - used_annual_leave) as annual_remaining,
    medical_leave_entitlement,
    used_medical_leave,
    (medical_leave_entitlement - used_medical_leave) as medical_remaining,
    employee_type
    FROM employees WHERE id = $user_id"));
$is_intern_dash = isset($balance['employee_type']) && $balance['employee_type'] == 'intern';

// Get recent leaves
$recent_leaves = mysqli_query($conn, "SELECT * FROM leaves WHERE employee_id = $user_id ORDER BY applied_at DESC LIMIT 3");

// Get recent claims
$recent_claims = mysqli_query($conn, "SELECT * FROM claims WHERE employee_id = $user_id ORDER BY applied_at DESC LIMIT 3");

// Get announcements for employees
$announcements = mysqli_query($conn, "SELECT * FROM announcements WHERE is_active = 1 AND (target_role = 'all' OR target_role = 'employee') AND (end_date IS NULL OR end_date >= CURDATE()) ORDER BY 
    CASE announcement_type 
        WHEN 'urgent' THEN 1 
        WHEN 'holiday' THEN 2 
        ELSE 3 
    END, created_at DESC LIMIT 5");
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
<?php require_once '../includes/global_ui.php'; ?>
<?php require_once '../includes/toast.php'; ?>
<?php require_once '../includes/confirm_modal.php'; ?>
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
        
        <!-- Right side -->
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
            <?php if($is_intern_dash): ?>
            <div class="col-span-2 bg-gradient-to-br from-blue-50 to-indigo-50 border border-blue-100 rounded-xl p-4 flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-graduation-cap text-blue-500"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold text-blue-700">Intern — No Leave Entitlement</p>
                    <p class="text-xs text-blue-500 mt-0.5">You may apply for <strong>Unpaid Leave</strong> only. It will be deducted from your salary.</p>
                </div>
            </div>
            <?php else: ?>
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
            <?php endif; ?>
        </div>

        <!-- ANNOUNCEMENTS SECTION (Replaces This Week's Attendance) -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden mb-6">
            <div class="bg-gradient-to-r from-purple-50 to-indigo-50 px-4 py-3 border-b">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                        <i class="fas fa-bullhorn text-purple-600"></i> Announcements
                    </h3>
                    <span class="text-xs text-gray-400">Latest updates</span>
                </div>
            </div>
            <div class="divide-y max-h-80 overflow-y-auto">
                <?php if(mysqli_num_rows($announcements) > 0): ?>
                    <?php while($ann = mysqli_fetch_assoc($announcements)): 
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
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full <?php echo $type_color; ?> flex items-center justify-center flex-shrink-0">
                                <i class="fas <?php echo $type_icon; ?> text-sm"></i>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2 flex-wrap mb-1">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium <?php echo $type_color; ?>">
                                        <?php echo ucfirst($ann['announcement_type']); ?>
                                    </span>
                                    <span class="text-xs text-gray-400">
                                        <i class="far fa-clock mr-1"></i><?php echo date('d M Y', strtotime($ann['created_at'])); ?>
                                    </span>
                                </div>
                                <p class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($ann['title']); ?></p>
                                <p class="text-xs text-gray-600 mt-1"><?php echo nl2br(htmlspecialchars($ann['message'])); ?></p>
                                <?php if($ann['end_date']): ?>
                                    <p class="text-xs text-gray-400 mt-2">
                                        <i class="far fa-calendar-alt mr-1"></i> Valid until: <?php echo date('d M Y', strtotime($ann['end_date'])); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="p-6 text-center text-gray-500">
                        <i class="fas fa-bullhorn text-3xl mb-2 block text-gray-300"></i>
                        <p class="text-sm">No announcements yet</p>
                        <p class="text-xs text-gray-400 mt-1">Check back later for updates</p>
                    </div>
                <?php endif; ?>
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
    <div class="bottom-nav fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 md:hidden shadow-lg z-20">
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