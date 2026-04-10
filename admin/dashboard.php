<?php
require_once '../includes/auth.php';
redirectIfNotAdmin();
require_once '../includes/db.php';

// Get statistics
$total_employees = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM employees WHERE role='employee'"))['count'];
$active_employees = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM employees WHERE role='employee' AND status='active'"))['count'];
$pending_leaves = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM leaves WHERE status='pending'"))['count'];
$pending_claims = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM claims WHERE status='pending'"))['count'];
$today_attendance = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM attendance WHERE date=CURDATE() AND clock_in IS NOT NULL"))['count'];
$total_assets = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(quantity) as count FROM assets"))['count'];
$total_payroll = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(net_salary) as total FROM payroll WHERE month_year = DATE_FORMAT(CURDATE(), '%Y-%m')"))['total'];

// Get recent employees
$recent_employees = mysqli_query($conn, "SELECT * FROM employees WHERE role='employee' ORDER BY id DESC LIMIT 5");

// Get upcoming holidays
$upcoming_holidays = mysqli_query($conn, "SELECT * FROM holidays WHERE holiday_date >= CURDATE() ORDER BY holiday_date ASC LIMIT 3");
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
        
        /* Premium Animations */
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
        @keyframes shimmer {
            0% { background-position: -1000px 0; }
            100% { background-position: 1000px 0; }
        }
        
        .animate-fadeInUp { animation: fadeInUp 0.5s ease-out; }
        .animate-slideLeft { animation: slideInLeft 0.5s ease-out; }
        .animate-slideRight { animation: slideInRight 0.5s ease-out; }
        
        /* Glassmorphism */
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        /* Gradient Text */
        .gradient-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        /* Stat Card Hover */
        .stat-card {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 25px -12px rgba(0, 0, 0, 0.15);
        }
        
        /* Quick Action Button */
        .quick-action {
            transition: all 0.2s ease;
        }
        .quick-action:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.15);
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 10px; }
        
        /* Welcome Card Animation */
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
        
        /* Notification Badge */
        .notification-badge {
            animation: pulse 2s ease-in-out infinite;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 min-h-screen pb-20">

<!-- Premium Mobile Header -->
<div class="bg-gradient-to-r from-slate-900 via-indigo-900 to-slate-900 text-white sticky top-0 z-40 shadow-2xl">
    <div class="flex justify-between items-center px-4 py-4">
        <div class="flex items-center gap-3">
            <!-- MENU BUTTON - Now on LEFT side -->
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
            <!-- Notification Bell - Right side -->
            <div class="relative">
                <i class="fas fa-bell text-white/80 text-lg"></i>
                <?php if($pending_leaves + $pending_claims > 0): ?>
                    <span class="absolute -top-1 -right-2 w-4 h-4 bg-red-500 rounded-full text-[10px] flex items-center justify-center notification-badge"><?php echo $pending_leaves + $pending_claims; ?></span>
                <?php endif; ?>
            </div>
            <!-- Back Button - Right side (optional) -->
            <button onclick="history.back()" class="text-white/80 hover:text-white p-2 rounded-full hover:bg-white/10">
                
            </button>
        </div>
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
            <div class="stat-card bg-white rounded-2xl p-5 shadow-lg border-l-4 border-blue-500 hover-lift animate-fadeInUp">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 font-medium">Total Employees</p>
                        <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $total_employees; ?></p>
                        <p class="text-xs text-gray-400 mt-1">
                            <i class="fas fa-user-check text-green-500 mr-1"></i><?php echo $active_employees; ?> active
                        </p>
                    </div>
                    <div class="w-14 h-14 bg-blue-100 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-users text-blue-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-white rounded-2xl p-5 shadow-lg border-l-4 border-yellow-500 hover-lift animate-fadeInUp">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 font-medium">Pending Leaves</p>
                        <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $pending_leaves; ?></p>
                        <p class="text-xs text-gray-400 mt-1">Awaiting approval</p>
                    </div>
                    <div class="w-14 h-14 bg-yellow-100 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-calendar-check text-yellow-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-white rounded-2xl p-5 shadow-lg border-l-4 border-purple-500 hover-lift animate-fadeInUp">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-500 font-medium">Pending Claims</p>
                        <p class="text-3xl font-bold text-gray-800 mt-2"><?php echo $pending_claims; ?></p>
                        <p class="text-xs text-gray-400 mt-1">Need review</p>
                    </div>
                    <div class="w-14 h-14 bg-purple-100 rounded-2xl flex items-center justify-center">
                        <i class="fas fa-receipt text-purple-600 text-2xl"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card bg-gradient-to-r from-green-600 to-emerald-600 rounded-2xl p-5 shadow-lg text-white hover-lift animate-fadeInUp">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-green-100 font-medium">Today's Attendance</p>
                        <p class="text-3xl font-bold mt-2"><?php echo $today_attendance; ?></p>
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

        <!-- Two Column Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- Recent Employees -->
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden animate-slideLeft">
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

            <!-- Upcoming Holidays -->
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden animate-slideRight">
                <div class="bg-gradient-to-r from-gray-50 to-white px-5 py-4 border-b">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-calendar-alt text-green-500 text-xl"></i>
                        <h3 class="font-semibold text-gray-800">Upcoming Holidays</h3>
                    </div>
                </div>
                
                <?php if(mysqli_num_rows($upcoming_holidays) > 0): ?>
                    <div class="divide-y divide-gray-100">
                        <?php while($holiday = mysqli_fetch_assoc($upcoming_holidays)): ?>
                        <div class="flex items-center gap-3 p-4 hover:bg-gray-50 transition">
                            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                                <i class="fas fa-gift text-red-500"></i>
                            </div>
                            <div class="flex-1">
                                <p class="font-medium text-gray-800"><?php echo $holiday['holiday_name']; ?></p>
                                <p class="text-xs text-gray-500">
                                    <i class="far fa-calendar-alt mr-1"></i> <?php echo date('l, d F Y', strtotime($holiday['holiday_date'])); ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full">Upcoming</span>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="p-8 text-center">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-calendar-day text-2xl text-gray-400"></i>
                        </div>
                        <p class="text-gray-500 font-medium">No upcoming holidays</p>
                        <p class="text-xs text-gray-400 mt-1">Add holidays to inform employees</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Summary Cards Row -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 rounded-2xl p-5 text-white shadow-xl">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-blue-100">Total Assets</p>
                        <p class="text-3xl font-bold mt-1"><?php echo $total_assets ?? 0; ?></p>
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
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('overlay');
            sidebar.classList.toggle('-translate-x-full');
            overlay.classList.toggle('hidden');
        }
    </script>
</body>
</html>