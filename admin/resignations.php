<?php
require_once '../includes/auth.php';
redirectIfNotAdmin();
require_once '../includes/db.php';

// Handle resignation request
if (isset($_POST['add_resignation'])) {
    $employee_id = $_POST['employee_id'];
    $resignation_date = $_POST['resignation_date'];
    $last_working_date = $_POST['last_working_date'];
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);
    $type = $_POST['type'];
    
    $query = "INSERT INTO resignations (employee_id, resignation_date, last_working_date, reason, type, status) 
              VALUES ($employee_id, '$resignation_date', '$last_working_date', '$reason', '$type', 'pending')";
    mysqli_query($conn, $query);
    $resign_id = mysqli_insert_id($conn);
    
    mysqli_query($conn, "UPDATE employees SET resignation_id = $resign_id, employment_status = 'on_leave' WHERE id = $employee_id");
    
    header('Location: resignations.php');
    exit();
}

// Approve/Reject
if (isset($_GET['approve'])) {
    $id = $_GET['approve'];
    $status = $_GET['status'];
    
    mysqli_query($conn, "UPDATE resignations SET status='$status', approved_by={$_SESSION['user_id']}, approved_date=CURDATE() WHERE id=$id");
    
    if ($status == 'approved') {
        $res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT employee_id FROM resignations WHERE id=$id"));
        mysqli_query($conn, "UPDATE employees SET employment_status='resigned' WHERE id={$res['employee_id']}");
    }
    
    header('Location: resignations.php');
    exit();
}

// Update clearance
if (isset($_POST['update_clearance'])) {
    $id = $_POST['resign_id'];
    $clearance_status = $_POST['clearance_status'];
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);
    
    mysqli_query($conn, "UPDATE resignations SET clearance_status='$clearance_status', remarks='$remarks' WHERE id=$id");
    header('Location: resignations.php');
    exit();
}

