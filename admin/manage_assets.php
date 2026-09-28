<?php
require_once '../includes/auth.php';
redirectIfNotAdmin();
require_once '../includes/db.php';
/** @var mysqli $conn */
require_once '../includes/functions.php';
require_once '../includes/toast_fn.php';

// ========================================
// PAGINATION & FILTERS
// ========================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
if ($per_page < 1) $per_page = 10;
if ($per_page > 100) $per_page = 100;
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$category_filter = isset($_GET['category']) ? intval($_GET['category']) : 0;
$status_filter = isset($_GET['status']) && in_array($_GET['status'], ['available', 'outofstock'], true) ? $_GET['status'] : '';

// Build WHERE clause for assets
$where = "WHERE 1=1";
if (!empty($search)) {
    $where .= " AND (a.asset_name LIKE '%$search%' OR a.asset_code LIKE '%$search%' OR a.brand LIKE '%$search%' OR a.model LIKE '%$search%')";
}
if (!empty($category_filter)) {
    $where .= " AND a.category_id = $category_filter";
}
if (!empty($status_filter)) {
    if ($status_filter == 'available') {
        $where .= " AND a.available_quantity > 0";
    } elseif ($status_filter == 'outofstock') {
        $where .= " AND a.available_quantity = 0";
    }
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM assets a $where";
$count_result = mysqli_query($conn, $count_query);
$total_rows = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_rows / $per_page);
$offset = ($page - 1) * $per_page;

