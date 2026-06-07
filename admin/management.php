<?php
require_once '../includes/auth.php';
redirectIfNotAdmin();
require_once '../includes/db.php';
require_once '../includes/functions.php';

// ========================================
// DELETE RESIGNATION RECORD
// ========================================
if (isset($_GET['delete_resignation'])) {
    $id = intval($_GET['delete_resignation']);

    $res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT employee_id, status FROM employee_resignations WHERE id=$id"));
    if ($res) {
        if ($res['status'] == 'approved') {
            mysqli_query($conn, "UPDATE employees SET employment_status='active' WHERE id={$res['employee_id']}");
        }
        mysqli_query($conn, "DELETE FROM employee_resignations WHERE id=$id");
    }
    header('Location: management.php');
    exit();
}

// ========================================
// DELETE TERMINATION RECORD
// ========================================
if (isset($_GET['delete_termination'])) {
    $id = intval($_GET['delete_termination']);

    $term = mysqli_fetch_assoc(mysqli_query($conn, "SELECT employee_id FROM terminations WHERE id=$id"));
    if ($term) {
        mysqli_query($conn, "UPDATE employees SET is_terminated = 0, termination_id = NULL, employment_status = 'active', status = 'active' WHERE id={$term['employee_id']}");
        mysqli_query($conn, "DELETE FROM terminations WHERE id=$id");
    }
    header('Location: management.php');
    exit();
}

