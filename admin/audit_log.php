<?php
require_once '../includes/auth.php';
redirectIfNotAdmin();
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/toast_fn.php';

// ── Filters ──────────────────────────────────────────────────────────────────
$filter_action = isset($_GET['action_type']) ? mysqli_real_escape_string($conn, trim($_GET['action_type'])) : '';
$filter_from   = isset($_GET['date_from'])   ? mysqli_real_escape_string($conn, trim($_GET['date_from']))   : '';
$filter_to     = isset($_GET['date_to'])     ? mysqli_real_escape_string($conn, trim($_GET['date_to']))     : '';

$where = "WHERE 1=1";
if ($filter_action !== '') $where .= " AND al.action = '$filter_action'";
if ($filter_from   !== '') $where .= " AND DATE(al.created_at) >= '$filter_from'";
if ($filter_to     !== '') $where .= " AND DATE(al.created_at) <= '$filter_to'";

// ── Pagination ────────────────────────────────────────────────────────────────
$per_page    = 20;
$page        = max(1, intval($_GET['page'] ?? 1));
$offset      = ($page - 1) * $per_page;

$count_result = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total FROM audit_log al $where"));
$total_rows  = (int)($count_result['total'] ?? 0);
$total_pages = max(1, (int)ceil($total_rows / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$logs = mysqli_query($conn,
    "SELECT al.*, e.name AS employee_name, e.employee_id AS emp_code
     FROM audit_log al
     LEFT JOIN employees e ON al.user_id = e.id
     $where
     ORDER BY al.created_at DESC
     LIMIT $per_page OFFSET $offset");

// ── Distinct action types for filter dropdown ─────────────────────────────────
$action_types_result = mysqli_query($conn, "SELECT DISTINCT action FROM audit_log ORDER BY action ASC");

// ── Build pagination query string ─────────────────────────────────────────────
function pagUrl($p) {
    $params = $_GET;
    $params['page'] = $p;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Audit Log - IPINFRA HRM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .sidebar { transition: transform 0.3s ease-in-out; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-fadeIn   { animation: fadeIn   0.3s ease-out; }
        .animate-fadeInUp { animation: fadeInUp 0.4s ease-out; }

        .card-hover { transition: all 0.2s ease; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }

        /* Action badges */
        .badge-approve  { background:#d1fae5; color:#065f46; }
        .badge-approved { background:#d1fae5; color:#065f46; }
        .badge-reject   { background:#fee2e2; color:#991b1b; }
        .badge-rejected { background:#fee2e2; color:#991b1b; }
        .badge-delete   { background:#fee2e2; color:#991b1b; }
        .badge-generate { background:#dbeafe; color:#1e40af; }
        .badge-login    { background:#f3f4f6; color:#374151; }
        .badge-logout   { background:#f3f4f6; color:#374151; }
        .badge-update   { background:#fef9c3; color:#854d0e; }
        .badge-create   { background:#ede9fe; color:#5b21b6; }
        .badge-default  { background:#e0f2fe; color:#075985; }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen pb-20">

<?php require_once '../includes/global_ui.php'; ?>
<?php require_once '../includes/toast.php'; ?>
<?php require_once '../includes/confirm_modal.php'; ?>

<!-- Mobile Header -->
<div class="bg-gradient-to-r from-slate-900 via-indigo-900 to-slate-900 text-white sticky top-0 z-40 shadow-2xl">
    <div class="flex justify-between items-center px-4 py-4">
        <div class="flex items-center gap-3">
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
                <h2 class="font-bold"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h2>
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
        <a href="management.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-gray-800/30 transition mb-1">
            <i class="fas fa-briefcase w-5"></i> Management
        </a>
        <a href="payroll.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-gray-800/30 transition mb-1">
            <i class="fas fa-file-invoice-dollar w-5"></i> Payroll
        </a>
        <a href="holidays.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-gray-800/30 transition mb-1">
            <i class="fas fa-calendar-alt w-5"></i> Holidays
        </a>
        <a href="audit_log.php" class="flex items-center gap-3 py-3 px-4 rounded-xl bg-gray-800/50 transition mb-1">
            <i class="fas fa-shield-alt w-5"></i> Audit Log
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

    <!-- Page Header -->
    <div class="mb-6 animate-fadeInUp">
        <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-shield-alt mr-2 text-indigo-600"></i>Audit Log</h1>
        <p class="text-sm text-gray-500 mt-1">Track all system actions and admin activities</p>
    </div>

    <!-- Stats Row -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6 animate-fadeInUp">
        <?php
        $stat_total   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM audit_log"))['c'] ?? 0;
        $stat_today   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM audit_log WHERE DATE(created_at)=CURDATE()"))['c'] ?? 0;
        $stat_logins  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM audit_log WHERE action='login'"))['c'] ?? 0;
        $stat_deletes = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM audit_log WHERE action='delete'"))['c'] ?? 0;
        ?>
        <div class="bg-white rounded-xl p-4 shadow-md card-hover">
            <p class="text-xs text-gray-500">Total Events</p>
            <p class="text-2xl font-bold text-gray-800"><?php echo number_format($stat_total); ?></p>
            <p class="text-xs text-gray-400">All time</p>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-md card-hover">
            <p class="text-xs text-gray-500">Today</p>
            <p class="text-2xl font-bold text-indigo-600"><?php echo number_format($stat_today); ?></p>
            <p class="text-xs text-gray-400">Events</p>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-md card-hover">
            <p class="text-xs text-gray-500">Logins</p>
            <p class="text-2xl font-bold text-gray-600"><?php echo number_format($stat_logins); ?></p>
            <p class="text-xs text-gray-400">All time</p>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-md card-hover">
            <p class="text-xs text-gray-500">Deletions</p>
            <p class="text-2xl font-bold text-red-500"><?php echo number_format($stat_deletes); ?></p>
            <p class="text-xs text-gray-400">All time</p>
        </div>
    </div>

    <!-- Filter Card -->
    <div class="bg-white rounded-xl shadow-md p-5 mb-6 animate-fadeInUp">
        <div class="flex items-center gap-2 mb-4">
            <div class="w-8 h-8 bg-indigo-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-filter text-indigo-600 text-sm"></i>
            </div>
            <h2 class="font-semibold text-gray-800">Filter Logs</h2>
        </div>
        <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
            <!-- Action type -->
            <div class="relative">
                <label class="block text-xs text-gray-500 mb-1">Action Type</label>
                <select name="action_type" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                    <option value="">All Actions</option>
                    <?php
                    // Preset common actions
                    $preset_actions = ['login','logout','approve','reject','delete','generate','create','update'];
                    // Merge DB actions
                    $db_actions = [];
                    if ($action_types_result) {
                        while ($ar = mysqli_fetch_assoc($action_types_result)) {
                            $db_actions[] = $ar['action'];
                        }
                    }
                    $all_actions = array_unique(array_merge($preset_actions, $db_actions));
                    sort($all_actions);
                    foreach ($all_actions as $at):
                        $sel = ($filter_action === $at) ? 'selected' : '';
                    ?>
                    <option value="<?php echo htmlspecialchars($at); ?>" <?php echo $sel; ?>><?php echo ucfirst(htmlspecialchars($at)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <!-- Date from -->
            <div>
                <label class="block text-xs text-gray-500 mb-1">Date From</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($filter_from); ?>"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
            </div>
            <!-- Date to -->
            <div>
                <label class="block text-xs text-gray-500 mb-1">Date To</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($filter_to); ?>"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
            </div>
            <!-- Buttons -->
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center justify-center gap-1">
                    <i class="fas fa-search"></i> Filter
                </button>
                <a href="audit_log.php" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium transition flex items-center justify-center gap-1">
                    <i class="fas fa-times"></i> Clear
                </a>
            </div>
        </form>
    </div>

    <!-- Log Table -->
    <div class="bg-white rounded-xl shadow-md overflow-hidden animate-fadeInUp">
        <div class="bg-gray-50 px-5 py-4 border-b flex items-center justify-between flex-wrap gap-2">
            <div class="flex items-center gap-2">
                <i class="fas fa-list-alt text-indigo-500"></i>
                <p class="font-semibold text-gray-800">Log Entries</p>
            </div>
            <span class="text-xs text-gray-400">
                <?php echo number_format($total_rows); ?> total &bull; Page <?php echo $page; ?> of <?php echo $total_pages; ?>
            </span>
        </div>

        <?php if ($logs && mysqli_num_rows($logs) > 0): ?>
        <!-- Desktop table -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b text-xs text-gray-500 uppercase tracking-wider">
                        <th class="px-5 py-3 text-left">Date / Time</th>
                        <th class="px-5 py-3 text-left">User</th>
                        <th class="px-5 py-3 text-left">Action</th>
                        <th class="px-5 py-3 text-left">Description</th>
                        <th class="px-5 py-3 text-left">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php while ($log = mysqli_fetch_assoc($logs)):
                        $action_key = strtolower($log['action']);
                        $badge_class = 'badge-default';
                        if (in_array($action_key, ['approve','approved'])) $badge_class = 'badge-approve';
                        elseif (in_array($action_key, ['reject','rejected'])) $badge_class = 'badge-reject';
                        elseif ($action_key === 'delete') $badge_class = 'badge-delete';
                        elseif ($action_key === 'generate') $badge_class = 'badge-generate';
                        elseif (in_array($action_key, ['login'])) $badge_class = 'badge-login';
                        elseif (in_array($action_key, ['logout'])) $badge_class = 'badge-logout';
                        elseif ($action_key === 'update') $badge_class = 'badge-update';
                        elseif ($action_key === 'create') $badge_class = 'badge-create';
                    ?>
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-5 py-3 whitespace-nowrap text-gray-500 text-xs">
                            <div class="font-medium text-gray-700"><?php echo date('d M Y', strtotime($log['created_at'])); ?></div>
                            <div class="text-gray-400"><?php echo date('h:i:s A', strtotime($log['created_at'])); ?></div>
                        </td>
                        <td class="px-5 py-3">
                            <?php if ($log['employee_name']): ?>
                                <div class="font-medium text-gray-800"><?php echo htmlspecialchars($log['employee_name']); ?></div>
                                <?php if ($log['emp_code']): ?>
                                <div class="text-xs text-gray-400"><?php echo htmlspecialchars($log['emp_code']); ?></div>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-gray-400 text-xs">System / ID <?php echo intval($log['user_id']); ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold <?php echo $badge_class; ?>">
                                <?php echo htmlspecialchars(ucfirst($log['action'])); ?>
                            </span>
                        </td>
                        <td class="px-5 py-3 text-gray-600 max-w-xs">
                            <span class="line-clamp-2"><?php echo htmlspecialchars($log['description']); ?></span>
                            <?php if ($log['target_type'] && $log['target_id']): ?>
                            <div class="text-xs text-gray-400 mt-0.5">
                                <?php echo htmlspecialchars(ucfirst($log['target_type'])); ?> #<?php echo intval($log['target_id']); ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3 text-gray-500 text-xs font-mono">
                            <?php echo htmlspecialchars($log['ip_address'] ?: '-'); ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <!-- Mobile cards -->
        <div class="md:hidden divide-y divide-gray-100">
            <?php
            // Re-run query for mobile since the pointer is exhausted
            $logs_mobile = mysqli_query($conn,
                "SELECT al.*, e.name AS employee_name, e.employee_id AS emp_code
                 FROM audit_log al
                 LEFT JOIN employees e ON al.user_id = e.id
                 $where
                 ORDER BY al.created_at DESC
                 LIMIT $per_page OFFSET $offset");
            while ($log = mysqli_fetch_assoc($logs_mobile)):
                $action_key  = strtolower($log['action']);
                $badge_class = 'badge-default';
                if (in_array($action_key, ['approve','approved'])) $badge_class = 'badge-approve';
                elseif (in_array($action_key, ['reject','rejected'])) $badge_class = 'badge-reject';
                elseif ($action_key === 'delete') $badge_class = 'badge-delete';
                elseif ($action_key === 'generate') $badge_class = 'badge-generate';
                elseif (in_array($action_key, ['login'])) $badge_class = 'badge-login';
                elseif (in_array($action_key, ['logout'])) $badge_class = 'badge-logout';
                elseif ($action_key === 'update') $badge_class = 'badge-update';
                elseif ($action_key === 'create') $badge_class = 'badge-create';
            ?>
            <div class="p-4">
                <div class="flex items-start justify-between mb-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold <?php echo $badge_class; ?>">
                        <?php echo htmlspecialchars(ucfirst($log['action'])); ?>
                    </span>
                    <span class="text-xs text-gray-400"><?php echo date('d M Y, h:i A', strtotime($log['created_at'])); ?></span>
                </div>
                <p class="text-sm text-gray-700 mb-1"><?php echo htmlspecialchars($log['description']); ?></p>
                <div class="flex items-center gap-3 text-xs text-gray-400 mt-1">
                    <span><i class="fas fa-user mr-1"></i><?php echo $log['employee_name'] ? htmlspecialchars($log['employee_name']) : 'System'; ?></span>
                    <span><i class="fas fa-globe mr-1"></i><?php echo htmlspecialchars($log['ip_address'] ?: '-'); ?></span>
                </div>
                <?php if ($log['target_type'] && $log['target_id']): ?>
                <div class="text-xs text-gray-400 mt-1">
                    Target: <?php echo htmlspecialchars(ucfirst($log['target_type'])); ?> #<?php echo intval($log['target_id']); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endwhile; ?>
        </div>

        <?php else: ?>
        <div class="py-16 text-center">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-shield-alt text-gray-300 text-2xl"></i>
            </div>
            <p class="text-gray-500 font-medium">No audit log entries found</p>
            <p class="text-gray-400 text-sm mt-1">Logged actions will appear here</p>
        </div>
        <?php endif; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="px-5 py-4 border-t bg-gray-50 flex items-center justify-between gap-2 flex-wrap">
            <span class="text-xs text-gray-500">
                Showing <?php echo number_format($offset + 1); ?> – <?php echo number_format(min($offset + $per_page, $total_rows)); ?> of <?php echo number_format($total_rows); ?>
            </span>
            <div class="flex items-center gap-1">
                <?php if ($page > 1): ?>
                <a href="<?php echo pagUrl(1); ?>" class="px-2 py-1 rounded-lg text-xs bg-gray-200 hover:bg-gray-300 text-gray-700 transition">
                    <i class="fas fa-angle-double-left"></i>
                </a>
                <a href="<?php echo pagUrl($page - 1); ?>" class="px-3 py-1 rounded-lg text-xs bg-gray-200 hover:bg-gray-300 text-gray-700 transition">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <?php endif; ?>

                <?php
                $start_p = max(1, $page - 2);
                $end_p   = min($total_pages, $page + 2);
                for ($p = $start_p; $p <= $end_p; $p++):
                    $active = ($p === $page) ? 'bg-indigo-600 text-white' : 'bg-gray-200 hover:bg-gray-300 text-gray-700';
                ?>
                <a href="<?php echo pagUrl($p); ?>" class="px-3 py-1 rounded-lg text-xs <?php echo $active; ?> transition"><?php echo $p; ?></a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                <a href="<?php echo pagUrl($page + 1); ?>" class="px-3 py-1 rounded-lg text-xs bg-gray-200 hover:bg-gray-300 text-gray-700 transition">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <a href="<?php echo pagUrl($total_pages); ?>" class="px-2 py-1 rounded-lg text-xs bg-gray-200 hover:bg-gray-300 text-gray-700 transition">
                    <i class="fas fa-angle-double-right"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /main content -->

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');
    const isOpen  = !sidebar.classList.contains('-translate-x-full');
    if (isOpen) {
        sidebar.classList.add('-translate-x-full');
        overlay.classList.add('hidden');
    } else {
        sidebar.classList.remove('-translate-x-full');
        overlay.classList.remove('hidden');
    }
}
</script>
</body>
</html>
