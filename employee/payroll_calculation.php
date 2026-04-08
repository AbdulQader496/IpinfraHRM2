<?php
require_once '../includes/auth.php';
redirectIfNotLoggedIn();
require_once '../includes/db.php';
require_once '../includes/functions.php';

$user_id = $_SESSION['user_id'];
$selected_month = isset($_GET['month']) ? $_GET['month'] : date('Y-m');

// Get employee details
$emp_query = mysqli_query($conn, "SELECT * FROM employees WHERE id = $user_id");
$employee = mysqli_fetch_assoc($emp_query);

// Get month range
$month_start = date('Y-m-01', strtotime($selected_month . '-01'));
$month_end = date('Y-m-t', strtotime($selected_month . '-01'));
$month_name = date('F Y', strtotime($selected_month . '-01'));
$working_days_in_month = date('t', strtotime($selected_month . '-01'));

// Get attendance records
$attendance_query = mysqli_query($conn, "SELECT * FROM attendance 
    WHERE employee_id = $user_id 
    AND date BETWEEN '$month_start' AND '$month_end'
    ORDER BY date ASC");

$attendance_records = [];
$present_days = 0;
$absent_days = 0;
$half_days = 0;
$late_days = 0;
$total_working_hours = 0;
$total_overtime_hours = 0;

while ($att = mysqli_fetch_assoc($attendance_query)) {
    $attendance_records[] = $att;
    
    if ($att['clock_in']) {
        $present_days++;
        if ($att['status'] == 'late') $late_days++;
        
        if ($att['clock_in'] && $att['clock_out']) {
            $start = new DateTime($att['clock_in']);
            $end = new DateTime($att['clock_out']);
            $diff = $start->diff($end);
            $hours = $diff->h + ($diff->i / 60);
            $total_working_hours += $hours;
            
            $office_end = new DateTime('18:00:00');
            if ($end > $office_end) {
                $overtime_diff = $office_end->diff($end);
                $overtime_hours = $overtime_diff->h + ($overtime_diff->i / 60);
                $total_overtime_hours += $overtime_hours;
            }
        }
    } else {
        $absent_days++;
    }
}

// Get leaves
$leaves_query = mysqli_query($conn, "SELECT * FROM leaves 
    WHERE employee_id = $user_id 
    AND status = 'approved'
    AND ((start_date BETWEEN '$month_start' AND '$month_end')
    OR (end_date BETWEEN '$month_start' AND '$month_end'))");

$paid_leave_days = 0;
$unpaid_leave_days = 0;

while ($leave = mysqli_fetch_assoc($leaves_query)) {
    $leave_start = max(strtotime($leave['start_date']), strtotime($month_start));
    $leave_end = min(strtotime($leave['end_date']), strtotime($month_end));
    $days = ($leave_end - $leave_start) / 86400 + 1;
    
    if ($leave['leave_type'] == 'unpaid') {
        $unpaid_leave_days += $days;
    } else {
        $paid_leave_days += $days;
    }
}

// Calculations
$total_unpaid_days = $unpaid_leave_days + $absent_days + ($half_days * 0.5);
$basic_salary = $employee['basic_salary'];
$per_day_salary = $basic_salary / $working_days_in_month;
$unpaid_deduction = $per_day_salary * $total_unpaid_days;
$hourly_rate = $basic_salary / ($working_days_in_month * 8);
$overtime_amount = $total_overtime_hours * $hourly_rate * 1.5;

// Statutory deductions
$is_malaysian = ($employee['nationality'] == 'Malaysian');
$epf = calculateEPF($basic_salary, true, $is_malaysian);
$socso = calculateSOCSO($basic_salary, true, $is_malaysian);
$eis = calculateEIS($basic_salary, $is_malaysian);
$pcb = calculatePCB($basic_salary, $is_malaysian);

$total_deductions = $epf + $socso + $eis + $pcb;
$net_salary = $basic_salary - $unpaid_deduction + $overtime_amount - $total_deductions;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Calculation - <?php echo $employee['name']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        @media print {
            .no-print { display: none; }
            body { background: white; padding: 0; margin: 0; }
            .print-padding { padding: 20px; }
        }
    </style>
</head>
<body class="bg-gray-100 p-4">
    <div class="max-w-5xl mx-auto">
        <!-- Buttons - No Print -->
        <div class="no-print text-right mb-4 flex justify-end gap-2">
            <button onclick="window.print()" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition flex items-center gap-2">
                <i class="fas fa-print"></i> Print / Save as PDF
            </button>
            <button onclick="window.close()" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition flex items-center gap-2">
                <i class="fas fa-times"></i> Close
            </button>
        </div>

        <!-- Main Card -->
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden" id="payrollContent">
            
            <!-- Header -->
            <div class="bg-gradient-to-r from-blue-800 to-indigo-800 text-white p-6 text-center">
                <div class="flex justify-center mb-3">
                    <div class="w-16 h-16 bg-white rounded-xl flex items-center justify-center">
                        <span class="text-blue-800 font-bold text-2xl">IN</span>
                    </div>
                </div>
                <h1 class="text-2xl font-bold">IPINFRA NETWORKS SDN BHD</h1>
                <p class="text-blue-200 text-sm">Payroll Calculation for <?php echo $month_name; ?></p>
            </div>

            <!-- Employee Info -->
            <div class="p-6 border-b">
                <div class="flex justify-between items-start flex-wrap gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-800"><?php echo $employee['name']; ?></h2>
                        <p class="text-gray-500 text-sm"><?php echo $employee['employee_id']; ?> • <?php echo $employee['department']; ?> • <?php echo $employee['position']; ?></p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-500">Pay Period</p>
                        <p class="font-semibold"><?php echo date('d/m/Y', strtotime($month_start)); ?> - <?php echo date('d/m/Y', strtotime($month_end)); ?></p>
                        <p class="text-xs text-gray-400">Working Days: <?php echo $working_days_in_month; ?></p>
                    </div>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 p-6 bg-gray-50">
                <div class="text-center">
                    <p class="text-xs text-gray-500">Basic Salary</p>
                    <p class="text-xl font-bold text-blue-600">RM <?php echo number_format($basic_salary, 2); ?></p>
                </div>
                <div class="text-center">
                    <p class="text-xs text-gray-500">Per Day Salary</p>
                    <p class="text-xl font-bold text-gray-800">RM <?php echo number_format($per_day_salary, 2); ?></p>
                </div>
                <div class="text-center">
                    <p class="text-xs text-gray-500">Overtime Hours</p>
                    <p class="text-xl font-bold text-orange-600"><?php echo number_format($total_overtime_hours, 1); ?>h</p>
                </div>
                <div class="text-center">
                    <p class="text-xs text-gray-500">Net Salary</p>
                    <p class="text-xl font-bold text-green-600">RM <?php echo number_format($net_salary, 2); ?></p>
                </div>
            </div>

            <!-- Attendance Summary -->
            <div class="p-6 border-b">
                <h3 class="font-bold text-gray-800 mb-4"><i class="fas fa-calendar-check text-blue-600 mr-2"></i> Attendance Summary</h3>
                <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
                    <div class="bg-green-50 p-3 rounded-lg text-center">
                        <p class="text-2xl font-bold text-green-600"><?php echo $present_days; ?></p>
                        <p class="text-xs text-gray-600">Present</p>
                    </div>
                    <div class="bg-yellow-50 p-3 rounded-lg text-center">
                        <p class="text-2xl font-bold text-yellow-600"><?php echo $half_days; ?></p>
                        <p class="text-xs text-gray-600">Half Days</p>
                    </div>
                    <div class="bg-red-50 p-3 rounded-lg text-center">
                        <p class="text-2xl font-bold text-red-600"><?php echo $absent_days; ?></p>
                        <p class="text-xs text-gray-600">Absent</p>
                    </div>
                    <div class="bg-blue-50 p-3 rounded-lg text-center">
                        <p class="text-2xl font-bold text-blue-600"><?php echo $paid_leave_days; ?></p>
                        <p class="text-xs text-gray-600">Paid Leave</p>
                    </div>
                    <div class="bg-purple-50 p-3 rounded-lg text-center">
                        <p class="text-2xl font-bold text-purple-600"><?php echo $unpaid_leave_days; ?></p>
                        <p class="text-xs text-gray-600">Unpaid Leave</p>
                    </div>
                    <div class="bg-orange-50 p-3 rounded-lg text-center">
                        <p class="text-2xl font-bold text-orange-600"><?php echo $late_days; ?></p>
                        <p class="text-xs text-gray-600">Late</p>
                    </div>
                </div>
                <div class="mt-3 p-3 bg-gray-100 rounded-lg">
                    <p class="text-sm"><strong>Total Unpaid Days:</strong> <?php echo number_format($total_unpaid_days, 2); ?> days</p>
                    <p class="text-xs text-gray-500">(Unpaid Leaves: <?php echo $unpaid_leave_days; ?> + Absent: <?php echo $absent_days; ?> + Half Days: <?php echo $half_days * 0.5; ?>)</p>
                </div>
            </div>

            <!-- Daily Attendance Records -->
            <div class="p-6 border-b">
                <h3 class="font-bold text-gray-800 mb-4"><i class="fas fa-list-alt text-blue-600 mr-2"></i> Daily Attendance Records</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="p-2 text-left">Date</th>
                                <th class="p-2 text-left">Day</th>
                                <th class="p-2 text-left">Clock In</th>
                                <th class="p-2 text-left">Clock Out</th>
                                <th class="p-2 text-left">Hours</th>
                                <th class="p-2 text-left">Overtime</th>
                                <th class="p-2 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_records as $att): 
                                $hours_worked = 0;
                                $overtime = 0;
                                if ($att['clock_in'] && $att['clock_out']) {
                                    $start = new DateTime($att['clock_in']);
                                    $end = new DateTime($att['clock_out']);
                                    $diff = $start->diff($end);
                                    $hours_worked = $diff->h + ($diff->i / 60);
                                    
                                    $office_end = new DateTime('18:00:00');
                                    if ($end > $office_end) {
                                        $overtime_diff = $office_end->diff($end);
                                        $overtime = $overtime_diff->h + ($overtime_diff->i / 60);
                                    }
                                }
                                $day_name = date('D', strtotime($att['date']));
                            ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="p-2"><?php echo date('d-m-Y', strtotime($att['date'])); ?></td>
                                <td class="p-2"><?php echo $day_name; ?></td>
                                <td class="p-2"><?php echo $att['clock_in'] ? date('H:i', strtotime($att['clock_in'])) : '-'; ?></td>
                                <td class="p-2"><?php echo $att['clock_out'] ? date('H:i', strtotime($att['clock_out'])) : '-'; ?></td>
                                <td class="p-2"><?php echo $hours_worked > 0 ? number_format($hours_worked, 2) . 'h' : '-'; ?></td>
                                <td class="p-2"><?php echo $overtime > 0 ? number_format($overtime, 2) . 'h' : '-'; ?></td>
                                <td class="p-2">
                                    <?php if (!$att['clock_in']): ?>
                                        <span class="px-2 py-1 rounded-full text-xs bg-red-100 text-red-700">Absent</span>
                                    <?php elseif ($att['status'] == 'late'): ?>
                                        <span class="px-2 py-1 rounded-full text-xs bg-yellow-100 text-yellow-700">Late</span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 rounded-full text-xs bg-green-100 text-green-700">Present</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Earnings and Deductions -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6 border-b">
                <div>
                    <h3 class="font-bold text-gray-800 mb-3"><i class="fas fa-plus-circle text-green-600 mr-2"></i> Earnings</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between py-2 border-b">
                            <span>Basic Salary</span>
                            <span class="font-semibold">RM <?php echo number_format($basic_salary, 2); ?></span>
                        </div>
                        <div class="flex justify-between py-2 border-b">
                            <span>Overtime (<?php echo number_format($total_overtime_hours, 1); ?> hrs @ 1.5x)</span>
                            <span class="font-semibold">RM <?php echo number_format($overtime_amount, 2); ?></span>
                        </div>
                        <div class="flex justify-between py-2 font-bold text-green-600 border-t-2">
                            <span>Total Earnings</span>
                            <span>RM <?php echo number_format($basic_salary + $overtime_amount, 2); ?></span>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="font-bold text-gray-800 mb-3"><i class="fas fa-minus-circle text-red-600 mr-2"></i> Deductions</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between py-2 border-b">
                            <span>Unpaid Leave (<?php echo number_format($total_unpaid_days, 2); ?> days)</span>
                            <span class="text-red-600">- RM <?php echo number_format($unpaid_deduction, 2); ?></span>
                        </div>
                        <?php if ($is_malaysian): ?>
                        <div class="flex justify-between py-2 border-b">
                            <span>EPF (11%)</span>
                            <span class="text-red-600">- RM <?php echo number_format($epf, 2); ?></span>
                        </div>
                        <div class="flex justify-between py-2 border-b">
                            <span>SOCSO (0.5%)</span>
                            <span class="text-red-600">- RM <?php echo number_format($socso, 2); ?></span>
                        </div>
                        <div class="flex justify-between py-2 border-b">
                            <span>EIS (0.2%)</span>
                            <span class="text-red-600">- RM <?php echo number_format($eis, 2); ?></span>
                        </div>
                        <div class="flex justify-between py-2 border-b">
                            <span>PCB</span>
                            <span class="text-red-600">- RM <?php echo number_format($pcb, 2); ?></span>
                        </div>
                        <?php else: ?>
                        <div class="flex justify-between py-2 border-b">
                            <span>Statutory Deductions</span>
                            <span class="text-gray-500">Not applicable (Non-Malaysian)</span>
                        </div>
                        <?php endif; ?>
                        <div class="flex justify-between py-2 font-bold text-red-600 border-t-2">
                            <span>Total Deductions</span>
                            <span>- RM <?php echo number_format($unpaid_deduction + $total_deductions, 2); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Final Calculation -->
            <div class="p-6 bg-gray-50">
                <h3 class="font-bold text-gray-800 mb-4"><i class="fas fa-calculator text-blue-600 mr-2"></i> Final Calculation</h3>
                <div class="space-y-2 max-w-md mx-auto">
                    <div class="flex justify-between py-2">
                        <span>Base Salary</span>
                        <span>RM <?php echo number_format($basic_salary, 2); ?></span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span>Add: Overtime Amount</span>
                        <span class="text-green-600">+ RM <?php echo number_format($overtime_amount, 2); ?></span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span>Less: Unpaid Leave Deduction</span>
                        <span class="text-red-600">- RM <?php echo number_format($unpaid_deduction, 2); ?></span>
                    </div>
                    <div class="flex justify-between py-2">
                        <span>Less: Statutory Deductions</span>
                        <span class="text-red-600">- RM <?php echo number_format($total_deductions, 2); ?></span>
                    </div>
                    <div class="flex justify-between py-3 border-t-2 border-blue-300 font-bold text-lg">
                        <span>Net Salary</span>
                        <span class="text-green-600">RM <?php echo number_format($net_salary, 2); ?></span>
                    </div>
                </div>
                
                <div class="mt-4 p-3 bg-blue-50 rounded-lg text-xs text-blue-700">
                    <p class="font-semibold">Calculation Formula:</p>
                    <p>Net Salary = (Basic Salary + Overtime) - (Per Day Salary × Unpaid Days) - (EPF + SOCSO + EIS + PCB)</p>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-4 text-center text-xs text-gray-400 border-t">
                <p>IPINFRA Networks Sdn Bhd - Payroll System</p>
                <p>Generated on: <?php echo date('d/m/Y H:i:s'); ?></p>
            </div>
        </div>
    </div>

    <script>
        // Simple print function - works perfectly to save as PDF
        function printPage() {
            window.print();
        }
    </script>
</body>
</html>