// ========================================
// ADD WARNING RECORD
// ========================================
if (isset($_POST['add_warning'])) {
    $employee_id  = intval($_POST['warn_employee_id']);
    $warning_type = mysqli_real_escape_string($conn, $_POST['warning_type']);
    $subject      = mysqli_real_escape_string($conn, $_POST['subject']);
    $description  = mysqli_real_escape_string($conn, $_POST['description']);
    $issued_date  = mysqli_real_escape_string($conn, $_POST['issued_date']);
    $issued_by    = intval($_SESSION['user_id']);
    mysqli_query($conn, "INSERT INTO employee_warnings (employee_id, warning_type, subject, description, issued_by, issued_date)
        VALUES ($employee_id, '$warning_type', '$subject', '$description', $issued_by, '$issued_date')");
    $type_labels = ['verbal'=>'Verbal Warning','written'=>'Written Warning','final'=>'Final Warning','suspension'=>'Suspension','counselling'=>'Counselling'];
    $label = $type_labels[$_POST['warning_type']] ?? 'Warning';
    addNotification($employee_id, $label . ' Issued', 'You have received a ' . strtolower($label) . ': ' . $_POST['subject']);
    logAction('create', $label . ' issued to employee #' . $employee_id . ': ' . $_POST['subject'], mysqli_insert_id($conn), 'warning');
    header('Location: management.php?tab=warnings');
    exit();
}

// ========================================
// DELETE WARNING RECORD
// ========================================
if (isset($_GET['delete_warning'])) {
    $id = intval($_GET['delete_warning']);
    $warn = mysqli_fetch_assoc(mysqli_query($conn, "SELECT employee_id, subject FROM employee_warnings WHERE id=$id"));
    mysqli_query($conn, "DELETE FROM employee_warnings WHERE id=$id");
    logAction('delete', 'Warning record deleted: ' . ($warn['subject'] ?? 'Unknown') . ' for employee #' . ($warn['employee_id'] ?? ''), $id, 'warning');
    header('Location: management.php?tab=warnings');
    exit();
}

// ========================================
// DELETE DOCUMENT
// ========================================
if (isset($_GET['delete_doc'])) {
    $id = intval($_GET['delete_doc']);
    $doc = mysqli_fetch_assoc(mysqli_query($conn, "SELECT file_path FROM employee_documents WHERE id=$id"));
    // Check both possible directories
    if ($doc) {
        $paths = [
            "../uploads/documents/" . $doc['file_path'],
            "../uploads/employee_documents/" . $doc['file_path']
        ];
        foreach ($paths as $path) {
            if (file_exists($path)) {
                unlink($path);
                break;
            }
        }
    }
    mysqli_query($conn, "DELETE FROM employee_documents WHERE id=$id");
    header('Location: management.php');
    exit();
}

// ========================================
// HANDLE RESIGNATION APPROVAL/REJECTION
// ========================================
if (isset($_GET['approve_resignation'])) {
    $id = intval($_GET['approve_resignation']);
    $status = in_array($_GET['status'], ['approved', 'rejected']) ? $_GET['status'] : 'rejected';
    $admin_notes = isset($_POST['admin_notes']) ? mysqli_real_escape_string($conn, $_POST['admin_notes']) : '';

    mysqli_query($conn, "UPDATE employee_resignations SET status='$status', admin_notes='$admin_notes', approved_by={$_SESSION['user_id']}, approved_date=CURDATE() WHERE id=$id");

    $res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT employee_id FROM employee_resignations WHERE id=$id"));
    if ($status == 'approved') {
        mysqli_query($conn, "UPDATE employees SET employment_status='resigned' WHERE id={$res['employee_id']}");
        addNotification($res['employee_id'], 'Resignation Approved', 'Your resignation has been approved.');
    } else {
        addNotification($res['employee_id'], 'Resignation Rejected', 'Your resignation request has been rejected. Reason: ' . $admin_notes);
    }
    header('Location: management.php');
    exit();
}

// ========================================
// HANDLE TERMINATION
// ========================================
if (isset($_POST['send_termination'])) {
    $employee_id = intval($_POST['termination_employee_id']);
    $termination_date = mysqli_real_escape_string($conn, $_POST['termination_date']);
    $effective_date = mysqli_real_escape_string($conn, $_POST['effective_date']);
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);
    $termination_type = mysqli_real_escape_string($conn, $_POST['termination_type']);
    $notice_period_days = intval($_POST['notice_period_days']);
    $severance_pay = floatval($_POST['severance_pay']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
    
    $query = "INSERT INTO terminations (employee_id, termination_date, effective_date, reason, termination_type, notice_period_days, severance_pay, notes, status, created_by) 
              VALUES ($employee_id, '$termination_date', '$effective_date', '$reason', '$termination_type', $notice_period_days, $severance_pay, '$notes', 'approved', {$_SESSION['user_id']})";
    mysqli_query($conn, $query);
    $term_id = mysqli_insert_id($conn);
    
    mysqli_query($conn, "UPDATE employees SET is_terminated = 1, termination_id = $term_id, employment_status = 'terminated', status = 'inactive' WHERE id = $employee_id");
    
    addNotification($employee_id, 'Employment Termination', 'Your employment has been terminated effective ' . date('d M Y', strtotime($effective_date)));
    header('Location: management.php');
    exit();
}

// ========================================
// HANDLE DOCUMENT UPLOAD
// ========================================
if (isset($_POST['upload_document'])) {
    $employee_id = intval($_POST['employee_id']);
    $document_title = mysqli_real_escape_string($conn, $_POST['document_title']);
    $document_type = mysqli_real_escape_string($conn, $_POST['document_type']);
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);

    $allowed_doc_ext  = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
    $allowed_doc_mime = ['image/jpeg', 'image/png', 'application/pdf',
                         'application/msword',
                         'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                         'application/vnd.ms-excel',
                         'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];

    $target_dir = "../uploads/documents/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

    $file_name = basename($_FILES['document_file']['name']);
    $file_size = $_FILES['document_file']['size'];
    $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $mime = mime_content_type($_FILES['document_file']['tmp_name']);
    $file_path = time() . '_' . $employee_id . '.' . $file_extension;

    if (in_array($file_extension, $allowed_doc_ext) && in_array($mime, $allowed_doc_mime)
        && move_uploaded_file($_FILES['document_file']['tmp_name'], $target_dir . $file_path)) {
        $query = "INSERT INTO employee_documents (employee_id, document_title, document_type, file_path, file_name, file_size, upload_date, notes, uploaded_by) 
                  VALUES ($employee_id, '$document_title', '$document_type', '$file_path', '$file_name', $file_size, CURDATE(), '$notes', {$_SESSION['user_id']})";
        mysqli_query($conn, $query);
        addNotification($employee_id, 'New Document', 'A new document "' . $document_title . '" has been uploaded.');
        $success = "Document uploaded successfully!";
    }
}

// ========================================
// GET ALL DATA
// ========================================
$resignations = mysqli_query($conn, "SELECT r.*, e.name, e.employee_id, e.department 
    FROM employee_resignations r 
    JOIN employees e ON r.employee_id = e.id 
    ORDER BY r.created_at DESC");

$terminations = mysqli_query($conn, "SELECT t.*, e.name, e.employee_id, e.department, a.name as created_by_name
    FROM terminations t 
    JOIN employees e ON t.employee_id = e.id 
    LEFT JOIN employees a ON t.created_by = a.id 
    ORDER BY t.created_at DESC");

// Get documents with proper file path detection
$documents = mysqli_query($conn, "SELECT d.*, e.name, e.employee_id, 
    CASE WHEN d.uploaded_by = d.employee_id THEN 'Employee' ELSE 'HR' END as uploaded_by_role
    FROM employee_documents d 
    JOIN employees e ON d.employee_id = e.id 
    ORDER BY d.created_at DESC");

$employees = mysqli_query($conn, "SELECT id, name, employee_id FROM employees WHERE role='employee' AND status='active' AND (is_terminated = 0 OR is_terminated IS NULL)");

$pending_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as count FROM employee_resignations WHERE status='pending'"))['count'];

// Warnings — create table if missing, then query
@mysqli_query($conn, "CREATE TABLE IF NOT EXISTS employee_warnings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    warning_type ENUM('verbal','written','final','suspension','counselling') NOT NULL DEFAULT 'verbal',
    subject VARCHAR(255) NOT NULL,
    description TEXT,
    issued_by INT NULL,
    issued_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (issued_by) REFERENCES employees(id) ON DELETE SET NULL
)");
$all_warnings = [];
try {
    $warnings_result = mysqli_query($conn,
        "SELECT w.*, e.name, e.employee_id as emp_code, a.name as issued_by_name
         FROM employee_warnings w
         JOIN employees e ON w.employee_id = e.id
         LEFT JOIN employees a ON w.issued_by = a.id
         ORDER BY w.issued_date DESC");
    if ($warnings_result) while ($wr = mysqli_fetch_assoc($warnings_result)) $all_warnings[] = $wr;
} catch (Exception $e) { /* table still missing */ }
$warnings_count = count($all_warnings);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Management Portal - IPINFRA HRM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .sidebar { transition: transform 0.3s ease-in-out; }
        .tab-active { background: linear-gradient(135deg, #2563eb, #4f46e5); color: white; }
        .tab-inactive { background-color: #e5e7eb; color: #4b5563; }
        .tab-btn { transition: all 0.2s ease; border-radius: 12px; }
        .tab-btn:hover { transform: translateY(-1px); }
        @keyframes fadeInUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .animate-fadeInUp { animation: fadeInUp 0.4s ease-out; }
        .card-hover { transition: all 0.2s ease; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen pb-20">

<?php require_once '../includes/global_ui.php'; ?>
<?php require_once '../includes/toast.php'; ?>
<?php require_once '../includes/confirm_modal.php'; ?>

<!-- Premium Mobile Header -->
<div class="bg-gradient-to-r from-slate-900 via-indigo-900 to-slate-900 text-white sticky top-0 z-40 shadow-2xl">
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

<!-- SIDEBAR -->
<div id="sidebar" class="fixed top-0 left-0 h-full w-72 bg-gradient-to-b from-gray-900 to-gray-950 text-white z-50 transform -translate-x-full transition-transform duration-300 shadow-2xl overflow-y-auto">
    <div class="p-6 border-b border-gray-800">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center">
                <span class="text-gray-900 font-bold text-xl">IN</span>
            </div>
            <div>
                <h2 class="font-bold"><?php echo $_SESSION['user_name']; ?></h2>
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
        <a href="management.php" class="flex items-center gap-3 py-3 px-4 rounded-xl bg-gray-800/50 mb-1">
            <i class="fas fa-briefcase w-5"></i> Management
        </a>
        <a href="payroll.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-gray-800/30 transition mb-1">
            <i class="fas fa-file-invoice-dollar w-5"></i> Payroll
        </a>
        <a href="holidays.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-gray-800/30 transition mb-1">
            <i class="fas fa-calendar-alt w-5"></i> Holidays
        </a>
        <a href="audit_log.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-gray-800/30 transition mb-1">
            <i class="fas fa-shield-alt w-5"></i> Audit Log
        </a>
        <div class="border-t border-gray-800 my-4"></div>
        <a href="../logout.php" class="flex items-center gap-3 py-3 px-4 rounded-xl bg-red-600/20 text-red-300 hover:bg-red-600/30 transition">
            <i class="fas fa-sign-out-alt w-5"></i> Logout
        </a>
    </nav>
</div>

<div id="overlay" class="fixed inset-0 bg-black/50 z-40 hidden" onclick="toggleSidebar()"></div>

<!-- MAIN CONTENT -->
<div class="px-4 py-6 max-w-7xl mx-auto">
    
    <div class="text-center mb-6 animate-fadeInUp">
        <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-briefcase mr-2 text-indigo-600"></i>Management Portal</h1>
        <p class="text-sm text-gray-500 mt-1">Manage resignations, terminations, and employee documents</p>
    </div>

    <?php if($pending_count > 0): ?>
        <div class="bg-gradient-to-r from-yellow-50 to-orange-50 border-l-4 border-yellow-500 rounded-xl p-4 mb-6 animate-fadeInUp">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                    <i class="fas fa-bell text-yellow-600 text-lg"></i>
                </div>
                <div>
                    <p class="font-semibold text-yellow-800"><?php echo $pending_count; ?> Pending Resignation Request(s)</p>
                    <p class="text-xs text-yellow-600">Please review and take action</p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="flex gap-2 mb-6 overflow-x-auto pb-1">
        <button onclick="showTab('resignations')" id="tabResignations" class="tab-btn shrink-0 px-4 py-2.5 rounded-xl font-semibold transition-all tab-active whitespace-nowrap">
            <i class="fas fa-user-minus mr-1.5"></i>Resignations
            <?php if($pending_count > 0): ?>
                <span class="ml-1.5 bg-red-500 text-white text-xs px-1.5 py-0.5 rounded-full"><?php echo $pending_count; ?></span>
            <?php endif; ?>
        </button>
        <button onclick="showTab('terminations')" id="tabTerminations" class="tab-btn shrink-0 px-4 py-2.5 rounded-xl font-semibold transition-all tab-inactive whitespace-nowrap">
            <i class="fas fa-gavel mr-1.5"></i>Terminations
        </button>
        <button onclick="showTab('documents')" id="tabDocuments" class="tab-btn shrink-0 px-4 py-2.5 rounded-xl font-semibold transition-all tab-inactive whitespace-nowrap">
            <i class="fas fa-folder-open mr-1.5"></i>Documents
        </button>
        <button onclick="showTab('upload')" id="tabUpload" class="tab-btn shrink-0 px-4 py-2.5 rounded-xl font-semibold transition-all tab-inactive whitespace-nowrap">
            <i class="fas fa-upload mr-1.5"></i>Upload
        </button>
        <button onclick="showTab('warnings')" id="tabWarnings" class="tab-btn shrink-0 px-4 py-2.5 rounded-xl font-semibold transition-all tab-inactive whitespace-nowrap">
            <i class="fas fa-exclamation-triangle mr-1.5"></i>Warnings
            <?php if($warnings_count > 0): ?>
                <span class="ml-1.5 bg-orange-500 text-white text-xs px-1.5 py-0.5 rounded-full"><?php echo $warnings_count; ?></span>
            <?php endif; ?>
        </button>
    </div>

    <!-- ======================================== -->
    <!-- RESIGNATIONS TAB -->
    <!-- ======================================== -->
    <div id="resignationsTab" class="animate-fadeInUp">
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <div class="bg-gradient-to-r from-gray-50 to-white px-5 py-4 border-b">
                <div class="flex items-center gap-2">
                    <i class="fas fa-user-minus text-2xl text-red-500"></i>
                    <div>
                        <p class="font-semibold text-gray-800">Employee Resignation Requests</p>
                        <p class="text-xs text-gray-500">Review and process resignation requests</p>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="p-3 text-left text-xs font-semibold text-gray-600 uppercase">Employee</th>
                            <th class="p-3 text-left text-xs font-semibold text-gray-600 uppercase">Requested</th>
                            <th class="p-3 text-left text-xs font-semibold text-gray-600 uppercase">Last Working Day</th>
                            <th class="p-3 text-left text-xs font-semibold text-gray-600 uppercase">Reason</th>
                            <th class="p-3 text-center text-xs font-semibold text-gray-600 uppercase">Status</th>
                            <th class="p-3 text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php while($row = mysqli_fetch_assoc($resignations)): ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="p-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center">
                                        <i class="fas fa-user text-red-600 text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($row['name']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($row['employee_id']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="p-3 text-sm"><?php echo date('d M Y', strtotime($row['requested_date'])); ?></td>
                            <td class="p-3 text-sm font-medium text-red-600"><?php echo date('d M Y', strtotime($row['last_working_date'])); ?></td>
                            <td class="p-3 text-sm max-w-[200px] truncate"><?php echo substr($row['reason'], 0, 50); ?></td>
                            <td class="p-3 text-center">
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold <?php 
                                    echo $row['status'] == 'approved' ? 'bg-green-100 text-green-700' : 
                                        ($row['status'] == 'rejected' ? 'bg-red-100 text-red-700' : 
                                        ($row['status'] == 'cancelled' ? 'bg-gray-100 text-gray-700' : 'bg-yellow-100 text-yellow-700')); ?>">
                                    <i class="fas <?php echo $row['status'] == 'approved' ? 'fa-check-circle' : ($row['status'] == 'rejected' ? 'fa-times-circle' : 'fa-clock'); ?>"></i>
                                    <?php echo ucfirst($row['status']); ?>
                                </span>
                            </td>
                            <td class="p-3 text-center">
                                <?php if($row['status'] == 'pending'): ?>
                                    <div class="flex gap-2 justify-center">
                                        <button onclick="openApproveModal(<?php echo htmlspecialchars(json_encode($row)); ?>)" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded-lg text-xs font-medium transition">Approve</button>
                                        <button onclick="openRejectModal(<?php echo htmlspecialchars(json_encode($row)); ?>)" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded-lg text-xs font-medium transition">Reject</button>
                                        <a href="?delete_resignation=<?php echo $row['id']; ?>" data-confirm="Delete this resignation record?" data-confirm-title="Delete Resignation" class="text-gray-500 hover:text-red-600 transition">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <a href="?delete_resignation=<?php echo $row['id']; ?>" data-confirm="Delete this resignation record?" data-confirm-title="Delete Resignation" class="text-red-600 hover:text-red-800 text-sm">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if(mysqli_num_rows($resignations) == 0): ?>
                        <tr><td colspan="6" class="p-8 text-center text-gray-500">No resignation requests</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ======================================== -->
    <!-- TERMINATIONS TAB -->
    <!-- ======================================== -->
    <div id="terminationsTab" class="hidden animate-fadeInUp">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Send Termination Form -->
            <div class="bg-white rounded-2xl shadow-xl p-6 card-hover">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-10 h-10 bg-gradient-to-br from-red-500 to-rose-600 rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-gavel text-white"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-gray-800">Issue Termination Notice</h2>
                        <p class="text-xs text-gray-500">Terminate employee contract</p>
                    </div>
                </div>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Employee</label>
                        <select name="termination_employee_id" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:outline-none">
                            <option value="">Select Employee</option>
                            <?php while($emp = mysqli_fetch_assoc($employees)): ?>
                                <option value="<?php echo $emp['id']; ?>"><?php echo $emp['name']; ?> (<?php echo $emp['employee_id']; ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Termination Type</label>
                        <select name="termination_type" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:outline-none">
                            <option value="mutual">Mutual Agreement</option>
                            <option value="misconduct">Misconduct</option>
                            <option value="poor_performance">Poor Performance</option>
                            <option value="redundancy">Redundancy / Layoff</option>
                            <option value="contract_end">Contract End</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Termination Date</label>
                            <input type="date" name="termination_date" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Effective Date</label>
                            <input type="date" name="effective_date" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:outline-none">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Notice Period (Days)</label>
                        <input type="number" name="notice_period_days" value="30" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Severance Pay (RM)</label>
                        <input type="number" step="0.01" name="severance_pay" value="0" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Reason for Termination</label>
                        <textarea name="reason" rows="3" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:outline-none" placeholder="Provide detailed reason..."></textarea>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Additional Notes</label>
                        <textarea name="notes" rows="2" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl"></textarea>
                    </div>
                    <button type="button" onclick="confirmTermination(this)" class="w-full bg-gradient-to-r from-red-600 to-rose-600 text-white py-3 rounded-xl font-semibold hover:shadow-xl transition transform hover:scale-105">
                        <i class="fas fa-paper-plane mr-2"></i> Send Termination Notice
                    </button>
                </form>
            </div>
            
            <!-- Termination History -->
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                <div class="bg-gradient-to-r from-gray-50 to-white px-5 py-4 border-b">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-history text-2xl text-red-500"></i>
                        <div>
                            <p class="font-semibold text-gray-800">Termination History</p>
                            <p class="text-xs text-gray-500">Past termination records</p>
                        </div>
                    </div>
                </div>
                <div class="divide-y divide-gray-100 max-h-[500px] overflow-y-auto">
                    <?php while($term = mysqli_fetch_assoc($terminations)): ?>
                    <div class="p-4 hover:bg-gray-50 transition">
                        <div class="flex justify-between items-start">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center">
                                    <i class="fas fa-user text-red-600 text-sm"></i>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($term['name']); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($term['employee_id']); ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs bg-red-100 text-red-700 px-2 py-1 rounded-full">Terminated</span>
                                <a href="?delete_termination=<?php echo $term['id']; ?>" data-confirm="Delete this termination record?" data-confirm-title="Delete Termination" class="text-red-500 hover:text-red-700" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                        <div class="mt-2 text-sm space-y-1">
                            <p><strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $term['termination_type'])); ?></p>
                            <p><strong>Effective:</strong> <?php echo date('d M Y', strtotime($term['effective_date'])); ?></p>
                            <p class="text-gray-600"><?php echo substr($term['reason'], 0, 100); ?></p>
                            <?php if($term['severance_pay'] > 0): ?>
                                <p class="text-green-600 font-semibold">Severance: RM <?php echo number_format($term['severance_pay'], 2); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                    <?php if(mysqli_num_rows($terminations) == 0): ?>
                    <div class="p-8 text-center text-gray-500">No termination records</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================== -->
    <!-- DOCUMENTS TAB - WITH DOWNLOAD BUTTON -->
    <!-- ======================================== -->
    <div id="documentsTab" class="hidden animate-fadeInUp">
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <div class="bg-gradient-to-r from-gray-50 to-white px-5 py-4 border-b">
                <div class="flex items-center gap-2">
                    <i class="fas fa-folder-open text-2xl text-blue-500"></i>
                    <div>
                        <p class="font-semibold text-gray-800">Employee Documents</p>
                        <p class="text-xs text-gray-500">All uploaded documents (HR & Employee)</p>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="p-3 text-left text-xs font-semibold text-gray-600 uppercase">Employee</th>
                            <th class="p-3 text-left text-xs font-semibold text-gray-600 uppercase">Document Title</th>
                            <th class="p-3 text-left text-xs font-semibold text-gray-600 uppercase">Type</th>
                            <th class="p-3 text-left text-xs font-semibold text-gray-600 uppercase">Uploaded By</th>
                            <th class="p-3 text-left text-xs font-semibold text-gray-600 uppercase">Date</th>
                            <th class="p-3 text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php while($doc = mysqli_fetch_assoc($documents)): 
                            // Find the correct file path
                            $file_path = "";
                            $possible_paths = [
                                "../uploads/documents/" . $doc['file_path'],
                                "../uploads/employee_documents/" . $doc['file_path']
                            ];
                            foreach ($possible_paths as $path) {
                                if (file_exists($path)) {
                                    $file_path = $path;
                                    break;
                                }
                            }
                        ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="p-3">
                                <div class="flex items-center gap-2">
                                    <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                                        <i class="fas fa-user text-blue-600 text-sm"></i>
                                    </div>
                                    <div>
                                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($doc['name']); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($doc['employee_id']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="p-3 font-medium"><?php echo $doc['document_title']; ?></td>
                            <td class="p-3">
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-700">
                                    <i class="fas fa-file-alt"></i> <?php echo ucfirst(str_replace('_', ' ', $doc['document_type'])); ?>
                                </span>
                            </td>
                            <td class="p-3">
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold <?php echo $doc['uploaded_by_role'] == 'Employee' ? 'bg-green-100 text-green-700' : 'bg-purple-100 text-purple-700'; ?>">
                                    <i class="fas <?php echo $doc['uploaded_by_role'] == 'Employee' ? 'fa-user' : 'fa-building'; ?>"></i>
                                    <?php echo $doc['uploaded_by_role']; ?>
                                </span>
                            </td>
                            <td class="p-3 text-sm"><?php echo date('d M Y', strtotime($doc['upload_date'])); ?></td>
                            <td class="p-3 text-center">
                                <div class="flex gap-2 justify-center">
                                    <?php if($file_path): ?>
                                        <a href="<?php echo $file_path; ?>" target="_blank" class="bg-blue-500 text-white p-2 rounded-lg hover:bg-blue-600 transition" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?php echo $file_path; ?>" download class="bg-green-500 text-white p-2 rounded-lg hover:bg-green-600 transition" title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="?delete_doc=<?php echo $doc['id']; ?>" data-confirm="Delete this document permanently?" data-confirm-title="Delete Document" class="bg-red-500 text-white p-2 rounded-lg hover:bg-red-600 transition" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if(mysqli_num_rows($documents) == 0): ?>
                        <tr><td colspan="6" class="p-8 text-center text-gray-500">No documents uploaded</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ======================================== -->
    <!-- WARNINGS TAB -->
    <!-- ======================================== -->
    <div id="warningsTab" class="hidden animate-fadeInUp">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Issue Warning Form -->
            <div class="bg-white rounded-2xl shadow-xl p-6 card-hover">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-10 h-10 bg-gradient-to-br from-orange-500 to-amber-600 rounded-xl flex items-center justify-center shadow-md">
                        <i class="fas fa-exclamation-triangle text-white"></i>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-gray-800">Issue Warning / Disciplinary Notice</h2>
                        <p class="text-xs text-gray-500">Record a warning or counselling session</p>
                    </div>
                </div>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Employee</label>
                        <select name="warn_employee_id" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-orange-500 focus:outline-none">
                            <option value="">Select Employee</option>
                            <?php
                            $we = mysqli_query($conn, "SELECT id, name, employee_id FROM employees WHERE role='employee' AND status='active' ORDER BY name");
                            while($emp = mysqli_fetch_assoc($we)): ?>
                                <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['name']); ?> (<?php echo $emp['employee_id']; ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Warning Type</label>
                        <select name="warning_type" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-orange-500 focus:outline-none">
                            <option value="verbal">💬 Verbal Warning</option>
                            <option value="written">📝 Written Warning</option>
                            <option value="final">⛔ Final Warning</option>
                            <option value="suspension">🚫 Suspension</option>
                            <option value="counselling">🤝 Counselling</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Subject</label>
                        <input type="text" name="subject" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-orange-500 focus:outline-none" placeholder="e.g., Late attendance — 3rd occurrence">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Date Issued</label>
                        <input type="date" name="issued_date" required value="<?php echo date('Y-m-d'); ?>" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-orange-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Description / Details</label>
                        <textarea name="description" rows="3" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-orange-500 focus:outline-none" placeholder="Describe the incident or reason for warning..."></textarea>
                    </div>
                    <button type="submit" name="add_warning" class="w-full bg-gradient-to-r from-orange-500 to-amber-500 text-white py-3 rounded-xl font-semibold hover:shadow-xl transition transform hover:scale-105">
                        <i class="fas fa-paper-plane mr-2"></i> Issue Warning
                    </button>
                </form>
            </div>

            <!-- Warning History -->
            <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                <div class="bg-gradient-to-r from-gray-50 to-white px-5 py-4 border-b">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-history text-2xl text-orange-500"></i>
                        <div>
                            <p class="font-semibold text-gray-800">Warning Records</p>
                            <p class="text-xs text-gray-500"><?php echo $warnings_count; ?> record(s) total</p>
                        </div>
                    </div>
                </div>
                <?php if(empty($all_warnings)): ?>
                <div class="p-12 text-center">
                    <div class="w-16 h-16 bg-orange-50 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-shield-check text-3xl text-orange-300"></i>
                    </div>
                    <p class="font-semibold text-gray-600">No warnings on record</p>
                    <p class="text-xs text-gray-400 mt-1">All employees are in good standing</p>
                </div>
                <?php else: ?>
                <div class="divide-y divide-gray-100 max-h-[520px] overflow-y-auto">
                    <?php
                    $warn_badges = [
                        'verbal'      => ['bg-blue-100 text-blue-700',   'fa-comment',              'Verbal'],
                        'written'     => ['bg-yellow-100 text-yellow-700','fa-file-alt',             'Written'],
                        'final'       => ['bg-red-100 text-red-700',     'fa-exclamation-circle',   'Final'],
                        'suspension'  => ['bg-purple-100 text-purple-700','fa-ban',                  'Suspension'],
                        'counselling' => ['bg-green-100 text-green-700', 'fa-hands-helping',        'Counselling'],
                    ];
                    foreach($all_warnings as $w):
                        $badge = $warn_badges[$w['warning_type']] ?? ['bg-gray-100 text-gray-700','fa-flag','Unknown'];
                    ?>
                    <div class="p-4 hover:bg-orange-50/40 transition">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-9 h-9 rounded-full bg-orange-100 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-user text-orange-600 text-sm"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="font-semibold text-gray-800 truncate"><?php echo htmlspecialchars($w['name']); ?></p>
                                    <p class="text-xs text-gray-400"><?php echo $w['emp_code']; ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold <?php echo $badge[0]; ?>">
                                    <i class="fas <?php echo $badge[1]; ?> text-xs"></i>
                                    <?php echo $badge[2]; ?>
                                </span>
                                <a href="?delete_warning=<?php echo $w['id']; ?>" data-confirm="Delete this warning record permanently?" data-confirm-title="Delete Warning" class="text-red-400 hover:text-red-600 transition p-1" title="Delete">
                                    <i class="fas fa-trash text-sm"></i>
                                </a>
                            </div>
                        </div>
                        <div class="mt-2 ml-11">
                            <p class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($w['subject']); ?></p>
                            <?php if($w['description']): ?>
                                <p class="text-xs text-gray-500 mt-1 line-clamp-2"><?php echo htmlspecialchars(substr($w['description'], 0, 120)); ?></p>
                            <?php endif; ?>
                            <p class="text-xs text-gray-400 mt-1.5">
                                <i class="fas fa-calendar-alt mr-1"></i><?php echo date('d M Y', strtotime($w['issued_date'])); ?>
                                <?php if($w['issued_by_name']): ?>
                                    &nbsp;·&nbsp;<i class="fas fa-user-shield mr-1"></i><?php echo htmlspecialchars($w['issued_by_name']); ?>
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ======================================== -->
    <!-- UPLOAD TAB -->
    <!-- ======================================== -->
    <div id="uploadTab" class="hidden animate-fadeInUp">
        <div class="bg-white rounded-2xl shadow-xl p-6 max-w-lg mx-auto card-hover">
            <div class="flex items-center gap-3 mb-5">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-md">
                    <i class="fas fa-upload text-white"></i>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-gray-800">Upload Employee Document</h2>
                    <p class="text-xs text-gray-500">Add document to employee record</p>
                </div>
            </div>
            <?php if(isset($success)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-3 rounded-xl mb-4"><?php echo $success; ?></div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Employee</label>
                    <select name="employee_id" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none">
                        <option value="">Select Employee</option>
                        <?php 
                        $all_emps = mysqli_query($conn, "SELECT id, name, employee_id FROM employees WHERE role='employee'");
                        while($emp = mysqli_fetch_assoc($all_emps)): ?>
                            <option value="<?php echo $emp['id']; ?>"><?php echo $emp['name']; ?> (<?php echo $emp['employee_id']; ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Document Type</label>
                    <select name="document_type" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none">
                        <option value="offer_letter">📄 Offer Letter</option>
                        <option value="contract">📑 Employment Contract</option>
                        <option value="id_copy">🆔 IC/Passport Copy</option>
                        <option value="academic_certificate">🎓 Academic Certificate</option>
                        <option value="performance_review">⭐ Performance Review</option>
                        <option value="disciplinary">⚠️ Disciplinary Record</option>
                        <option value="other">📁 Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Document Title</label>
                    <input type="text" name="document_title" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none" placeholder="e.g., Annual Performance Review 2024">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Select File</label>
                    <div class="relative">
                        <input type="file" name="document_file" id="docFile" required class="hidden" accept=".pdf,.doc,.docx,.jpg,.png">
                        <button type="button" onclick="document.getElementById('docFile').click()" class="w-full border-2 border-dashed border-gray-300 rounded-xl p-4 text-center hover:border-blue-500 transition group">
                            <i class="fas fa-cloud-upload-alt text-gray-400 text-3xl group-hover:text-blue-500 transition"></i>
                            <p class="text-sm text-gray-500 mt-1 group-hover:text-blue-500 transition">Click to select file</p>
                            <p class="text-xs text-gray-400 mt-1" id="fileName">No file chosen</p>
                        </button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Notes (Optional)</label>
                    <textarea name="notes" rows="2" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none"></textarea>
                </div>
                <button type="submit" name="upload_document" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white py-3 rounded-xl font-semibold hover:shadow-xl transition transform hover:scale-105">
                    <i class="fas fa-upload mr-2"></i> Upload Document
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Approve Modal -->
<div id="approveModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl">
        <div class="bg-gradient-to-r from-green-600 to-emerald-600 p-5 rounded-t-2xl">
            <h2 class="text-xl font-bold text-white">Approve Resignation</h2>
            <p class="text-xs text-green-100 mt-1">Confirm resignation approval</p>
        </div>
        <form method="POST" class="p-5 space-y-4" id="approveForm">
            <div class="bg-green-50 p-3 rounded-xl text-center">
                <i class="fas fa-check-circle text-green-600 text-2xl mb-2 block"></i>
                <p class="text-sm text-green-800">Are you sure you want to approve this resignation?</p>
            </div>
            <div>
                <textarea name="admin_notes" rows="2" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none" placeholder="Optional notes..."></textarea>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-green-600 text-white py-2.5 rounded-xl font-semibold hover:bg-green-700 transition">Confirm Approve</button>
                <button type="button" onclick="closeModals()" class="flex-1 bg-gray-200 text-gray-700 py-2.5 rounded-xl font-semibold hover:bg-gray-300 transition">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl">
        <div class="bg-gradient-to-r from-red-600 to-rose-600 p-5 rounded-t-2xl">
            <h2 class="text-xl font-bold text-white">Reject Resignation</h2>
            <p class="text-xs text-red-100 mt-1">Confirm resignation rejection</p>
        </div>
        <form method="POST" class="p-5 space-y-4" id="rejectForm">
            <div class="bg-red-50 p-3 rounded-xl text-center">
                <i class="fas fa-times-circle text-red-600 text-2xl mb-2 block"></i>
                <p class="text-sm text-red-800">Are you sure you want to reject this resignation?</p>
            </div>
            <div>
                <textarea name="admin_notes" rows="3" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:outline-none" placeholder="Reason for rejection..."></textarea>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-red-600 text-white py-2.5 rounded-xl font-semibold hover:bg-red-700 transition">Confirm Reject</button>
                <button type="button" onclick="closeModals()" class="flex-1 bg-gray-200 text-gray-700 py-2.5 rounded-xl font-semibold hover:bg-gray-300 transition">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Mobile Bottom Navigation -->
<div class="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-lg border-t border-gray-200 md:hidden shadow-2xl z-30">
    <div class="flex justify-around py-2">
        <a href="dashboard.php" class="flex flex-col items-center py-2 px-4 text-gray-500 hover:text-blue-600 transition group">
            <i class="fas fa-home text-xl group-hover:scale-110 transition"></i>
            <span class="text-xs mt-1">Home</span>
        </a>
        <a href="employees.php" class="flex flex-col items-center py-2 px-4 text-gray-500 hover:text-blue-600 transition group">
            <i class="fas fa-users text-xl group-hover:scale-110 transition"></i>
            <span class="text-xs mt-1">Staff</span>
        </a>
        <a href="management.php" class="flex flex-col items-center py-2 px-4 text-blue-600 relative">
            <i class="fas fa-briefcase text-xl"></i>
            <span class="text-xs mt-1 font-semibold">Manage</span>
            <div class="absolute -top-1 right-1 w-2 h-2 bg-blue-600 rounded-full"></div>
        </a>
        <a href="payroll.php" class="flex flex-col items-center py-2 px-4 text-gray-500 hover:text-blue-600 transition group">
            <i class="fas fa-file-invoice-dollar text-xl group-hover:scale-110 transition"></i>
            <span class="text-xs mt-1">Payroll</span>
        </a>
    </div>
</div>

<script>
let currentResignationId = null;

function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('-translate-x-full');
    document.getElementById('overlay').classList.toggle('hidden');
}

function showTab(tab) {
    const tabs = ['resignations', 'terminations', 'documents', 'upload', 'warnings'];
    tabs.forEach(t => {
        const el = document.getElementById(t + 'Tab');
        if (el) el.classList.add('hidden');
        const btn = document.getElementById('tab' + t.charAt(0).toUpperCase() + t.slice(1));
        if (btn) btn.className = 'tab-btn flex-1 py-2.5 rounded-xl font-semibold transition-all tab-inactive';
    });
    const activeEl = document.getElementById(tab + 'Tab');
    if (activeEl) activeEl.classList.remove('hidden');
    const activeBtn = document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1));
    if (activeBtn) activeBtn.className = 'tab-btn flex-1 py-2.5 rounded-xl font-semibold transition-all tab-active';
}

// Auto-open tab from URL
(function() {
    const params = new URLSearchParams(window.location.search);
    const t = params.get('tab');
    if (t) showTab(t);
})();

function openApproveModal(resignation) {
    currentResignationId = resignation.id;
    document.getElementById('approveForm').action = '?approve_resignation=' + resignation.id + '&status=approved';
    document.getElementById('approveModal').classList.remove('hidden');
}

function openRejectModal(resignation) {
    currentResignationId = resignation.id;
    document.getElementById('rejectForm').action = '?approve_resignation=' + resignation.id + '&status=rejected';
    document.getElementById('rejectModal').classList.remove('hidden');
}

function closeModals() {
    document.getElementById('approveModal').classList.add('hidden');
    document.getElementById('rejectModal').classList.add('hidden');
}

function confirmTermination(btn) {
    const form = btn.closest('form');
    confirmAction(
        'Send Termination Notice',
        'This will permanently mark the employee as <strong>terminated</strong> and deactivate their account. This cannot be undone.',
        function() { form.submit(); }
    );
}

// File name display
document.getElementById('docFile')?.addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name || 'No file chosen';
    document.getElementById('fileName').textContent = fileName;
});
</script>
</body>
</html>