<?php
require_once '../includes/auth.php';
redirectIfNotAdmin();
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/toast_fn.php';

// ========================================
// PAGINATION & FILTERS
// ========================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$allowed_statuses = ['pending', 'approved', 'rejected', ''];
$allowed_types = ['travel', 'meal', 'medical', 'toll', 'parking', 'other', ''];
$status_filter = in_array($_GET['status'] ?? 'pending', $allowed_statuses) ? ($_GET['status'] ?? 'pending') : 'pending';
$type_filter   = in_array($_GET['type'] ?? '', $allowed_types) ? ($_GET['type'] ?? '') : '';
$date_from = isset($_GET['date_from']) ? preg_replace('/[^0-9\-]/', '', $_GET['date_from']) : '';
$date_to   = isset($_GET['date_to'])   ? preg_replace('/[^0-9\-]/', '', $_GET['date_to'])   : '';

// Build WHERE clause
$where = "WHERE 1=1";
if (!empty($search)) {
    $where .= " AND (e.name LIKE '%$search%' OR e.employee_id LIKE '%$search%')";
}
if (!empty($status_filter)) {
    $where .= " AND c.status = '$status_filter'";
}
if (!empty($type_filter)) {
    $where .= " AND c.claim_type = '$type_filter'";
}
if (!empty($date_from)) {
    $where .= " AND c.applied_at >= '$date_from'";
}
if (!empty($date_to)) {
    $where .= " AND c.applied_at <= '$date_to 23:59:59'";
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM claims c JOIN employees e ON c.employee_id = e.id $where";
$count_result = mysqli_query($conn, $count_query);
$total_rows = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_rows / $per_page);
$offset = ($page - 1) * $per_page;

