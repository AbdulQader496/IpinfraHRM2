<?php
require_once '../includes/auth.php';
redirectIfNotAdmin();
require_once '../includes/db.php';
/** @var mysqli $conn */

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$date = isset($_GET['date']) ? mysqli_real_escape_string($conn, $_GET['date']) : date('Y-m-d');
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$per_page = 10;
$offset = ($page - 1) * $per_page;

$employee_where = "WHERE e.role = 'employee'";
if (!empty($search)) {
    $employee_where .= " AND (e.name LIKE '%$search%' OR e.employee_id LIKE '%$search%')";
}

$attendance = mysqli_query($conn, "SELECT a.*, e.id as emp_id, e.name, e.employee_id, e.department, e.nationality
    FROM employees e
    LEFT JOIN attendance a ON a.employee_id = e.id AND a.date = '$date'
    $employee_where
    ORDER BY e.name
    LIMIT $offset, $per_page");

$is_weekend = (date('l', strtotime($date)) == 'Saturday' || date('l', strtotime($date)) == 'Sunday');

// Buffered into an array (instead of streaming the query straight to output) so this same
// page's worth of rows can be rendered twice -- once as <tr>s for the desktop table, once as
// cards for the mobile layout -- without a second query.
$rows = [];
while ($r = mysqli_fetch_assoc($attendance)) { $rows[] = $r; }

foreach ($rows as &$row) {
    $row_status = 'absent';
    $attendance_id = $row['id'] ?? null;

    if ($is_weekend) {
        $row_status = 'weekend';
    } elseif ($row['clock_in'] && $row['clock_out']) {
        $row_status = 'completed';
    } elseif ($row['clock_in'] && !$row['clock_out']) {
        $row_status = 'in_progress';
    } elseif ($row['status'] == 'late') {
        $row_status = 'late';
    }
    $row['_status'] = $row_status;
    $row['_attendance_id'] = $attendance_id;
}
unset($row); // break the reference so a later `foreach (... as $row)` doesn't alias it
?>
<!--DESKTOP-->
<?php foreach ($rows as $row): $attendance_id = $row['_attendance_id']; ?>
<tr class="hover:bg-slate-50 transition">
    <td class="p-4">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center">
                <i class="fas fa-user text-blue-600 text-sm"></i>
            </div>
            <span class="font-medium text-slate-800 text-sm"><?php echo htmlspecialchars($row['name']); ?></span>
        </div>
    </td>
    <td class="p-4 text-sm text-slate-500 font-mono"><?php echo htmlspecialchars($row['employee_id']); ?></td>

    <?php if(!$is_weekend): ?>
    <td class="p-4">
        <?php if ($row['clock_in']): ?>
            <div class="flex flex-col">
                <span class="text-sm font-semibold <?php echo (strtotime($row['clock_in']) > strtotime('10:00:00')) ? 'text-orange-600' : 'text-green-600'; ?>">
                    <i class="fas fa-sign-in-alt mr-1 text-xs"></i> <?php echo date('h:i A', strtotime($row['clock_in'])); ?>
                </span>
                <?php if (strtotime($row['clock_in']) > strtotime('10:00:00')): ?>
                    <span class="text-xs text-orange-500 mt-0.5">(Late)</span>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <span class="text-sm text-slate-400">-- : --</span>
        <?php endif; ?>
    </td>
    <td class="p-4">
        <?php if ($row['clock_out']): ?>
            <span class="text-sm font-medium text-red-600">
                <i class="fas fa-sign-out-alt mr-1 text-xs"></i> <?php echo date('h:i A', strtotime($row['clock_out'])); ?>
            </span>
        <?php elseif ($row['clock_in'] && !$row['clock_out']): ?>
            <span class="text-sm text-orange-500">
                <i class="fas fa-hourglass-end mr-1"></i> Not Clocked Out
            </span>
        <?php else: ?>
            <span class="text-sm text-slate-400">-- : --</span>
        <?php endif; ?>
    </td>
    <td class="p-4">
        <?php if ($row['clock_in'] && $row['clock_out']):
            $start = new DateTime($row['clock_in']);
            $end = new DateTime($row['clock_out']);
            $diff = $start->diff($end);
            $hours = $diff->h;
            $minutes = $diff->i;
        ?>
            <span class="text-sm font-medium text-slate-700"><?php echo $hours; ?>h <?php echo $minutes; ?>m</span>
        <?php elseif ($row['clock_in'] && !$row['clock_out']): ?>
            <span class="text-sm text-orange-600">
                <i class="fas fa-spinner fa-pulse mr-1"></i> In Progress
            </span>
        <?php else: ?>
            <span class="text-sm text-slate-400">-</span>
        <?php endif; ?>
    </td>
    <?php endif; ?>

    <td class="p-4">
        <?php if($is_weekend): ?>
            <span class="status-badge px-3 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-700">
                <i class="fas fa-calendar-week mr-1"></i> Weekend
            </span>
        <?php elseif ($row['clock_in'] && $row['clock_out']): ?>
            <span class="status-badge px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                <i class="fas fa-check-circle mr-1"></i> Completed
            </span>
        <?php elseif ($row['clock_in'] && !$row['clock_out']): ?>
            <span class="status-badge px-3 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                <i class="fas fa-hourglass-half mr-1"></i> In Progress
            </span>
        <?php elseif ($row['status'] == 'late'): ?>
            <span class="status-badge px-3 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-700">
                <i class="fas fa-clock mr-1"></i> Late
            </span>
        <?php else: ?>
            <span class="status-badge px-3 py-1 rounded-full text-xs font-medium bg-red-100 text-red-700">
                <i class="fas fa-times-circle mr-1"></i> Absent
            </span>
        <?php endif; ?>
    </td>

    <td class="p-4 text-center">
        <?php /* openEditModalFromLoad() was called here but never defined anywhere in the
                 codebase -- Edit silently did nothing (ReferenceError) for every row loaded
                 via "Load More". This HTML is fetched and appended into the same DOM as
                 attendance.php, so its already-working openEditModal() is directly callable. */ ?>
        <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES); ?>, '<?php echo $date; ?>')"
                class="text-blue-600 hover:text-blue-800 transition" title="Edit Attendance">
            <i class="fas fa-edit"></i>
        </button>
        <?php /* This used to be a plain GET link, but the handler in attendance.php only ever
                 checked $_POST['delete_attendance'] -- clicking it just navigated to a URL
                 whose query param was silently ignored, deleting nothing. Matches the working
                 POST form used for rows rendered by attendance.php itself. */ ?>
        <?php if($attendance_id): ?>
        <form method="POST" action="attendance.php" style="display:inline" onsubmit="return confirm('Delete this attendance record?')">
            <?php echo csrfField(); ?>
            <input type="hidden" name="delete_attendance" value="<?php echo $attendance_id; ?>">
            <input type="hidden" name="date" value="<?php echo htmlspecialchars($date, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="text-red-500 hover:text-red-700 transition ml-2" title="Delete">
                <i class="fas fa-trash"></i>
            </button>
        </form>
        <?php endif; ?>
    </td>
