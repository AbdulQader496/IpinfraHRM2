<?php
require_once '../includes/auth.php';
redirectIfNotLoggedIn();
require_once '../includes/db.php';
require_once '../includes/toast_fn.php';

$user_id  = $_SESSION['user_id'];
$today    = date('Y-m-d');
$now_time = date('H:i:s');
$now_day  = date('l');

$is_weekend       = in_array($now_day, ['Saturday', 'Sunday']);
$grace_period_end = '10:00:00';
$is_late          = ($now_time > $grace_period_end);
$work_target_h    = 8.5; // 9:30 AM – 6:00 PM

// ── Today's record ──────────────────────────────────────────────
$attendance = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT * FROM attendance WHERE employee_id = $user_id AND date = '$today'")
);

// ── Clock In ────────────────────────────────────────────────────
if (isset($_POST['clock_in']) && !$attendance && !$is_weekend) {
    $status = $is_late ? 'late' : 'present';
    mysqli_query($conn, "INSERT INTO attendance (employee_id, date, clock_in, status)
                         VALUES ($user_id, '$today', '$now_time', '$status')");
    header('Location: clock.php'); exit();
}

// ── Clock Out ───────────────────────────────────────────────────
if (isset($_POST['clock_out']) && $attendance && !$attendance['clock_out'] && !$is_weekend) {
    mysqli_query($conn, "UPDATE attendance SET clock_out = '$now_time' WHERE id = {$attendance['id']}");
    header('Location: clock.php'); exit();
}

// ── Undo Clock Out (same day only) ──────────────────────────────
if (isset($_POST['undo_clockout']) && $attendance && $attendance['clock_out'] && $attendance['date'] === $today) {
    mysqli_query($conn, "UPDATE attendance SET clock_out = NULL WHERE id = {$attendance['id']}");
    header('Location: clock.php'); exit();
}

// ── Computed helpers ────────────────────────────────────────────
$clock_in_ts  = ($attendance && $attendance['clock_in'])  ? strtotime($attendance['clock_in'])  : null;
$clock_out_ts = ($attendance && $attendance['clock_out']) ? strtotime($attendance['clock_out']) : null;
$expected_end = $clock_in_ts ? date('h:i A', $clock_in_ts + (int)($work_target_h * 3600)) : '';

$total_hours_str = '—';
$total_mins_worked = 0;
if ($clock_in_ts && $clock_out_ts) {
    $total_mins_worked = (int)(($clock_out_ts - $clock_in_ts) / 60);
    $h = floor($total_mins_worked / 60);
    $m = $total_mins_worked % 60;
    $total_hours_str = $h . 'h ' . str_pad($m, 2, '0', STR_PAD_LEFT) . 'm';
}

// ── Weekly data (Mon–Sun) ───────────────────────────────────────
$week_mon  = date('Y-m-d', strtotime('monday this week'));
$week_data = [];
$wr_res = mysqli_query($conn, "SELECT * FROM attendance WHERE employee_id = $user_id
    AND date >= '$week_mon' AND date <= DATE_ADD('$week_mon', INTERVAL 6 DAY) ORDER BY date ASC");
while ($wr = mysqli_fetch_assoc($wr_res)) $week_data[$wr['date']] = $wr;

$week_total_mins = 0;
foreach ($week_data as $wr) {
    if ($wr['clock_in'] && $wr['clock_out'])
        $week_total_mins += (strtotime($wr['clock_out']) - strtotime($wr['clock_in'])) / 60;
}
$week_h = floor($week_total_mins / 60);
$week_m = $week_total_mins % 60;

// ── Monthly stats ───────────────────────────────────────────────
$month_start = date('Y-m-01');
$ms = mysqli_fetch_assoc(mysqli_query($conn, "
    SELECT COUNT(*) as days_present,
           SUM(CASE WHEN status='late' THEN 1 ELSE 0 END) as late_days,
           SUM(CASE WHEN clock_out IS NOT NULL
               THEN TIMESTAMPDIFF(MINUTE, clock_in, clock_out) ELSE 0 END) as total_minutes
    FROM attendance WHERE employee_id = $user_id AND date >= '$month_start' AND clock_in IS NOT NULL
"));
$month_avg = ($ms['days_present'] > 0) ? round($ms['total_minutes'] / 60 / $ms['days_present'], 1) : 0;
$month_h   = floor(($ms['total_minutes'] ?? 0) / 60);
$month_m   = ($ms['total_minutes'] ?? 0) % 60;

// ── Attendance history (paginated) ──────────────────────────────
$page         = max(1, (int)($_GET['page'] ?? 1));
$per_page     = (int)($_GET['per_page'] ?? 10);
$month_filter = preg_replace('/[^0-9\-]/', '', $_GET['month'] ?? '');

$hw = "WHERE employee_id = $user_id";
if ($month_filter) $hw .= " AND date LIKE '$month_filter%'";

$total_rows  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM attendance $hw"))['total'];
$total_pages = $per_page > 0 ? (int)ceil($total_rows / $per_page) : 1;
$offset      = ($page - 1) * $per_page;
$history     = mysqli_query($conn, "SELECT * FROM attendance $hw ORDER BY date DESC LIMIT $offset, $per_page");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
<title>Attendance — IPINFRA HRM</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { font-family: 'Inter', sans-serif; }
.mono { font-family: 'JetBrains Mono', 'Courier New', monospace; font-weight: 700; }

/* Dark live-clock card */
.clock-card { background: linear-gradient(135deg, #0f172a 0%, #1e293b 55%, #0f172a 100%); }

/* Colon blink */
@keyframes colon-blink { 0%,100%{opacity:1} 50%{opacity:.15} }
.colon-blink { animation: colon-blink 1s ease-in-out infinite; }

/* Status cards */
.card-idle    { background: linear-gradient(135deg,#f8fafc,#f1f5f9); }
.card-working { background: linear-gradient(135deg,#064e3b,#065f46 55%,#047857); }
.card-late    { background: linear-gradient(135deg,#78350f,#92400e 55%,#b45309); }
.card-done    { background: linear-gradient(135deg,#1e1b4b,#1e3a8a 55%,#1d4ed8); }
.card-weekend { background: linear-gradient(135deg,#4a044e,#6b21a8 55%,#7c3aed); }

/* Duration glow while working */
@keyframes dur-glow { 0%,100%{text-shadow:0 0 20px rgba(52,211,153,.35)} 50%{text-shadow:0 0 45px rgba(52,211,153,.7)} }
.dur-glow { animation: dur-glow 2.5s ease-in-out infinite; }

/* Pulsing ring for clock-in button */
@keyframes ping-ring { 0%{transform:scale(1);opacity:.7} 70%,100%{transform:scale(1.5);opacity:0} }
.ping-ring { animation: ping-ring 2s ease-out infinite; }

/* Overtime pulse */
@keyframes ot-pulse { 0%,100%{box-shadow:0 0 12px rgba(239,68,68,.4)} 50%{box-shadow:0 0 28px rgba(239,68,68,.8)} }
.ot-pulse { animation: ot-pulse 1.8s ease-in-out infinite; }

/* Progress bar fill animation */
@keyframes bar-fill { from{width:0} }
.bar-fill { animation: bar-fill .9s ease-out forwards; }

/* Smooth stat card hover */
.stat-card { transition: transform .18s ease, box-shadow .18s ease; }
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 28px rgba(0,0,0,.12); }

/* History row */
.hist-row { transition: background .15s ease; }
.hist-row:hover { background: #f8fafc; }

/* Page fade-in */
@keyframes fade-up { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:none} }
.fade-up { animation: fade-up .4s ease both; }
.fade-up-2 { animation: fade-up .4s .1s ease both; }
.fade-up-3 { animation: fade-up .4s .2s ease both; }
.fade-up-4 { animation: fade-up .4s .3s ease both; }
</style>
</head>
<body class="bg-gray-50 min-h-screen pb-24">

<?php require_once '../includes/global_ui.php'; ?>
<?php require_once '../includes/toast.php'; ?>
<?php require_once '../includes/confirm_modal.php'; ?>

<!-- ══════════════════════════════════════
     HEADER
══════════════════════════════════════ -->
<header class="bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white sticky top-0 z-40 shadow-2xl">
    <div class="flex items-center justify-between px-4 py-3.5 max-w-3xl mx-auto">
        <div class="flex items-center gap-3">
            <button onclick="toggleSidebar()" class="w-9 h-9 rounded-xl bg-white/10 flex items-center justify-center hover:bg-white/20 transition">
                <i class="fas fa-bars"></i>
            </button>
            <div class="w-9 h-9 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg">
                <i class="fas fa-clock text-sm"></i>
            </div>
            <div class="hidden sm:block">
                <p class="text-[10px] text-blue-300 font-semibold tracking-widest uppercase">IPINFRA Networks</p>
                <p class="text-sm font-bold">Attendance</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span class="hidden sm:block text-xs text-blue-200"><?php echo htmlspecialchars($_SESSION['user_name'], ENT_QUOTES, 'UTF-8'); ?></span>
            <div class="w-9 h-9 bg-gradient-to-br from-indigo-500 to-purple-600 rounded-full flex items-center justify-center font-bold text-sm shadow-lg">
                <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
            </div>
        </div>
    </div>
    <div class="h-px bg-gradient-to-r from-transparent via-indigo-400/50 to-transparent"></div>
</header>

<?php require_once '../includes/employee_sidebar.php'; ?>

<!-- ══════════════════════════════════════
     MAIN CONTENT
══════════════════════════════════════ -->
<div class="max-w-2xl mx-auto px-4 py-5 space-y-4">

<!-- ① Premium Live Clock ────────────────────────────────────── -->
<div class="clock-card rounded-2xl shadow-2xl p-6 text-center relative overflow-hidden fade-up">
    <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
        <div class="w-72 h-72 rounded-full bg-indigo-600/10 blur-3xl"></div>
    </div>
    <p class="text-slate-400 text-[10px] font-bold uppercase tracking-widest mb-3 relative">
        <i class="fas fa-satellite-dish mr-1 text-indigo-400"></i> Live Time
    </p>
    <div class="flex items-center justify-center gap-1 relative mb-2">
        <span id="lc-h" class="mono text-5xl sm:text-6xl text-white tabular-nums">--</span>
        <span class="mono text-4xl text-indigo-400 colon-blink leading-none">:</span>
        <span id="lc-m" class="mono text-5xl sm:text-6xl text-white tabular-nums">--</span>
        <span class="mono text-4xl text-indigo-400 colon-blink leading-none">:</span>
        <span id="lc-s" class="mono text-5xl sm:text-6xl text-white tabular-nums">--</span>
    </div>
    <p id="lc-date" class="text-slate-300 text-sm font-medium tracking-wide relative">
        <?php echo date('l, d F Y'); ?>
    </p>
    <?php if ($is_weekend): ?>
    <div class="inline-flex items-center gap-1.5 mt-3 bg-purple-500/20 text-purple-300 text-xs px-3 py-1 rounded-full">
        <i class="fas fa-umbrella-beach"></i> Weekend — No attendance required
    </div>
    <?php elseif ($is_late && !$attendance): ?>
    <div class="inline-flex items-center gap-1.5 mt-3 bg-red-500/20 text-red-300 text-xs px-3 py-1 rounded-full">
        <i class="fas fa-exclamation-triangle"></i> After grace period — will be marked LATE
    </div>
    <?php else: ?>
    <div class="inline-flex items-center gap-1.5 mt-3 bg-green-500/15 text-green-300 text-xs px-3 py-1 rounded-full">
        <i class="fas fa-building mr-1"></i> Office: 9:30 AM – 6:00 PM &nbsp;·&nbsp; Grace: until 10:00 AM
    </div>
    <?php endif; ?>
</div>

<!-- ② Adaptive State Card ───────────────────────────────────── -->
<?php if ($is_weekend): ?>
<!-- WEEKEND -->
<div class="card-weekend rounded-2xl shadow-xl p-8 text-center fade-up-2">
    <div class="w-20 h-20 bg-white/15 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <i class="fas fa-umbrella-beach text-4xl text-purple-200"></i>
    </div>
    <h2 class="text-white text-xl font-bold mb-1">Enjoy Your Weekend!</h2>
    <p class="text-purple-200 text-sm">No clock-in required on <?php echo $now_day; ?>.</p>
    <a href="dashboard.php" class="inline-flex items-center gap-2 mt-5 bg-white/20 hover:bg-white/30 text-white px-6 py-3 rounded-xl font-semibold transition text-sm">
        <i class="fas fa-home"></i> Back to Dashboard
    </a>
</div>

<?php elseif (!$attendance): ?>
<!-- NOT CLOCKED IN -->
<div class="card-idle rounded-2xl shadow-xl p-8 text-center fade-up-2">
    <div class="relative inline-flex mb-5">
        <span class="ping-ring absolute inset-0 rounded-full bg-green-400/40"></span>
        <div class="w-24 h-24 bg-gradient-to-br from-green-400 to-emerald-600 rounded-full flex items-center justify-center shadow-xl relative z-10">
            <i class="fas fa-fingerprint text-4xl text-white"></i>
        </div>
    </div>
    <h2 class="text-gray-800 text-xl font-bold mb-1">Good <?php echo (date('H') < 12) ? 'Morning' : ((date('H') < 17) ? 'Afternoon' : 'Evening'); ?>!</h2>
    <p class="text-gray-500 text-sm mb-1">You haven't clocked in yet today.</p>
    <?php if ($is_late): ?>
    <p class="text-amber-600 text-xs font-medium mb-5 bg-amber-50 inline-block px-3 py-1 rounded-full">
        <i class="fas fa-exclamation-triangle mr-1"></i> Will be marked <strong>LATE</strong> (after 10:00 AM grace period)
    </p>
    <?php else: ?>
    <p class="text-green-600 text-xs font-medium mb-5">
        <i class="fas fa-check-circle mr-1"></i> On time — clock in before 10:00 AM
    </p>
    <?php endif; ?>
    <form method="POST">
        <button type="submit" name="clock_in"
            class="w-full bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white py-4 rounded-xl font-bold text-lg shadow-lg hover:shadow-xl transition flex items-center justify-center gap-2">
            <i class="fas fa-sign-in-alt"></i> Clock In Now
        </button>
    </form>
</div>

<?php elseif ($attendance && !$attendance['clock_out']): ?>
<!-- CURRENTLY WORKING -->
<?php $card_class = ($attendance['status'] === 'late') ? 'card-late' : 'card-working'; ?>
<div class="<?php echo $card_class; ?> rounded-2xl shadow-xl p-6 fade-up-2" id="working-card">
    <!-- Status badge -->
    <div class="flex items-center justify-between mb-5">
        <div class="flex items-center gap-2">
            <span class="relative flex h-3 w-3">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-3 w-3 bg-green-400"></span>
            </span>
            <span class="text-white/90 text-sm font-semibold uppercase tracking-wider">
                <?php echo ($attendance['status'] === 'late') ? '⚠ Late Arrival' : '● Currently Working'; ?>
            </span>
        </div>
        <span class="bg-white/20 text-white text-xs px-2.5 py-1 rounded-full font-medium">
            <?php echo date('l, d M', strtotime($today)); ?>
        </span>
    </div>

    <!-- Work Duration (big display) -->
    <div class="text-center mb-5">
        <p class="text-white/60 text-xs uppercase tracking-widest mb-2">Time Worked</p>
        <div class="flex items-center justify-center gap-1">
            <span id="dur-h" class="mono text-5xl text-green-300 dur-glow tabular-nums">00</span>
            <span class="mono text-4xl text-green-400/60 colon-blink">:</span>
            <span id="dur-m" class="mono text-5xl text-green-300 dur-glow tabular-nums">00</span>
            <span class="mono text-4xl text-green-400/60 colon-blink">:</span>
            <span id="dur-s" class="mono text-5xl text-green-300 dur-glow tabular-nums">00</span>
        </div>
    </div>

    <!-- Progress bar -->
    <div class="mb-5">
        <div class="flex justify-between text-white/60 text-xs mb-1.5">
            <span>Work Progress</span>
            <span id="progress-pct">0%</span>
        </div>
        <div class="w-full bg-white/10 rounded-full h-2.5 overflow-hidden">
            <div id="progress-bar" class="h-full rounded-full bg-gradient-to-r from-green-400 to-emerald-300 transition-all duration-1000" style="width:0%"></div>
        </div>
        <div class="flex justify-between text-white/40 text-[10px] mt-1">
            <span>0h</span>
            <span><?php echo $work_target_h; ?>h target</span>
        </div>
    </div>

    <!-- Time info row -->
    <div class="grid grid-cols-2 gap-3 mb-5">
        <div class="bg-white/10 rounded-xl p-3 text-center">
            <p class="text-white/50 text-[10px] uppercase tracking-wider mb-0.5">Clocked In</p>
            <p class="text-white font-bold text-lg mono"><?php echo date('h:i A', $clock_in_ts); ?></p>
        </div>
        <div class="bg-white/10 rounded-xl p-3 text-center">
            <p class="text-white/50 text-[10px] uppercase tracking-wider mb-0.5">Expected Out</p>
            <p class="text-white font-bold text-lg" id="expected-out"><?php echo $expected_end; ?></p>
        </div>
    </div>

    <!-- Overtime badge (shown by JS when past expected end) -->
    <div id="overtime-badge" class="hidden ot-pulse bg-red-500/30 border border-red-400/40 text-red-200 text-xs text-center py-2 px-4 rounded-xl mb-4 font-semibold">
        <i class="fas fa-fire mr-1"></i> You're in overtime — great dedication!
    </div>

    <!-- Clock Out button -->
    <form method="POST">
        <button type="submit" name="clock_out"
            class="w-full bg-gradient-to-r from-red-500 to-rose-600 hover:from-red-600 hover:to-rose-700 text-white py-4 rounded-xl font-bold text-lg shadow-lg hover:shadow-xl transition flex items-center justify-center gap-2">
            <i class="fas fa-sign-out-alt"></i> Clock Out
        </button>
    </form>
</div>

<?php else: ?>
<!-- DAY COMPLETED -->
<?php
$perf_pct = $work_target_h > 0 ? ($total_mins_worked / ($work_target_h * 60) * 100) : 0;
if ($total_mins_worked >= $work_target_h * 60) {
    $perf_msg = '🌟 Excellent work today!'; $perf_color = 'text-emerald-300';
} elseif ($total_mins_worked >= 420) {
    $perf_msg = '👍 Good job today!'; $perf_color = 'text-blue-200';
} elseif ($total_mins_worked >= 240) {
    $perf_msg = '☕ Short day — catch up tomorrow!'; $perf_color = 'text-amber-300';
} else {
    $perf_msg = 'Day logged.'; $perf_color = 'text-white/60';
}
?>
<div class="card-done rounded-2xl shadow-xl p-6 text-center fade-up-2">
    <div class="w-16 h-16 bg-white/15 rounded-2xl flex items-center justify-center mx-auto mb-3">
        <i class="fas fa-check-circle text-3xl text-blue-200"></i>
    </div>
    <h2 class="text-white text-xl font-bold mb-0.5">Day Completed!</h2>
    <p class="<?php echo $perf_color; ?> text-sm mb-4"><?php echo $perf_msg; ?></p>

    <!-- Summary row -->
    <div class="grid grid-cols-3 gap-3 mb-5">
        <div class="bg-white/10 rounded-xl p-3">
            <p class="text-white/50 text-[10px] uppercase tracking-wider mb-0.5">In</p>
            <p class="text-white font-bold"><?php echo date('h:i A', $clock_in_ts); ?></p>
        </div>
        <div class="bg-white/10 rounded-xl p-3">
            <p class="text-white/50 text-[10px] uppercase tracking-wider mb-0.5">Out</p>
            <p class="text-white font-bold"><?php echo date('h:i A', $clock_out_ts); ?></p>
        </div>
        <div class="bg-white/10 rounded-xl p-3">
            <p class="text-white/50 text-[10px] uppercase tracking-wider mb-0.5">Total</p>
            <p class="text-white font-bold"><?php echo $total_hours_str; ?></p>
        </div>
    </div>

    <!-- Completion bar -->
    <div class="mb-5">
        <div class="w-full bg-white/10 rounded-full h-2 overflow-hidden">
            <div class="bar-fill h-full rounded-full bg-gradient-to-r from-blue-400 to-indigo-300"
                 style="width:<?php echo min(100, round($perf_pct)); ?>%"></div>
        </div>
        <p class="text-white/40 text-[10px] mt-1"><?php echo min(100, round($perf_pct)); ?>% of <?php echo $work_target_h; ?>h target</p>
    </div>

    <a href="dashboard.php"
       class="flex items-center justify-center gap-2 w-full bg-white/20 hover:bg-white/30 text-white py-3 rounded-xl font-semibold transition mb-2">
        <i class="fas fa-home"></i> Back to Dashboard
    </a>

    <?php if ($attendance['date'] === $today): ?>
    <form id="undoForm" method="POST" data-no-loading>
        <input type="hidden" name="undo_clockout" value="1">
    </form>
    <button type="button"
        onclick="confirmAction('Undo Clock-Out?', 'This will revert you to "working" state so you can clock out at the correct time.', function(){ document.getElementById('undoForm').submit(); })"
        class="w-full border border-white/20 text-white/70 hover:text-white hover:border-white/40 py-2.5 rounded-xl text-sm transition flex items-center justify-center gap-2">
        <i class="fas fa-undo"></i> Clocked out by mistake? Undo
    </button>
    <p class="text-white/30 text-[10px] mt-2">Same-day only. Contact HR for previous days.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ③ This Week Summary ────────────────────────────────────── -->
<div class="bg-white rounded-2xl shadow-xl overflow-hidden fade-up-3">
    <div class="flex items-center justify-between px-5 py-4 border-b bg-gradient-to-r from-gray-50 to-white">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-calendar-week text-blue-600 text-sm"></i>
            </div>
            <h3 class="font-bold text-gray-800">This Week</h3>
        </div>
        <div class="text-right">
            <p class="text-xs text-gray-400">Total</p>
            <p class="font-bold text-blue-600 text-sm"><?php echo $week_h; ?>h <?php echo str_pad($week_m, 2, '0', STR_PAD_LEFT); ?>m</p>
        </div>
    </div>
    <div class="divide-y divide-gray-50 px-4 py-2">
    <?php
    $day_labels = [
        'Monday'    => 'Mon', 'Tuesday'  => 'Tue', 'Wednesday' => 'Wed',
        'Thursday'  => 'Thu', 'Friday'   => 'Fri', 'Saturday'  => 'Sat', 'Sunday' => 'Sun'
    ];
    foreach ($day_labels as $day_full => $day_short):
        $d_date  = date('Y-m-d', strtotime($day_full . ' this week'));
        $d_rec   = $week_data[$d_date] ?? null;
        $is_wd   = in_array($day_full, ['Saturday','Sunday']);
        $is_today_d = ($d_date === $today);
        $is_future  = ($d_date > $today);

        $d_mins = 0;
        if ($d_rec && $d_rec['clock_in'] && $d_rec['clock_out']) {
            $d_mins = (strtotime($d_rec['clock_out']) - strtotime($d_rec['clock_in'])) / 60;
        }
        $d_h = floor($d_mins / 60); $d_m = $d_mins % 60;
        $pct = min(100, ($d_mins / ($work_target_h * 60)) * 100);

        if ($is_wd) {
            $badge = '<span class="text-purple-500 text-xs">Weekend</span>';
            $bar_color = 'bg-purple-200'; $pct = 0;
        } elseif ($is_future && !$d_rec) {
            $badge = '<span class="text-gray-300 text-xs">Upcoming</span>';
            $bar_color = 'bg-gray-100'; $pct = 0;
        } elseif (!$d_rec) {
            $badge = '<span class="text-red-400 text-xs font-medium">Absent</span>';
            $bar_color = 'bg-red-200'; $pct = 20; // show a sliver for absent
        } elseif ($d_rec['clock_in'] && $d_rec['clock_out']) {
            $bar_color = ($d_rec['status'] === 'late') ? 'bg-amber-400' : 'bg-emerald-400';
            $badge = ($d_rec['status'] === 'late')
                ? '<span class="text-amber-600 text-xs font-medium">Late</span>'
                : '<span class="text-emerald-600 text-xs font-medium">✓ Done</span>';
        } else {
            $badge = '<span class="text-blue-500 text-xs font-medium animate-pulse">In Progress</span>';
            $bar_color = 'bg-blue-400';
        }
    ?>
    <div class="py-3 flex items-center gap-3">
        <div class="w-10 text-center">
            <p class="text-xs font-bold <?php echo $is_today_d ? 'text-blue-600' : 'text-gray-400'; ?>"><?php echo $day_short; ?></p>
            <p class="text-[10px] text-gray-300"><?php echo date('d/m', strtotime($d_date)); ?></p>
        </div>
        <div class="flex-1">
            <div class="flex justify-between items-center mb-1">
                <?php echo $badge; ?>
                <span class="text-xs text-gray-400">
                    <?php if ($d_mins > 0) echo $d_h . 'h ' . str_pad($d_m, 2, '0', STR_PAD_LEFT) . 'm'; ?>
                </span>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
                <div class="bar-fill h-full rounded-full <?php echo $bar_color; ?>"
                     style="width:<?php echo round($pct); ?>%"></div>
            </div>
        </div>
        <?php if ($is_today_d): ?>
        <div class="w-2 h-2 rounded-full bg-blue-500 ring-2 ring-blue-200 flex-shrink-0"></div>
        <?php else: ?>
        <div class="w-2"></div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
</div>

<!-- ④ Monthly Overview ──────────────────────────────────────── -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 fade-up-4">
    <div class="stat-card bg-white rounded-2xl shadow p-4 text-center">
        <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center mx-auto mb-2">
            <i class="fas fa-calendar-check text-green-600"></i>
        </div>
        <p class="text-2xl font-bold text-gray-800"><?php echo $ms['days_present'] ?? 0; ?></p>
        <p class="text-xs text-gray-400 mt-0.5">Days Present</p>
    </div>
    <div class="stat-card bg-white rounded-2xl shadow p-4 text-center">
        <div class="w-10 h-10 bg-amber-100 rounded-xl flex items-center justify-center mx-auto mb-2">
            <i class="fas fa-hourglass-half text-amber-500"></i>
        </div>
        <p class="text-2xl font-bold text-gray-800"><?php echo $ms['late_days'] ?? 0; ?></p>
        <p class="text-xs text-gray-400 mt-0.5">Late Arrivals</p>
    </div>
    <div class="stat-card bg-white rounded-2xl shadow p-4 text-center">
        <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center mx-auto mb-2">
            <i class="fas fa-clock text-blue-600"></i>
        </div>
        <p class="text-2xl font-bold text-gray-800"><?php echo $month_h; ?><span class="text-base font-medium text-gray-400">h</span></p>
        <p class="text-xs text-gray-400 mt-0.5">Total Hours</p>
    </div>
    <div class="stat-card bg-white rounded-2xl shadow p-4 text-center">
        <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center mx-auto mb-2">
            <i class="fas fa-chart-bar text-purple-600"></i>
        </div>
        <p class="text-2xl font-bold text-gray-800"><?php echo $month_avg; ?><span class="text-base font-medium text-gray-400">h</span></p>
        <p class="text-xs text-gray-400 mt-0.5">Avg / Day</p>
    </div>
</div>

<!-- ⑤ Attendance History ────────────────────────────────────── -->
<div class="bg-white rounded-2xl shadow-xl overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b bg-gradient-to-r from-gray-50 to-white flex-wrap gap-3">
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-history text-indigo-600 text-sm"></i>
            </div>
            <h3 class="font-bold text-gray-800">Attendance History</h3>
            <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full"><?php echo $total_rows; ?></span>
        </div>
        <form method="GET" class="flex gap-2 items-center">
            <input type="month" name="month" value="<?php echo htmlspecialchars($month_filter, ENT_QUOTES, 'UTF-8'); ?>"
                   class="text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-2 focus:ring-blue-300">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1.5 rounded-lg text-sm transition">
                <i class="fas fa-filter"></i>
            </button>
            <?php if ($month_filter): ?>
            <a href="clock.php" class="bg-gray-100 hover:bg-gray-200 text-gray-600 px-3 py-1.5 rounded-lg text-sm transition">
                <i class="fas fa-times"></i>
            </a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (mysqli_num_rows($history) > 0): ?>
    <!-- Table header -->
    <div class="hidden sm:grid grid-cols-5 gap-2 px-5 py-2 text-[11px] font-semibold text-gray-400 uppercase tracking-wider bg-gray-50 border-b">
        <div>Date</div>
        <div class="text-center">Clock In</div>
        <div class="text-center">Clock Out</div>
        <div class="text-center">Duration</div>
        <div class="text-center">Status</div>
    </div>
    <div class="divide-y divide-gray-50">
    <?php while ($att = mysqli_fetch_assoc($history)):
        $att_in   = $att['clock_in']  ? strtotime($att['clock_in'])  : null;
        $att_out  = $att['clock_out'] ? strtotime($att['clock_out']) : null;
        $att_mins = ($att_in && $att_out) ? (int)(($att_out - $att_in) / 60) : 0;
        $att_h    = floor($att_mins / 60); $att_m = $att_mins % 60;
        $att_dur  = $att_mins > 0 ? $att_h . 'h ' . str_pad($att_m, 2, '0', STR_PAD_LEFT) . 'm' : '—';
        $att_day  = date('l', strtotime($att['date']));
        $is_we    = in_array($att_day, ['Saturday','Sunday']);

        if ($att_in && $att_out) {
            $s_label = ($att['status'] === 'late') ? 'Late' : 'Present';
            $s_style = ($att['status'] === 'late')
                ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700';
        } elseif ($att_in) {
            $s_label = 'In Progress'; $s_style = 'bg-blue-100 text-blue-700';
        } elseif ($is_we) {
            $s_label = 'Weekend'; $s_style = 'bg-purple-100 text-purple-700';
        } else {
            $s_label = 'Absent'; $s_style = 'bg-red-100 text-red-600';
        }

        // Duration color
        $dur_color = 'text-gray-600';
        if ($att_mins >= $work_target_h * 60) $dur_color = 'text-emerald-600 font-semibold';
        elseif ($att_mins > 0) $dur_color = 'text-blue-600';
    ?>
    <div class="hist-row px-5 py-3.5">
        <!-- Desktop layout -->
        <div class="hidden sm:grid grid-cols-5 gap-2 items-center">
            <div>
                <p class="font-semibold text-gray-800 text-sm"><?php echo date('d M Y', strtotime($att['date'])); ?></p>
                <p class="text-xs text-gray-400"><?php echo $att_day; ?></p>
            </div>
            <div class="text-center">
                <p class="text-sm <?php echo $att_in ? 'text-gray-700 font-medium' : 'text-gray-300'; ?>">
                    <?php echo $att_in ? date('h:i A', $att_in) : '—'; ?>
                </p>
            </div>
            <div class="text-center">
                <p class="text-sm <?php echo $att_out ? 'text-gray-700 font-medium' : 'text-gray-300'; ?>">
                    <?php echo $att_out ? date('h:i A', $att_out) : '—'; ?>
                </p>
            </div>
            <div class="text-center">
                <p class="text-sm <?php echo $dur_color; ?>"><?php echo $att_dur; ?></p>
            </div>
            <div class="text-center">
                <span class="text-xs px-2.5 py-1 rounded-full font-semibold <?php echo $s_style; ?>"><?php echo $s_label; ?></span>
            </div>
        </div>

        <!-- Mobile layout -->
        <div class="sm:hidden flex justify-between items-center">
            <div>
                <p class="font-semibold text-gray-800 text-sm"><?php echo date('d M Y', strtotime($att['date'])); ?></p>
                <p class="text-xs text-gray-400"><?php echo $att_day; ?></p>
                <?php if ($att_in): ?>
                <p class="text-xs text-gray-500 mt-0.5">
                    <?php echo date('h:i A', $att_in); ?>
                    <?php echo $att_out ? ' → ' . date('h:i A', $att_out) : ' → ...'; ?>
                </p>
                <?php endif; ?>
            </div>
            <div class="text-right">
                <span class="text-xs px-2 py-0.5 rounded-full font-semibold <?php echo $s_style; ?>"><?php echo $s_label; ?></span>
                <?php if ($att_mins > 0): ?>
                <p class="text-xs <?php echo $dur_color; ?> mt-1"><?php echo $att_dur; ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="bg-gray-50 px-5 py-3 border-t flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs text-gray-500">
            <?php echo $offset + 1; ?> – <?php echo min($offset + $per_page, $total_rows); ?> of <?php echo $total_rows; ?>
        </p>
        <div class="flex gap-1">
            <?php if ($page > 1): ?>
            <a href="?page=1&per_page=<?php echo $per_page; ?>&month=<?php echo $month_filter; ?>"
               class="px-3 py-1 bg-white border rounded-lg text-xs hover:bg-gray-100 transition">«</a>
            <a href="?page=<?php echo $page-1; ?>&per_page=<?php echo $per_page; ?>&month=<?php echo $month_filter; ?>"
               class="px-3 py-1 bg-white border rounded-lg text-xs hover:bg-gray-100 transition">‹ Prev</a>
            <?php endif; ?>
            <span class="px-3 py-1 bg-blue-600 text-white rounded-lg text-xs font-semibold"><?php echo $page; ?> / <?php echo $total_pages; ?></span>
            <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page+1; ?>&per_page=<?php echo $per_page; ?>&month=<?php echo $month_filter; ?>"
               class="px-3 py-1 bg-white border rounded-lg text-xs hover:bg-gray-100 transition">Next ›</a>
            <a href="?page=<?php echo $total_pages; ?>&per_page=<?php echo $per_page; ?>&month=<?php echo $month_filter; ?>"
               class="px-3 py-1 bg-white border rounded-lg text-xs hover:bg-gray-100 transition">»</a>
            <?php endif; ?>
        </div>
        <select onchange="window.location.href=this.value" class="text-xs border rounded-lg px-2 py-1">
            <?php foreach ([10, 25, 50] as $pp): ?>
            <option value="?per_page=<?php echo $pp; ?>&page=1&month=<?php echo $month_filter; ?>"
                <?php echo ($per_page == $pp) ? 'selected' : ''; ?>><?php echo $pp; ?> per page</option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="py-14 text-center">
        <div class="w-14 h-14 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-3">
            <i class="fas fa-calendar-alt text-2xl text-gray-300"></i>
        </div>
        <p class="text-gray-500 font-medium text-sm">No records found</p>
        <?php if ($month_filter): ?>
        <a href="clock.php" class="text-blue-600 text-xs mt-2 inline-block">Clear filter</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

</div><!-- /main -->

<!-- Mobile Bottom Nav -->
<div class="bottom-nav fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-sm border-t border-gray-200 md:hidden shadow-xl z-20">
    <div class="flex justify-around py-2 max-w-lg mx-auto">
        <a href="dashboard.php" class="flex flex-col items-center py-1 px-4 text-gray-400 hover:text-gray-600 transition">
            <i class="fas fa-home text-xl"></i>
            <span class="text-[10px] mt-0.5 font-medium">Home</span>
        </a>
        <a href="clock.php" class="flex flex-col items-center py-1 px-4 text-blue-600">
            <i class="fas fa-clock text-xl"></i>
            <span class="text-[10px] mt-0.5 font-semibold">Attendance</span>
        </a>
        <a href="leave.php" class="flex flex-col items-center py-1 px-4 text-gray-400 hover:text-gray-600 transition">
            <i class="fas fa-calendar-alt text-xl"></i>
            <span class="text-[10px] mt-0.5 font-medium">Leave</span>
        </a>
        <a href="profile.php" class="flex flex-col items-center py-1 px-4 text-gray-400 hover:text-gray-600 transition">
            <i class="fas fa-user text-xl"></i>
            <span class="text-[10px] mt-0.5 font-medium">Profile</span>
        </a>
    </div>
</div>

<script>
/* ── Sidebar ─────────────────────────────────────────────── */

/* ── Live clock ──────────────────────────────────────────── */
const DAYS   = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
const MONTHS = ['January','February','March','April','May','June',
                'July','August','September','October','November','December'];
function p2(n) { return String(n).padStart(2,'0'); }
function updateClock() {
    const n = new Date();
    document.getElementById('lc-h').textContent = p2(n.getHours());
    document.getElementById('lc-m').textContent = p2(n.getMinutes());
    document.getElementById('lc-s').textContent = p2(n.getSeconds());
    document.getElementById('lc-date').textContent =
        DAYS[n.getDay()] + ', ' + p2(n.getDate()) + ' ' + MONTHS[n.getMonth()] + ' ' + n.getFullYear();
}
updateClock();
setInterval(updateClock, 1000);

/* ── Work duration counter (when clocked in) ─────────────── */
const clockInMs = <?php echo $clock_in_ts ? ($clock_in_ts * 1000) : 'null'; ?>;
const clockOutMs = <?php echo $clock_out_ts ? ($clock_out_ts * 1000) : 'null'; ?>;
const targetMs  = <?php echo (int)($work_target_h * 3600 * 1000); ?>;

function updateDuration() {
    if (!clockInMs) return;
    const now     = clockOutMs || Date.now();
    const elapsed = Math.max(0, Math.floor((now - clockInMs) / 1000));
    const h = Math.floor(elapsed / 3600);
    const m = Math.floor((elapsed % 3600) / 60);
    const s = elapsed % 60;

    const dh = document.getElementById('dur-h');
    const dm = document.getElementById('dur-m');
    const ds = document.getElementById('dur-s');
    if (dh) dh.textContent = p2(h);
    if (dm) dm.textContent = p2(m);
    if (ds) ds.textContent = p2(s);

    // Progress bar
    const pct = Math.min(100, (elapsed * 1000 / targetMs) * 100);
    const bar = document.getElementById('progress-bar');
    const pctEl = document.getElementById('progress-pct');
    if (bar) bar.style.width = pct.toFixed(1) + '%';
    if (pctEl) pctEl.textContent = Math.round(pct) + '%';

    // Overtime badge
    const otBadge = document.getElementById('overtime-badge');
    if (otBadge) {
        const expectedEnd = clockInMs + targetMs;
        otBadge.classList.toggle('hidden', Date.now() < expectedEnd);
    }
}

if (clockInMs && !clockOutMs) {
    updateDuration();
    setInterval(updateDuration, 1000);
} else if (clockInMs && clockOutMs) {
    updateDuration(); // run once for the completed state display
}
</script>
</body>
</html>