// Get paginated claims with attachment count
$claims = mysqli_query($conn, "SELECT c.*, e.name, e.employee_id, e.department,
    (SELECT COUNT(*) FROM claim_attachments WHERE claim_id = c.id) as attachments_count
    FROM claims c 
    JOIN employees e ON c.employee_id = e.id 
    $where 
    ORDER BY CASE WHEN c.status='pending' THEN 1 ELSE 2 END, c.applied_at DESC 
    LIMIT $offset, $per_page");

// Get statistics
$stats_query = mysqli_query($conn, "SELECT 
    SUM(CASE WHEN status='pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status='approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected,
    SUM(CASE WHEN status='approved' THEN amount ELSE 0 END) as total_approved_amount
    FROM claims");
$stats = mysqli_fetch_assoc($stats_query);

// Get unique claim types for filter dropdown
$claim_types = mysqli_query($conn, "SELECT DISTINCT claim_type FROM claims");

// ========================================
// BULK APPROVE / REJECT
// ========================================
if (isset($_POST['bulk_action']) && !empty($_POST['ids'])) {
    $bulk_action = $_POST['bulk_action'];
    $bulk_status = ($bulk_action === 'approve') ? 'approved' : 'rejected';
    $ids = array_map('intval', $_POST['ids']);
    $ids_safe = implode(',', $ids);

    $bulk_rows = mysqli_query($conn, "SELECT id, employee_id FROM claims WHERE id IN ($ids_safe) AND status='pending'");
    $affected = 0;
    while ($br = mysqli_fetch_assoc($bulk_rows)) {
        mysqli_query($conn, "UPDATE claims SET status='$bulk_status', reviewed_at=NOW() WHERE id={$br['id']}");
        addNotification($br['employee_id'], 'Claim ' . ucfirst($bulk_status), 'Your claim has been ' . $bulk_status . '.');
        logAction($bulk_action, 'Claim ' . $bulk_status . ' for employee #' . $br['employee_id'], $br['id'], 'claim');
        $affected++;
    }

    if ($affected > 0) {
        if ($bulk_status === 'approved') {
            showToast("$affected claim(s) approved successfully.", 'success');
        } else {
            showToast("$affected claim(s) rejected.", 'warning');
        }
    }

    header("Location: manage_claim.php?page=$page&per_page=$per_page&search=" . urlencode($search) . "&status=$status_filter&type=$type_filter&date_from=$date_from&date_to=$date_to");
    exit();
}

// Handle Approve/Reject (uses 'act' param to avoid conflict with 'type' filter)
if (isset($_GET['action']) && isset($_GET['act'])) {
    $id = (int)$_GET['action'];
    $action = $_GET['act'];
    $status = ($action == 'approve') ? 'approved' : 'rejected';

    mysqli_query($conn, "UPDATE claims SET status='$status', reviewed_at=NOW() WHERE id=$id");

    $claim = mysqli_fetch_assoc(mysqli_query($conn, "SELECT employee_id FROM claims WHERE id=$id"));
    if ($claim) {
        addNotification($claim['employee_id'], 'Claim ' . ucfirst($status), 'Your claim has been ' . $status);
        logAction($action, 'Claim ' . $status . ' for employee #' . $claim['employee_id'], $id, 'claim');
    }

    if ($status === 'approved') {
        showToast('Claim approved and will be added to next payroll.', 'success');
    } else {
        showToast('Claim rejected.', 'warning');
    }

    header("Location: manage_claim.php?page=$page&per_page=$per_page&search=" . urlencode($search) . "&status=$status_filter&type=$type_filter&date_from=$date_from&date_to=$date_to");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Claim Management - IPINFRA HRM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .sidebar { transition: transform 0.3s ease-in-out; }
        .stat-card { transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
        .claim-card { transition: all 0.2s ease; }
        .claim-card:hover { background-color: #f9fafb; transform: translateX(4px); }
        .badge { transition: all 0.2s ease; }
        .btn-action { transition: all 0.2s ease; }
        .btn-action:hover { transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .attachment-preview {
            transition: all 0.2s ease;
        }
        .attachment-preview:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }

        @keyframes floatY {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-12px); }
        }
        .empty-state-svg { animation: floatY 3.4s ease-in-out infinite; }

        /* Bulk action bar */
        #bulkBar {
            transition: transform 0.3s ease, opacity 0.3s ease;
            bottom: 0;
        }
        #bulkBar.hidden-bar {
            transform: translateY(100%);
            opacity: 0;
            pointer-events: none;
        }
        @media (max-width: 767px) {
            #bulkBar { bottom: 64px; }
        }
        .bulk-check {
            width: 18px; height: 18px;
            accent-color: #7c3aed;
            cursor: pointer;
            flex-shrink: 0;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen pb-20">
<?php require_once '../includes/global_ui.php'; ?>
<?php require_once '../includes/toast.php'; ?>
<?php require_once '../includes/confirm_modal.php'; ?>

<!-- Premium Mobile Header -->
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
                <p class="text-sm font-bold tracking-wide">Admin Portal</p>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/admin_sidebar.php'; ?>

<!-- Main Content -->
<div class="px-4 py-6 pb-24 max-w-7xl mx-auto">
    
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Claim Applications</h1>
        <p class="text-sm text-gray-500 mt-1">Review and manage employee reimbursement claims</p>
    </div>

    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        <div class="stat-card bg-white rounded-2xl p-5 shadow-md border-l-4 border-yellow-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 font-medium">Pending Claims</p>
                    <p class="text-3xl font-bold text-gray-800 mt-1"><?php echo $stats['pending'] ?? 0; ?></p>
                    <p class="text-xs text-gray-400 mt-1">Awaiting review</p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-clock text-yellow-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card bg-white rounded-2xl p-5 shadow-md border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 font-medium">Approved</p>
                    <p class="text-3xl font-bold text-gray-800 mt-1"><?php echo $stats['approved'] ?? 0; ?></p>
                    <p class="text-xs text-gray-400 mt-1">Successfully processed</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card bg-white rounded-2xl p-5 shadow-md border-l-4 border-red-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500 font-medium">Rejected</p>
                    <p class="text-3xl font-bold text-gray-800 mt-1"><?php echo $stats['rejected'] ?? 0; ?></p>
                    <p class="text-xs text-gray-400 mt-1">Declined claims</p>
                </div>
                <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-times-circle text-red-600 text-xl"></i>
                </div>
            </div>
        </div>
        
        <div class="stat-card bg-gradient-to-r from-green-600 to-emerald-600 rounded-2xl p-5 shadow-md text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm font-medium">Total Approved</p>
                    <p class="text-3xl font-bold mt-1">RM <?php echo number_format($stats['total_approved_amount'] ?? 0, 2); ?></p>
                    <p class="text-green-100 text-xs mt-1">Total reimbursed</p>
                </div>
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                    <i class="fas fa-money-bill-wave text-2xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <div class="bg-white rounded-xl shadow-md p-4 mb-6">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-3">
            <div class="md:col-span-2">
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by name or ID..." 
                           class="w-full pl-9 pr-3 py-2 border border-gray-200 rounded-lg text-sm">
                </div>
            </div>
            <div>
                <select name="status" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                    <option value="">All Status</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
            </div>
            <div>
                <select name="type" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                    <option value="">All Types</option>
                    <?php while($type = mysqli_fetch_assoc($claim_types)): ?>
                        <option value="<?php echo $type['claim_type']; ?>" <?php echo $type_filter == $type['claim_type'] ? 'selected' : ''; ?>>
                            <?php echo ucfirst(str_replace('_', ' ', $type['claim_type'])); ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div>
                <input type="date" name="date_from" value="<?php echo $date_from; ?>" placeholder="From" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
            </div>
            <div>
                <input type="date" name="date_to" value="<?php echo $date_to; ?>" placeholder="To" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 bg-blue-600 text-white px-3 py-2 rounded-lg text-sm hover:bg-blue-700">Filter</button>
                <a href="manage_claim.php" class="flex-1 bg-gray-200 text-gray-700 px-3 py-2 rounded-lg text-sm text-center hover:bg-gray-300">Reset</a>
            </div>
        </form>
    </div>

    <!-- Results Summary -->
    <div class="flex justify-between items-center mb-4">
        <p class="text-sm text-gray-500">
            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_rows); ?> of <?php echo $total_rows; ?> claims
        </p>
        <div class="flex items-center gap-2">
            <span class="text-xs text-gray-500">Show:</span>
            <select onchange="window.location.href=this.value" class="text-sm border rounded px-2 py-1">
                <option value="<?php echo "?per_page=10&page=1&search=" . urlencode($search) . "&status=$status_filter&type=$type_filter&date_from=$date_from&date_to=$date_to"; ?>" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
                <option value="<?php echo "?per_page=25&page=1&search=" . urlencode($search) . "&status=$status_filter&type=$type_filter&date_from=$date_from&date_to=$date_to"; ?>" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                <option value="<?php echo "?per_page=50&page=1&search=" . urlencode($search) . "&status=$status_filter&type=$type_filter&date_from=$date_from&date_to=$date_to"; ?>" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                <option value="<?php echo "?per_page=100&page=1&search=" . urlencode($search) . "&status=$status_filter&type=$type_filter&date_from=$date_from&date_to=$date_to"; ?>" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
            </select>
        </div>
    </div>

    <!-- Claim List -->
    <?php if (mysqli_num_rows($claims) > 0): ?>
        <!-- Bulk form wrapping the entire list -->
        <form id="bulkForm" method="POST">
            <input type="hidden" name="bulk_action" id="bulkActionInput" value="">

            <!-- Select All header -->
            <div class="flex items-center gap-3 mb-3 px-1">
                <label class="flex items-center gap-2 cursor-pointer select-none text-sm font-medium text-gray-600">
                    <input type="checkbox" id="selectAllCheck" class="bulk-check" onchange="toggleSelectAll(this)">
                    Select All Pending
                </label>
                <span id="selectedCount" class="text-xs text-gray-400 hidden">0 selected</span>
            </div>

        <div class="space-y-4">
            <?php while ($row = mysqli_fetch_assoc($claims)): ?>
            <div class="claim-card bg-white rounded-2xl shadow-md overflow-hidden">
                <div class="p-5">
                    <div class="flex flex-col md:flex-row justify-between gap-4">
                        <div class="flex-1">
                            <!-- Employee Info -->
                            <div class="flex items-center gap-3 mb-4">
                                <?php if ($row['status'] === 'pending'): ?>
                                <input type="checkbox" name="ids[]" value="<?php echo $row['id']; ?>" class="bulk-check" onchange="updateBulkBar()">
                                <?php endif; ?>
                                <div class="w-12 h-12 rounded-full bg-gradient-to-br from-purple-500 to-purple-600 flex items-center justify-center text-white font-bold shadow-sm">
                                    <?php echo strtoupper(substr($row['name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800 text-lg"><?php echo htmlspecialchars($row['name']); ?></p>
                                    <div class="flex items-center gap-2 text-xs text-gray-500">
                                        <span><i class="fas fa-id-card mr-1"></i><?php echo $row['employee_id']; ?></span>
                                        <span>•</span>
                                        <span><i class="fas fa-building mr-1"></i><?php echo $row['department'] ?? 'N/A'; ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Claim Details -->
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
                                <div class="flex items-center gap-2 p-2 bg-gray-50 rounded-lg">
                                    <i class="fas fa-tag text-purple-500 text-sm w-5"></i>
                                    <span class="text-sm text-gray-600">Type:</span>
                                    <span class="text-sm font-semibold text-gray-800"><?php echo ucfirst(str_replace('_', ' ', $row['claim_type'])); ?></span>
                                </div>
                                <div class="flex items-center gap-2 p-2 bg-gray-50 rounded-lg">
                                    <i class="fas fa-money-bill-wave text-green-500 text-sm w-5"></i>
                                    <span class="text-sm text-gray-600">Amount:</span>
                                    <span class="text-sm font-bold text-green-600">RM <?php echo number_format($row['amount'], 2); ?></span>
                                </div>
                                <div class="flex items-center gap-2 p-2 bg-gray-50 rounded-lg">
                                    <i class="fas fa-calendar text-blue-500 text-sm w-5"></i>
                                    <span class="text-sm text-gray-600">Applied:</span>
                                    <span class="text-sm text-gray-700"><?php echo date('d M Y', strtotime($row['applied_at'])); ?></span>
                                </div>
                            </div>
                            
                            <?php if($row['description']): ?>
                                <div class="mt-3 p-3 bg-gray-50 rounded-xl">
                                    <p class="text-xs text-gray-500 mb-1"><i class="fas fa-comment mr-1"></i> Description:</p>
                                    <p class="text-sm text-gray-700"><?php echo nl2br(htmlspecialchars($row['description'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Attachments Section -->
                            <?php
                            $attachments = mysqli_query($conn, "SELECT * FROM claim_attachments WHERE claim_id = {$row['id']}");
                            if (mysqli_num_rows($attachments) > 0):
                            ?>
                            <div class="mt-3">
                                <p class="text-xs text-gray-500 mb-2"><i class="fas fa-paperclip mr-1"></i> Attachments (<?php echo mysqli_num_rows($attachments); ?> files):</p>
                                <div class="flex flex-wrap gap-2">
                                    <?php while($att = mysqli_fetch_assoc($attachments)): 
                                        $file_ext = pathinfo($att['file_name'], PATHINFO_EXTENSION);
                                        $is_image = in_array(strtolower($file_ext), ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                                        $is_pdf = strtolower($file_ext) == 'pdf';
                                        $is_zip = in_array(strtolower($file_ext), ['zip', 'rar', '7z']);
                                    ?>
                                    <div class="attachment-preview bg-gray-100 rounded-lg p-2 inline-flex items-center gap-2">
                                        <?php if($is_image): ?>
                                            <i class="fas fa-image text-blue-500"></i>
                                        <?php elseif($is_pdf): ?>
                                            <i class="fas fa-file-pdf text-red-500"></i>
                                        <?php elseif($is_zip): ?>
                                            <i class="fas fa-file-archive text-yellow-600"></i>
                                        <?php else: ?>
                                            <i class="fas fa-file-alt text-gray-500"></i>
                                        <?php endif; ?>
                                        <span class="text-xs text-gray-600 max-w-[150px] truncate"><?php echo $att['file_name']; ?></span>
                                        <a href="../uploads/claims/<?php echo $att['file_path']; ?>" target="_blank" class="text-blue-500 hover:text-blue-700" title="View">
                                            <i class="fas fa-eye text-xs"></i>
                                        </a>
                                        <a href="../uploads/claims/<?php echo $att['file_path']; ?>" download class="text-green-500 hover:text-green-700" title="Download">
                                            <i class="fas fa-download text-xs"></i>
                                        </a>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="md:text-right">
                            <!-- Status Badge -->
                            <span class="badge inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-semibold 
                                <?php echo $row['status'] == 'approved' ? 'bg-green-100 text-green-700' : ($row['status'] == 'rejected' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'); ?>">
                                <i class="fas <?php echo $row['status'] == 'approved' ? 'fa-check-circle' : ($row['status'] == 'rejected' ? 'fa-times-circle' : 'fa-clock'); ?>"></i>
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                            
                            <?php if ($row['status'] == 'pending'): ?>
                                <div class="flex gap-2 mt-4">
                                    <a href="?action=<?php echo $row['id']; ?>&act=approve&page=<?php echo $page; ?>&per_page=<?php echo $per_page; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&type=<?php echo urlencode($type_filter); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="btn-action flex-1 md:flex-none bg-gradient-to-r from-green-600 to-emerald-600 text-white px-6 py-2 rounded-xl text-sm font-semibold hover:shadow-lg inline-flex items-center justify-center gap-1">
                                        <i class="fas fa-check"></i> Approve
                                    </a>
                                    <a href="?action=<?php echo $row['id']; ?>&act=reject&page=<?php echo $page; ?>&per_page=<?php echo $per_page; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&type=<?php echo urlencode($type_filter); ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="btn-action flex-1 md:flex-none bg-gradient-to-r from-red-600 to-rose-600 text-white px-6 py-2 rounded-xl text-sm font-semibold hover:shadow-lg inline-flex items-center justify-center gap-1">
                                        <i class="fas fa-times"></i> Reject
                                    </a>
                                </div>
                            <?php endif; ?>
                            
                            <?php if($row['reviewed_at'] && $row['reviewed_at'] != '0000-00-00 00:00:00'): ?>
                                <p class="text-xs text-gray-400 mt-3">
                                    <i class="fas fa-clock mr-1"></i> Reviewed: <?php echo date('d M Y', strtotime($row['reviewed_at'])); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div><!-- end space-y-4 -->
        </form><!-- end bulkForm -->

        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <div class="flex justify-between items-center mt-6 bg-white rounded-xl shadow-md px-4 py-3">
            <p class="text-sm text-gray-500">
                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_rows); ?> of <?php echo $total_rows; ?> claims
            </p>
            <div class="flex gap-1">
                <?php if($page > 1): ?>
                    <a href="?page=1&per_page=<?php echo $per_page; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="px-3 py-1 bg-gray-100 border rounded-lg text-sm hover:bg-gray-200">First</a>
                    <a href="?page=<?php echo $page-1; ?>&per_page=<?php echo $per_page; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="px-3 py-1 bg-gray-100 border rounded-lg text-sm hover:bg-gray-200">Previous</a>
                <?php endif; ?>
                
                <span class="px-3 py-1 bg-blue-600 text-white rounded-lg text-sm"><?php echo $page; ?></span>
                
                <?php if($page < $total_pages): ?>
                    <a href="?page=<?php echo $page+1; ?>&per_page=<?php echo $per_page; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="px-3 py-1 bg-gray-100 border rounded-lg text-sm hover:bg-gray-200">Next</a>
                    <a href="?page=<?php echo $total_pages; ?>&per_page=<?php echo $per_page; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="px-3 py-1 bg-gray-100 border rounded-lg text-sm hover:bg-gray-200">Last</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
    <?php else: ?>
        <div class="bg-white rounded-2xl shadow-md py-16 px-8 text-center">
            <div class="flex justify-center mb-6">
                <svg class="empty-state-svg" width="150" height="150" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg" style="filter: drop-shadow(0 10px 30px rgba(147,51,234,0.18));">
                    <!-- Receipt body -->
                    <rect x="20" y="12" width="80" height="90" rx="9" fill="#faf5ff"/>
                    <rect x="20" y="12" width="80" height="90" rx="9" stroke="#9333ea" stroke-width="2.5" fill="none"/>
                    <!-- Zigzag tear edge -->
                    <path d="M20 88 L27 96 L34 88 L41 96 L48 88 L55 96 L62 88 L69 96 L76 88 L83 96 L90 88 L97 96 L100 96 L100 104 L20 104 Z" fill="#faf5ff" stroke="#9333ea" stroke-width="2" stroke-linejoin="round"/>
                    <!-- Magnifying glass circle -->
                    <circle cx="60" cy="44" r="19" fill="#ede9fe"/>
                    <circle cx="60" cy="44" r="19" stroke="#9333ea" stroke-width="2.5" fill="none"/>
                    <!-- Magnifying glass handle -->
                    <line x1="74" y1="58" x2="83" y2="67" stroke="#9333ea" stroke-width="3.5" stroke-linecap="round"/>
                    <!-- Question mark inside glass -->
                    <text x="60" y="52" text-anchor="middle" font-size="22" font-weight="800" fill="#9333ea" font-family="Inter,sans-serif">?</text>
                    <!-- Lines representing list rows -->
                    <rect x="32" y="72" width="56" height="4" rx="2" fill="#9333ea" opacity="0.25"/>
                    <rect x="38" y="80" width="44" height="4" rx="2" fill="#9333ea" opacity="0.15"/>
                    <!-- Sparkles -->
                    <circle cx="98" cy="16" r="3.5" fill="#c084fc" opacity="0.7"/>
                    <circle cx="108" cy="28" r="2" fill="#9333ea" opacity="0.35"/>
                    <circle cx="88" cy="8" r="2.5" fill="#7c3aed" opacity="0.45"/>
                    <circle cx="14" cy="30" r="2" fill="#a855f7" opacity="0.4"/>
                </svg>
            </div>
            <h3 class="text-xl font-bold text-gray-800 mb-2">No Claim Applications Found</h3>
            <p class="text-sm text-gray-500 mb-5 max-w-sm mx-auto">
                <?php if(!empty($search) || !empty($status_filter) || !empty($type_filter) || !empty($date_from)): ?>
                    No claims match your current filters. Try broadening your search criteria.
                <?php else: ?>
                    There are no claim applications in the system yet. They will appear here once employees start submitting claims.
                <?php endif; ?>
            </p>
            <?php if(!empty($search) || !empty($type_filter) || !empty($date_from) || !empty($date_to)): ?>
                <a href="manage_claim.php" class="inline-flex items-center gap-2 text-sm font-semibold text-purple-600 hover:text-purple-800 border border-purple-200 hover:border-purple-400 bg-purple-50 hover:bg-purple-100 px-5 py-2.5 rounded-xl transition">
                    <i class="fas fa-undo text-xs"></i> Reset All Filters
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Bulk Action Bar (sticky at bottom, above mobile nav) -->
<div id="bulkBar" class="fixed left-0 right-0 z-30 hidden-bar">
    <div class="bg-gradient-to-r from-indigo-700 to-purple-700 text-white px-4 py-3 shadow-2xl flex items-center justify-between flex-wrap gap-2">
        <span class="text-sm font-semibold" id="bulkCountLabel">0 selected</span>
        <div class="flex items-center gap-2">
            <button type="button" onclick="submitBulk('approve')"
                class="bg-green-500 hover:bg-green-400 text-white px-4 py-1.5 rounded-xl text-sm font-semibold flex items-center gap-1 transition">
                <i class="fas fa-check"></i> Approve All
            </button>
            <button type="button" onclick="submitBulk('reject')"
                class="bg-red-500 hover:bg-red-400 text-white px-4 py-1.5 rounded-xl text-sm font-semibold flex items-center gap-1 transition">
                <i class="fas fa-times"></i> Reject All
            </button>
            <button type="button" onclick="clearSelection()"
                class="text-white/70 hover:text-white text-xs underline ml-2 transition">
                Clear
            </button>
        </div>
    </div>
</div>

<!-- Mobile Bottom Navigation -->
<div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 md:hidden shadow-lg z-20">
    <div class="flex justify-around py-2">
        <a href="dashboard.php" class="flex flex-col items-center py-1 px-3 text-gray-500 hover:text-purple-600 transition">
            <i class="fas fa-home text-xl"></i>
            <span class="text-xs mt-1">Home</span>
        </a>
        <a href="employees.php" class="flex flex-col items-center py-1 px-3 text-gray-500 hover:text-purple-600 transition">
            <i class="fas fa-users text-xl"></i>
            <span class="text-xs mt-1">Staff</span>
        </a>
        <a href="manage_leave.php" class="flex flex-col items-center py-1 px-3 text-gray-500 hover:text-purple-600 transition">
            <i class="fas fa-calendar-alt text-xl"></i>
            <span class="text-xs mt-1">Leaves</span>
        </a>
        <a href="manage_claim.php" class="flex flex-col items-center py-1 px-3 text-purple-600">
            <i class="fas fa-receipt text-xl"></i>
            <span class="text-xs mt-1 font-semibold">Claims</span>
        </a>
    </div>
</div>

<script>

    // ---- Bulk selection ----
    function getCheckedBoxes() {
        return Array.from(document.querySelectorAll('.bulk-check[name="ids[]"]:checked'));
    }

    function updateBulkBar() {
        const checked = getCheckedBoxes();
        const count = checked.length;
        const bar = document.getElementById('bulkBar');
        const countLabel = document.getElementById('bulkCountLabel');
        const headerCount = document.getElementById('selectedCount');

        countLabel.textContent = count + ' selected';
        if (headerCount) {
            headerCount.textContent = count + ' selected';
            headerCount.classList.toggle('hidden', count === 0);
        }

        if (count > 0) {
            bar.classList.remove('hidden-bar');
        } else {
            bar.classList.add('hidden-bar');
        }

        // Sync select-all checkbox state
        const allBoxes = document.querySelectorAll('.bulk-check[name="ids[]"]');
        const selectAll = document.getElementById('selectAllCheck');
        if (selectAll) {
            selectAll.checked = allBoxes.length > 0 && count === allBoxes.length;
            selectAll.indeterminate = count > 0 && count < allBoxes.length;
        }
    }

    function toggleSelectAll(cb) {
        const boxes = document.querySelectorAll('.bulk-check[name="ids[]"]');
        boxes.forEach(b => b.checked = cb.checked);
        updateBulkBar();
    }

    function submitBulk(action) {
        const checked = getCheckedBoxes();
        if (checked.length === 0) return;
        const isApprove = action === 'approve';
        const label     = isApprove ? 'Approve' : 'Reject';
        const count     = checked.length;
        const icon      = isApprove ? '✅' : '❌';
        confirmAction(
            icon + ' ' + label + ' Claim' + (count > 1 ? 's' : ''),
            'You are about to ' + label.toLowerCase() + ' <strong>' + count + ' claim' + (count > 1 ? 's' : '') + '</strong>. This will notify the employee' + (count > 1 ? 's' : '') + ' immediately.',
            function () {
                document.getElementById('bulkActionInput').value = action;
                document.getElementById('bulkForm').submit();
            }
        );
    }

    function clearSelection() {
        document.querySelectorAll('.bulk-check').forEach(b => b.checked = false);
        updateBulkBar();
    }
</script>
</body>
</html>