</tr>
<?php endforeach; ?>
<!--MOBILE-->
<?php
$card_badges = [
    'weekend'     => ['bg-purple-100 text-purple-700', 'fa-calendar-week', 'Weekend'],
    'completed'   => ['bg-green-100 text-green-700', 'fa-check-circle', 'Completed'],
    'in_progress' => ['bg-amber-100 text-amber-800', 'fa-hourglass-half', 'In Progress'],
    'late'        => ['bg-orange-100 text-orange-700', 'fa-clock', 'Late'],
    'absent'      => ['bg-red-100 text-red-700', 'fa-times-circle', 'Absent'],
];
foreach ($rows as $row):
    $attendance_id = $row['_attendance_id'];
    $card_badge = $card_badges[$row['_status']];
?>
<div class="p-4 border-t border-slate-100">
    <div class="flex items-start justify-between gap-3 mb-3">
        <div class="flex items-center gap-3 min-w-0">
            <div class="w-10 h-10 shrink-0 rounded-full bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center">
                <i class="fas fa-user text-blue-600 text-sm"></i>
            </div>
            <div class="min-w-0">
                <p class="font-semibold text-slate-800 text-sm truncate"><?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></p>
                <p class="text-xs text-slate-400 font-mono"><?php echo htmlspecialchars($row['employee_id'], ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        </div>
        <span class="shrink-0 status-badge px-2.5 py-1 rounded-full text-xs font-medium <?php echo $card_badge[0]; ?>">
            <i class="fas <?php echo $card_badge[1]; ?> mr-1"></i> <?php echo $card_badge[2]; ?>
        </span>
    </div>

    <?php if(!$is_weekend): ?>
    <div class="grid grid-cols-3 gap-2 mb-3 bg-slate-50 rounded-xl p-3">
        <div>
            <p class="text-[10px] uppercase tracking-wide text-slate-400 font-semibold mb-0.5">Clock In</p>
            <?php if ($row['clock_in']): ?>
                <p class="text-sm font-semibold <?php echo (strtotime($row['clock_in']) > strtotime('10:00:00')) ? 'text-orange-600' : 'text-green-600'; ?>">
                    <?php echo date('h:i A', strtotime($row['clock_in'])); ?>
                </p>
            <?php else: ?>
                <p class="text-sm text-slate-400">-- : --</p>
            <?php endif; ?>
        </div>
        <div>
            <p class="text-[10px] uppercase tracking-wide text-slate-400 font-semibold mb-0.5">Clock Out</p>
            <?php if ($row['clock_out']): ?>
                <p class="text-sm font-semibold text-red-600"><?php echo date('h:i A', strtotime($row['clock_out'])); ?></p>
            <?php elseif ($row['clock_in']): ?>
                <p class="text-xs font-medium text-orange-500 leading-tight">Not Out</p>
            <?php else: ?>
                <p class="text-sm text-slate-400">-- : --</p>
            <?php endif; ?>
        </div>
        <div>
            <p class="text-[10px] uppercase tracking-wide text-slate-400 font-semibold mb-0.5">Duration</p>
            <?php if ($row['clock_in'] && $row['clock_out']):
                $start = new DateTime($row['clock_in']);
                $end = new DateTime($row['clock_out']);
                $diff = $start->diff($end);
            ?>
                <p class="text-sm font-semibold text-slate-700"><?php echo $diff->h; ?>h <?php echo $diff->i; ?>m</p>
            <?php elseif ($row['clock_in']): ?>
                <p class="text-xs font-medium text-orange-600 leading-tight">In Progress</p>
            <?php else: ?>
                <p class="text-sm text-slate-400">-</p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="flex gap-2">
        <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES); ?>, '<?php echo $date; ?>')"
                class="flex-1 flex items-center justify-center gap-1.5 py-2 rounded-xl text-sm font-medium bg-blue-50 text-blue-600 hover:bg-blue-100 transition">
            <i class="fas fa-edit"></i> Edit
        </button>
        <?php if($attendance_id): ?>
        <form method="POST" action="attendance.php" class="flex-1" onsubmit="return confirm('Delete this attendance record?')">
            <?php echo csrfField(); ?>
            <input type="hidden" name="delete_attendance" value="<?php echo $attendance_id; ?>">
            <input type="hidden" name="date" value="<?php echo htmlspecialchars($date, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="w-full flex items-center justify-center gap-1.5 py-2 rounded-xl text-sm font-medium bg-red-50 text-red-500 hover:bg-red-100 transition">
                <i class="fas fa-trash"></i> Delete
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
