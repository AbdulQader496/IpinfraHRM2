<?php
require_once '../includes/auth.php';
redirectIfNotLoggedIn();
require_once '../includes/db.php';
require_once '../includes/functions.php';

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'documents';

// ========================================
// HANDLE RESIGNATION REQUEST
// ========================================
if (isset($_POST['submit_resignation'])) {
    $requested_date = date('Y-m-d');
    $last_working_date = $_POST['last_working_date'];
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);
    
    $check = mysqli_query($conn, "SELECT id FROM employee_resignations WHERE employee_id = $user_id AND status = 'pending'");
    if (mysqli_num_rows($check) > 0) {
        $error = '<div class="bg-red-100 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">⚠️ You already have a pending resignation request.</div>';
    } else {
        $query = "INSERT INTO employee_resignations (employee_id, requested_date, last_working_date, reason, status) 
                  VALUES ($user_id, '$requested_date', '$last_working_date', '$reason', 'pending')";
        if (mysqli_query($conn, $query)) {
            $admin_query = mysqli_query($conn, "SELECT id FROM employees WHERE role='admin' LIMIT 1");
            if ($admin = mysqli_fetch_assoc($admin_query)) {
                addNotification($admin['id'], 'New Resignation Request', $_SESSION['user_name'] . ' has submitted a resignation request.');
            }
            $success = '<div class="bg-green-100 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm">✓ Resignation request submitted successfully!</div>';
            $active_tab = 'resignation';
        }
    }
}

// Cancel Resignation
if (isset($_GET['cancel_resignation'])) {
    $id = $_GET['cancel_resignation'];
    mysqli_query($conn, "UPDATE employee_resignations SET status = 'cancelled' WHERE id = $id AND employee_id = $user_id");
    $success = '<div class="bg-green-100 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm">✓ Resignation request cancelled.</div>';
    $active_tab = 'resignation';
}

// ========================================
// HANDLE DOCUMENT UPLOAD TO HR
// ========================================
if (isset($_POST['upload_document'])) {
    $document_title = mysqli_real_escape_string($conn, $_POST['document_title']);
    $document_type = $_POST['document_type'];
    $notes = mysqli_real_escape_string($conn, $_POST['notes']);
    
    $target_dir = "../uploads/employee_documents/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    
    $file_name = basename($_FILES['document_file']['name']);
    $file_size = $_FILES['document_file']['size'];
    $file_path = time() . '_' . $user_id . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file_name);
    
    if (move_uploaded_file($_FILES['document_file']['tmp_name'], $target_dir . $file_path)) {
        $query = "INSERT INTO employee_documents (employee_id, document_title, document_type, file_path, file_name, file_size, upload_date, notes, uploaded_by, status) 
                  VALUES ($user_id, '$document_title', '$document_type', '$file_path', '$file_name', $file_size, CURDATE(), '$notes', $user_id, 'active')";
        mysqli_query($conn, $query);
        
        $admin_query = mysqli_query($conn, "SELECT id FROM employees WHERE role='admin' LIMIT 1");
        if ($admin = mysqli_fetch_assoc($admin_query)) {
            addNotification($admin['id'], 'New Document Uploaded', $_SESSION['user_name'] . ' has uploaded: ' . $document_title);
        }
        
        $success = '<div class="bg-green-100 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm">✓ Document uploaded successfully!</div>';
        $active_tab = 'upload';
    } else {
        $error = '<div class="bg-red-100 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">✗ Error uploading document.</div>';
    }
}

// ========================================
// GET EMPLOYEE DATA
// ========================================
$resignation_query = mysqli_query($conn, "SELECT * FROM employee_resignations WHERE employee_id = $user_id ORDER BY created_at DESC LIMIT 1");
$resignation = mysqli_fetch_assoc($resignation_query);

