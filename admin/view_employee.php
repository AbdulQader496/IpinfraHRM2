<?php
require_once '../includes/auth.php';
redirectIfNotAdmin();
require_once '../includes/db.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$id) { header('Location: employees.php'); exit(); }

$emp = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM employees WHERE id = $id"));
if (!$emp) { header('Location: employees.php'); exit(); }

// Leave history summary
$leave_summary = mysqli_query($conn, "
    SELECT leave_type, half_day, status, COUNT(*) as cnt, SUM(total_days) as days
    FROM leaves WHERE employee_id = $id
    GROUP BY leave_type, status
    ORDER BY status, leave_type
");

// Recent leaves
$recent_leaves = mysqli_query($conn, "
    SELECT * FROM leaves WHERE employee_id = $id
    ORDER BY applied_at DESC LIMIT 5
");

// Recent claims
$recent_claims = mysqli_query($conn, "
    SELECT * FROM claims WHERE employee_id = $id
    ORDER BY applied_at DESC LIMIT 5
");

// Recent payroll
$recent_payroll = mysqli_query($conn, "
    SELECT * FROM payroll WHERE employee_id = $id
    ORDER BY month_year DESC LIMIT 3
");

$profile_pic_path = "../uploads/profiles/" . $emp['profile_pic'];
$has_profile = !empty($emp['profile_pic']) && file_exists($profile_pic_path);
$is_intern = ($emp['employee_type'] ?? '') === 'intern';
$al_remain  = ($emp['annual_leave_entitlement']  ?? 0) - ($emp['used_annual_leave']  ?? 0);
$ml_remain  = ($emp['medical_leave_entitlement'] ?? 0) - ($emp['used_medical_leave'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($emp['name']); ?> — Employee Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .section-card { @apply bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden; }
        .stat-card { background: linear-gradient(135deg, var(--from), var(--to)); }
        .badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 10px; border-radius: 9999px; font-size: 12px; font-weight: 600; }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 min-h-screen">
<?php require_once '../includes/global_ui.php'; ?>

<!-- Header -->
<div class="bg-gradient-to-r from-slate-900 via-indigo-900 to-slate-900 text-white sticky top-0 z-40 shadow-2xl">
    <div class="flex items-center gap-4 px-4 py-4">
        <a href="employees.php" class="w-9 h-9 rounded-xl bg-white/10 hover:bg-white/20 flex items-center justify-center transition">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="font-bold text-lg leading-none">Employee Profile</h1>
            <p class="text-xs text-blue-200 mt-0.5"><?php echo htmlspecialchars($emp['employee_id']); ?> &bull; <?php echo htmlspecialchars($emp['department']); ?></p>
        </div>
        <div class="ml-auto">
            <a href="employees.php?edit_id=<?php echo $emp['id']; ?>#editTrigger"
               onclick="sessionStorage.setItem('openEditId','<?php echo $emp['id']; ?>')"
               class="inline-flex items-center gap-2 bg-blue-500 hover:bg-blue-400 text-white px-4 py-2 rounded-xl text-sm font-semibold transition">
                <i class="fas fa-edit"></i> Edit
            </a>
        </div>
    </div>
</div>

<div class="max-w-4xl mx-auto px-4 py-6 space-y-5 pb-24">

    <!-- Profile Hero -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="h-28 bg-gradient-to-r from-indigo-600 via-blue-600 to-purple-600 relative">
            <div class="absolute inset-0 opacity-10" style="background-image:repeating-linear-gradient(45deg,#fff 0,#fff 1px,transparent 0,transparent 50%);background-size:20px 20px;"></div>
        </div>
        <div class="px-6 pb-6">
            <div class="flex flex-col sm:flex-row sm:items-end gap-4 -mt-14">
                <!-- Avatar -->
                <div class="relative shrink-0">
                    <?php if ($has_profile): ?>
                        <img src="<?php echo $profile_pic_path; ?>"
                             class="w-24 h-24 rounded-2xl object-cover border-4 border-white shadow-xl">
                    <?php else: ?>
                        <div class="w-24 h-24 rounded-2xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center border-4 border-white shadow-xl">
                            <span class="text-3xl font-bold text-white"><?php echo strtoupper(substr($emp['name'], 0, 1)); ?></span>
                        </div>
                    <?php endif; ?>
                    <span class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full border-2 border-white <?php echo $emp['status'] == 'active' ? 'bg-green-500' : 'bg-red-500'; ?>"></span>
                </div>
                <!-- Name / meta -->
                <div class="flex-1 pt-2 sm:pt-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-2xl font-bold text-gray-900"><?php echo htmlspecialchars($emp['name']); ?></h2>
                        <?php if ($is_intern): ?>
                            <span class="badge bg-blue-100 text-blue-700"><i class="fas fa-graduation-cap"></i> Intern</span>
                        <?php elseif ($emp['nationality'] !== 'Malaysian'): ?>
                            <span class="badge bg-orange-100 text-orange-700"><i class="fas fa-globe"></i> Expat</span>
                        <?php else: ?>
                            <span class="badge bg-green-100 text-green-700"><i class="fas fa-check-circle"></i> Local</span>
                        <?php endif; ?>
                        <span class="badge <?php echo $emp['status'] == 'active' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                            <?php echo ucfirst($emp['status']); ?>
                        </span>
                    </div>
                    <p class="text-gray-500 mt-1"><?php echo htmlspecialchars($emp['position']); ?> &bull; <?php echo htmlspecialchars($emp['department']); ?></p>
                    <p class="text-sm text-gray-400 mt-0.5">
                        <i class="far fa-calendar-alt mr-1"></i> Joined <?php echo date('d M Y', strtotime($emp['join_date'])); ?>
                        &nbsp;&bull;&nbsp;
                        <i class="fas fa-id-badge mr-1"></i> <?php echo htmlspecialchars($emp['employee_id']); ?>
                    </p>
                </div>
                <!-- Salary pill -->
                <div class="sm:text-right">
                    <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Basic Salary</p>
                    <p class="text-2xl font-bold text-green-600">RM <?php echo number_format($emp['basic_salary'], 2); ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats Row -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 text-center">
            <p class="text-2xl font-bold text-blue-600"><?php echo (int)($emp['annual_leave_entitlement'] ?? 0); ?></p>
            <p class="text-xs text-gray-400 mt-1">Annual Leave</p>
            <p class="text-xs text-blue-500 font-medium"><?php echo (int)$al_remain; ?> remaining</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 text-center">
            <p class="text-2xl font-bold text-purple-600"><?php echo (int)($emp['medical_leave_entitlement'] ?? 0); ?></p>
            <p class="text-xs text-gray-400 mt-1">Medical Leave</p>
            <p class="text-xs text-purple-500 font-medium"><?php echo (int)$ml_remain; ?> remaining</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 text-center">
            <?php
            $total_leaves = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM leaves WHERE employee_id=$id AND status='approved'"))['c'];
            ?>
            <p class="text-2xl font-bold text-green-600"><?php echo $total_leaves; ?></p>
            <p class="text-xs text-gray-400 mt-1">Approved Leaves</p>
            <p class="text-xs text-gray-400 font-medium">all time</p>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 text-center">
            <?php
            $total_claims_row = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) as s FROM claims WHERE employee_id=$id AND status='approved'"));
            ?>
            <p class="text-xl font-bold text-orange-600">RM <?php echo number_format($total_claims_row['s'], 0); ?></p>
            <p class="text-xs text-gray-400 mt-1">Total Claims</p>
            <p class="text-xs text-gray-400 font-medium">approved</p>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

        <!-- Personal Information -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center">
                    <i class="fas fa-user text-blue-600 text-sm"></i>
                </div>
                <h3 class="font-semibold text-gray-800">Personal Information</h3>
            </div>
            <div class="divide-y divide-gray-50">
                <?php
                $personal = [
                    ['IC Number',   $emp['ic_number']   ?: '—', 'fa-id-card',    'text-gray-500'],
                    ['Passport',    $emp['passport_no']  ?: '—', 'fa-passport',   'text-gray-500'],
                    ['Nationality', $emp['nationality']  ?: '—', 'fa-flag',       'text-blue-500'],
                    ['Phone',       $emp['phone']        ?: '—', 'fa-phone',      'text-green-500'],
                    ['Email',       $emp['email']        ?: '—', 'fa-envelope',   'text-indigo-500'],
                    ['Address',     $emp['address']      ?: '—', 'fa-map-marker-alt', 'text-red-500'],
                ];
                foreach ($personal as [$label, $value, $icon, $color]):
                ?>
                <div class="flex items-start gap-3 px-5 py-3">
                    <i class="fas <?php echo $icon; ?> <?php echo $color; ?> w-4 mt-0.5 text-sm"></i>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-gray-400"><?php echo $label; ?></p>
                        <p class="text-sm font-medium text-gray-800 break-all"><?php echo htmlspecialchars($value); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Employment Information -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center">
                    <i class="fas fa-briefcase text-green-600 text-sm"></i>
                </div>
                <h3 class="font-semibold text-gray-800">Employment</h3>
            </div>
            <div class="divide-y divide-gray-50">
                <?php
                $show_stat = ($emp['nationality'] === 'Malaysian') && !$is_intern;
                $employment = [
                    ['Department',    $emp['department'] ?: '—',                          'fa-building',       'text-indigo-500'],
                    ['Position',      $emp['position']   ?: '—',                          'fa-user-tie',       'text-blue-500'],
                    ['Employee Type', ucfirst($emp['employee_type'] ?? 'regular'),         'fa-id-badge',       'text-purple-500'],
                    ['Join Date',     date('d M Y', strtotime($emp['join_date'])),        'fa-calendar-alt',   'text-orange-500'],
                    ['Statutory',     $show_stat ? 'EPF, SOCSO, EIS, PCB' : 'Not applicable', 'fa-landmark', 'text-gray-500'],
                    ['Basic Salary',  'RM ' . number_format($emp['basic_salary'], 2),     'fa-money-bill-wave','text-green-600'],
                ];
                foreach ($employment as [$label, $value, $icon, $color]):
                ?>
                <div class="flex items-start gap-3 px-5 py-3">
                    <i class="fas <?php echo $icon; ?> <?php echo $color; ?> w-4 mt-0.5 text-sm"></i>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs text-gray-400"><?php echo $label; ?></p>
                        <p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($value); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Bank Information -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center">
                    <i class="fas fa-university text-orange-600 text-sm"></i>
                </div>
                <h3 class="font-semibold text-gray-800">Bank Information</h3>
            </div>
            <div class="divide-y divide-gray-50">
                <div class="flex items-start gap-3 px-5 py-3">
                    <i class="fas fa-university text-orange-500 w-4 mt-0.5 text-sm"></i>
                    <div>
                        <p class="text-xs text-gray-400">Bank Name</p>
                        <p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($emp['bank_name'] ?: '—'); ?></p>
                    </div>
                </div>
                <div class="flex items-start gap-3 px-5 py-3">
                    <i class="fas fa-credit-card text-orange-400 w-4 mt-0.5 text-sm"></i>
                    <div>
                        <p class="text-xs text-gray-400">Account Number</p>
                        <p class="text-sm font-medium text-gray-800 font-mono"><?php echo htmlspecialchars($emp['bank_account'] ?: '—'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Leave Balance -->
        <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center">
                    <i class="fas fa-calendar-check text-purple-600 text-sm"></i>
                </div>
                <h3 class="font-semibold text-gray-800">Leave Balance</h3>
            </div>
            <div class="p-5 space-y-4">
                <!-- Annual Leave -->
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-sm font-medium text-gray-700"><i class="fas fa-umbrella-beach text-blue-500 mr-1"></i> Annual Leave</span>
                        <span class="text-sm font-bold text-blue-600"><?php echo (int)$al_remain; ?> / <?php echo (int)($emp['annual_leave_entitlement'] ?? 0); ?> days</span>
                    </div>
                    <?php $al_pct = $emp['annual_leave_entitlement'] > 0 ? round(($al_remain / $emp['annual_leave_entitlement']) * 100) : 0; ?>
                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-blue-500 rounded-full transition-all" style="width:<?php echo $al_pct; ?>%"></div>
                    </div>
                    <p class="text-xs text-gray-400 mt-1"><?php echo (int)($emp['used_annual_leave'] ?? 0); ?> days used</p>
                </div>
                <!-- Medical Leave -->
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-sm font-medium text-gray-700"><i class="fas fa-hospital-user text-purple-500 mr-1"></i> Medical Leave</span>
                        <span class="text-sm font-bold text-purple-600"><?php echo (int)$ml_remain; ?> / <?php echo (int)($emp['medical_leave_entitlement'] ?? 0); ?> days</span>
                    </div>
                    <?php $ml_pct = $emp['medical_leave_entitlement'] > 0 ? round(($ml_remain / $emp['medical_leave_entitlement']) * 100) : 0; ?>
                    <div class="h-2 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-purple-500 rounded-full transition-all" style="width:<?php echo $ml_pct; ?>%"></div>
                    </div>
                    <p class="text-xs text-gray-400 mt-1"><?php echo (int)($emp['used_medical_leave'] ?? 0); ?> days used</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Leaves -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-teal-100 flex items-center justify-center">
                    <i class="fas fa-calendar-times text-teal-600 text-sm"></i>
                </div>
                <h3 class="font-semibold text-gray-800">Recent Leave Applications</h3>
            </div>
            <a href="manage_leave.php?search=<?php echo urlencode($emp['name']); ?>" class="text-xs text-blue-600 hover:underline">View all</a>
        </div>
        <?php
        $rl_rows = [];
        while ($rl = mysqli_fetch_assoc($recent_leaves)) $rl_rows[] = $rl;
        ?>
        <?php if (empty($rl_rows)): ?>
            <p class="text-center text-gray-400 text-sm py-8">No leave applications yet</p>
        <?php else: ?>
        <div class="divide-y divide-gray-50">
            <?php foreach ($rl_rows as $rl):
                $is_hd = ($rl['leave_type'] === 'HD');
                $sess  = ($is_hd && $rl['half_day'] != 'none') ? ' (' . ($rl['half_day'] == 'first_half' ? 'Morning' : 'Afternoon') . ')' : '';
                $ltype = $is_hd ? 'Half Day' . $sess : ucfirst($rl['leave_type']) . ' Leave' . $sess;
                $days  = $is_hd ? 'Half Day' : ($rl['total_days'] . ' day(s)');
                $sc    = $rl['status'] == 'approved' ? 'green' : ($rl['status'] == 'rejected' ? 'red' : 'yellow');
            ?>
            <div class="flex items-center gap-4 px-5 py-3">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($ltype); ?></p>
                    <p class="text-xs text-gray-400"><?php echo date('d M Y', strtotime($rl['start_date'])); ?> — <?php echo date('d M Y', strtotime($rl['end_date'])); ?> &bull; <?php echo $days; ?></p>
                </div>
                <span class="badge bg-<?php echo $sc; ?>-100 text-<?php echo $sc; ?>-700"><?php echo ucfirst($rl['status']); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Recent Claims -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-orange-100 flex items-center justify-center">
                    <i class="fas fa-receipt text-orange-600 text-sm"></i>
                </div>
                <h3 class="font-semibold text-gray-800">Recent Claims</h3>
            </div>
            <a href="manage_claim.php?search=<?php echo urlencode($emp['name']); ?>" class="text-xs text-blue-600 hover:underline">View all</a>
        </div>
        <?php
        $rc_rows = [];
        while ($rc = mysqli_fetch_assoc($recent_claims)) $rc_rows[] = $rc;
        ?>
        <?php if (empty($rc_rows)): ?>
            <p class="text-center text-gray-400 text-sm py-8">No claims submitted yet</p>
        <?php else: ?>
        <div class="divide-y divide-gray-50">
            <?php foreach ($rc_rows as $rc):
                $sc = $rc['status'] == 'approved' ? 'green' : ($rc['status'] == 'rejected' ? 'red' : 'yellow');
            ?>
            <div class="flex items-center gap-4 px-5 py-3">
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($rc['claim_type']); ?></p>
                    <p class="text-xs text-gray-400"><?php echo date('d M Y', strtotime($rc['applied_at'])); ?></p>
                </div>
                <p class="text-sm font-bold text-gray-700">RM <?php echo number_format($rc['amount'], 2); ?></p>
                <span class="badge bg-<?php echo $sc; ?>-100 text-<?php echo $sc; ?>-700"><?php echo ucfirst($rc['status']); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Recent Payroll -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center">
                    <i class="fas fa-money-check-alt text-green-600 text-sm"></i>
                </div>
                <h3 class="font-semibold text-gray-800">Recent Payroll</h3>
            </div>
            <a href="payroll.php" class="text-xs text-blue-600 hover:underline">View all</a>
        </div>
        <?php
        $rp_rows = [];
        while ($rp = mysqli_fetch_assoc($recent_payroll)) $rp_rows[] = $rp;
        ?>
        <?php if (empty($rp_rows)): ?>
            <p class="text-center text-gray-400 text-sm py-8">No payroll records yet</p>
        <?php else: ?>
        <div class="divide-y divide-gray-50">
            <?php foreach ($rp_rows as $rp): ?>
            <div class="flex items-center gap-4 px-5 py-4">
                <div class="w-10 h-10 rounded-xl bg-green-100 flex items-center justify-center shrink-0">
                    <i class="fas fa-calendar text-green-600 text-sm"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-semibold text-gray-800"><?php echo date('F Y', strtotime($rp['month_year'] . '-01')); ?></p>
                    <p class="text-xs text-gray-400">Basic: RM <?php echo number_format($rp['basic_salary'], 2); ?>
                        <?php if ($rp['unpaid_deduction'] > 0): ?>
                            &bull; <span class="text-red-500">Unpaid: -RM <?php echo number_format($rp['unpaid_deduction'], 2); ?></span>
                        <?php endif; ?>
                        <?php if ($rp['approved_claims'] > 0): ?>
                            &bull; <span class="text-green-500">Claims: +RM <?php echo number_format($rp['approved_claims'], 2); ?></span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-sm font-bold text-green-600">RM <?php echo number_format($rp['net_salary'], 2); ?></p>
                    <p class="text-xs text-gray-400">Net</p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- Mobile bottom nav -->
<div class="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-lg border-t border-gray-200 md:hidden shadow-2xl z-30">
    <div class="flex justify-around py-2">
        <a href="dashboard.php" class="flex flex-col items-center gap-1 py-1 px-3 text-gray-400 hover:text-blue-600 transition text-xs">
            <i class="fas fa-home text-lg"></i><span>Home</span>
        </a>
        <a href="employees.php" class="flex flex-col items-center gap-1 py-1 px-3 text-blue-600 transition text-xs">
            <i class="fas fa-users text-lg"></i><span>Staff</span>
        </a>
        <a href="management.php" class="flex flex-col items-center gap-1 py-1 px-3 text-gray-400 hover:text-blue-600 transition text-xs">
            <i class="fas fa-cog text-lg"></i><span>Manage</span>
        </a>
        <a href="payroll.php" class="flex flex-col items-center gap-1 py-1 px-3 text-gray-400 hover:text-blue-600 transition text-xs">
            <i class="fas fa-money-bill-wave text-lg"></i><span>Payroll</span>
        </a>
    </div>
</div>

</body>
</html>
