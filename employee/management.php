<?php
require_once '../includes/auth.php';
redirectIfNotLoggedIn();
require_once '../includes/db.php';
require_once '../includes/functions.php';

$user_id    = $_SESSION['user_id'];
$success    = '';
$error      = '';
$valid_tabs = ['documents', 'upload', 'resignation', 'warnings'];
$active_tab = in_array($_GET['tab'] ?? '', $valid_tabs) ? $_GET['tab'] : 'documents';

// ========================================
// HANDLE RESIGNATION REQUEST
// ========================================
if (isset($_POST['submit_resignation'])) {
    $last_working_date = mysqli_real_escape_string($conn, $_POST['last_working_date'] ?? '');
    $reason            = mysqli_real_escape_string($conn, $_POST['reason'] ?? '');
    $requested_date    = date('Y-m-d');

    $check = mysqli_query($conn, "SELECT id FROM employee_resignations WHERE employee_id = $user_id AND status = 'pending'");
    if (mysqli_num_rows($check) > 0) {
        $error = 'You already have a pending resignation request.';
    } else {
        mysqli_query($conn, "INSERT INTO employee_resignations (employee_id, requested_date, last_working_date, reason, status)
            VALUES ($user_id, '$requested_date', '$last_working_date', '$reason', 'pending')");
        $admin_q = mysqli_query($conn, "SELECT id FROM employees WHERE role='admin' LIMIT 1");
        if ($admin = mysqli_fetch_assoc($admin_q)) {
            addNotification($admin['id'], 'New Resignation Request', $_SESSION['user_name'] . ' has submitted a resignation request.');
        }
        $success    = 'Resignation request submitted successfully!';
        $active_tab = 'resignation';
    }
}

// ========================================
// CANCEL RESIGNATION
// ========================================
if (isset($_GET['cancel_resignation'])) {
    $id = intval($_GET['cancel_resignation']);
    mysqli_query($conn, "UPDATE employee_resignations SET status = 'cancelled' WHERE id = $id AND employee_id = $user_id");
    $success    = 'Resignation request cancelled.';
    $active_tab = 'resignation';
}

// ========================================
// HANDLE DOCUMENT UPLOAD TO HR
// ========================================
if (isset($_POST['upload_document'])) {
    $document_title = mysqli_real_escape_string($conn, $_POST['document_title'] ?? '');
    $document_type  = mysqli_real_escape_string($conn, $_POST['document_type']  ?? '');
    $notes          = mysqli_real_escape_string($conn, $_POST['notes']          ?? '');

    $allowed_ext  = ['jpg','jpeg','png','pdf','doc','docx','xls','xlsx'];
    $allowed_mime = [
        'image/jpeg','image/png','application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    $target_dir = "../uploads/employee_documents/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

    $file_name = basename($_FILES['document_file']['name'] ?? '');
    $file_size = intval($_FILES['document_file']['size'] ?? 0);
    $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $mime      = isset($_FILES['document_file']['tmp_name']) ? mime_content_type($_FILES['document_file']['tmp_name']) : '';
    $file_path = time() . '_' . $user_id . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file_name);

    if (!in_array($file_ext, $allowed_ext) || !in_array($mime, $allowed_mime)) {
        $error = 'Invalid file type. Only PDF, DOC, DOCX, JPG, PNG, XLS, XLSX are allowed.';
    } elseif ($file_size > 5 * 1024 * 1024) {
        $error = 'File size exceeds the 5 MB limit.';
    } elseif (move_uploaded_file($_FILES['document_file']['tmp_name'], $target_dir . $file_path)) {
        mysqli_query($conn, "INSERT INTO employee_documents
            (employee_id, document_title, document_type, file_path, file_name, file_size, upload_date, notes, uploaded_by)
            VALUES ($user_id, '$document_title', '$document_type', '$file_path', '$file_name', $file_size, CURDATE(), '$notes', $user_id)");
        $admin_q = mysqli_query($conn, "SELECT id FROM employees WHERE role='admin' LIMIT 1");
        if ($admin = mysqli_fetch_assoc($admin_q)) {
            addNotification($admin['id'], 'New Document Uploaded', $_SESSION['user_name'] . ' uploaded: ' . htmlspecialchars($_POST['document_title']));
        }
        $success    = 'Document sent to HR successfully!';
        $active_tab = 'upload';
    } else {
        $error = 'Upload failed. Please try again.';
    }
}

// ========================================
// FETCH DATA
// ========================================
$resignation = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM employee_resignations WHERE employee_id = $user_id ORDER BY created_at DESC LIMIT 1"));

$termination = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT t.*, e.name as created_by_name FROM terminations t
     LEFT JOIN employees e ON t.created_by = e.id
     WHERE t.employee_id = $user_id ORDER BY t.created_at DESC LIMIT 1"));

$documents_result = mysqli_query($conn,
    "SELECT d.* FROM employee_documents d
     WHERE d.employee_id = $user_id AND d.uploaded_by != $user_id
     ORDER BY d.created_at DESC");

$my_warnings = [];
try {
    $warn_res = mysqli_query($conn,
        "SELECT w.*, a.name as issued_by_name
         FROM employee_warnings w
         LEFT JOIN employees a ON w.issued_by = a.id
         WHERE w.employee_id = $user_id
         ORDER BY w.issued_date DESC");
    if ($warn_res) while ($wr = mysqli_fetch_assoc($warn_res)) $my_warnings[] = $wr;
} catch (Exception $e) { /* table not yet created */ }
$warnings_count = count($my_warnings);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>My Management - IPINFRA HRM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .sidebar { transition: transform 0.3s ease-in-out; }
        .tab-active   { background: linear-gradient(135deg,#2563eb,#4f46e5); color:#fff; }
        .tab-inactive { background-color:#e5e7eb; color:#4b5563; }
        .tab-btn { transition: all 0.2s ease; }
        @keyframes fadeInUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
        .animate-fadeInUp { animation: fadeInUp 0.35s ease-out; }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen pb-24">

<?php require_once '../includes/global_ui.php'; ?>
<?php require_once '../includes/confirm_modal.php'; ?>

<!-- Header -->
<div class="bg-gradient-to-r from-slate-900 via-indigo-900 to-slate-900 text-white sticky top-0 z-40 shadow-2xl">
    <div class="flex items-center justify-between px-5 py-4">
        <div class="flex items-center gap-3">
            <button onclick="toggleSidebar()" class="w-10 h-10 rounded-xl bg-white/10 flex items-center justify-center hover:bg-white/20 transition">
                <i class="fas fa-bars text-lg"></i>
            </button>
            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 via-indigo-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg">
                <span class="text-white font-bold text-sm">IN</span>
            </div>
            <div class="hidden sm:block">
                <p class="text-xs text-blue-200 font-medium tracking-wide">IPINFRA NETWORKS</p>
                <p class="text-sm font-bold">Employee Portal</p>
            </div>
        </div>
        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-indigo-500 flex items-center justify-center">
            <span class="text-white text-xs font-bold"><?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?></span>
        </div>
    </div>
    <div class="h-0.5 bg-gradient-to-r from-transparent via-indigo-400 to-transparent"></div>
</div>

<!-- Sidebar -->
<div id="sidebar" class="fixed top-0 left-0 h-full w-72 bg-gradient-to-b from-blue-900 to-blue-950 text-white z-50 transform -translate-x-full transition-transform duration-300 shadow-2xl overflow-y-auto">
    <div class="p-6 border-b border-blue-800">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center">
                <span class="text-blue-900 font-bold text-xl">IN</span>
            </div>
            <div>
                <h2 class="font-bold"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h2>
                <p class="text-xs text-blue-300"><?php echo htmlspecialchars($_SESSION['employee_id']); ?></p>
            </div>
        </div>
        <button onclick="toggleSidebar()" class="absolute top-4 right-4 text-white/60 hover:text-white">
            <i class="fas fa-times text-xl"></i>
        </button>
    </div>
    <nav class="p-4">
        <a href="dashboard.php"  class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1"><i class="fas fa-tachometer-alt w-5"></i> Dashboard</a>
        <a href="clock.php"      class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1"><i class="fas fa-clock w-5"></i> Clock In/Out</a>
        <a href="leave.php"      class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1"><i class="fas fa-calendar-alt w-5"></i> Apply Leave</a>
        <a href="claim.php"      class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1"><i class="fas fa-receipt w-5"></i> Apply Claim</a>
        <a href="gallery.php"    class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1"><i class="fas fa-images w-5"></i> Company Gallery</a>
        <a href="assets.php"     class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1"><i class="fas fa-boxes w-5"></i> Asset Tracker</a>
        <a href="management.php" class="flex items-center gap-3 py-3 px-4 rounded-xl bg-blue-800/50 mb-1"><i class="fas fa-briefcase w-5"></i> My Management</a>
        <a href="payslip.php"    class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1"><i class="fas fa-file-invoice-dollar w-5"></i> Payslip</a>
        <a href="calendar.php"   class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1"><i class="fas fa-calendar w-5"></i> Calendar</a>
        <a href="profile.php"    class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1"><i class="fas fa-user-circle w-5"></i> My Profile</a>
        <div class="border-t border-blue-800 my-4"></div>
        <a href="../logout.php" class="flex items-center gap-3 py-3 px-4 rounded-xl bg-red-600/20 text-red-300 hover:bg-red-600/30 transition"><i class="fas fa-sign-out-alt w-5"></i> Logout</a>
    </nav>
</div>
<div id="overlay" class="fixed inset-0 bg-black/50 z-40 hidden" onclick="toggleSidebar()"></div>

<!-- Main Content -->
<div class="px-4 py-6 max-w-4xl mx-auto">

    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800"><i class="fas fa-briefcase mr-2 text-blue-600"></i>My Management</h1>
        <p class="text-sm text-gray-500 mt-1">Documents, resignation and HR communications</p>
    </div>

    <?php if ($success): ?>
    <div class="bg-green-50 border-l-4 border-green-500 rounded-xl p-4 mb-5 flex items-center gap-3">
        <i class="fas fa-check-circle text-green-500 text-lg"></i>
        <p class="text-green-800 text-sm font-medium"><?php echo htmlspecialchars($success); ?></p>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="bg-red-50 border-l-4 border-red-500 rounded-xl p-4 mb-5 flex items-center gap-3">
        <i class="fas fa-exclamation-circle text-red-500 text-lg"></i>
        <p class="text-red-800 text-sm font-medium"><?php echo htmlspecialchars($error); ?></p>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="flex gap-2 mb-6 overflow-x-auto pb-1">
        <button onclick="showTab('documents')" id="tabDocuments" class="tab-btn shrink-0 px-4 py-2.5 rounded-xl font-semibold transition-all tab-active whitespace-nowrap">
            <i class="fas fa-folder-open mr-1.5"></i>Documents
        </button>
        <button onclick="showTab('upload')" id="tabUpload" class="tab-btn shrink-0 px-4 py-2.5 rounded-xl font-semibold transition-all tab-inactive whitespace-nowrap">
            <i class="fas fa-upload mr-1.5"></i>Send to HR
        </button>
        <button onclick="showTab('resignation')" id="tabResignation" class="tab-btn shrink-0 px-4 py-2.5 rounded-xl font-semibold transition-all tab-inactive whitespace-nowrap">
            <i class="fas fa-user-minus mr-1.5"></i>Resignation
        </button>
        <button onclick="showTab('warnings')" id="tabWarnings" class="tab-btn shrink-0 px-4 py-2.5 rounded-xl font-semibold transition-all tab-inactive whitespace-nowrap">
            <i class="fas fa-exclamation-triangle mr-1.5"></i>My Warnings
            <?php if ($warnings_count > 0): ?>
                <span class="ml-1.5 bg-orange-500 text-white text-xs px-1.5 py-0.5 rounded-full"><?php echo $warnings_count; ?></span>
            <?php endif; ?>
        </button>
    </div>

    <!-- ======================================== -->
    <!-- DOCUMENTS TAB -->
    <!-- ======================================== -->
    <div id="documentsTab" class="animate-fadeInUp">
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <div class="bg-gradient-to-r from-gray-50 to-white px-6 py-4 border-b flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-folder-open text-blue-600 text-lg"></i>
                </div>
                <div>
                    <h2 class="font-semibold text-gray-800">Company Documents</h2>
                    <p class="text-xs text-gray-500">Important documents shared by HR</p>
                </div>
            </div>
            <div class="p-5">
                <?php if (mysqli_num_rows($documents_result) > 0): ?>
                <div class="space-y-3">
                    <?php while ($doc = mysqli_fetch_assoc($documents_result)):
                        $fp = "../uploads/documents/" . $doc['file_path'];
                        if (!file_exists($fp)) $fp = "../uploads/employee_documents/" . $doc['file_path'];
                        $icon = 'fa-file-pdf';
                        $ext  = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                        if (in_array($ext, ['doc','docx'])) $icon = 'fa-file-word';
                        elseif (in_array($ext, ['jpg','jpeg','png'])) $icon = 'fa-file-image';
                        elseif (in_array($ext, ['xls','xlsx'])) $icon = 'fa-file-excel';
                    ?>
                    <div class="border border-gray-100 rounded-xl p-4 hover:shadow-md transition">
                        <div class="flex items-center justify-between flex-wrap gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center shrink-0">
                                    <i class="fas <?php echo $icon; ?> text-blue-600 text-lg"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($doc['document_title']); ?></p>
                                    <div class="flex flex-wrap gap-3 text-xs text-gray-400 mt-1">
                                        <span><i class="fas fa-tag mr-1"></i><?php echo ucfirst(str_replace('_', ' ', $doc['document_type'])); ?></span>
                                        <span><i class="fas fa-calendar mr-1"></i><?php echo date('d M Y', strtotime($doc['upload_date'])); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <a href="<?php echo htmlspecialchars($fp); ?>" target="_blank" class="bg-blue-500 text-white px-3 py-2 rounded-lg text-sm hover:bg-blue-600 transition">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <a href="<?php echo htmlspecialchars($fp); ?>" download class="bg-green-500 text-white px-3 py-2 rounded-lg text-sm hover:bg-green-600 transition">
                                    <i class="fas fa-download"></i> Download
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-12">
                    <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-folder-open text-gray-400 text-3xl"></i>
                    </div>
                    <p class="text-gray-500 font-medium">No documents yet</p>
                    <p class="text-xs text-gray-400 mt-1">Documents shared by HR will appear here</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ======================================== -->
    <!-- SEND TO HR TAB -->
    <!-- ======================================== -->
    <div id="uploadTab" class="hidden animate-fadeInUp">
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <div class="bg-gradient-to-r from-gray-50 to-white px-6 py-4 border-b flex items-center gap-3">
                <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-upload text-green-600 text-lg"></i>
                </div>
                <div>
                    <h2 class="font-semibold text-gray-800">Send Document to HR</h2>
                    <p class="text-xs text-gray-500">Upload invoices, delivery orders, or other documents</p>
                </div>
            </div>
            <div class="p-6 max-w-lg mx-auto">
                <form method="POST" enctype="multipart/form-data" class="space-y-5">
                    <div>
                        <label class="block text-gray-700 text-sm font-semibold mb-2">Document Type</label>
                        <select name="document_type" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                            <option value="invoice">Invoice</option>
                            <option value="delivery_order">Delivery Order</option>
                            <option value="purchase_order">Purchase Order</option>
                            <option value="quotation">Quotation</option>
                            <option value="receipt">Receipt</option>
                            <option value="complaint">Complaint / Issue</option>
                            <option value="request_letter">Request Letter</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-semibold mb-2">Document Title</label>
                        <input type="text" name="document_title" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none" placeholder="e.g., Invoice #INV-001">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-semibold mb-2">Select File</label>
                        <input type="file" name="document_file" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xls,.xlsx">
                        <p class="text-xs text-gray-400 mt-1">PDF, DOC, DOCX, JPG, PNG, XLS, XLSX — max 5 MB</p>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-semibold mb-2">Notes (Optional)</label>
                        <textarea name="notes" rows="2" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none" placeholder="Any additional information..."></textarea>
                    </div>
                    <button type="submit" name="upload_document" class="w-full bg-gradient-to-r from-green-600 to-teal-600 text-white py-3 rounded-xl font-semibold hover:shadow-lg transition">
                        <i class="fas fa-paper-plane mr-2"></i>Send to HR
                    </button>
                </form>
                <div class="mt-4 bg-blue-50 rounded-xl p-3 text-xs text-blue-700">
                    <i class="fas fa-info-circle mr-1"></i>
                    HR will be notified immediately after upload.
                </div>
            </div>
        </div>
    </div>

    <!-- ======================================== -->
    <!-- RESIGNATION TAB -->
    <!-- ======================================== -->
    <div id="resignationTab" class="hidden animate-fadeInUp">
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <div class="bg-gradient-to-r from-gray-50 to-white px-6 py-4 border-b flex items-center gap-3">
                <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-user-minus text-red-600 text-lg"></i>
                </div>
                <div>
                    <h2 class="font-semibold text-gray-800">Resignation Request</h2>
                    <p class="text-xs text-gray-500">Submit your resignation to HR</p>
                </div>
            </div>
            <div class="p-6 max-w-lg mx-auto">
                <?php if ($termination && $termination['status'] != 'cancelled'): ?>
                    <div class="bg-red-50 border border-red-200 rounded-xl p-5 text-center">
                        <i class="fas fa-ban text-red-500 text-3xl mb-3 block"></i>
                        <p class="font-semibold text-red-800">Employment Terminated</p>
                        <p class="text-sm text-red-600 mt-1">You cannot submit a resignation as your employment has been terminated.</p>
                    </div>
                <?php elseif ($resignation && $resignation['status'] == 'pending'): ?>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-5">
                        <div class="flex items-center gap-3 mb-3">
                            <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-hourglass-half text-yellow-600"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-yellow-800">Resignation Pending Review</p>
                                <p class="text-xs text-yellow-600">HR is reviewing your request</p>
                            </div>
                        </div>
                        <div class="border-t border-yellow-200 pt-3 space-y-1 text-sm">
                            <p><strong>Submitted:</strong> <?php echo date('d M Y', strtotime($resignation['requested_date'])); ?></p>
                            <p><strong>Last Working Day:</strong> <span class="text-red-600 font-semibold"><?php echo date('d M Y', strtotime($resignation['last_working_date'])); ?></span></p>
                        </div>
                        <a href="?cancel_resignation=<?php echo intval($resignation['id']); ?>&tab=resignation"
                           data-confirm="Cancel your resignation request? You can re-submit later."
                           data-confirm-title="Cancel Resignation"
                           class="inline-flex items-center gap-2 mt-4 text-red-600 text-sm font-medium hover:underline">
                            <i class="fas fa-times-circle"></i> Cancel Request
                        </a>
                    </div>
                <?php elseif ($resignation && $resignation['status'] == 'approved'): ?>
                    <div class="bg-green-50 border border-green-200 rounded-xl p-5 text-center">
                        <i class="fas fa-check-circle text-green-500 text-3xl mb-3 block"></i>
                        <p class="font-semibold text-green-800">Resignation Approved</p>
                        <p class="text-sm text-green-600 mt-1">Your last working day: <strong><?php echo date('d M Y', strtotime($resignation['last_working_date'])); ?></strong></p>
                    </div>
                <?php elseif ($resignation && $resignation['status'] == 'rejected'): ?>
                    <div class="bg-red-50 border border-red-200 rounded-xl p-5 text-center">
                        <i class="fas fa-times-circle text-red-500 text-3xl mb-3 block"></i>
                        <p class="font-semibold text-red-800">Resignation Rejected</p>
                        <?php if ($resignation['admin_notes']): ?>
                            <p class="text-sm text-red-600 mt-2">Reason: <?php echo nl2br(htmlspecialchars($resignation['admin_notes'])); ?></p>
                        <?php endif; ?>
                        <p class="text-xs text-gray-400 mt-3">You may submit a new resignation request.</p>
                    </div>
                    <form method="POST" class="space-y-5 mt-6">
                        <div>
                            <label class="block text-gray-700 text-sm font-semibold mb-2">Last Working Day <span class="text-red-500">*</span></label>
                            <input type="date" name="last_working_date" required min="<?php echo date('Y-m-d', strtotime('+14 days')); ?>" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:outline-none">
                            <p class="text-xs text-gray-400 mt-1">Minimum notice: 14 days</p>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-semibold mb-2">Reason <span class="text-red-500">*</span></label>
                            <textarea name="reason" rows="4" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:outline-none" placeholder="Please provide your reason..."></textarea>
                        </div>
                        <button type="button" onclick="confirmResignationSubmit(this)" class="w-full bg-gradient-to-r from-red-600 to-rose-600 text-white py-3 rounded-xl font-semibold hover:shadow-lg transition">
                            <i class="fas fa-paper-plane mr-2"></i>Submit Resignation Request
                        </button>
                    </form>
                <?php else: ?>
                    <form method="POST" class="space-y-5">
                        <div>
                            <label class="block text-gray-700 text-sm font-semibold mb-2">Last Working Day <span class="text-red-500">*</span></label>
                            <input type="date" name="last_working_date" required min="<?php echo date('Y-m-d', strtotime('+14 days')); ?>" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:outline-none">
                            <p class="text-xs text-gray-400 mt-1">Minimum notice: 14 days</p>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-semibold mb-2">Reason for Resignation <span class="text-red-500">*</span></label>
                            <textarea name="reason" rows="4" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:outline-none" placeholder="Please provide your reason..."></textarea>
                        </div>
                        <div class="bg-yellow-50 rounded-xl p-3 text-xs text-yellow-700">
                            <i class="fas fa-info-circle mr-1"></i>
                            Once submitted, HR will review your request. You can cancel before it is approved.
                        </div>
                        <button type="button" onclick="confirmResignationSubmit(this)" class="w-full bg-gradient-to-r from-red-600 to-rose-600 text-white py-3 rounded-xl font-semibold hover:shadow-lg transition">
                            <i class="fas fa-paper-plane mr-2"></i>Submit Resignation Request
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ======================================== -->
    <!-- WARNINGS TAB -->
    <!-- ======================================== -->
    <div id="warningsTab" class="hidden animate-fadeInUp">
        <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
            <div class="bg-gradient-to-r from-gray-50 to-white px-6 py-4 border-b flex items-center gap-3">
                <div class="w-10 h-10 bg-orange-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-orange-500 text-lg"></i>
                </div>
                <div>
                    <h2 class="font-semibold text-gray-800">My Disciplinary Records</h2>
                    <p class="text-xs text-gray-500">Warnings and notices issued by HR</p>
                </div>
            </div>
            <div class="p-5">
                <?php if (empty($my_warnings)): ?>
                <div class="text-center py-12">
                    <div class="w-20 h-20 bg-green-50 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-shield-alt text-green-400 text-3xl"></i>
                    </div>
                    <p class="text-gray-600 font-medium">You are in good standing</p>
                    <p class="text-xs text-gray-400 mt-1">No warnings or disciplinary notices on record</p>
                </div>
                <?php else: ?>
                <?php
                $badge_map = [
                    'verbal'      => ['bg-blue-100 text-blue-700',    'fa-comment',             'Verbal Warning'],
                    'written'     => ['bg-yellow-100 text-yellow-700','fa-file-alt',            'Written Warning'],
                    'final'       => ['bg-red-100 text-red-700',      'fa-exclamation-circle',  'Final Warning'],
                    'suspension'  => ['bg-purple-100 text-purple-700','fa-ban',                 'Suspension'],
                    'counselling' => ['bg-green-100 text-green-700',  'fa-hands-helping',       'Counselling'],
                ];
                ?>
                <div class="space-y-3">
                    <?php foreach ($my_warnings as $w):
                        $badge = $badge_map[$w['warning_type']] ?? ['bg-gray-100 text-gray-700','fa-flag','Unknown'];
                    ?>
                    <div class="border border-gray-100 rounded-xl p-4 hover:shadow-md transition">
                        <div class="flex items-start justify-between gap-3 flex-wrap">
                            <div>
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $badge[0]; ?> mb-2">
                                    <i class="fas <?php echo $badge[1]; ?>"></i><?php echo $badge[2]; ?>
                                </span>
                                <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($w['subject']); ?></p>
                                <?php if ($w['description']): ?>
                                    <p class="text-sm text-gray-500 mt-1"><?php echo nl2br(htmlspecialchars(substr($w['description'], 0, 200))); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-4 mt-3 text-xs text-gray-400">
                            <span><i class="fas fa-calendar-alt mr-1"></i><?php echo date('d M Y', strtotime($w['issued_date'])); ?></span>
                            <?php if ($w['issued_by_name']): ?>
                                <span><i class="fas fa-user-shield mr-1"></i>Issued by <?php echo htmlspecialchars($w['issued_by_name']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div><!-- /main -->

<!-- Mobile Bottom Nav -->
<div class="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-lg border-t border-gray-200 md:hidden shadow-2xl z-30">
    <div class="flex justify-around py-2">
        <a href="dashboard.php" class="flex flex-col items-center py-2 px-3 text-gray-500 hover:text-blue-600 transition group">
            <i class="fas fa-home text-xl group-hover:scale-110 transition"></i>
            <span class="text-xs mt-1">Home</span>
        </a>
        <a href="leave.php" class="flex flex-col items-center py-2 px-3 text-gray-500 hover:text-blue-600 transition group">
            <i class="fas fa-calendar-alt text-xl group-hover:scale-110 transition"></i>
            <span class="text-xs mt-1">Leave</span>
        </a>
        <a href="management.php" class="flex flex-col items-center py-2 px-3 text-blue-600 relative">
            <i class="fas fa-briefcase text-xl"></i>
            <span class="text-xs mt-1 font-semibold">Manage</span>
            <div class="absolute -top-1 right-2 w-2 h-2 bg-blue-600 rounded-full"></div>
        </a>
        <a href="claim.php" class="flex flex-col items-center py-2 px-3 text-gray-500 hover:text-blue-600 transition group">
            <i class="fas fa-receipt text-xl group-hover:scale-110 transition"></i>
            <span class="text-xs mt-1">Claim</span>
        </a>
        <a href="profile.php" class="flex flex-col items-center py-2 px-3 text-gray-500 hover:text-blue-600 transition group">
            <i class="fas fa-user-circle text-xl group-hover:scale-110 transition"></i>
            <span class="text-xs mt-1">Profile</span>
        </a>
    </div>
</div>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('-translate-x-full');
    document.getElementById('overlay').classList.toggle('hidden');
}

function showTab(tab) {
    ['documents','upload','resignation','warnings'].forEach(function(t) {
        var el  = document.getElementById(t + 'Tab');
        var btn = document.getElementById('tab' + t.charAt(0).toUpperCase() + t.slice(1));
        if (el)  el.classList.add('hidden');
        if (btn) btn.className = 'tab-btn shrink-0 px-4 py-2.5 rounded-xl font-semibold transition-all tab-inactive whitespace-nowrap';
    });
    var activeEl  = document.getElementById(tab + 'Tab');
    var activeBtn = document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1));
    if (activeEl)  activeEl.classList.remove('hidden');
    if (activeBtn) activeBtn.className = 'tab-btn shrink-0 px-4 py-2.5 rounded-xl font-semibold transition-all tab-active whitespace-nowrap';
}

function confirmResignationSubmit(btn) {
    var form = btn.closest('form');
    confirmAction(
        'Submit Resignation',
        'Are you sure you want to submit your resignation? HR will be notified immediately.',
        function() { form.submit(); }
    );
}

// Restore active tab from PHP / URL
showTab('<?php echo $active_tab; ?>');
</script>
</body>
</html>
