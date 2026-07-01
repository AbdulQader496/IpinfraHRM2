<?php
require_once '../includes/auth.php';
redirectIfNotAdmin();
require_once '../includes/db.php';

// Month navigation
$month = isset($_GET['m']) ? $_GET['m'] : date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');
$ts       = strtotime($month . '-01');
$year     = (int)date('Y', $ts);
$mon      = (int)date('m', $ts);
$prevM    = date('Y-m', strtotime('-1 month', $ts));
$nextM    = date('Y-m', strtotime('+1 month', $ts));
$daysInM  = (int)date('t', $ts);
$firstDay = (int)date('N', $ts); // 1=Mon … 7=Sun

$monthStart = "$year-" . str_pad($mon,2,'0',STR_PAD_LEFT) . "-01";
$monthEnd   = "$year-" . str_pad($mon,2,'0',STR_PAD_LEFT) . "-$daysInM";

// Approved & pending leaves that overlap this month
$leaves_q = mysqli_query($conn,
    "SELECT l.id, l.leave_type, l.start_date, l.end_date, l.status, e.name
     FROM leaves l JOIN employees e ON l.employee_id = e.id
     WHERE l.status IN ('approved','pending')
       AND l.start_date <= '$monthEnd'
       AND l.end_date   >= '$monthStart'
     ORDER BY l.start_date");

// Build a map: day → [ events ]
$events = [];
while ($row = mysqli_fetch_assoc($leaves_q)) {
    $s = max(strtotime($row['start_date']), $ts);
    $e = min(strtotime($row['end_date']),   strtotime($monthEnd));
    for ($d = $s; $d <= $e; $d = strtotime('+1 day', $d)) {
        $day = (int)date('j', $d);
        $events[$day][] = [
            'type'  => 'leave',
            'label' => $row['name'],
            'sub'   => $row['leave_type'],
            'status'=> $row['status'],
        ];
    }
}

// Approved claims this month (by applied_at date)
$claims_q = mysqli_query($conn,
    "SELECT c.claim_type, c.amount, c.status, e.name,
            DATE(c.applied_at) as cdate
     FROM claims c JOIN employees e ON c.employee_id = e.id
     WHERE c.status IN ('approved','pending')
       AND DATE(c.applied_at) BETWEEN '$monthStart' AND '$monthEnd'");
while ($row = mysqli_fetch_assoc($claims_q)) {
    $day = (int)date('j', strtotime($row['cdate']));
    $events[$day][] = [
        'type'  => 'claim',
        'label' => $row['name'],
        'sub'   => $row['claim_type'] . ' (RM ' . number_format($row['amount'],0) . ')',
        'status'=> $row['status'],
    ];
}

// Payroll processed this month
$pay_q = mysqli_query($conn,
    "SELECT e.name, p.net_salary, p.month_year
     FROM payroll p JOIN employees e ON p.employee_id = e.id
     WHERE p.month_year = '" . date('Y-m', $ts) . "'");
$pay_list = [];
while ($r = mysqli_fetch_assoc($pay_q)) $pay_list[] = $r;
if ($pay_list) {
    // Show payroll on day 1
    foreach ($pay_list as $r) {
        $events[1][] = [
            'type'  => 'payroll',
            'label' => $r['name'],
            'sub'   => 'RM ' . number_format($r['net_salary'],0),
            'status'=> 'done',
        ];
    }
}

// Public holidays this month
$hol_q = mysqli_query($conn,
    "SELECT holiday_date, holiday_name FROM holidays
     WHERE holiday_date BETWEEN '$monthStart' AND '$monthEnd'
     ORDER BY holiday_date");
$holidays = [];
while ($r = mysqli_fetch_assoc($hol_q)) {
    $holidays[(int)date('j', strtotime($r['holiday_date']))] = $r['holiday_name'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
<title>Calendar - IPINFRA HRM</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { font-family: 'Inter', sans-serif; }
.cal-day { min-height: 80px; }
@media (max-width: 640px) { .cal-day { min-height: 56px; } }
.evt { font-size:.6rem; border-radius:3px; padding:1px 4px; margin-top:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:100%; }
.evt-leave-approved { background:#dcfce7; color:#166534; }
.evt-leave-pending  { background:#fef9c3; color:#854d0e; }
.evt-claim-approved { background:#dbeafe; color:#1e40af; }
.evt-claim-pending  { background:#fef3c7; color:#92400e; }
.evt-payroll        { background:#ede9fe; color:#5b21b6; }
</style>
</head>
<body class="bg-gray-50 min-h-screen pb-20">

<!-- Header -->
<div class="bg-[#060912] text-white sticky top-0 z-40 shadow-2xl">
    <div class="flex justify-between items-center px-4 py-4">
        <div class="flex items-center gap-3">
            <button onclick="toggleSidebar()" class="text-white/80 hover:text-white p-2 rounded-full hover:bg-white/10">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg">
                <img src="../uploads/1775551018_4xzREYTcMvK7ReGODviudjeDBIofOQ78mr5DsN9g.jpg" alt="IPINFRA" style="width:28px;height:28px;object-fit:contain;border-radius:4px;background:#fff;">
            </div>
            <div>
                <p class="text-xs text-blue-200 font-medium">IPINFRA NETWORKS</p>
                <p class="text-sm font-bold">Admin Calendar</p>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/admin_sidebar.php'; ?>

<div class="px-4 py-5 max-w-5xl mx-auto">

    <!-- Month navigation -->
    <div class="flex items-center justify-between mb-4">
        <a href="?m=<?php echo $prevM ?>" class="flex items-center gap-1 px-3 py-2 bg-white rounded-xl shadow text-sm font-semibold text-gray-600 hover:bg-gray-50">
            <i class="fas fa-chevron-left text-xs"></i> Prev
        </a>
        <div class="text-center">
            <h2 class="text-xl font-bold text-gray-800"><?php echo date('F Y', $ts) ?></h2>
        </div>
        <a href="?m=<?php echo $nextM ?>" class="flex items-center gap-1 px-3 py-2 bg-white rounded-xl shadow text-sm font-semibold text-gray-600 hover:bg-gray-50">
            Next <i class="fas fa-chevron-right text-xs"></i>
        </a>
    </div>

    <!-- Legend -->
    <div class="flex flex-wrap gap-2 mb-4">
        <span class="evt evt-leave-approved">Approved Leave</span>
        <span class="evt evt-leave-pending">Pending Leave</span>
        <span class="evt evt-claim-approved">Approved Claim</span>
        <span class="evt evt-claim-pending">Pending Claim</span>
        <span class="evt evt-payroll">Payroll</span>
        <span class="flex items-center gap-1 text-xs font-semibold text-red-600 bg-red-50 px-2 py-0.5 rounded">
            <i class="fas fa-star text-xs"></i> Holiday
        </span>
    </div>

    <!-- Calendar grid -->
    <div class="bg-white rounded-2xl shadow overflow-hidden">
        <!-- Day headers -->
        <div class="grid grid-cols-7 text-center text-xs font-bold text-gray-500 bg-gray-50 border-b border-gray-100">
            <?php foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?>
            <div class="py-2"><?php echo $d ?></div>
            <?php endforeach ?>
        </div>

        <!-- Day cells -->
        <div class="grid grid-cols-7 border-l border-t border-gray-100">
            <?php
            // Empty cells before first day
            for ($i = 1; $i < $firstDay; $i++):
            ?>
            <div class="cal-day border-r border-b border-gray-100 bg-gray-50/50"></div>
            <?php endfor;

            for ($day = 1; $day <= $daysInM; $day++):
                $isToday    = ($day == date('j') && $year == date('Y') && $mon == date('n'));
                $isHoliday  = isset($holidays[$day]);
                $isWeekend  = in_array(date('N', mktime(0,0,0,$mon,$day,$year)), [6,7]);
                $dayEvents  = $events[$day] ?? [];
                $shown      = array_slice($dayEvents, 0, 3);
                $extra      = count($dayEvents) - count($shown);
            ?>
            <div class="cal-day border-r border-b border-gray-100 p-1 relative
                <?php echo $isToday   ? 'bg-indigo-50' : '' ?>
                <?php echo $isHoliday ? 'bg-red-50'    : '' ?>
                <?php echo $isWeekend && !$isHoliday ? 'bg-gray-50/70' : '' ?>">
                <!-- Day number -->
                <div class="flex items-center justify-between mb-0.5">
                    <span class="text-xs font-semibold leading-none
                        <?php echo $isToday   ? 'w-5 h-5 bg-indigo-600 text-white rounded-full flex items-center justify-center text-[10px]' : '' ?>
                        <?php echo $isHoliday && !$isToday ? 'text-red-600' : '' ?>
                        <?php echo !$isToday && !$isHoliday ? 'text-gray-700' : '' ?>">
                        <?php echo $day ?>
                    </span>
                    <?php if ($isHoliday): ?>
                    <span title="<?php echo htmlspecialchars($holidays[$day]) ?>" class="text-red-400 text-[9px]">
                        <i class="fas fa-star"></i>
                    </span>
                    <?php endif ?>
                </div>
                <?php if ($isHoliday): ?>
                <div class="text-[9px] text-red-500 font-semibold truncate leading-tight mb-0.5"><?php echo htmlspecialchars($holidays[$day]) ?></div>
                <?php endif ?>
                <!-- Events -->
                <?php foreach ($shown as $ev):
                    $cls = match(true) {
                        $ev['type']==='leave'   && $ev['status']==='approved' => 'evt-leave-approved',
                        $ev['type']==='leave'   && $ev['status']==='pending'  => 'evt-leave-pending',
                        $ev['type']==='claim'   && $ev['status']==='approved' => 'evt-claim-approved',
                        $ev['type']==='claim'   && $ev['status']==='pending'  => 'evt-claim-pending',
                        $ev['type']==='payroll'                               => 'evt-payroll',
                        default => 'evt-leave-approved',
                    };
                ?>
                <div class="evt <?php echo $cls ?>" title="<?php echo htmlspecialchars($ev['label'] . ' — ' . $ev['sub']) ?>">
                    <?php echo htmlspecialchars($ev['label']) ?>
                </div>
                <?php endforeach ?>
                <?php if ($extra > 0): ?>
                <div class="text-[9px] text-gray-400 mt-0.5">+<?php echo $extra ?> more</div>
                <?php endif ?>
            </div>
            <?php endfor;

            // Fill remaining cells to complete last row
            $totalCells = $firstDay - 1 + $daysInM;
            $remaining  = (7 - ($totalCells % 7)) % 7;
            for ($i = 0; $i < $remaining; $i++):
            ?>
            <div class="cal-day border-r border-b border-gray-100 bg-gray-50/50"></div>
            <?php endfor ?>
        </div>
    </div>

    <!-- Event list for the month -->
    <?php
    // Collect all events sorted by date for the list view
    $list = [];

    // Leaves
    $lq = mysqli_query($conn,
        "SELECT l.leave_type, l.start_date, l.end_date, l.status, e.name
         FROM leaves l JOIN employees e ON l.employee_id = e.id
         WHERE l.status IN ('approved','pending')
           AND l.start_date <= '$monthEnd' AND l.end_date >= '$monthStart'
         ORDER BY l.start_date");
    while ($r = mysqli_fetch_assoc($lq)) {
        $list[] = ['date'=>$r['start_date'], 'type'=>'leave', 'name'=>$r['name'],
                   'desc'=>$r['leave_type'], 'end'=>$r['end_date'], 'status'=>$r['status']];
    }

    // Claims
    $cq = mysqli_query($conn,
        "SELECT c.claim_type, c.amount, c.status, e.name, DATE(c.applied_at) as d
         FROM claims c JOIN employees e ON c.employee_id = e.id
         WHERE c.status IN ('approved','pending')
           AND DATE(c.applied_at) BETWEEN '$monthStart' AND '$monthEnd'
         ORDER BY c.applied_at");
    while ($r = mysqli_fetch_assoc($cq)) {
        $list[] = ['date'=>$r['d'], 'type'=>'claim', 'name'=>$r['name'],
                   'desc'=>$r['claim_type'] . ' — RM ' . number_format($r['amount'],2),
                   'end'=>null, 'status'=>$r['status']];
    }

    usort($list, fn($a,$b) => strcmp($a['date'], $b['date']));
    $total   = count($list);
    $preview = array_slice($list, 0, 10);
    $hasMore = $total > 10;
    ?>

    <?php if ($list): ?>
    <div class="mt-6">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-bold text-gray-700">
                <i class="fas fa-list mr-1 text-indigo-500"></i>
                Events this month
                <span class="ml-1 text-xs font-normal text-gray-400">(<?php echo $total ?>)</span>
            </h3>
            <?php if ($hasMore): ?>
            <button onclick="toggleAllEvents()" id="viewAllBtn"
                    class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                View all <?php echo $total ?>
            </button>
            <?php endif ?>
        </div>
        <div class="space-y-2" id="evtPreview">
        <?php foreach ($preview as $i => $ev):
            $badgeClass = match(true) {
                $ev['type']==='leave'  && $ev['status']==='approved' => 'bg-green-100 text-green-700',
                $ev['type']==='leave'  && $ev['status']==='pending'  => 'bg-yellow-100 text-yellow-700',
                $ev['type']==='claim'  && $ev['status']==='approved' => 'bg-blue-100 text-blue-700',
                $ev['type']==='claim'  && $ev['status']==='pending'  => 'bg-amber-100 text-amber-700',
                default => 'bg-purple-100 text-purple-700',
            };
            $icon = $ev['type']==='leave' ? 'fa-calendar-check' : 'fa-receipt';
        ?>
        <div class="bg-white rounded-xl px-4 py-3 shadow-sm flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center <?php echo $badgeClass ?> flex-shrink-0">
                <i class="fas <?php echo $icon ?> text-xs"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($ev['name']) ?></p>
                <p class="text-xs text-gray-500">
                    <?php echo htmlspecialchars($ev['desc']) ?>
                    &nbsp;·&nbsp;
                    <?php echo date('d M', strtotime($ev['date'])) ?>
                    <?php if ($ev['end'] && $ev['end'] !== $ev['date']): ?>
                        – <?php echo date('d M', strtotime($ev['end'])) ?>
                    <?php endif ?>
                </p>
            </div>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?php echo $badgeClass ?>">
                <?php echo ucfirst($ev['status']) ?>
            </span>
        </div>
        <?php endforeach ?>
        </div>

        <?php if ($hasMore): ?>
        <div id="evtAll" class="space-y-2 mt-2 hidden">
        <?php foreach (array_slice($list, 10) as $ev):
            $badgeClass = match(true) {
                $ev['type']==='leave'  && $ev['status']==='approved' => 'bg-green-100 text-green-700',
                $ev['type']==='leave'  && $ev['status']==='pending'  => 'bg-yellow-100 text-yellow-700',
                $ev['type']==='claim'  && $ev['status']==='approved' => 'bg-blue-100 text-blue-700',
                $ev['type']==='claim'  && $ev['status']==='pending'  => 'bg-amber-100 text-amber-700',
                default => 'bg-purple-100 text-purple-700',
            };
            $icon = $ev['type']==='leave' ? 'fa-calendar-check' : 'fa-receipt';
        ?>
        <div class="bg-white rounded-xl px-4 py-3 shadow-sm flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg flex items-center justify-center <?php echo $badgeClass ?> flex-shrink-0">
                <i class="fas <?php echo $icon ?> text-xs"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($ev['name']) ?></p>
                <p class="text-xs text-gray-500">
                    <?php echo htmlspecialchars($ev['desc']) ?>
                    &nbsp;·&nbsp;
                    <?php echo date('d M', strtotime($ev['date'])) ?>
                    <?php if ($ev['end'] && $ev['end'] !== $ev['date']): ?>
                        – <?php echo date('d M', strtotime($ev['end'])) ?>
                    <?php endif ?>
                </p>
            </div>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?php echo $badgeClass ?>">
                <?php echo ucfirst($ev['status']) ?>
            </span>
        </div>
        <?php endforeach ?>
        </div>
        <button onclick="toggleAllEvents()" id="collapseBtn"
                class="hidden w-full mt-2 text-xs font-semibold text-gray-400 hover:text-gray-600 py-1">
            Show less
        </button>
        <?php endif ?>
    </div>
    <?php else: ?>
    <div class="mt-6 text-center text-gray-400 text-sm py-8">
        <i class="fas fa-calendar-times text-3xl mb-2 block"></i>
        No events this month
    </div>
    <?php endif ?>

    <!-- Holidays management link -->
    <div class="mt-4 text-center">
        <a href="holidays.php" class="text-xs text-indigo-500 hover:text-indigo-700 font-semibold">
            <i class="fas fa-cog mr-1"></i> Manage Public Holidays
        </a>
    </div>

</div>
<script>
function toggleAllEvents() {
    var all     = document.getElementById('evtAll');
    var viewBtn = document.getElementById('viewAllBtn');
    var colBtn  = document.getElementById('collapseBtn');
    if (!all) return;
    var open = !all.classList.contains('hidden');
    all.classList.toggle('hidden', open);
    if (viewBtn) viewBtn.classList.toggle('hidden', !open);
    if (colBtn)  colBtn.classList.toggle('hidden', open);
}
</script>
</body>
</html>
