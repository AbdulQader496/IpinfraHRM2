<?php
require_once '../includes/auth.php';
redirectIfNotLoggedIn();
require_once '../includes/db.php';

$user_id = $_SESSION['user_id'];
$payrolls = mysqli_query($conn, "SELECT * FROM payroll WHERE employee_id = $user_id ORDER BY month_year DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
<title>Payslip - IPINFRA HRM</title>

<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
* { font-family: 'Inter', sans-serif; }
.sidebar { transition: transform 0.3s ease-in-out; }

/* Premium Animations */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fadeInUp { animation: fadeInUp 0.4s ease-out; }

/* Card Hover */
.payslip-card { transition: all 0.2s ease; }
.payslip-card:hover { transform: translateY(-3px); box-shadow: 0 20px 25px -12px rgba(0,0,0,0.15); }

/* Custom Scrollbar */
::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
</style>
</head>

<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-100 min-h-screen pb-24">

<!-- HEADER (EXACT SAME AS LEAVE.PHP) -->
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
                <p class="text-xs font-bold">Employee Portal</p>
            </div>
        </div>
        <button onclick="toggleSidebar()" class="text-white text-2xl">
            <i class="fas fa-bars"></i>
        </button>
    </div>
</div>


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

<!-- MAIN CONTENT -->
<div class="px-4 py-6 max-w-xl mx-auto">
    
    <!-- Header -->
    <div class="text-center mb-6 animate-fadeInUp">
        <h1 class="text-2xl font-bold text-gray-800">💰 Payslip</h1>
        <p class="text-sm text-gray-500 mt-1">View and download your monthly salary</p>
    </div>

    <!-- PAYSLIP LIST -->
    <?php if(mysqli_num_rows($payrolls) > 0): ?>
        <?php while ($row = mysqli_fetch_assoc($payrolls)): ?>
        <div class="bg-white rounded-2xl shadow-xl p-5 mb-5 payslip-card animate-fadeInUp">
            
            <!-- Month and Net Salary -->
            <div class="flex justify-between items-start mb-4 pb-3 border-b border-gray-100">
                <div>
                    <div class="flex items-center gap-2 mb-1">
                        <div class="w-8 h-8 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center">
                            <i class="fas fa-calendar-alt text-white text-sm"></i>
                        </div>
                        <p class="font-bold text-gray-800 text-lg"><?php echo $row['month_year']; ?></p>
                    </div>
                    <p class="text-xs text-gray-500">Net Salary</p>
                    <p class="text-2xl font-bold text-green-600 mt-1">
                        RM <?php echo number_format($row['net_salary'], 2); ?>
                    </p>
                </div>
                
                <div class="flex gap-2">
                    <button onclick="printPayslip(<?php echo $row['id']; ?>)" 
                        class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-semibold shadow-md hover:shadow-xl transition-all transform hover:scale-105 flex items-center gap-1">
                        <i class="fas fa-eye"></i> View
                    </button>
                    <button onclick="viewCalculation('<?php echo $row['month_year']; ?>')" 
                        class="bg-gradient-to-r from-purple-600 to-pink-600 text-white px-4 py-2 rounded-xl text-sm font-semibold shadow-md hover:shadow-xl transition-all transform hover:scale-105 flex items-center gap-1">
                        <i class="fas fa-calculator"></i> Details
                    </button>
                </div>
            </div>

            <!-- Salary Breakdown -->
            <div class="space-y-3">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Salary Breakdown</p>
                <div class="grid grid-cols-2 gap-3">
                    <div class="bg-gradient-to-r from-gray-50 to-white p-3 rounded-xl">
                        <p class="text-xs text-gray-500">Basic Salary</p>
                        <p class="text-base font-bold text-gray-800">RM <?php echo number_format($row['basic_salary'], 2); ?></p>
                    </div>
                    <div class="bg-gradient-to-r from-gray-50 to-white p-3 rounded-xl">
                        <p class="text-xs text-gray-500">EPF (Employee)</p>
                        <p class="text-base font-semibold text-blue-600">RM <?php echo number_format($row['epf_employee'], 2); ?></p>
                    </div>
                    <div class="bg-gradient-to-r from-gray-50 to-white p-3 rounded-xl">
                        <p class="text-xs text-gray-500">SOCSO</p>
                        <p class="text-base font-semibold text-orange-600">RM <?php echo number_format($row['socso_employee'], 2); ?></p>
                    </div>
                    <div class="bg-gradient-to-r from-gray-50 to-white p-3 rounded-xl">
                        <p class="text-xs text-gray-500">EIS</p>
                        <p class="text-base font-semibold text-purple-600">RM <?php echo number_format($row['eis_employee'], 2); ?></p>
                    </div>
                    <div class="bg-gradient-to-r from-gray-50 to-white p-3 rounded-xl">
                        <p class="text-xs text-gray-500">PCB</p>
                        <p class="text-base font-semibold text-red-600">RM <?php echo number_format($row['pcb'], 2); ?></p>
                    </div>
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 p-3 rounded-xl border border-green-200">
                        <p class="text-xs text-green-600 font-semibold">Total Deductions</p>
                        <p class="text-base font-bold text-green-700">
                            RM <?php echo number_format($row['epf_employee'] + $row['socso_employee'] + $row['eis_employee'] + $row['pcb'], 2); ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Employer Contributions -->
            <div class="mt-4 pt-3 border-t border-gray-100">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Employer Contributions</p>
                <div class="grid grid-cols-3 gap-2 text-center text-xs">
                    <div class="bg-gray-50 p-2 rounded-lg">
                        <p class="text-gray-500">EPF (13%)</p>
                        <p class="font-semibold text-gray-700">RM <?php echo number_format($row['epf_employer'], 2); ?></p>
                    </div>
                    <div class="bg-gray-50 p-2 rounded-lg">
                        <p class="text-gray-500">SOCSO</p>
                        <p class="font-semibold text-gray-700">RM <?php echo number_format($row['socso_employer'], 2); ?></p>
                    </div>
                    <div class="bg-gray-50 p-2 rounded-lg">
                        <p class="text-gray-500">EIS</p>
                        <p class="font-semibold text-gray-700">RM <?php echo number_format($row['eis_employer'], 2); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="bg-white rounded-2xl shadow-xl p-12 text-center animate-fadeInUp">
            <div class="w-20 h-20 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-file-invoice-dollar text-3xl text-gray-400"></i>
            </div>
            <p class="text-gray-500 font-medium">No payslips available</p>
            <p class="text-xs text-gray-400 mt-1">Payroll records will appear here</p>
        </div>
    <?php endif; ?>

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
        <a href="leave.php" class="flex flex-col items-center py-1 px-3 text-gray-500">
            <i class="fas fa-calendar-alt text-xl"></i>
            <span class="text-xs mt-1">Leave</span>
        </a>
        <a href="payslip.php" class="flex flex-col items-center py-1 px-3 text-blue-600">
            <i class="fas fa-file-invoice-dollar text-xl"></i>
            <span class="text-xs mt-1">Payslip</span>
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

function printPayslip(id) {
    window.open('print_payslip.php?id=' + id, '_blank', 'width=500,height=700');
}

function viewCalculation(monthYear) {
    window.open('payroll_calculation.php?month=' + monthYear, '_blank', 'width=1000,height=800');
}
</script>

</body>
</html>