$resignations = mysqli_query($conn, "SELECT r.*, e.name, e.employee_id, e.department 
    FROM resignations r 
    JOIN employees e ON r.employee_id = e.id 
    ORDER BY r.created_at DESC");

$employees = mysqli_query($conn, "SELECT id, name, employee_id FROM employees WHERE role='employee' AND employment_status='active'");

$pending = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM resignations WHERE status='pending'"))['count'];
$approved = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM resignations WHERE status='approved'"))['count'];
$completed = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM resignations WHERE clearance_status='completed'"))['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Resignations - IPINFRA HRM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen pb-20">

<!-- Premium Mobile Header -->
<div class="bg-[#060912] text-white sticky top-0 z-40 shadow-2xl">
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

<?php require_once '../includes/admin_sidebar.php'; ?>

<!-- Main Content -->
<div class="px-4 py-6 pb-24 max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-xl font-bold text-gray-800">Resignation & Termination Management</h1>
        <button onclick="document.getElementById('addModal').classList.remove('hidden')" class="bg-red-600 text-white px-4 py-2 rounded-xl text-sm shadow-md">
            <i class="fas fa-plus mr-1"></i> New Resignation
        </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-yellow-100 rounded-xl p-4 text-center">
            <p class="text-2xl font-bold text-yellow-700"><?php echo $pending; ?></p>
            <p class="text-xs text-yellow-600">Pending Approval</p>
        </div>
        <div class="bg-blue-100 rounded-xl p-4 text-center">
            <p class="text-2xl font-bold text-blue-700"><?php echo $approved; ?></p>
            <p class="text-xs text-blue-600">Approved</p>
        </div>
        <div class="bg-green-100 rounded-xl p-4 text-center">
            <p class="text-2xl font-bold text-green-700"><?php echo $completed; ?></p>
            <p class="text-xs text-green-600">Clearance Completed</p>
        </div>
    </div>

    <!-- Resignations Table -->
    <div class="bg-white rounded-xl shadow-xl overflow-hidden">
        <div class="bg-gray-50 px-4 py-3 border-b">
            <p class="font-semibold text-gray-800"><i class="fas fa-user-minus mr-2 text-red-600"></i> Resignation Records</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="p-3 text-left text-xs font-semibold text-gray-600">Employee</th>
                        <th class="p-3 text-left text-xs font-semibold text-gray-600">Resignation Date</th>
                        <th class="p-3 text-left text-xs font-semibold text-gray-600">Last Working Date</th>
                        <th class="p-3 text-left text-xs font-semibold text-gray-600">Type</th>
                        <th class="p-3 text-left text-xs font-semibold text-gray-600">Status</th>
                        <th class="p-3 text-left text-xs font-semibold text-gray-600">Clearance</th>
                        <th class="p-3 text-left text-xs font-semibold text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    <?php while($row = mysqli_fetch_assoc($resignations)): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="p-3">
                            <p class="font-semibold"><?php echo $row['name']; ?></p>
                            <p class="text-xs text-gray-500"><?php echo $row['employee_id']; ?></p>
                        </td>
                        <td class="p-3"><?php echo date('d M Y', strtotime($row['resignation_date'])); ?></td>
                        <td class="p-3"><?php echo date('d M Y', strtotime($row['last_working_date'])); ?></td>
                        <td class="p-3">
                            <span class="px-2 py-1 rounded-full text-xs <?php echo $row['type'] == 'resignation' ? 'bg-blue-100 text-blue-700' : ($row['type'] == 'termination' ? 'bg-red-100 text-red-700' : 'bg-orange-100 text-orange-700'); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $row['type'])); ?>
                            </span>
                        </td>
                        <td class="p-3">
                            <span class="px-2 py-1 rounded-full text-xs <?php echo $row['status'] == 'approved' ? 'bg-green-100 text-green-700' : ($row['status'] == 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-700'); ?>">
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                        </td>
                        <td class="p-3">
                            <span class="px-2 py-1 rounded-full text-xs <?php echo $row['clearance_status'] == 'completed' ? 'bg-green-100 text-green-700' : ($row['clearance_status'] == 'in_progress' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700'); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $row['clearance_status'])); ?>
                            </span>
                        </td>
                        <td class="p-3">
                            <button onclick='openClearanceModal(<?php echo json_encode($row); ?>)' class="text-blue-600 text-sm mr-2">Clearance</button>
                            <?php if($row['status'] == 'pending'): ?>
                                <a href="?approve=<?php echo $row['id']; ?>&status=approved" class="text-green-600 text-sm mr-2">Approve</a>
                                <a href="?approve=<?php echo $row['id']; ?>&status=rejected" class="text-red-600 text-sm">Reject</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div id="addModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full">
        <div class="p-4 border-b flex justify-between items-center">
            <h2 class="text-lg font-bold">Process Resignation/Termination</h2>
            <button onclick="document.getElementById('addModal').classList.add('hidden')" class="text-gray-500">&times;</button>
        </div>
        <form method="POST" class="p-4 space-y-4">
            <div>
                <label class="block text-sm font-medium mb-1">Employee</label>
                <select name="employee_id" required class="w-full px-4 py-2 border rounded-xl">
                    <option value="">Select Employee</option>
                    <?php while($emp = mysqli_fetch_assoc($employees)): ?>
                        <option value="<?php echo $emp['id']; ?>"><?php echo $emp['name']; ?> (<?php echo $emp['employee_id']; ?>)</option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Type</label>
                <select name="type" required class="w-full px-4 py-2 border rounded-xl">
                    <option value="resignation">Resignation</option>
                    <option value="termination">Termination</option>
                    <option value="contract_end">Contract End</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Resignation Date</label>
                <input type="date" name="resignation_date" required class="w-full px-4 py-2 border rounded-xl">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Last Working Date</label>
                <input type="date" name="last_working_date" required class="w-full px-4 py-2 border rounded-xl">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Reason</label>
                <textarea name="reason" rows="3" class="w-full px-4 py-2 border rounded-xl" placeholder="Reason for resignation/termination..."></textarea>
            </div>
            <button type="submit" name="add_resignation" class="w-full bg-red-600 text-white py-2 rounded-xl">Submit</button>
        </form>
    </div>
</div>

<!-- Clearance Modal -->
<div id="clearanceModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full">
        <div class="p-4 border-b flex justify-between items-center">
            <h2 class="text-lg font-bold">Update Clearance Status</h2>
            <button onclick="document.getElementById('clearanceModal').classList.add('hidden')" class="text-gray-500">&times;</button>
        </div>
        <form method="POST" class="p-4 space-y-4">
            <input type="hidden" name="resign_id" id="clearance_resign_id">
            <div>
                <label class="block text-sm font-medium mb-1">Clearance Status</label>
                <select name="clearance_status" id="clearance_status" class="w-full px-4 py-2 border rounded-xl">
                    <option value="pending">Pending</option>
                    <option value="in_progress">In Progress</option>
                    <option value="completed">Completed</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Remarks</label>
                <textarea name="remarks" id="clearance_remarks" rows="3" class="w-full px-4 py-2 border rounded-xl" placeholder="Clearance remarks..."></textarea>
            </div>
            <button type="submit" name="update_clearance" class="w-full bg-blue-600 text-white py-2 rounded-xl">Update Clearance</button>
        </form>
    </div>
</div>

<script>
    
    function openClearanceModal(resignation) {
        document.getElementById('clearance_resign_id').value = resignation.id;
        document.getElementById('clearance_status').value = resignation.clearance_status;
        document.getElementById('clearance_remarks').value = resignation.remarks || '';
        document.getElementById('clearanceModal').classList.remove('hidden');
    }
</script>
</body>
</html>