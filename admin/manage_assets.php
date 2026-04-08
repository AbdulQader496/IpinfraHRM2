<?php
require_once '../includes/auth.php';
redirectIfNotAdmin();
require_once '../includes/db.php';

// Add new asset
if (isset($_POST['add_asset'])) {
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
    $asset_id = intval($_POST['asset_id']);
    $new_quantity = intval($_POST['new_quantity']);
    
    $assigned_query = mysqli_query($conn, "SELECT SUM(quantity) as assigned FROM asset_requests WHERE asset_id = $asset_id AND status = 'approved' AND returned_date IS NULL");
    $assigned = mysqli_fetch_assoc($assigned_query);
    $assigned_count = $assigned['assigned'] ?: 0;
    
    $available = $new_quantity - $assigned_count;
    if ($available < 0) $available = 0;
    
    mysqli_query($conn, "UPDATE assets SET quantity = $new_quantity, available_quantity = $available WHERE id = $asset_id");
    header('Location: manage_assets.php');
    exit();
}

// Delete asset
if (isset($_GET['delete'])) {
    $asset_id = intval($_GET['delete']);
    $check = mysqli_query($conn, "SELECT id FROM asset_requests WHERE asset_id = $asset_id LIMIT 1");
    if (mysqli_num_rows($check) > 0) {
        $error = "Cannot delete asset with existing requests.";
    } else {
        mysqli_query($conn, "DELETE FROM assets WHERE id = $asset_id");
    }
    header('Location: manage_assets.php');
    exit();
}