$termination_query = mysqli_query($conn, "SELECT t.*, e.name as created_by_name 
    FROM terminations t 
    LEFT JOIN employees e ON t.created_by = e.id 
    WHERE t.employee_id = $user_id 
    ORDER BY t.created_at DESC LIMIT 1");
$termination = mysqli_fetch_assoc($termination_query);

// Documents FROM Admin only
$documents = mysqli_query($conn, "SELECT d.* 
    FROM employee_documents d 
    WHERE d.employee_id = $user_id 
    AND d.uploaded_by != $user_id
    AND d.status = 'active'
    ORDER BY d.created_at DESC");
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
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen pb-20">

<!-- Premium Header -->
<div class="bg-gradient-to-r from-slate-900 via-indigo-900 to-slate-900 text-white sticky top-0 z-40 shadow-2xl backdrop-blur-sm">
    <div class="flex items-center justify-between px-5 py-4">
        <div class="flex items-center gap-3">
            <!-- Menu Button -->
            <button onclick="toggleSidebar()" class="relative group">
                <div class="w-10 h-10 rounded-xl bg-white/10 backdrop-blur-sm flex items-center justify-center group-hover:bg-white/20 transition-all duration-300 group-hover:scale-105">
                    <i class="fas fa-bars text-lg"></i>
                </div>
            </button>
            
            <!-- Logo -->
            <div class="relative">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 via-indigo-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-500/20 animate-pulse">
                    <span class="text-white font-bold text-sm">IN</span>
                </div>
                <div class="absolute -top-1 -right-1 w-3 h-3 bg-green-400 rounded-full border-2 border-slate-900"></div>
            </div>
            
            <!-- Brand -->
            <div class="hidden sm:block">
                <p class="text-xs text-blue-200 font-medium tracking-wide">IPINFRA NETWORKS</p>
                <p class="text-sm font-bold tracking-tight">Employee Portal</p>
            </div>
        </div>
        
        <!-- Right side - Empty for now, can add profile/user later -->
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-indigo-500 flex items-center justify-center shadow-lg">
                <span class="text-white text-xs font-bold"><?php echo substr($_SESSION['user_name'], 0, 1); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Subtle bottom border glow -->
    <div class="h-0.5 bg-gradient-to-r from-transparent via-indigo-400 to-transparent"></div>
</div>

<!-- SIDEBAR -->
<div id="sidebar" class="fixed top-0 left-0 h-full w-72 bg-gradient-to-b from-blue-900 to-blue-950 text-white z-50 transform -translate-x-full transition-transform duration-300 shadow-2xl overflow-y-auto">
    <div class="p-6 border-b border-blue-800">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-12 h-12 bg-white rounded-xl flex items-center justify-center">
                <span class="text-blue-900 font-bold text-xl">IN</span>
            </div>
            <div>
                <h2 class="font-bold"><?php echo $_SESSION['user_name']; ?></h2>
                <p class="text-xs text-blue-300"><?php echo $_SESSION['employee_id']; ?></p>
            </div>
        </div>
        <button onclick="toggleSidebar()" class="absolute top-4 right-4 text-white/60 hover:text-white">
            <i class="fas fa-times text-xl"></i>
        </button>
    </div>
    <nav class="p-4">
        <a href="dashboard.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1">
            <i class="fas fa-tachometer-alt w-5"></i> Dashboard
        </a>
        <a href="clock.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1">
            <i class="fas fa-clock w-5"></i> Clock In/Out
        </a>
        <a href="leave.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1">
            <i class="fas fa-calendar-alt w-5"></i> Apply Leave
        </a>
        <a href="claim.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1">
            <i class="fas fa-receipt w-5"></i> Apply Claim
        </a>
        <a href="gallery.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1">
            <i class="fas fa-images w-5"></i> Company Gallery
        </a>
        <a href="assets.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1">
            <i class="fas fa-boxes w-5"></i> Asset Tracker
        </a>
        <a href="management.php" class="flex items-center gap-3 py-3 px-4 rounded-xl bg-blue-800/50 mb-1">
            <i class="fas fa-briefcase w-5"></i> My Management
        </a>
        <a href="payslip.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1">
            <i class="fas fa-file-invoice-dollar w-5"></i> Payslip
        </a>
        <a href="calendar.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1">
            <i class="fas fa-calendar w-5"></i> Calendar
        </a>
        <a href="profile.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1">
            <i class="fas fa-user-circle w-5"></i> My Profile
        </a>
        <div class="border-t border-blue-800 my-4"></div>
        <a href="../logout.php" class="flex items-center gap-3 py-3 px-4 rounded-xl bg-red-600/20 text-red-300 hover:bg-red-600/30 transition">
            <i class="fas fa-sign-out-alt w-5"></i> Logout
        </a>
    </nav>
</div>

<div id="overlay" class="fixed inset-0 bg-black/50 z-40 hidden" onclick="toggleSidebar()"></div>

<!-- MAIN CONTENT -->
<div class="px-4 py-6 max-w-6xl mx-auto">
    
    <!-- Page Header -->
    <div class="text-center mb-8">
        <h1 class="text-2xl font-bold text-gray-800">My Management</h1>
        <p class="text-sm text-gray-500 mt-1">Manage your documents, resignation, and communicate with HR</p>
    </div>

    <?php echo $success; ?>
    <?php echo $error; ?>

    <!-- Tabs Navigation -->
    <div class="flex flex-wrap gap-2 mb-6 border-b border-gray-200 pb-2">
        <a href="?tab=documents" class="px-5 py-2 rounded-t-xl text-sm font-medium transition <?php echo $active_tab == 'documents' ? 'bg-blue-600 text-white shadow-md' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
            <i class="fas fa-folder-open mr-2"></i> Company Documents
        </a>
        <a href="?tab=upload" class="px-5 py-2 rounded-t-xl text-sm font-medium transition <?php echo $active_tab == 'upload' ? 'bg-blue-600 text-white shadow-md' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
            <i class="fas fa-upload mr-2"></i> Send to HR
        </a>
        <a href="?tab=resignation" class="px-5 py-2 rounded-t-xl text-sm font-medium transition <?php echo $active_tab == 'resignation' ? 'bg-blue-600 text-white shadow-md' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'; ?>">
            <i class="fas fa-user-minus mr-2"></i> Resignation
        </a>
    </div>

    <!-- ======================================== -->
    <!-- TAB 1: COMPANY DOCUMENTS -->
    <!-- ======================================== -->
    <?php if($active_tab == 'documents'): ?>
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-gradient-to-r from-gray-50 to-white px-6 py-4 border-b">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-folder-open text-blue-600 text-lg"></i>
                </div>
                <div>
                    <h2 class="font-semibold text-gray-800">Company Documents</h2>
                    <p class="text-xs text-gray-500">Important documents shared by HR</p>
                </div>
            </div>
        </div>
        
        <div class="p-5">
            <?php if(mysqli_num_rows($documents) > 0): ?>
                <div class="space-y-3">
                    <?php while($doc = mysqli_fetch_assoc($documents)): 
                        $file_path = "../uploads/documents/" . $doc['file_path'];
                        if (!file_exists($file_path)) $file_path = "../uploads/employee_documents/" . $doc['file_path'];
                        $file_icon = 'fa-file-pdf';
                        if (strpos($doc['file_name'], '.doc') !== false) $file_icon = 'fa-file-word';
                        if (strpos($doc['file_name'], '.jpg') !== false || strpos($doc['file_name'], '.png') !== false) $file_icon = 'fa-file-image';
                        if (strpos($doc['file_name'], '.xls') !== false) $file_icon = 'fa-file-excel';
                    ?>
                    <div class="border border-gray-100 rounded-xl p-4 hover:shadow-md transition">
                        <div class="flex items-center justify-between flex-wrap gap-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                    <i class="fas <?php echo $file_icon; ?> text-blue-600 text-lg"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-800"><?php echo htmlspecialchars($doc['document_title']); ?></p>
                                    <div class="flex flex-wrap gap-3 text-xs text-gray-400 mt-1">
                                        <span><i class="fas fa-tag mr-1"></i> <?php echo ucfirst(str_replace('_', ' ', $doc['document_type'])); ?></span>
                                        <span><i class="fas fa-calendar mr-1"></i> <?php echo date('d M Y', strtotime($doc['upload_date'])); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <a href="<?php echo $file_path; ?>" download class="bg-green-500 text-white px-3 py-2 rounded-lg text-sm hover:bg-green-600 transition">
                                    <i class="fas fa-download"></i> Download
                                </a>
                                <a href="<?php echo $file_path; ?>" target="_blank" class="bg-blue-500 text-white px-3 py-2 rounded-lg text-sm hover:bg-blue-600 transition">
                                    <i class="fas fa-eye"></i> View
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
                    <p class="text-gray-500">No company documents available</p>
                    <p class="text-xs text-gray-400 mt-1">Documents shared by HR will appear here</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ======================================== -->
    <!-- TAB 2: SEND TO HR (UPLOAD) -->
    <!-- ======================================== -->
    <?php if($active_tab == 'upload'): ?>
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-gradient-to-r from-gray-50 to-white px-6 py-4 border-b">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-upload text-green-600 text-lg"></i>
                </div>
                <div>
                    <h2 class="font-semibold text-gray-800">Send Document to HR</h2>
                    <p class="text-xs text-gray-500">Upload invoices, delivery orders, or other documents</p>
                </div>
            </div>
        </div>
        
        <div class="p-6 max-w-lg mx-auto">
            <form method="POST" enctype="multipart/form-data" class="space-y-5">
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Document Type</label>
                    <select name="document_type" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none">
                        <option value="invoice">🧾 Invoice</option>
                        <option value="delivery_order">📦 Delivery Order</option>
                        <option value="purchase_order">📋 Purchase Order</option>
                        <option value="quotation">📊 Quotation</option>
                        <option value="receipt">📎 Receipt</option>
                        <option value="complaint">⚠️ Complaint / Issue</option>
                        <option value="request_letter">✉️ Request Letter</option>
                        <option value="other">📁 Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Document Title</label>
                    <input type="text" name="document_title" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:outline-none" placeholder="e.g., Invoice #INV-001 - ABC Company">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Select File</label>
                    <input type="file" name="document_file" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-green-500">
                    <p class="text-xs text-gray-400 mt-1">PDF, DOC, DOCX, JPG, PNG (Max 5MB)</p>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Notes (Optional)</label>
                    <textarea name="notes" rows="2" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-green-500" placeholder="Any additional information..."></textarea>
                </div>
                <button type="submit" name="upload_document" class="w-full bg-gradient-to-r from-green-600 to-teal-600 text-white py-3 rounded-xl font-semibold hover:shadow-lg transition">
                    <i class="fas fa-paper-plane mr-2"></i> Send to HR
                </button>
            </form>
            <div class="mt-4 bg-blue-50 rounded-xl p-3 text-xs text-blue-700">
                <i class="fas fa-info-circle mr-1"></i>
                Documents you upload will be sent to HR for review. HR will be notified immediately.
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ======================================== -->
    <!-- TAB 3: RESIGNATION -->
    <!-- ======================================== -->
    <?php if($active_tab == 'resignation'): ?>
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-gradient-to-r from-gray-50 to-white px-6 py-4 border-b">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-user-minus text-red-600 text-lg"></i>
                </div>
                <div>
                    <h2 class="font-semibold text-gray-800">Resignation Request</h2>
                    <p class="text-xs text-gray-500">Submit your resignation to HR</p>
                </div>
            </div>
        </div>
        
        <div class="p-6 max-w-lg mx-auto">
            <?php if ($termination && $termination['status'] != 'cancelled'): ?>
                <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-center">
                    <i class="fas fa-ban text-red-600 text-3xl mb-2 block"></i>
                    <p class="font-semibold text-red-800">Employment Terminated</p>
                    <p class="text-sm text-red-600">You cannot submit resignation as your employment has been terminated.</p>
                </div>
            <?php elseif ($resignation && $resignation['status'] == 'pending'): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fas fa-hourglass-half text-yellow-600"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-yellow-800">Resignation Pending</p>
                            <p class="text-xs text-yellow-600">HR is reviewing your request</p>
                        </div>
                    </div>
                    <div class="mt-3 pt-3 border-t border-yellow-200">
                        <p class="text-sm"><strong>Last Working Day:</strong> <?php echo date('d M Y', strtotime($resignation['last_working_date'])); ?></p>
                        <a href="?cancel_resignation=<?php echo $resignation['id']; ?>&tab=resignation" data-confirm="Cancel your resignation request?" data-confirm-title="Cancel Resignation" class="inline-block mt-3 text-red-600 text-sm">
                            <i class="fas fa-times-circle"></i> Cancel Request
                        </a>
                    </div>
                </div>
            <?php elseif ($resignation && $resignation['status'] == 'approved'): ?>
                <div class="bg-green-50 border border-green-200 rounded-xl p-4 text-center">
                    <i class="fas fa-check-circle text-green-600 text-3xl mb-2 block"></i>
                    <p class="font-semibold text-green-800">Resignation Approved</p>
                    <p class="text-sm text-green-600">Your last working day: <?php echo date('d M Y', strtotime($resignation['last_working_date'])); ?></p>
                </div>
            <?php elseif ($resignation && $resignation['status'] == 'rejected'): ?>
                <div class="bg-red-50 border border-red-200 rounded-xl p-4">
                    <div class="text-center">
                        <i class="fas fa-times-circle text-red-600 text-3xl mb-2 block"></i>
                        <p class="font-semibold text-red-800">Resignation Rejected</p>
                        <?php if($resignation['admin_notes']): ?>
                            <p class="text-sm text-red-600 mt-2">Reason: <?php echo nl2br(htmlspecialchars($resignation['admin_notes'])); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <form method="POST" class="space-y-5">
                    <div>
                        <label class="block text-gray-700 text-sm font-semibold mb-2">Last Working Day <span class="text-red-500">*</span></label>
                        <input type="date" name="last_working_date" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-red-500" min="<?php echo date('Y-m-d', strtotime('+14 days')); ?>">
                        <p class="text-xs text-gray-500 mt-1">Minimum notice period: 14 days</p>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-semibold mb-2">Reason for Resignation <span class="text-red-500">*</span></label>
                        <textarea name="reason" rows="4" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-red-500 focus:outline-none" placeholder="Please provide your reason..."></textarea>
                    </div>
                    <div class="bg-yellow-50 rounded-xl p-3 text-xs text-yellow-700">
                        <i class="fas fa-info-circle mr-1"></i>
                        Once submitted, your resignation will be reviewed by HR. You can cancel your request before it's approved.
                    </div>
                    <button type="submit" name="submit_resignation" class="w-full bg-gradient-to-r from-red-600 to-rose-600 text-white py-3 rounded-xl font-semibold hover:shadow-lg transition" onclick="return confirm('Submit resignation? This is a serious action.')">
                        <i class="fas fa-paper-plane mr-2"></i> Submit Resignation Request
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('-translate-x-full');
    document.getElementById('overlay').classList.toggle('hidden');
}
</script>
</body>
</html>