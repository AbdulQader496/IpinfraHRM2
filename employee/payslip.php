<?php
require_once '../includes/auth.php';
redirectIfNotLoggedIn();
require_once '../includes/db.php';
require_once '../includes/toast_fn.php';

$user_id = $_SESSION['user_id'];
$payrolls = mysqli_query($conn, "SELECT * FROM payroll WHERE employee_id = $user_id ORDER BY month_year DESC");

$emp_data = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nationality, employee_type FROM employees WHERE id = $user_id"));
$is_malaysian_emp = isset($emp_data['nationality']) && $emp_data['nationality'] == 'Malaysian';
$is_intern_emp    = isset($emp_data['employee_type']) && $emp_data['employee_type'] == 'intern';
$show_statutory   = $is_malaysian_emp && !$is_intern_emp;
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
<?php require_once '../includes/global_ui.php'; ?>
<?php require_once '../includes/toast.php'; ?>
<?php require_once '../includes/confirm_modal.php'; ?>

<!-- Premium Header -->
<div class="bg-[#060912] text-white sticky top-0 z-40 shadow-2xl backdrop-blur-sm">
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
                    <img src="../uploads/1775551018_4xzREYTcMvK7ReGODviudjeDBIofOQ78mr5DsN9g.jpg" alt="IPINFRA" style="width:28px;height:28px;object-fit:contain;border-radius:4px;background:#fff;">
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

<?php require_once '../includes/employee_sidebar.php'; ?>

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
                
                <div class="flex gap-2 flex-wrap">
                    <button onclick="printPayslip(<?php echo $row['id']; ?>)"
                        class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-4 py-2 rounded-xl text-sm font-semibold shadow-md hover:shadow-xl transition-all transform hover:scale-105 flex items-center gap-1">
                        <i class="fas fa-eye"></i> View
                    </button>
                    <button onclick="downloadPayslip(<?php echo $row['id']; ?>)"
                        class="bg-gradient-to-r from-emerald-500 to-green-600 text-white px-4 py-2 rounded-xl text-sm font-semibold shadow-md hover:shadow-xl transition-all transform hover:scale-105 flex items-center gap-1">
                        <i class="fas fa-download"></i> Download
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
                    <?php if(isset($row['approved_claims']) && $row['approved_claims'] > 0): ?>
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 p-3 rounded-xl">
                        <p class="text-xs text-gray-500">Approved Claims</p>
                        <p class="text-base font-semibold text-green-600">+RM <?php echo number_format($row['approved_claims'], 2); ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if(isset($row['unpaid_deduction']) && $row['unpaid_deduction'] > 0): ?>
                    <div class="bg-gradient-to-r from-red-50 to-rose-50 p-3 rounded-xl">
                        <p class="text-xs text-gray-500">Unpaid Leave Deduction</p>
                        <p class="text-base font-semibold text-red-600">-RM <?php echo number_format($row['unpaid_deduction'], 2); ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if($show_statutory): ?>
                    <div class="bg-gradient-to-r from-gray-50 to-white p-3 rounded-xl">
                        <p class="text-xs text-gray-500">EPF (Employee 11%)</p>
                        <p class="text-base font-semibold text-blue-600">RM <?php echo number_format($row['epf_employee'], 2); ?></p>
                    </div>
                    <div class="bg-gradient-to-r from-gray-50 to-white p-3 rounded-xl">
                        <p class="text-xs text-gray-500">SOCSO (0.5%)</p>
                        <p class="text-base font-semibold text-orange-600">RM <?php echo number_format($row['socso_employee'], 2); ?></p>
                    </div>
                    <div class="bg-gradient-to-r from-gray-50 to-white p-3 rounded-xl">
                        <p class="text-xs text-gray-500">EIS (0.2%)</p>
                        <p class="text-base font-semibold text-purple-600">RM <?php echo number_format($row['eis_employee'], 2); ?></p>
                    </div>
                    <div class="bg-gradient-to-r from-gray-50 to-white p-3 rounded-xl">
                        <p class="text-xs text-gray-500">PCB</p>
                        <p class="text-base font-semibold text-red-600">RM <?php echo number_format($row['pcb'], 2); ?></p>
                    </div>
                    <div class="bg-gradient-to-r from-orange-50 to-amber-50 p-3 rounded-xl border border-orange-200">
                        <p class="text-xs text-orange-600 font-semibold">Total Statutory Deductions</p>
                        <p class="text-base font-bold text-orange-700">
                            RM <?php echo number_format($row['epf_employee'] + $row['socso_employee'] + $row['eis_employee'] + $row['pcb'], 2); ?>
                        </p>
                    </div>
                    <?php else: ?>
                    <div class="bg-gradient-to-r from-gray-50 to-white p-3 rounded-xl col-span-2 text-center">
                        <p class="text-xs text-gray-400">
                            <?php echo $is_intern_emp ? '<i class="fas fa-graduation-cap mr-1"></i> Intern' : '<i class="fas fa-globe mr-1"></i> Non-Malaysian'; ?>
                            — No statutory deductions (EPF/SOCSO/EIS/PCB)
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Employer Contributions (only for Malaysian regular employees) -->
            <?php if($show_statutory): ?>
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
            <?php endif; ?>
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
<div class="bottom-nav fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 md:hidden shadow-lg z-20">
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

function printPayslip(id) {
    window.open('print_payslip.php?id=' + id + '&action=print', '_blank', 'width=850,height=900');
}

function downloadPayslip(id) {
    window.open('print_payslip.php?id=' + id + '&action=download', '_blank', 'width=850,height=900');
}

function viewCalculation(monthYear) {
    window.open('payroll_calculation.php?month=' + monthYear, '_blank', 'width=1000,height=800');
}
</script>

</body>
</html>