// Approve/Reject asset request
if (isset($_GET['action']) && isset($_GET['request_id'])) {
    $request_id = intval($_GET['request_id']);
    $action = $_GET['action'];
    $status = ($action == 'approve') ? 'approved' : 'rejected';
    
    $req_query = mysqli_query($conn, "SELECT * FROM asset_requests WHERE id = $request_id");
    $request = mysqli_fetch_assoc($req_query);
    
    if ($status == 'approved') {
        mysqli_query($conn, "UPDATE assets SET available_quantity = available_quantity - {$request['quantity']} WHERE id = {$request['asset_id']}");
        $asset_check = mysqli_query($conn, "SELECT available_quantity FROM assets WHERE id = {$request['asset_id']}");
        $asset = mysqli_fetch_assoc($asset_check);
        if ($asset['available_quantity'] == 0) {
            mysqli_query($conn, "UPDATE assets SET status = 'assigned' WHERE id = {$request['asset_id']}");
        }
    }
    
    mysqli_query($conn, "UPDATE asset_requests SET status='$status', approved_by={$_SESSION['user_id']}, approved_date=CURDATE() WHERE id=$request_id");
    
    if ($status == 'approved') {
        mysqli_query($conn, "INSERT INTO asset_assignment_history (asset_id, employee_id, assigned_date, quantity) 
                            VALUES ({$request['asset_id']}, {$request['employee_id']}, CURDATE(), {$request['quantity']})");
    }
    
    header('Location: manage_assets.php');
    exit();
}

// Return asset
if (isset($_GET['return']) && isset($_GET['request_id'])) {
    $request_id = intval($_GET['request_id']);
    
    $req_query = mysqli_query($conn, "SELECT * FROM asset_requests WHERE id = $request_id");
    $request = mysqli_fetch_assoc($req_query);
    
    mysqli_query($conn, "UPDATE assets SET available_quantity = available_quantity + {$request['quantity']}, status = 'available' WHERE id = {$request['asset_id']}");
    mysqli_query($conn, "UPDATE asset_requests SET status='returned', returned_date=CURDATE() WHERE id=$request_id");
    mysqli_query($conn, "UPDATE asset_assignment_history SET returned_date=CURDATE() WHERE asset_id={$request['asset_id']} AND employee_id={$request['employee_id']} AND returned_date IS NULL");
    
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

// Get all assets
$assets = mysqli_query($conn, "SELECT a.*, c.category_name, 
    (SELECT SUM(quantity) FROM asset_requests WHERE asset_id = a.id AND status = 'approved' AND returned_date IS NULL) as assigned_out
    FROM assets a 
    JOIN asset_categories c ON a.category_id = c.id 
    ORDER BY a.status, a.asset_name");

$categories = mysqli_query($conn, "SELECT * FROM asset_categories ORDER BY category_name");
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
        
        /* Premium Animations */
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fadeInUp { animation: fadeInUp 0.4s ease-out; }
        
        /* Card Hover */
        .card-hover { transition: all 0.2s ease; }
        .card-hover:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
        
        /* Table Row Hover */
        .table-row { transition: all 0.2s ease; }
        .table-row:hover { background-color: #f8fafc; }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen pb-20">
    
    <!-- Mobile Header -->
    <div class="bg-gradient-to-r from-gray-900 to-gray-800 text-white sticky top-0 z-30 shadow-lg">
        <div class="flex justify-between items-center px-4 py-3">
            <div class="flex items-center gap-2">
                <button onclick="history.back()" class="text-white text-xl mr-2">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                    <span class="text-white font-bold text-sm">IN</span>
                </div>
                <div>
                    <p class="text-xs text-gray-300">IPINFRA NETWORKS</p>
                    <p class="text-xs font-bold">Asset Management</p>
                </div>
            </div>
            <button onclick="toggleSidebar()" class="text-white text-2xl">
                <i class="fas fa-bars"></i>
            </button>
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
                                        <p class="font-semibold text-gray-800"><?php echo $req['name']; ?></p>
                                        <p class="text-xs text-gray-500"><?php echo $req['employee_id']; ?> • <?php echo $req['department']; ?></p>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-sm mt-2">
                                    <div><span class="text-gray-500">Asset:</span> <span class="font-medium"><?php echo $req['asset_name']; ?></span></div>
                                    <div><span class="text-gray-500">Qty:</span> <span class="font-medium"><?php echo $req['quantity']; ?></span></div>
                                    <div><span class="text-gray-500">From:</span> <span class="font-medium"><?php echo date('d M Y', strtotime($req['start_date'])); ?></span></div>
                                    <div><span class="text-gray-500">To:</span> <span class="font-medium"><?php echo date('d M Y', strtotime($req['end_date'])); ?></span></div>
                                </div>
                                <?php if($req['purpose']): ?>
                                    <p class="text-xs text-gray-500 mt-2"><span class="font-medium">Purpose:</span> <?php echo substr($req['purpose'], 0, 80); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="flex gap-2">
                                <a href="?action=approve&request_id=<?php echo $req['id']; ?>" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-1">
                                    <i class="fas fa-check"></i> Approve
                                </a>
                                <a href="?action=reject&request_id=<?php echo $req['id']; ?>" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-1">
                                    <i class="fas fa-times"></i> Reject
                                </a>
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

        <!-- All Assets Tab -->
        <div id="assetsTab" class="hidden animate-fadeInUp">
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
                            <?php while($asset = mysqli_fetch_assoc($assets)): ?>
                            <tr class="table-row">
                                <td class="p-3 text-sm font-mono"><?php echo $asset['asset_code']; ?></td>
                                <td class="p-3 text-sm font-medium text-gray-800"><?php echo $asset['asset_name']; ?></td>
                                <td class="p-3 text-sm text-gray-600"><?php echo $asset['category_name']; ?></td>
                                <td class="p-3 text-sm text-center font-bold"><?php echo $asset['quantity']; ?></td>
                                <td class="p-3 text-sm text-center">
                                    <span class="font-bold <?php echo $asset['available_quantity'] > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                        <?php echo $asset['available_quantity']; ?>
                                    </span>
                                </td>
                                <td class="p-3 text-center">
                                    <span class="text-xs px-2 py-1 rounded-full <?php echo $asset['available_quantity'] > 0 ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700'; ?>">
                                        <?php echo $asset['available_quantity'] > 0 ? 'In Stock' : 'Out of Stock'; ?>
                                    </span>
                                </td>
                                <td class="p-3 text-center">
                                    <button onclick="openQuantityModal(<?php echo $asset['id']; ?>, <?php echo $asset['quantity']; ?>)" class="text-blue-600 hover:text-blue-800 text-sm mr-2">
                                        <i class="fas fa-edit"></i> Qty
                                    </button>
                                    <a href="?delete=<?php echo $asset['id']; ?>" onclick="return confirm('Delete this asset?')" class="text-red-600 hover:text-red-800 text-sm">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
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
                                    <option value="<?php echo $cat['id']; ?>"><?php echo $cat['category_name']; ?></option>
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
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('overlay').classList.toggle('hidden');
        }
        
        function showTab(tab) {
            const pending = document.getElementById('pendingTab');
            const assets = document.getElementById('assetsTab');
            const add = document.getElementById('addTab');
            const tabPending = document.getElementById('tabPending');
            const tabAssets = document.getElementById('tabAssets');
            const tabAdd = document.getElementById('tabAdd');
            
            // Reset all tabs
            const tabs = [tabPending, tabAssets, tabAdd];
            tabs.forEach(t => {
                t.className = 'px-5 py-2.5 rounded-lg text-sm font-medium transition-all bg-gray-100 text-gray-700 hover:bg-gray-200';
            });
            
            if (tab === 'pending') {
                pending.classList.remove('hidden');
                assets.classList.add('hidden');
                add.classList.add('hidden');
                tabPending.className = 'px-5 py-2.5 rounded-lg text-sm font-medium transition-all bg-red-600 text-white shadow-sm';
            } else if (tab === 'assets') {
                pending.classList.add('hidden');
                assets.classList.remove('hidden');
                add.classList.add('hidden');
                tabAssets.className = 'px-5 py-2.5 rounded-lg text-sm font-medium transition-all bg-blue-600 text-white shadow-sm';
            } else {
                pending.classList.add('hidden');
                assets.classList.add('hidden');
                add.classList.remove('hidden');
                tabAdd.className = 'px-5 py-2.5 rounded-lg text-sm font-medium transition-all bg-green-600 text-white shadow-sm';
            }
        }
        
        function openQuantityModal(assetId, currentQty) {
            document.getElementById('qty_asset_id').value = assetId;
            document.getElementById('new_quantity').value = currentQty;
            document.getElementById('quantityModal').classList.remove('hidden');
        }
        
        function closeQuantityModal() {
            document.getElementById('quantityModal').classList.add('hidden');
        }
    </script>
</body>
</html>