// Add new asset
if (isset($_POST['add_asset'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        header('Location: manage_assets.php'); exit();
    }
    $asset_code = mysqli_real_escape_string($conn, $_POST['asset_code']);
    $asset_name = mysqli_real_escape_string($conn, $_POST['asset_name']);
    $quantity = intval($_POST['quantity']);
    $category_id = intval($_POST['category_id']);
    $brand = mysqli_real_escape_string($conn, $_POST['brand']);
    $model = mysqli_real_escape_string($conn, $_POST['model']);
    $serial_number = mysqli_real_escape_string($conn, $_POST['serial_number']);
    $purchase_date = mysqli_real_escape_string($conn, $_POST['purchase_date']);
    $purchase_price = floatval($_POST['purchase_price']);
    $location = mysqli_real_escape_string($conn, $_POST['location']);

    $query = "INSERT INTO assets (asset_code, asset_name, quantity, available_quantity, category_id, brand, model, serial_number, purchase_date, purchase_price, location, status)
              VALUES ('$asset_code', '$asset_name', $quantity, $quantity, $category_id, '$brand', '$model', '$serial_number', '$purchase_date', '$purchase_price', '$location', 'available')";
    mysqli_query($conn, $query);
    header('Location: manage_assets.php');
    exit();
}

// Update quantity
if (isset($_POST['update_quantity'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        header('Location: manage_assets.php'); exit();
    }
    $asset_id = intval($_POST['asset_id']);
    $new_quantity = intval($_POST['new_quantity']);

    $assigned_query = mysqli_query($conn, "SELECT SUM(quantity) as assigned FROM asset_requests WHERE asset_id = $asset_id AND status = 'approved' AND returned_date IS NULL");
    $assigned = mysqli_fetch_assoc($assigned_query);
    $assigned_count = $assigned['assigned'] ?: 0;

    // Shrinking the total below what's currently checked out used to just clamp
    // available_quantity to 0 and move on -- but the very next Return then adds back
    // to available_quantity with no cap at the (now-smaller) total, so available_quantity
    // ends up exceeding quantity after a few returns, and every later approval only checks
    // available_quantity >= requested, letting the asset be overbooked beyond what
    // physically exists. Reject the shrink instead of silently creating that inconsistency.
    if ($new_quantity < $assigned_count) {
        showToast("Can't set total below $assigned_count — that many units are currently checked out. Wait for returns first.", 'error');
        header('Location: manage_assets.php'); exit();
    }

    $available = $new_quantity - $assigned_count;
    mysqli_query($conn, "UPDATE assets SET quantity = $new_quantity, available_quantity = $available WHERE id = $asset_id");
    header('Location: manage_assets.php');
    exit();
}

// Delete asset
if (isset($_POST['delete'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        header('Location: manage_assets.php'); exit();
    }
    $asset_id = intval($_POST['delete']);
    $check = mysqli_query($conn, "SELECT id FROM asset_requests WHERE asset_id = $asset_id LIMIT 1");
    if (mysqli_num_rows($check) > 0) {
        $error = "Cannot delete asset with existing requests.";
    } else {
        mysqli_query($conn, "DELETE FROM assets WHERE id = $asset_id");
        logAction('delete', 'Deleted asset', $asset_id, 'asset');
    }
    header('Location: manage_assets.php');
    exit();
}

// Approve/Reject/Return asset request — POST + CSRF (F022)
if (isset($_POST['asset_action']) && validateCsrfToken($_POST['csrf_token'] ?? '')) {
    // Action whitelist (F050)
    $allowed_actions = ['approve', 'reject', 'return'];
    if (!in_array($_POST['asset_action'] ?? '', $allowed_actions)) {
        header('Location: manage_assets.php');
        exit();
    }

    $asset_action = $_POST['asset_action'];
    $request_id = intval($_POST['request_id'] ?? 0);

    if ($asset_action === 'return') {
        $req_query = mysqli_query($conn, "SELECT * FROM asset_requests WHERE id = $request_id AND status = 'approved' AND returned_date IS NULL");
        $request = mysqli_fetch_assoc($req_query);
        if (!$request) {
            showToast('Request not found or already processed.', 'error');
            header('Location: manage_assets.php'); exit();
        }

        // Claim the request row FIRST, atomically, before touching stock -- otherwise a
        // duplicate submission (double-click, retry) can pass the SELECT above twice and
        // both go on to double-credit available_quantity and insert two history rows for
        // what should be a single return.
        mysqli_query($conn, "UPDATE asset_requests SET status='returned', returned_date=CURDATE() WHERE id=$request_id AND status='approved' AND returned_date IS NULL");
        if (mysqli_affected_rows($conn) == 0) {
            showToast('Request already processed.', 'error');
            header('Location: manage_assets.php'); exit();
        }

        // LEAST(...) caps this at the asset's own total -- without it, available_quantity
        // could be pushed above quantity (e.g. after an admin shrinks the total while units
        // are checked out), and every later approval only checks available_quantity, so an
        // uncapped return here is what actually let stock become overbooked.
        mysqli_query($conn, "UPDATE assets SET available_quantity = LEAST(quantity, available_quantity + {$request['quantity']}), status = 'available' WHERE id = {$request['asset_id']}");
        mysqli_query($conn, "UPDATE asset_assignment_history SET returned_date=CURDATE() WHERE asset_id={$request['asset_id']} AND employee_id={$request['employee_id']} AND returned_date IS NULL");
        logAction('update', 'Marked asset request as returned', $request_id, 'asset_request');
    } else {
        $status = ($asset_action == 'approve') ? 'approved' : 'rejected';

        $req_query = mysqli_query($conn, "SELECT * FROM asset_requests WHERE id = $request_id AND status = 'pending'");
        $request = mysqli_fetch_assoc($req_query);
        if (!$request) {
            showToast('Request not found or already processed.', 'error');
            header('Location: manage_assets.php'); exit();
        }

        if ($status == 'approved' && $request['quantity'] <= 0) {
            showToast('Invalid request quantity.', 'error');
            header('Location: manage_assets.php'); exit();
        }

        // Claim the request row FIRST, atomically, before touching stock -- otherwise two
        // near-simultaneous approvals of the same request_id can both pass the SELECT
        // above and both decrement available_quantity for what should be a single approval.
        mysqli_query($conn, "UPDATE asset_requests SET status='$status', approved_by={$_SESSION['user_id']}, approved_date=CURDATE() WHERE id=$request_id AND status='pending'");
        if (mysqli_affected_rows($conn) == 0) {
            showToast('Request already processed.', 'error');
            header('Location: manage_assets.php'); exit();
        }

        if ($status == 'approved') {
            mysqli_query($conn, "UPDATE assets SET available_quantity = available_quantity - {$request['quantity']} WHERE id = {$request['asset_id']} AND available_quantity >= {$request['quantity']}");
            if (mysqli_affected_rows($conn) == 0) {
                // Not enough stock after all -- the request row already claimed 'approved'
                // above, so undo that claim rather than leaving it approved with nothing
                // actually reserved for it.
                mysqli_query($conn, "UPDATE asset_requests SET status='pending', approved_by=NULL, approved_date=NULL WHERE id=$request_id");
                showToast('Insufficient stock to approve this request.', 'error');
                header('Location: manage_assets.php'); exit();
            }
            $asset_check = mysqli_query($conn, "SELECT available_quantity FROM assets WHERE id = {$request['asset_id']}");
            $asset = mysqli_fetch_assoc($asset_check);
            if ($asset['available_quantity'] == 0) {
                mysqli_query($conn, "UPDATE assets SET status = 'assigned' WHERE id = {$request['asset_id']}");
            }
            mysqli_query($conn, "INSERT INTO asset_assignment_history (asset_id, employee_id, assigned_date, quantity)
                                VALUES ({$request['asset_id']}, {$request['employee_id']}, CURDATE(), {$request['quantity']})");
        }
        logAction($status === 'approved' ? 'approve' : 'reject', ucfirst($status) . ' asset request', $request_id, 'asset_request');
    }

    header('Location: manage_assets.php');
    exit();
}

// Get statistics
$stats_query = mysqli_query($conn, "SELECT
    COUNT(*) as total_assets,
    SUM(quantity) as total_quantity,
    SUM(CASE WHEN available_quantity > 0 THEN 1 ELSE 0 END) as available_count,
    SUM(CASE WHEN available_quantity = 0 THEN 1 ELSE 0 END) as assigned_count,
    SUM(available_quantity) as total_available
    FROM assets");
$stats = mysqli_fetch_assoc($stats_query);

// Get all pending requests
$pending_requests = mysqli_query($conn, "SELECT ar.*, e.name, e.employee_id, e.department, a.asset_name, a.asset_code
    FROM asset_requests ar
    JOIN employees e ON ar.employee_id = e.id
    JOIN assets a ON ar.asset_id = a.id
    WHERE ar.status = 'pending'
    ORDER BY ar.created_at ASC");

// Get paginated assets with filters
$assets = mysqli_query($conn, "SELECT a.*, c.category_name,
    (SELECT SUM(quantity) FROM asset_requests WHERE asset_id = a.id AND status = 'approved' AND returned_date IS NULL) as assigned_out
    FROM assets a
    JOIN asset_categories c ON a.category_id = c.id
    $where
    ORDER BY a.asset_name
    LIMIT $offset, $per_page");

$categories = mysqli_query($conn, "SELECT * FROM asset_categories ORDER BY category_name");

// Active assignments — approved requests not yet returned
$active_assignments = mysqli_query($conn, "
    SELECT ar.id, ar.quantity, ar.start_date, ar.end_date, ar.purpose, ar.approved_date,
           e.name, e.employee_id, e.department,
           a.asset_name, a.asset_code
    FROM asset_requests ar
    JOIN employees e ON ar.employee_id = e.id
    JOIN assets a ON ar.asset_id = a.id
    WHERE ar.status = 'approved' AND ar.returned_date IS NULL
    ORDER BY a.asset_name, e.name
");
$active_count = mysqli_num_rows($active_assignments);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Asset Management - IPINFRA HRM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .sidebar { transition: transform 0.3s ease-in-out; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fadeInUp { animation: fadeInUp 0.4s ease-out; }
        .card-hover { transition: all 0.2s ease; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
        .table-row { transition: all 0.2s ease; }
        .table-row:hover { background-color: #f8fafc; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
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

<?php require_once '../includes/admin_sidebar.php'; ?>

<!-- Main Content -->
<div class="px-4 py-6 pb-24 max-w-7xl mx-auto">

    <!-- Header -->
    <div class="mb-6 animate-fadeInUp">
        <h1 class="text-2xl font-bold text-gray-800">📦 Asset Management</h1>
        <p class="text-sm text-gray-500 mt-1">Manage company assets, stock quantities, and track assignments</p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl p-4 shadow-md card-hover">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">Total Assets</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_assets'] ?? 0; ?></p>
                </div>
                <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-boxes text-blue-600"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-md card-hover">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">Total Items</p>
                    <p class="text-2xl font-bold text-gray-800"><?php echo $stats['total_quantity'] ?? 0; ?></p>
                </div>
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-cubes text-green-600"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-md card-hover">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">Available</p>
                    <p class="text-2xl font-bold text-green-600"><?php echo $stats['total_available'] ?? 0; ?></p>
                </div>
                <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600"></i>
                </div>
            </div>
        </div>
        <div class="bg-white rounded-xl p-4 shadow-md card-hover">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500">Pending Requests</p>
                    <p class="text-2xl font-bold text-orange-600"><?php echo mysqli_num_rows($pending_requests); ?></p>
                </div>
                <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center">
                    <i class="fas fa-clock text-orange-600"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-2 mb-6 overflow-x-auto pb-2 border-b border-gray-200">
        <button onclick="showTab('pending')" id="tabPending" class="px-5 py-2.5 rounded-lg text-sm font-medium transition-all bg-red-600 text-white shadow-sm">
            <i class="fas fa-clock mr-1"></i> Pending (<?php echo mysqli_num_rows($pending_requests); ?>)
        </button>
        <button onclick="showTab('assets')" id="tabAssets" class="px-5 py-2.5 rounded-lg text-sm font-medium transition-all bg-gray-100 text-gray-700 hover:bg-gray-200">
            <i class="fas fa-boxes mr-1"></i> All Assets
        </button>
        <button onclick="showTab('inuse')" id="tabInuse" class="px-5 py-2.5 rounded-lg text-sm font-medium transition-all bg-gray-100 text-gray-700 hover:bg-gray-200">
            <i class="fas fa-user-check mr-1"></i> In Use (<?php echo $active_count; ?>)
        </button>
        <button onclick="showTab('add')" id="tabAdd" class="px-5 py-2.5 rounded-lg text-sm font-medium transition-all bg-gray-100 text-gray-700 hover:bg-gray-200">
            <i class="fas fa-plus-circle mr-1"></i> Add Asset
        </button>
    </div>

    <!-- Pending Requests Tab -->
    <div id="pendingTab" class="animate-fadeInUp">
        <?php if(mysqli_num_rows($pending_requests) > 0): ?>
            <div class="space-y-3">
                <?php while($req = mysqli_fetch_assoc($pending_requests)): ?>
                <div class="bg-white rounded-xl shadow-md p-4 card-hover">
                    <div class="flex flex-col md:flex-row justify-between gap-3">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-2">
                                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center">
                                    <i class="fas fa-user text-blue-600 text-sm"></i>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($req['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($req['employee_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?> • <?php echo htmlspecialchars($req['department'] ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
                                </div>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm mt-2">
                                <div><span class="text-gray-500">Asset:</span> <span class="font-medium"><?php echo htmlspecialchars($req['asset_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></span></div>
                                <div><span class="text-gray-500">Qty:</span> <span class="font-medium"><?php echo intval($req['quantity']); ?></span></div>
                                <div><span class="text-gray-500">From:</span> <span class="font-medium"><?php echo date('d M Y', strtotime($req['start_date'])); ?></span></div>
                                <div><span class="text-gray-500">To:</span> <span class="font-medium"><?php echo date('d M Y', strtotime($req['end_date'])); ?></span></div>
                            </div>
                            <?php if($req['purpose']): ?>
                                <p class="text-xs text-gray-500 mt-2"><span class="font-medium">Purpose:</span> <?php echo htmlspecialchars(substr($req['purpose'], 0, 80), ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="flex gap-2">
                            <!-- Approve — POST + CSRF (F022) -->
                            <form method="POST" style="display:inline;">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="asset_action" value="approve">
                                <input type="hidden" name="request_id" value="<?php echo intval($req['id']); ?>">
                                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-1">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                            </form>
                            <!-- Reject — POST + CSRF (F022) -->
                            <form method="POST" style="display:inline;">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="asset_action" value="reject">
                                <input type="hidden" name="request_id" value="<?php echo intval($req['id']); ?>">
                                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-1">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-md p-12 text-center">
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-check-circle text-2xl text-green-600"></i>
                </div>
                <p class="text-gray-500 font-medium">No pending requests</p>
                <p class="text-xs text-gray-400 mt-1">All asset requests have been processed</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- All Assets Tab with Search & Filter -->
    <div id="assetsTab" class="hidden animate-fadeInUp">
        <!-- Search & Filter Bar -->
        <div class="bg-white rounded-xl shadow-md p-4 mb-4">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-3">
                <input type="hidden" name="tab" value="assets">
                <div class="md:col-span-2">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                               placeholder="Search by name, code, brand..."
                               class="w-full pl-9 pr-3 py-2 border border-gray-200 rounded-lg text-sm">
                    </div>
                </div>
                <div>
                    <select name="category" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                        <option value="">All Categories</option>
                        <?php mysqli_data_seek($categories, 0); ?>
                        <?php while($cat = mysqli_fetch_assoc($categories)): ?>
                            <option value="<?php echo intval($cat['id']); ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['category_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <select name="status" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                        <option value="">All Status</option>
                        <option value="available" <?php echo $status_filter == 'available' ? 'selected' : ''; ?>>In Stock</option>
                        <option value="outofstock" <?php echo $status_filter == 'outofstock' ? 'selected' : ''; ?>>Out of Stock</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 bg-blue-600 text-white px-3 py-2 rounded-lg text-sm hover:bg-blue-700">Filter</button>
                    <a href="?tab=assets" class="flex-1 bg-gray-200 text-gray-700 px-3 py-2 rounded-lg text-sm text-center hover:bg-gray-300">Reset</a>
                </div>
            </form>
        </div>

        <!-- Results Summary & Per Page -->
        <div class="flex justify-between items-center mb-4">
            <p class="text-sm text-gray-500">
                <?php if ($total_rows > 0) {
                    echo 'Showing ' . ($offset + 1) . ' to ' . min($offset + $per_page, $total_rows) . ' of ' . $total_rows . ' assets';
                } else {
                    echo 'No assets found';
                } ?>
            </p>
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-500">Show:</span>
                <select onchange="window.location.href=this.value" class="text-sm border rounded px-2 py-1">
                    <option value="<?php echo "?tab=assets&per_page=10&page=1&search=" . urlencode($search) . "&category=$category_filter&status=$status_filter"; ?>" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
                    <option value="<?php echo "?tab=assets&per_page=25&page=1&search=" . urlencode($search) . "&category=$category_filter&status=$status_filter"; ?>" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                    <option value="<?php echo "?tab=assets&per_page=50&page=1&search=" . urlencode($search) . "&category=$category_filter&status=$status_filter"; ?>" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                    <option value="<?php echo "?tab=assets&per_page=100&page=1&search=" . urlencode($search) . "&category=$category_filter&status=$status_filter"; ?>" <?php echo $per_page == 100 ? 'selected' : ''; ?>>100</option>
                </select>
            </div>
        </div>

        <!-- Assets Table -->
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-800 text-white">
                        <tr>
                            <th class="p-3 text-left text-xs font-semibold">Code</th>
                            <th class="p-3 text-left text-xs font-semibold">Asset Name</th>
                            <th class="p-3 text-left text-xs font-semibold">Category</th>
                            <th class="p-3 text-center text-xs font-semibold">Total</th>
                            <th class="p-3 text-center text-xs font-semibold">Available</th>
                            <th class="p-3 text-center text-xs font-semibold">Status</th>
                            <th class="p-3 text-center text-xs font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php if(mysqli_num_rows($assets) > 0): ?>
                            <?php while($asset = mysqli_fetch_assoc($assets)): ?>
                            <tr class="table-row">
                                <td class="p-3 text-sm font-mono"><?php echo htmlspecialchars($asset['asset_code'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="p-3 text-sm font-medium text-gray-800"><?php echo htmlspecialchars($asset['asset_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="p-3 text-sm text-gray-600"><?php echo htmlspecialchars($asset['category_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="p-3 text-sm text-center font-bold"><?php echo intval($asset['quantity']); ?></td>
                                <td class="p-3 text-sm text-center">
                                    <span class="font-bold <?php echo $asset['available_quantity'] > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                        <?php echo intval($asset['available_quantity']); ?>
                                    </span>
                                </td>
                                <td class="p-3 text-center">
                                    <span class="text-xs px-2 py-1 rounded-full <?php echo $asset['available_quantity'] > 0 ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700'; ?>">
                                        <?php echo $asset['available_quantity'] > 0 ? 'In Stock' : 'Out of Stock'; ?>
                                    </span>
                                </td>
                                <td class="p-3 text-center">
                                    <button onclick="openQuantityModal(<?php echo intval($asset['id']); ?>, <?php echo intval($asset['quantity']); ?>, <?php echo htmlspecialchars(json_encode($asset['asset_name'] ?? ''), ENT_QUOTES); ?>)" class="text-blue-600 hover:text-blue-800 text-sm mr-2">
                                        <i class="fas fa-edit"></i> Qty
                                    </button>
                                    <form method="POST" style="display:inline" onsubmit="return confirm('Delete this asset record permanently?')">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="delete" value="<?php echo intval($asset['id']); ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-800 text-sm">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                 </td>
                             </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="p-8 text-center text-gray-500">No assets found matching your criteria</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <div class="flex justify-between items-center mt-4 bg-white rounded-xl shadow-md px-4 py-3">
            <p class="text-sm text-gray-500">
                Page <?php echo $page; ?> of <?php echo $total_pages; ?>
            </p>
            <div class="flex gap-1">
                <?php if($page > 1): ?>
                    <a href="?tab=assets&page=1&per_page=<?php echo $per_page; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>" class="px-3 py-1 bg-gray-100 border rounded-lg text-sm hover:bg-gray-200">First</a>
                    <a href="?tab=assets&page=<?php echo $page-1; ?>&per_page=<?php echo $per_page; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>" class="px-3 py-1 bg-gray-100 border rounded-lg text-sm hover:bg-gray-200">Previous</a>
                <?php endif; ?>

                <span class="px-3 py-1 bg-blue-600 text-white rounded-lg text-sm"><?php echo $page; ?></span>

                <?php if($page < $total_pages): ?>
                    <a href="?tab=assets&page=<?php echo $page+1; ?>&per_page=<?php echo $per_page; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>" class="px-3 py-1 bg-gray-100 border rounded-lg text-sm hover:bg-gray-200">Next</a>
                    <a href="?tab=assets&page=<?php echo $total_pages; ?>&per_page=<?php echo $per_page; ?>&search=<?php echo urlencode($search); ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>" class="px-3 py-1 bg-gray-100 border rounded-lg text-sm hover:bg-gray-200">Last</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- In Use Tab -->
    <div id="inuseTab" class="hidden animate-fadeInUp">
        <?php if ($active_count > 0): ?>
        <div class="bg-white rounded-xl shadow-md overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b flex items-center gap-2">
                <i class="fas fa-user-check text-indigo-500 text-sm"></i>
                <span class="font-semibold text-gray-800 text-sm">Currently In Use</span>
                <span class="text-xs text-gray-400"><?php echo $active_count; ?> active assignment<?php echo $active_count > 1 ? 's' : ''; ?></span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b text-xs font-semibold text-gray-500 uppercase tracking-wide">
                            <th class="px-4 py-3 text-left">Employee</th>
                            <th class="px-4 py-3 text-left">Asset</th>
                            <th class="px-3 py-3 text-center whitespace-nowrap">Qty</th>
                            <th class="px-3 py-3 text-left whitespace-nowrap">From</th>
                            <th class="px-3 py-3 text-left whitespace-nowrap">Due Back</th>
                            <th class="px-3 py-3 text-left">Purpose</th>
                            <th class="px-3 py-3 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    <?php while ($asgn = mysqli_fetch_assoc($active_assignments)):
                        $overdue = $asgn['end_date'] && $asgn['end_date'] < date('Y-m-d');
                    ?>
                    <tr class="hover:bg-indigo-50/30 transition-colors">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2.5">
                                <div class="w-8 h-8 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold text-xs flex-shrink-0">
                                    <?php echo strtoupper(substr($asgn['name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <p class="font-medium text-gray-800 leading-tight"><?php echo htmlspecialchars($asgn['name']); ?></p>
                                    <p class="text-xs text-gray-400"><?php echo htmlspecialchars($asgn['employee_id']); ?> &bull; <?php echo htmlspecialchars($asgn['department']); ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-medium text-gray-800"><?php echo htmlspecialchars($asgn['asset_name']); ?></p>
                            <p class="text-xs text-gray-400"><?php echo htmlspecialchars($asgn['asset_code']); ?></p>
                        </td>
                        <td class="px-3 py-3 text-center">
                            <span class="font-semibold text-gray-700"><?php echo $asgn['quantity']; ?></span>
                        </td>
                        <td class="px-3 py-3 text-gray-600 whitespace-nowrap">
                            <?php echo $asgn['start_date'] ? date('d M Y', strtotime($asgn['start_date'])) : '—'; ?>
                        </td>
                        <td class="px-3 py-3 whitespace-nowrap">
                            <?php if ($asgn['end_date']): ?>
                                <span class="<?php echo $overdue ? 'text-red-600 font-semibold' : 'text-gray-600'; ?>">
                                    <?php echo date('d M Y', strtotime($asgn['end_date'])); ?>
                                    <?php if ($overdue): ?><span class="ml-1 text-[10px] bg-red-100 text-red-600 px-1.5 py-0.5 rounded-full">Overdue</span><?php endif; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-gray-400">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-3 py-3 text-gray-600 max-w-[160px]">
                            <p class="truncate text-xs"><?php echo htmlspecialchars($asgn['purpose'] ?? '—'); ?></p>
                        </td>
                        <td class="px-3 py-3 text-center">
                            <form method="POST" onsubmit="return false;">
                                <?php echo csrfField(); ?>
                                <input type="hidden" name="asset_action" value="return">
                                <input type="hidden" name="request_id" value="<?php echo $asgn['id']; ?>">
                                <button type="button"
                                    onclick="confirmAction('Mark as Returned?','Confirm that <?php echo htmlspecialchars($asgn['name'], ENT_QUOTES); ?> has returned <?php echo htmlspecialchars($asgn['asset_name'], ENT_QUOTES); ?>. The stock will be updated.',function(){this.closest('form').submit();}.bind(this))"
                                    class="inline-flex items-center gap-1.5 text-xs bg-green-100 hover:bg-green-200 text-green-700 font-semibold px-3 py-1.5 rounded-lg transition whitespace-nowrap">
                                    <i class="fas fa-undo text-[10px]"></i> Returned
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php else: ?>
        <div class="bg-white rounded-xl shadow-md p-16 text-center">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-check-double text-2xl text-gray-300"></i>
            </div>
            <p class="text-gray-500 font-medium">No assets currently in use</p>
            <p class="text-xs text-gray-400 mt-1">All assets are available or pending return.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Add Asset Tab -->
    <div id="addTab" class="hidden animate-fadeInUp">
        <div class="bg-white rounded-xl shadow-md p-6 max-w-3xl mx-auto">
            <div class="text-center mb-5">
                <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-plus-circle text-2xl text-blue-600"></i>
                </div>
                <h2 class="text-xl font-bold text-gray-800">Add New Asset</h2>
                <p class="text-xs text-gray-500 mt-1">Enter asset details to add to inventory</p>
            </div>

            <form method="POST" class="space-y-4">
                <?php echo csrfField(); ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-medium mb-1">Asset Code <span class="text-red-500">*</span></label>
                        <input type="text" name="asset_code" required placeholder="e.g., LAP-001" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-medium mb-1">Asset Name <span class="text-red-500">*</span></label>
                        <input type="text" name="asset_name" required placeholder="e.g., Dell XPS 15" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-medium mb-1">Quantity <span class="text-red-500">*</span></label>
                        <input type="number" name="quantity" required value="1" min="1" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-medium mb-1">Category <span class="text-red-500">*</span></label>
                        <select name="category_id" required class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:border-blue-500 focus:outline-none">
                            <?php mysqli_data_seek($categories, 0); ?>
                            <?php while($cat = mysqli_fetch_assoc($categories)): ?>
                                <option value="<?php echo intval($cat['id']); ?>"><?php echo htmlspecialchars($cat['category_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-medium mb-1">Brand</label>
                        <input type="text" name="brand" placeholder="e.g., Dell, Apple" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-medium mb-1">Model</label>
                        <input type="text" name="model" placeholder="e.g., XPS 15" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-medium mb-1">Serial Number</label>
                        <input type="text" name="serial_number" placeholder="Serial/IMEI" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-medium mb-1">Purchase Date</label>
                        <input type="date" name="purchase_date" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:border-blue-500 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-medium mb-1">Purchase Price (RM)</label>
                        <input type="number" step="0.01" name="purchase_price" placeholder="0.00" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:border-blue-500 focus:outline-none">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-gray-700 text-sm font-medium mb-1">Location</label>
                        <input type="text" name="location" placeholder="e.g., IT Store Room" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:border-blue-500 focus:outline-none">
                    </div>
                </div>
                <button type="submit" name="add_asset" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg font-semibold transition mt-2">
                    <i class="fas fa-save mr-2"></i> Add Asset to Inventory
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Update Quantity Modal -->
<div id="quantityModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl max-w-md w-full">
        <div class="p-4 border-b flex justify-between items-center">
            <h2 class="text-lg font-bold">Update Stock Quantity</h2>
            <button onclick="closeQuantityModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form method="POST" class="p-4 space-y-4">
            <?php echo csrfField(); ?>
            <input type="hidden" name="asset_id" id="qty_asset_id">
            <div class="bg-blue-50 p-3 rounded-lg text-center">
                <p class="font-medium text-gray-800" id="qty_asset_name"></p>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-medium mb-1">New Total Quantity</label>
                <input type="number" name="new_quantity" id="new_quantity" required min="0" class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:border-blue-500 focus:outline-none">
                <p class="text-xs text-gray-500 mt-1">Available quantity will auto-adjust based on assigned items</p>
            </div>
            <button type="submit" name="update_quantity" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-lg font-semibold transition">
                Update Quantity
            </button>
        </form>
    </div>
</div>

<!-- Mobile Bottom Navigation -->
<div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 md:hidden shadow-lg z-20">
    <div class="flex justify-around py-2">
        <a href="dashboard.php" class="flex flex-col items-center py-1 px-3 text-gray-500">
            <i class="fas fa-home text-xl"></i>
            <span class="text-xs mt-1">Home</span>
        </a>
        <a href="employees.php" class="flex flex-col items-center py-1 px-3 text-gray-500">
            <i class="fas fa-users text-xl"></i>
            <span class="text-xs mt-1">Staff</span>
        </a>
        <a href="manage_assets.php" class="flex flex-col items-center py-1 px-3 text-blue-600">
            <i class="fas fa-boxes text-xl"></i>
            <span class="text-xs mt-1">Assets</span>
        </a>
        <a href="payroll.php" class="flex flex-col items-center py-1 px-3 text-gray-500">
            <i class="fas fa-file-invoice-dollar text-xl"></i>
            <span class="text-xs mt-1">Payroll</span>
        </a>
    </div>
</div>

<script>
    // Check URL parameter for active tab
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab');
        if (['assets', 'inuse', 'add'].includes(tab)) {
            showTab(tab);
        } else {
            showTab('pending');
        }
    });

    function showTab(tab) {
        const panes = {
            pending: document.getElementById('pendingTab'),
            assets:  document.getElementById('assetsTab'),
            inuse:   document.getElementById('inuseTab'),
            add:     document.getElementById('addTab'),
        };
        const btns = {
            pending: document.getElementById('tabPending'),
            assets:  document.getElementById('tabAssets'),
            inuse:   document.getElementById('tabInuse'),
            add:     document.getElementById('tabAdd'),
        };
        const activeColors = {
            pending: 'bg-red-600 text-white shadow-sm',
            assets:  'bg-blue-600 text-white shadow-sm',
            inuse:   'bg-indigo-600 text-white shadow-sm',
            add:     'bg-green-600 text-white shadow-sm',
        };
        const base = 'px-5 py-2.5 rounded-lg text-sm font-medium transition-all';

        Object.keys(panes).forEach(t => {
            if (panes[t]) panes[t].classList.add('hidden');
            if (btns[t])  btns[t].className = base + ' bg-gray-100 text-gray-700 hover:bg-gray-200';
        });

        if (panes[tab]) panes[tab].classList.remove('hidden');
        if (btns[tab])  btns[tab].className = base + ' ' + (activeColors[tab] || 'bg-gray-600 text-white');
    }

    function openQuantityModal(assetId, currentQty, assetName) {
        document.getElementById('qty_asset_id').value = assetId;
        document.getElementById('new_quantity').value = currentQty;
        document.getElementById('qty_asset_name').innerHTML = assetName || 'Asset';
        document.getElementById('quantityModal').classList.remove('hidden');
    }

    function closeQuantityModal() {
        document.getElementById('quantityModal').classList.add('hidden');
    }
</script>
<?php require_once '../includes/confirm_modal.php'; ?>
</body>
</html>
