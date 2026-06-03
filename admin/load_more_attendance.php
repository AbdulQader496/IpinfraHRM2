<?php
require_once '../includes/auth.php';
redirectIfNotAdmin();
require_once '../includes/db.php';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
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

while ($row = mysqli_fetch_assoc($attendance)):
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
?>
<tr class="hover:bg-slate-50 transition">
    <td class="p-4">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-full bg-gradient-to-br from-blue-100 to-blue-200 flex items-center justify-center">
                <i class="fas fa-user text-blue-600 text-sm"></i>
            </div>
            <span class="font-medium text-slate-800 text-sm"><?php echo $row['name']; ?></span>
        </div>
    </td>
    <td class="p-4 text-sm text-slate-500 font-mono"><?php echo $row['employee_id']; ?></td>
    
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
            <span class="status-badge px-3 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">
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
        <button onclick="openEditModalFromLoad(<?php echo htmlspecialchars(json_encode($row)); ?>, '<?php echo $date; ?>')" 
                class="text-blue-600 hover:text-blue-800 transition" title="Edit Attendance">
            <i class="fas fa-edit"></i>
        </button>
        <?php if($attendance_id): ?>
        <a href="?delete_attendance=<?php echo $attendance_id; ?>&date=<?php echo $date; ?>" 
           data-confirm="Delete this attendance record?" data-confirm-title="Delete Record"
           class="text-red-500 hover:text-red-700 transition ml-2" title="Delete">
            <i class="fas fa-trash"></i>
        </a>
        <?php endif; ?>
    </td>
</tr>
<?php endwhile; ?>