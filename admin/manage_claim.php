<?php
require_once '../includes/auth.php';
redirectIfNotAdmin();
require_once '../includes/db.php';

// ========================================
// PAGINATION & FILTERS
// ========================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

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

// Get paginated claims
$claims = mysqli_query($conn, "SELECT c.*, e.name, e.employee_id, e.department 
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
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen pb-20">

<!-- Premium Mobile Header -->
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
        <a href="manage_claim.php" class="flex items-center gap-3 py-3 px-4 rounded-xl bg-gray-800/50 mb-1">
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
        <div class="border-t border-gray-800 my-4"></div>
        <a href="../logout.php" class="flex items-center gap-3 py-3 px-4 rounded-xl bg-red-600/20 text-red-300 hover:bg-red-600/30 transition">
            <i class="fas fa-sign-out-alt w-5"></i> Logout
        </a>
    </nav>
</div>

<div id="overlay" class="fixed inset-0 bg-black/50 z-40 hidden" onclick="toggleSidebar()"></div>

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
        <div class="space-y-4">
            <?php while ($row = mysqli_fetch_assoc($claims)): ?>
            <div class="claim-card bg-white rounded-2xl shadow-md overflow-hidden">
                <div class="p-5">
                    <div class="flex flex-col md:flex-row justify-between gap-4">
                        <div class="flex-1">
                            <!-- Employee Info -->
                            <div class="flex items-center gap-3 mb-4">
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
                            
                            <?php if($row['attachment']): ?>
                                <div class="mt-2">
                                    <a href="../uploads/<?php echo $row['attachment']; ?>" target="_blank" class="inline-flex items-center gap-2 text-xs text-purple-600 hover:text-purple-800 bg-purple-50 px-3 py-1.5 rounded-lg transition">
                                        <i class="fas fa-paperclip"></i> View Receipt / Attachment
                                    </a>
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
                                    <a href="?action=approve&id=<?php echo $row['id']; ?>&page=<?php echo $page; ?>&per_page=<?php echo $per_page; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="btn-action flex-1 md:flex-none bg-gradient-to-r from-green-600 to-emerald-600 text-white px-6 py-2 rounded-xl text-sm font-semibold hover:shadow-lg inline-flex items-center justify-center gap-1">
                                        <i class="fas fa-check"></i> Approve
                                    </a>
                                    <a href="?action=reject&id=<?php echo $row['id']; ?>&page=<?php echo $page; ?>&per_page=<?php echo $per_page; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo $status_filter; ?>&type=<?php echo $type_filter; ?>&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="btn-action flex-1 md:flex-none bg-gradient-to-r from-red-600 to-rose-600 text-white px-6 py-2 rounded-xl text-sm font-semibold hover:shadow-lg inline-flex items-center justify-center gap-1">
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
        </div>
        
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
        <div class="bg-white rounded-2xl shadow-md p-12 text-center">
            <div class="w-24 h-24 bg-gradient-to-br from-purple-100 to-pink-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-receipt text-4xl text-purple-600"></i>
            </div>
            <h3 class="text-lg font-semibold text-gray-800 mb-2">No Claim Applications Found</h3>
            <p class="text-sm text-gray-500">Try adjusting your search or filter criteria</p>
        </div>
    <?php endif; ?>
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
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('-translate-x-full');
        document.getElementById('overlay').classList.toggle('hidden');
    }
</script>
</body>
</html>