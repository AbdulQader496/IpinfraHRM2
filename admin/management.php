<?php
require_once '../includes/auth.php';
redirectIfNotAdmin();
require_once '../includes/db.php';
/** @var mysqli $conn */
require_once '../includes/functions.php';

// ========================================
// DELETE RESIGNATION RECORD
// ========================================
if (isset($_POST['delete_resignation']) && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $id = intval($_POST['delete_resignation']);

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
if (isset($_POST['delete_termination']) && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $id = intval($_POST['delete_termination']);

    $term = mysqli_fetch_assoc(mysqli_query($conn, "SELECT employee_id FROM terminations WHERE id=$id"));
    if ($term) {
        mysqli_query($conn, "UPDATE employees SET is_terminated = 0, termination_id = NULL, employment_status = 'active', status = 'active' WHERE id={$term['employee_id']}");
        mysqli_query($conn, "DELETE FROM terminations WHERE id=$id");
    }
    header('Location: management.php');
    exit();
}

// ========================================
// DELETE DOCUMENT
// ========================================
if (isset($_POST['delete_doc']) && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $id = intval($_POST['delete_doc']);
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
if (isset($_POST['approve_resignation']) && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    $id = intval($_POST['approve_resignation']);
    $status = in_array($_POST['status'] ?? '', ['approved', 'rejected']) ? $_POST['status'] : 'rejected';
    $admin_notes = isset($_POST['admin_notes']) ? mysqli_real_escape_string($conn, $_POST['admin_notes']) : '';

    // Atomic guard: only transition a still-pending request, and fetch the employee_id
    // from the same pre-update state instead of assuming the row exists afterward.
    $res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT employee_id FROM employee_resignations WHERE id=$id AND status='pending'"));
    if ($res) {
        mysqli_query($conn, "UPDATE employee_resignations SET status='$status', admin_notes='$admin_notes', approved_by={$_SESSION['user_id']}, approved_date=CURDATE() WHERE id=$id AND status='pending'");
        if (mysqli_affected_rows($conn) > 0) {
            if ($status == 'approved') {
                mysqli_query($conn, "UPDATE employees SET employment_status='resigned' WHERE id={$res['employee_id']}");
                addNotification($res['employee_id'], 'Resignation Approved', 'Your resignation has been approved.');
            } else {
                addNotification($res['employee_id'], 'Resignation Rejected', 'Your resignation request has been rejected. Reason: ' . $admin_notes);
            }
        }
    }
    header('Location: management.php');
    exit();
}

// ========================================
// HANDLE TERMINATION
// ========================================
if (isset($_POST['send_termination'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) { header('Location: management.php'); exit(); }
    $employee_id = intval($_POST['termination_employee_id']);
    $termination_date = $_POST['termination_date'];
    $effective_date = $_POST['effective_date'];
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);
    $termination_type = $_POST['termination_type'];
    $notice_period_days = intval($_POST['notice_period_days'] ?? 0);
    $severance_pay = floatval($_POST['severance_pay'] ?? 0);
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
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) { header('Location: management.php'); exit(); }
    $employee_id = intval($_POST['employee_id']);
    $document_title = mysqli_real_escape_string($conn, $_POST['document_title']);
    $document_type = $_POST['document_type'];
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);

    $target_dir = "../uploads/documents/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

    $file_name = basename($_FILES['document_file']['name']);
    $file_name_escaped = mysqli_real_escape_string($conn, $file_name);
    $file_size = $_FILES['document_file']['size'];
    $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $file_path = bin2hex(random_bytes(8)) . '.' . $file_extension;

    if ($_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'File upload error. Please try again.';
    } elseif ($file_size > 10 * 1024 * 1024) {
        $error = 'File must be under 10 MB.';
    } else {
        $allowed_ext = ['pdf','jpg','jpeg','png','doc','docx','xls','xlsx'];
        $allowed_doc = ['application/pdf','image/jpeg','image/png','application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $doc_mime = finfo_file($finfo, $_FILES['document_file']['tmp_name']);
        if (!in_array($file_extension, $allowed_ext) || !in_array($doc_mime, $allowed_doc)) {
            $error = 'Only PDF, image, Word, or Excel files are allowed.';
        }
    }

    if (!isset($error)) {
        if (move_uploaded_file($_FILES['document_file']['tmp_name'], $target_dir . $file_path)) {
            $query = "INSERT INTO employee_documents (employee_id, document_title, document_type, file_path, file_name, file_size, upload_date, notes, uploaded_by)
                      VALUES ($employee_id, '$document_title', '$document_type', '$file_path', '$file_name_escaped', $file_size, CURDATE(), '$notes', {$_SESSION['user_id']})";
            mysqli_query($conn, $query);
            addNotification($employee_id, 'New Document', 'A new document "' . $document_title . '" has been uploaded.');
            $success = "Document uploaded successfully!";
        }
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

<?php require_once '../includes/admin_sidebar.php'; ?>

<!-- MAIN CONTENT -->
<div class="px-4 py-6 max-w-7xl mx-auto">

    <div class="text-center mb-6 animate-fadeInUp">
        <h1 class="text-2xl font-bold text-gray-800">📋 Management Portal</h1>
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

    <?php if(isset($error)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-3 rounded-xl mb-4"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>

    <!-- Tabs -->
    <!-- flex-1 on 4 tabs (one with a long label, "Upload Document") squeezed them past the
         container's width on mobile with no way to scroll to the ones that didn't fit --
         switched to the same horizontally-scrollable tab pattern already used successfully
         on employee/management.php: natural-width, non-wrapping buttons in a scroll container. -->
    <div class="flex gap-3 mb-6 bg-white/50 backdrop-blur-sm rounded-2xl p-2 shadow-lg overflow-x-auto">
        <button onclick="showTab('resignations')" id="tabResignations" class="tab-btn shrink-0 sm:flex-1 whitespace-nowrap py-2.5 px-4 rounded-xl font-semibold transition-all tab-active">
            <i class="fas fa-user-minus mr-2"></i> Resignations
            <?php if($pending_count > 0): ?>
                <span class="ml-2 bg-red-500 text-white text-xs px-2 py-0.5 rounded-full"><?php echo $pending_count; ?></span>
            <?php endif; ?>
        </button>
        <button onclick="showTab('terminations')" id="tabTerminations" class="tab-btn shrink-0 sm:flex-1 whitespace-nowrap py-2.5 px-4 rounded-xl font-semibold transition-all tab-inactive">
            <i class="fas fa-gavel mr-2"></i> Terminations
        </button>
        <button onclick="showTab('documents')" id="tabDocuments" class="tab-btn shrink-0 sm:flex-1 whitespace-nowrap py-2.5 px-4 rounded-xl font-semibold transition-all tab-inactive">
            <i class="fas fa-folder-open mr-2"></i> Employee Documents
        </button>
        <button onclick="showTab('upload')" id="tabUpload" class="tab-btn shrink-0 sm:flex-1 whitespace-nowrap py-2.5 px-4 rounded-xl font-semibold transition-all tab-inactive">
            <i class="fas fa-upload mr-2"></i> Upload Document
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
            <!-- Desktop table -->
            <div class="overflow-x-auto hidden md:block">
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
                                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($row['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($row['employee_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="p-3 text-sm"><?php echo date('d M Y', strtotime($row['requested_date'])); ?></td>
                            <td class="p-3 text-sm font-medium text-red-600"><?php echo date('d M Y', strtotime($row['last_working_date'])); ?></td>
                            <td class="p-3 text-sm max-w-[200px] truncate"><?php echo htmlspecialchars(substr($row['reason'] ?? '', 0, 50), ENT_QUOTES, 'UTF-8'); ?></td>
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
                                        <button onclick="openApproveModal(<?php echo htmlspecialchars(json_encode(['id' => $row['id'], 'employee_id' => $row['employee_id'], 'name' => $row['name']], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG), ENT_QUOTES, 'UTF-8'); ?>)" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded-lg text-xs font-medium transition">Approve</button>
                                        <button onclick="openRejectModal(<?php echo htmlspecialchars(json_encode(['id' => $row['id'], 'employee_id' => $row['employee_id'], 'name' => $row['name']], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG), ENT_QUOTES, 'UTF-8'); ?>)" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded-lg text-xs font-medium transition">Reject</button>
                                        <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this resignation record?')">
                                            <?php echo csrfField(); ?>
                                            <input type="hidden" name="delete_resignation" value="<?php echo intval($row['id']); ?>">
                                            <button type="submit" class="text-gray-500 hover:text-red-600 transition">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this resignation record?')">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="delete_resignation" value="<?php echo intval($row['id']); ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-800 text-sm">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
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

            <!-- Mobile cards -->
            <div class="md:hidden divide-y divide-gray-100">
                <?php
                mysqli_data_seek($resignations, 0);
                if (mysqli_num_rows($resignations) == 0): ?>
                    <p class="p-8 text-center text-gray-500">No resignation requests</p>
                <?php else: while($row = mysqli_fetch_assoc($resignations)): ?>
                <div class="p-4">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-9 h-9 shrink-0 rounded-full bg-red-100 flex items-center justify-center">
                                <i class="fas fa-user text-red-600 text-sm"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-gray-800 text-sm truncate"><?php echo htmlspecialchars($row['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                                <p class="text-xs text-gray-400"><?php echo htmlspecialchars($row['employee_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                            </div>
                        </div>
                        <span class="shrink-0 inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold <?php
                            echo $row['status'] == 'approved' ? 'bg-green-100 text-green-700' :
                                ($row['status'] == 'rejected' ? 'bg-red-100 text-red-700' :
                                ($row['status'] == 'cancelled' ? 'bg-gray-100 text-gray-700' : 'bg-yellow-100 text-yellow-700')); ?>">
                            <i class="fas <?php echo $row['status'] == 'approved' ? 'fa-check-circle' : ($row['status'] == 'rejected' ? 'fa-times-circle' : 'fa-clock'); ?>"></i>
                            <?php echo ucfirst($row['status']); ?>
                        </span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 mb-3 bg-gray-50 rounded-xl p-3 text-sm">
                        <div>
                            <p class="text-[10px] uppercase tracking-wide text-gray-400 font-semibold mb-0.5">Requested</p>
                            <p class="text-gray-700"><?php echo date('d M Y', strtotime($row['requested_date'])); ?></p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase tracking-wide text-gray-400 font-semibold mb-0.5">Last Working Day</p>
                            <p class="text-red-600 font-medium"><?php echo date('d M Y', strtotime($row['last_working_date'])); ?></p>
                        </div>
                    </div>
                    <?php if(!empty($row['reason'])): ?>
                        <p class="text-xs text-gray-500 mb-3">"<?php echo htmlspecialchars(substr($row['reason'], 0, 100), ENT_QUOTES, 'UTF-8'); ?>"</p>
                    <?php endif; ?>
                    <?php if($row['status'] == 'pending'): ?>
                        <div class="flex gap-2">
                            <button onclick="openApproveModal(<?php echo htmlspecialchars(json_encode(['id' => $row['id'], 'employee_id' => $row['employee_id'], 'name' => $row['name']], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG), ENT_QUOTES, 'UTF-8'); ?>)" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-2 rounded-lg text-sm font-medium transition">Approve</button>
                            <button onclick="openRejectModal(<?php echo htmlspecialchars(json_encode(['id' => $row['id'], 'employee_id' => $row['employee_id'], 'name' => $row['name']], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG), ENT_QUOTES, 'UTF-8'); ?>)" class="flex-1 bg-red-600 hover:bg-red-700 text-white py-2 rounded-lg text-sm font-medium transition">Reject</button>
                            <form method="POST" onsubmit="return confirm('Delete this resignation record?')">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="delete_resignation" value="<?php echo intval($row['id']); ?>">
                                <button type="submit" class="px-3 py-2 rounded-lg bg-gray-100 text-gray-500 hover:text-red-600 transition">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <form method="POST" onsubmit="return confirm('Delete this resignation record?')">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="delete_resignation" value="<?php echo intval($row['id']); ?>">
                            <button type="submit" class="w-full py-2 rounded-lg bg-red-50 text-red-600 text-sm font-medium">
                                <i class="fas fa-trash mr-1"></i> Delete
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php endwhile; endif; ?>
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
                <form method="POST" id="termination-form" class="space-y-4">
                    <?php echo csrfField(); ?>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Employee</label>
                        <select name="termination_employee_id" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:outline-none">
                            <option value="">Select Employee</option>
                            <?php while($emp = mysqli_fetch_assoc($employees)): ?>
                                <option value="<?php echo intval($emp['id']); ?>"><?php echo htmlspecialchars($emp['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($emp['employee_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>)</option>
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
                    <button type="submit" name="send_termination" class="w-full bg-gradient-to-r from-red-600 to-rose-600 text-white py-3 rounded-xl font-semibold hover:shadow-xl transition transform hover:scale-105">
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
                                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($term['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($term['employee_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-xs bg-red-100 text-red-700 px-2 py-1 rounded-full">Terminated</span>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this termination record?')">
                                    <?php echo csrfField(); ?>
                                    <input type="hidden" name="delete_termination" value="<?php echo intval($term['id']); ?>">
                                    <button type="submit" class="text-red-500 hover:text-red-700" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="mt-2 text-sm space-y-1">
                            <p><strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $term['termination_type'])); ?></p>
                            <p><strong>Effective:</strong> <?php echo date('d M Y', strtotime($term['effective_date'])); ?></p>
                            <p class="text-gray-600"><?php echo htmlspecialchars(substr($term['reason'] ?? '', 0, 100), ENT_QUOTES, 'UTF-8'); ?></p>
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
            <!-- Desktop table -->
            <div class="overflow-x-auto hidden md:block">
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
                                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($doc['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                                        <p class="text-xs text-gray-500"><?php echo htmlspecialchars($doc['employee_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td class="p-3 font-medium"><?php echo htmlspecialchars($doc['document_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="p-3">
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-700">
                                    <i class="fas fa-file-alt"></i> <?php echo ucfirst(str_replace('_', ' ', $doc['document_type'])); ?>
                                </span>
                            </td>
                            <td class="p-3">
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold <?php echo $doc['uploaded_by_role'] == 'Employee' ? 'bg-green-100 text-green-700' : 'bg-purple-100 text-purple-700'; ?>">
                                    <i class="fas <?php echo $doc['uploaded_by_role'] == 'Employee' ? 'fa-user' : 'fa-building'; ?>"></i>
                                    <?php echo htmlspecialchars($doc['uploaded_by_role'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                                </span>
                            </td>
                            <td class="p-3 text-sm"><?php echo date('d M Y', strtotime($doc['upload_date'])); ?></td>
                            <td class="p-3 text-center">
                                <div class="flex gap-2 justify-center">
                                    <?php if($file_path): ?>
                                        <a href="<?php echo htmlspecialchars($file_path, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="bg-blue-500 text-white p-2 rounded-lg hover:bg-blue-600 transition" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?php echo htmlspecialchars($file_path, ENT_QUOTES, 'UTF-8'); ?>" download class="bg-green-500 text-white p-2 rounded-lg hover:bg-green-600 transition" title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    <?php endif; ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this document permanently?')">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="delete_doc" value="<?php echo intval($doc['id']); ?>">
                                        <button type="submit" class="bg-red-500 text-white p-2 rounded-lg hover:bg-red-600 transition" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
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

            <!-- Mobile cards -->
            <div class="md:hidden divide-y divide-gray-100">
                <?php
                mysqli_data_seek($documents, 0);
                if (mysqli_num_rows($documents) == 0): ?>
                    <p class="p-8 text-center text-gray-500">No documents uploaded</p>
                <?php else: while($doc = mysqli_fetch_assoc($documents)):
                    $file_path = "";
                    $possible_paths = [
                        "../uploads/documents/" . $doc['file_path'],
                        "../uploads/employee_documents/" . $doc['file_path']
                    ];
                    foreach ($possible_paths as $path) {
                        if (file_exists($path)) { $file_path = $path; break; }
                    }
                ?>
                <div class="p-4">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-9 h-9 shrink-0 rounded-full bg-blue-100 flex items-center justify-center">
                            <i class="fas fa-user text-blue-600 text-sm"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-800 text-sm truncate"><?php echo htmlspecialchars($doc['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="text-xs text-gray-400"><?php echo htmlspecialchars($doc['employee_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                        </div>
                    </div>
                    <p class="font-medium text-gray-800 text-sm mb-2"><?php echo htmlspecialchars($doc['document_title'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                    <div class="flex items-center gap-2 flex-wrap mb-3">
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs bg-blue-100 text-blue-700">
                            <i class="fas fa-file-alt"></i> <?php echo ucfirst(str_replace('_', ' ', $doc['document_type'])); ?>
                        </span>
                        <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-semibold <?php echo $doc['uploaded_by_role'] == 'Employee' ? 'bg-green-100 text-green-700' : 'bg-purple-100 text-purple-700'; ?>">
                            <i class="fas <?php echo $doc['uploaded_by_role'] == 'Employee' ? 'fa-user' : 'fa-building'; ?>"></i>
                            <?php echo htmlspecialchars($doc['uploaded_by_role'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                        </span>
                        <span class="text-xs text-gray-400"><?php echo date('d M Y', strtotime($doc['upload_date'])); ?></span>
                    </div>
                    <div class="flex gap-2">
                        <?php if($file_path): ?>
                            <a href="<?php echo htmlspecialchars($file_path, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" class="flex-1 flex items-center justify-center gap-1.5 bg-blue-50 text-blue-600 py-2 rounded-lg text-sm font-medium">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="<?php echo htmlspecialchars($file_path, ENT_QUOTES, 'UTF-8'); ?>" download class="flex-1 flex items-center justify-center gap-1.5 bg-green-50 text-green-600 py-2 rounded-lg text-sm font-medium">
                                <i class="fas fa-download"></i> Download
                            </a>
                        <?php endif; ?>
                        <form method="POST" onsubmit="return confirm('Delete this document permanently?')">
                            <?php echo csrfField(); ?>
                            <input type="hidden" name="delete_doc" value="<?php echo intval($doc['id']); ?>">
                            <button type="submit" class="px-3 py-2 rounded-lg bg-red-50 text-red-600" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
                <?php endwhile; endif; ?>
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
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-3 rounded-xl mb-4"><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <?php echo csrfField(); ?>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">Employee</label>
                    <select name="employee_id" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none">
                        <option value="">Select Employee</option>
                        <?php
                        $all_emps = mysqli_query($conn, "SELECT id, name, employee_id FROM employees WHERE role='employee'");
                        while($emp = mysqli_fetch_assoc($all_emps)): ?>
                            <option value="<?php echo intval($emp['id']); ?>"><?php echo htmlspecialchars($emp['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($emp['employee_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?>)</option>
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
            <?php echo csrfField(); ?>
            <input type="hidden" name="approve_resignation" id="approveResignationId" value="">
            <input type="hidden" name="status" value="approved">
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
            <?php echo csrfField(); ?>
            <input type="hidden" name="approve_resignation" id="rejectResignationId" value="">
            <input type="hidden" name="status" value="rejected">
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

<script>
let currentResignationId = null;


function showTab(tab) {
    const tabs = ['resignations', 'terminations', 'documents', 'upload'];
    tabs.forEach(t => {
        const el = document.getElementById(t + 'Tab');
        if (el) el.classList.add('hidden');
        const btn = document.getElementById('tab' + t.charAt(0).toUpperCase() + t.slice(1));
        if (btn) {
            btn.className = 'tab-btn flex-1 py-2.5 rounded-xl font-semibold transition-all tab-inactive';
        }
    });
    const activeEl = document.getElementById(tab + 'Tab');
    if (activeEl) activeEl.classList.remove('hidden');
    const activeBtn = document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1));
    if (activeBtn) {
        activeBtn.className = 'tab-btn flex-1 py-2.5 rounded-xl font-semibold transition-all tab-active';
    }
}

function openApproveModal(resignation) {
    currentResignationId = resignation.id;
    document.getElementById('approveResignationId').value = resignation.id;
    document.getElementById('approveModal').classList.remove('hidden');
}

function openRejectModal(resignation) {
    currentResignationId = resignation.id;
    document.getElementById('rejectResignationId').value = resignation.id;
    document.getElementById('rejectModal').classList.remove('hidden');
}

function closeModals() {
    document.getElementById('approveModal').classList.add('hidden');
    document.getElementById('rejectModal').classList.add('hidden');
}

// File name display
document.getElementById('docFile')?.addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name || 'No file chosen';
    document.getElementById('fileName').textContent = fileName;
});

// Termination form confirmation (F052)
document.getElementById('termination-form')?.addEventListener('submit', function(e) {
    if (!confirm('Send termination notice to this employee?')) e.preventDefault();
});
</script>
</body>
</html>
