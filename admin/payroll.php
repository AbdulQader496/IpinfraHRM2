<?php
require_once '../includes/auth.php';
redirectIfNotAdmin();
require_once '../includes/db.php';
require_once '../includes/functions.php';

$message = '';

// Get statistics
$stats_query = mysqli_query($conn, "SELECT 
    COUNT(DISTINCT month_year) as total_months,
    SUM(net_salary) as total_paid,
    COUNT(*) as total_records,
    SUM(epf_employee) as total_epf,
    SUM(socso_employee) as total_socso,
    SUM(pcb) as total_pcb
    FROM payroll");
$stats = mysqli_fetch_assoc($stats_query);

if (isset($_POST['generate_payroll'])) {
    $month_year = mysqli_real_escape_string($conn, $_POST['month_year']);
    $employees = mysqli_query($conn, "SELECT * FROM employees WHERE role='employee' AND status='active'");
    $generated_count = 0;
    
    while ($emp = mysqli_fetch_assoc($employees)) {
        $check = mysqli_query($conn, "SELECT id FROM payroll WHERE employee_id = {$emp['id']} AND month_year = '$month_year'");
        if (mysqli_num_rows($check) == 0) {
            $basic = $emp['basic_salary'];
            $is_malaysian = ($emp['nationality'] == 'Malaysian');
            
            $epf_emp = calculateEPF($basic, true, $is_malaysian);
            $epf_er = calculateEPF($basic, false, $is_malaysian);
            $socso_emp = calculateSOCSO($basic, true, $is_malaysian);
            $socso_er = calculateSOCSO($basic, false, $is_malaysian);
            $eis = calculateEIS($basic, $is_malaysian);
            $pcb = calculatePCB($basic, $is_malaysian);
            $net = $basic - $epf_emp - $socso_emp - $eis - $pcb;
            
            $insert = "INSERT INTO payroll (employee_id, month_year, basic_salary, epf_employee, epf_employer, socso_employee, socso_employer, eis_employee, eis_employer, pcb, net_salary) 
                       VALUES ({$emp['id']}, '$month_year', $basic, $epf_emp, $epf_er, $socso_emp, $socso_er, $eis, $eis, $pcb, $net)";
            mysqli_query($conn, $insert);
            $generated_count++;
        }
    }
    $message = '<div class="bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded-xl text-sm animate-fadeIn">
                    <i class="fas fa-check-circle mr-2"></i> ✓ Payroll generated for ' . htmlspecialchars($month_year) . ' (' . $generated_count . ' employees)
                </div>';
}

$payrolls = mysqli_query($conn, "SELECT p.*, e.name, e.employee_id, e.nationality, e.department 
    FROM payroll p 
    JOIN employees e ON p.employee_id = e.id 
    ORDER BY p.month_year DESC, e.name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Payroll - IPINFRA HRM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .sidebar { transition: transform 0.3s ease-in-out; }
        
        /* Premium Animations */
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
        
        /* Card Hover */
        .card-hover { transition: all 0.2s ease; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
        
        /* Payroll Row Hover */
        .payroll-row { transition: all 0.2s ease; }
        .payroll-row:hover { background-color: #f8fafc; transform: translateX(2px); }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
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
        
        <!-- Header -->
        <div class="mb-6 animate-fadeInUp">
            <h1 class="text-2xl font-bold text-gray-800">💰 Payroll Management</h1>
            <p class="text-sm text-gray-500 mt-1">Process employee salaries and manage payroll records</p>
        </div>

        <?php echo $message; ?>

        <!-- Stats Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl p-4 shadow-md card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500">Total Processed</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_months'] ?? 0; ?></p>
                        <p class="text-xs text-gray-400">Months</p>
                    </div>
                    <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-calendar-alt text-blue-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-md card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500">Total Paid</p>
                        <p class="text-2xl font-bold text-green-600">RM <?php echo number_format($stats['total_paid'] ?? 0, 0); ?></p>
                        <p class="text-xs text-gray-400">All time</p>
                    </div>
                    <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-money-bill-wave text-green-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-md card-hover">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-gray-500">Total Records</p>
                        <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_records'] ?? 0; ?></p>
                        <p class="text-xs text-gray-400">Salary slips</p>
                    </div>
                    <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                        <i class="fas fa-file-invoice text-purple-600"></i>
                    </div>
                </div>
            </div>
            <div class="bg-gradient-to-r from-red-600 to-pink-600 rounded-xl p-4 shadow-md text-white">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-xs text-red-100">Statutory Deductions</p>
                        <p class="text-xl font-bold">RM <?php echo number_format(($stats['total_epf'] ?? 0) + ($stats['total_socso'] ?? 0) + ($stats['total_pcb'] ?? 0), 0); ?></p>
                        <p class="text-xs text-red-100">EPF + SOCSO + PCB</p>
                    </div>
                    <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Generate Payroll Section -->
        <div class="bg-white rounded-xl shadow-md p-5 mb-6 card-hover">
            <div class="flex items-center gap-2 mb-3">
                <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-calculator text-blue-600"></i>
                </div>
                <h2 class="font-bold text-gray-800">Generate Payroll</h2>
            </div>
            <form method="POST" class="flex flex-col sm:flex-row gap-3">
                <div class="flex-1 relative">
                    <i class="fas fa-calendar-alt absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="month" name="month_year" required class="w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-lg focus:border-blue-500 focus:outline-none">
                </div>
                <button type="submit" name="generate_payroll" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2.5 rounded-lg font-medium transition flex items-center justify-center gap-2">
                    <i class="fas fa-sync-alt"></i> Generate Payroll
                </button>
            </form>
            <div class="mt-3 p-3 bg-blue-50 rounded-lg">
                <div class="flex items-start gap-2">
                    <i class="fas fa-info-circle text-blue-600 mt-0.5 text-sm"></i>
                    <div class="text-xs text-blue-800">
                        <p><strong>Statutory Deductions:</strong> Malaysian employees: EPF (11%), SOCSO (0.5%), EIS (0.2%), PCB deducted automatically. Non-Malaysian employees: No statutory deductions (full salary paid).</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payroll Records -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="bg-gray-50 px-5 py-3 border-b">
                <div class="flex items-center gap-2">
                    <i class="fas fa-file-invoice-dollar text-red-500"></i>
                    <p class="font-semibold text-gray-800">Payroll Records</p>
                    <span class="text-xs text-gray-400 ml-auto">Latest first</span>
                </div>
            </div>
            
            <?php if(mysqli_num_rows($payrolls) > 0): ?>
                <div class="divide-y divide-gray-100">
                    <?php while ($row = mysqli_fetch_assoc($payrolls)): ?>
                    <div class="payroll-row p-4">
                        <div class="flex flex-col md:flex-row justify-between gap-3">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                                        <i class="fas fa-user text-blue-600 text-sm"></i>
                                    </div>
                                    <div>
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <p class="font-semibold text-gray-800"><?php echo $row['name']; ?></p>
                                            <?php if($row['nationality'] != 'Malaysian'): ?>
                                                <span class="text-xs bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full">
                                                    <i class="fas fa-globe mr-1"></i> Expat
                                                </span>
                                            <?php else: ?>
                                                <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">
                                                    <i class="fas fa-check-circle mr-1"></i> Local
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <p class="text-xs text-gray-500"><?php echo $row['employee_id']; ?> • <?php echo $row['department']; ?></p>
                                    </div>
                                </div>
                                
                                <?php if($row['nationality'] == 'Malaysian'): ?>
                                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 text-center text-xs">
                                        <div class="p-2 bg-gray-50 rounded-lg">
                                            <p class="text-gray-500">Basic</p>
                                            <p class="font-semibold">RM <?php echo number_format($row['basic_salary'], 0); ?></p>
                                        </div>
                                        <div class="p-2 bg-gray-50 rounded-lg">
                                            <p class="text-gray-500">EPF</p>
                                            <p class="font-semibold text-blue-600">RM <?php echo number_format($row['epf_employee'], 0); ?></p>
                                        </div>
                                        <div class="p-2 bg-gray-50 rounded-lg">
                                            <p class="text-gray-500">SOCSO</p>
                                            <p class="font-semibold text-orange-600">RM <?php echo number_format($row['socso_employee'], 0); ?></p>
                                        </div>
                                        <div class="p-2 bg-gray-50 rounded-lg">
                                            <p class="text-gray-500">EIS</p>
                                            <p class="font-semibold text-purple-600">RM <?php echo number_format($row['eis_employee'], 0); ?></p>
                                        </div>
                                        <div class="p-2 bg-gray-50 rounded-lg">
                                            <p class="text-gray-500">PCB</p>
                                            <p class="font-semibold text-red-600">RM <?php echo number_format($row['pcb'], 0); ?></p>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="grid grid-cols-2 gap-2 text-center text-xs">
                                        <div class="p-2 bg-gray-50 rounded-lg">
                                            <p class="text-gray-500">Basic Salary</p>
                                            <p class="font-semibold">RM <?php echo number_format($row['basic_salary'], 0); ?></p>
                                        </div>
                                        <div class="p-2 bg-green-50 rounded-lg">
                                            <p class="text-gray-500">Net Salary</p>
                                            <p class="font-bold text-green-600">RM <?php echo number_format($row['net_salary'], 0); ?></p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="text-right md:text-center min-w-[120px]">
                                <div class="bg-green-100 rounded-lg px-3 py-2 inline-block">
                                    <p class="text-xs text-gray-500">Net Salary</p>
                                    <p class="text-lg font-bold text-green-600">RM <?php echo number_format($row['net_salary'], 2); ?></p>
                                </div>
                                <p class="text-xs text-gray-400 mt-2">
                                    <i class="fas fa-calendar mr-1"></i> <?php echo $row['month_year']; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="p-12 text-center">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-file-invoice-dollar text-2xl text-gray-400"></i>
                    </div>
                    <p class="text-gray-500 font-medium">No payroll records</p>
                    <p class="text-xs text-gray-400 mt-1">Generate payroll for a month to see records</p>
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
            <a href="employees.php" class="flex flex-col items-center py-1 px-3 text-gray-500">
                <i class="fas fa-users text-xl"></i>
                <span class="text-xs mt-1">Staff</span>
            </a>
            <a href="manage_assets.php" class="flex flex-col items-center py-1 px-3 text-gray-500">
                <i class="fas fa-boxes text-xl"></i>
                <span class="text-xs mt-1">Assets</span>
            </a>
            <a href="payroll.php" class="flex flex-col items-center py-1 px-3 text-red-600">
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
    </script>
</body>
</html>