<?php
require_once '../includes/auth.php';
redirectIfNotLoggedIn();
require_once '../includes/db.php';

$user_id = $_SESSION['user_id'];

// Handle asset request
if (isset($_POST['request_asset'])) {
    $asset_id = intval($_POST['asset_id']);
    $purpose = mysqli_real_escape_string($conn, $_POST['purpose']);
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
    $quantity_requested = intval($_POST['quantity_requested']);
    
    $query = "INSERT INTO asset_requests (employee_id, asset_id, purpose, start_date, end_date, request_date, quantity) 
              VALUES ($user_id, $asset_id, '$purpose', '$start_date', '$end_date', CURDATE(), $quantity_requested)";
    mysqli_query($conn, $query);
    $success = '<div class="bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded-xl text-sm animate-fadeIn">
                    <i class="fas fa-check-circle mr-2"></i> ✓ Asset request submitted successfully!
                </div>';
}

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$category_filter = isset($_GET['category']) ? $_GET['category'] : '';

// Get available assets with search and filter
$query = "SELECT a.*, c.category_name, c.icon 
    FROM assets a 
    JOIN asset_categories c ON a.category_id = c.id 
    WHERE a.available_quantity > 0";
    
if (!empty($search)) {
    $search = mysqli_real_escape_string($conn, $search);
    $query .= " AND (a.asset_name LIKE '%$search%' OR a.asset_code LIKE '%$search%' OR a.brand LIKE '%$search%')";
}
if (!empty($category_filter)) {
    $query .= " AND a.category_id = $category_filter";
}
$query .= " ORDER BY c.category_name, a.asset_name";

$assets = mysqli_query($conn, $query);

// Get user's current assets
$my_assets = mysqli_query($conn, "SELECT a.*, c.category_name, ar.status, ar.start_date, ar.end_date, ar.quantity
    FROM assets a 
    JOIN asset_categories c ON a.category_id = c.id 
    JOIN asset_requests ar ON a.id = ar.asset_id 
    WHERE ar.employee_id = $user_id AND ar.status IN ('approved', 'pending')
    ORDER BY ar.created_at DESC");

// Get request history
$request_history = mysqli_query($conn, "SELECT ar.*, a.asset_name, a.asset_code, c.category_name
    FROM asset_requests ar
    JOIN assets a ON ar.asset_id = a.id
    JOIN asset_categories c ON a.category_id = c.id
    WHERE ar.employee_id = $user_id
    ORDER BY ar.created_at DESC LIMIT 10");

// Get categories for filter
$categories = mysqli_query($conn, "SELECT * FROM asset_categories ORDER BY category_name");

// Get counts for badges
$available_count = mysqli_num_rows($assets);
$my_assets_count = mysqli_num_rows($my_assets);
$history_count = mysqli_num_rows($request_history);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Asset Tracker - IPINFRA HRM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .sidebar { transition: transform 0.3s ease-in-out; }
        
        /* Premium Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fadeIn { animation: fadeIn 0.3s ease-out; }
        .animate-fadeInUp { animation: fadeInUp 0.4s ease-out; }
        
        /* Card Hover */
        .asset-card { transition: all 0.2s ease; }
        .asset-card:hover { transform: translateY(-2px); box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    </style>
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

    <!-- Main Content -->
    <div class="px-4 py-6 pb-24 max-w-7xl mx-auto">
        
        <!-- Header -->
        <div class="text-center mb-6 animate-fadeInUp">
            <h1 class="text-2xl font-bold text-gray-800">📦 Asset Tracker</h1>
            <p class="text-sm text-gray-500 mt-1">Request company assets like laptops, phones, IoT devices, and more</p>
        </div>

        <?php if(isset($success)) echo $success; ?>

        <!-- Premium Tabs -->
        <div class="flex gap-3 mb-6 bg-white/50 backdrop-blur-sm rounded-2xl p-2 shadow-lg">
            <button onclick="showTab('available')" id="tabAvailable" class="tab-btn flex-1 py-2.5 rounded-xl font-semibold transition-all relative bg-blue-600 text-white shadow-md">
                <i class="fas fa-box-open mr-2"></i> Available Assets
                <?php if($available_count > 0): ?>
                    <span class="ml-2 bg-white/20 text-white text-xs px-2 py-0.5 rounded-full"><?php echo $available_count; ?></span>
                <?php endif; ?>
            </button>
            <button onclick="showTab('myAssets')" id="tabMyAssets" class="tab-btn flex-1 py-2.5 rounded-xl font-semibold transition-all relative bg-gray-100 text-gray-700">
                <i class="fas fa-user-check mr-2"></i> My Assets
                <?php if($my_assets_count > 0): ?>
                    <span class="ml-2 bg-blue-100 text-blue-600 text-xs px-2 py-0.5 rounded-full"><?php echo $my_assets_count; ?></span>
                <?php endif; ?>
            </button>
            <button onclick="showTab('history')" id="tabHistory" class="tab-btn flex-1 py-2.5 rounded-xl font-semibold transition-all relative bg-gray-100 text-gray-700">
                <i class="fas fa-history mr-2"></i> Request History
                <?php if($history_count > 0): ?>
                    <span class="ml-2 bg-gray-200 text-gray-600 text-xs px-2 py-0.5 rounded-full"><?php echo $history_count; ?></span>
                <?php endif; ?>
            </button>
        </div>

        <!-- Available Assets Tab -->
        <div id="availableTab" class="animate-fadeInUp">
            <!-- Search and Filter Bar -->
            <div class="bg-white rounded-2xl shadow-lg p-5 mb-6">
                <form method="GET" action="" class="flex flex-col md:flex-row gap-3">
                    <div class="flex-1 relative">
                        <i class="fas fa-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="Search by name, code, or brand..." 
                               class="w-full pl-12 pr-4 py-3 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none transition">
                    </div>
                    <div class="w-full md:w-56">
                        <select name="category" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none transition">
                            <option value="">All Categories</option>
                            <?php mysqli_data_seek($categories, 0); ?>
                            <?php while($cat = mysqli_fetch_assoc($categories)): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                    <?php echo $cat['category_name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <button type="submit" class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-6 py-3 rounded-xl font-semibold hover:shadow-lg transition transform hover:scale-105">
                        <i class="fas fa-search mr-1"></i> Search
                    </button>
                    <?php if(!empty($search) || !empty($category_filter)): ?>
                        <a href="assets.php" class="bg-gray-200 text-gray-700 px-6 py-3 rounded-xl font-semibold hover:bg-gray-300 transition text-center">
                            <i class="fas fa-times mr-1"></i> Clear
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Assets Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                <?php if(mysqli_num_rows($assets) > 0): ?>
                    <?php while($asset = mysqli_fetch_assoc($assets)): ?>
                    <div class="bg-white rounded-2xl shadow-lg overflow-hidden asset-card">
                        <div class="p-5">
                            <div class="flex items-start gap-3">
                                <div class="w-14 h-14 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-md">
                                    <i class="fas <?php echo $asset['icon']; ?> text-white text-2xl"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="flex justify-between items-start flex-wrap gap-2">
                                        <div>
                                            <h3 class="font-bold text-gray-800 text-lg"><?php echo $asset['asset_name']; ?></h3>
                                            <p class="text-xs text-gray-500"><?php echo $asset['asset_code']; ?></p>
                                        </div>
                                        <div class="text-right">
                                            <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full">Available</span>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <p class="text-xs text-gray-500">
                                            <i class="fas fa-tag mr-1"></i> <?php echo $asset['brand']; ?> <?php echo $asset['model']; ?>
                                        </p>
                                        <p class="text-xs text-gray-500 mt-1">
                                            <i class="fas fa-map-marker-alt mr-1"></i> <?php echo $asset['location']; ?>
                                        </p>
                                        <div class="mt-2 flex items-center justify-between">
                                            <span class="text-xs text-gray-500">Stock: <?php echo $asset['available_quantity']; ?>/<?php echo $asset['quantity']; ?></span>
                                            <?php if($asset['available_quantity'] > 0): ?>
                                                <button onclick="openRequestModal(<?php echo $asset['id']; ?>, '<?php echo addslashes($asset['asset_name']); ?>', <?php echo $asset['available_quantity']; ?>)" 
                                                        class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-4 py-1.5 rounded-lg text-sm font-semibold hover:shadow-lg transition transform hover:scale-105">
                                                    <i class="fas fa-paper-plane mr-1"></i> Request
                                                </button>
                                            <?php else: ?>
                                                <button disabled class="bg-gray-300 text-gray-500 px-4 py-1.5 rounded-lg text-sm cursor-not-allowed">
                                                    Out of Stock
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-span-full bg-white rounded-2xl shadow-lg p-12 text-center">
                        <div class="w-24 h-24 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-box-open text-4xl text-gray-400"></i>
                        </div>
                        <p class="text-gray-500 font-medium">No assets found</p>
                        <p class="text-xs text-gray-400 mt-1">Try adjusting your search or filter</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- My Assets Tab -->
        <div id="myAssetsTab" class="hidden animate-fadeInUp">
            <div class="space-y-4">
                <?php if(mysqli_num_rows($my_assets) > 0): ?>
                    <?php while($asset = mysqli_fetch_assoc($my_assets)): ?>
                    <div class="bg-white rounded-2xl shadow-lg p-5 asset-card">
                        <div class="flex items-start gap-3">
                            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center shadow-md">
                                <i class="fas fa-laptop text-white text-xl"></i>
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between items-start flex-wrap gap-2">
                                    <div>
                                        <h3 class="font-bold text-gray-800"><?php echo $asset['asset_name']; ?></h3>
                                        <p class="text-xs text-gray-500"><?php echo $asset['asset_code']; ?> • <?php echo $asset['brand']; ?></p>
                                    </div>
                                    <span class="text-xs px-2 py-1 rounded-full <?php echo $asset['status'] == 'approved' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>">
                                        <i class="fas <?php echo $asset['status'] == 'approved' ? 'fa-check-circle' : 'fa-clock'; ?> mr-1"></i>
                                        <?php echo ucfirst($asset['status']); ?>
                                    </span>
                                </div>
                                <p class="text-xs text-gray-500 mt-2">
                                    <i class="fas fa-calendar-alt mr-1"></i> 
                                    <?php echo date('d M Y', strtotime($asset['start_date'])); ?> - <?php echo date('d M Y', strtotime($asset['end_date'])); ?>
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="fas fa-cubes mr-1"></i> Quantity: <?php echo $asset['quantity']; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
                        <div class="w-24 h-24 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-box-open text-4xl text-gray-400"></i>
                        </div>
                        <p class="text-gray-500 font-medium">No assets assigned to you</p>
                        <p class="text-xs text-gray-400 mt-1">Request assets from the Available Assets tab</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- History Tab -->
        <div id="historyTab" class="hidden animate-fadeInUp">
            <div class="space-y-4">
                <?php if(mysqli_num_rows($request_history) > 0): ?>
                    <?php while($req = mysqli_fetch_assoc($request_history)): ?>
                    <div class="bg-white rounded-2xl shadow-lg p-5 asset-card">
                        <div class="flex justify-between items-start flex-wrap gap-3">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <i class="fas fa-box text-blue-500"></i>
                                    <h3 class="font-bold text-gray-800"><?php echo $req['asset_name']; ?></h3>
                                </div>
                                <p class="text-xs text-gray-500"><?php echo $req['asset_code']; ?></p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="fas fa-cubes mr-1"></i> Quantity: <?php echo $req['quantity']; ?>
                                </p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <i class="fas fa-comment mr-1"></i> <?php echo substr($req['purpose'], 0, 60); ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <span class="text-xs px-2 py-1 rounded-full <?php echo $req['status'] == 'approved' ? 'bg-green-100 text-green-700' : ($req['status'] == 'rejected' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'); ?>">
                                    <i class="fas <?php echo $req['status'] == 'approved' ? 'fa-check-circle' : ($req['status'] == 'rejected' ? 'fa-times-circle' : 'fa-clock'); ?> mr-1"></i>
                                    <?php echo ucfirst($req['status']); ?>
                                </span>
                                <p class="text-xs text-gray-400 mt-1">
                                    <i class="fas fa-calendar-alt mr-1"></i> <?php echo date('d M Y', strtotime($req['created_at'])); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="bg-white rounded-2xl shadow-lg p-12 text-center">
                        <div class="w-24 h-24 bg-gradient-to-br from-gray-100 to-gray-200 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-history text-4xl text-gray-400"></i>
                        </div>
                        <p class="text-gray-500 font-medium">No request history</p>
                        <p class="text-xs text-gray-400 mt-1">Your asset requests will appear here</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Request Modal -->
    <div id="requestModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl modal-enter">
            <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-5 rounded-t-2xl">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-xl font-bold text-white">Request Asset</h2>
                        <p class="text-xs text-blue-100 mt-1">Fill in the details below</p>
                    </div>
                    <button onclick="closeRequestModal()" class="w-10 h-10 rounded-full bg-white/20 hover:bg-white/30 transition-all flex items-center justify-center">
                        <i class="fas fa-times text-white text-xl"></i>
                    </button>
                </div>
            </div>
            <form method="POST" class="p-6 space-y-5">
                <input type="hidden" name="asset_id" id="request_asset_id">
                
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-xl text-center">
                    <i class="fas fa-box text-3xl text-blue-600 mb-2 block"></i>
                    <p class="font-semibold text-gray-800 text-lg" id="request_asset_name"></p>
                    <p class="text-xs text-gray-500 mt-1">Available: <span id="available_quantity" class="font-semibold text-green-600"></span></p>
                </div>
                
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Quantity Requested</label>
                    <input type="number" name="quantity_requested" id="quantity_requested" required min="1" max="1" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none transition">
                </div>
                
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Purpose of Request</label>
                    <textarea name="purpose" rows="3" required placeholder="Why do you need this asset?" class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none transition"></textarea>
                </div>
                
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-gray-700 text-sm font-semibold mb-2">Start Date</label>
                        <input type="date" name="start_date" required class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none transition">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-semibold mb-2">End Date</label>
                        <input type="date" name="end_date" required class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none transition">
                    </div>
                </div>
                
                <button type="submit" name="request_asset" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white py-3 rounded-xl font-semibold hover:shadow-xl transition-all transform hover:scale-105">
                    <i class="fas fa-paper-plane mr-2"></i> Submit Request
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
            <a href="assets.php" class="flex flex-col items-center py-1 px-3 text-blue-600">
                <i class="fas fa-boxes text-xl"></i>
                <span class="text-xs mt-1">Assets</span>
            </a>
            <a href="gallery.php" class="flex flex-col items-center py-1 px-3 text-gray-500">
                <i class="fas fa-images text-xl"></i>
                <span class="text-xs mt-1">Gallery</span>
            </a>
            <a href="profile.php" class="flex flex-col items-center py-1 px-3 text-gray-500">
                <i class="fas fa-user text-xl"></i>
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
            const available = document.getElementById('availableTab');
            const myAssets = document.getElementById('myAssetsTab');
            const history = document.getElementById('historyTab');
            const tabAvailable = document.getElementById('tabAvailable');
            const tabMyAssets = document.getElementById('tabMyAssets');
            const tabHistory = document.getElementById('tabHistory');
            
            // Reset all tabs to inactive
            const tabs = [tabAvailable, tabMyAssets, tabHistory];
            tabs.forEach(t => {
                t.className = 'tab-btn flex-1 py-2.5 rounded-xl font-semibold transition-all relative bg-gray-100 text-gray-700';
            });
            
            if (tab === 'available') {
                available.classList.remove('hidden');
                myAssets.classList.add('hidden');
                history.classList.add('hidden');
                tabAvailable.className = 'tab-btn flex-1 py-2.5 rounded-xl font-semibold transition-all relative bg-blue-600 text-white shadow-md';
            } else if (tab === 'myAssets') {
                available.classList.add('hidden');
                myAssets.classList.remove('hidden');
                history.classList.add('hidden');
                tabMyAssets.className = 'tab-btn flex-1 py-2.5 rounded-xl font-semibold transition-all relative bg-blue-600 text-white shadow-md';
            } else {
                available.classList.add('hidden');
                myAssets.classList.add('hidden');
                history.classList.remove('hidden');
                tabHistory.className = 'tab-btn flex-1 py-2.5 rounded-xl font-semibold transition-all relative bg-blue-600 text-white shadow-md';
            }
        }
        
        function openRequestModal(assetId, assetName, availableQty) {
            document.getElementById('request_asset_id').value = assetId;
            document.getElementById('request_asset_name').innerHTML = assetName;
            document.getElementById('available_quantity').innerHTML = availableQty;
            document.getElementById('quantity_requested').max = availableQty;
            document.getElementById('quantity_requested').value = 1;
            document.getElementById('requestModal').classList.remove('hidden');
        }
        
        function closeRequestModal() {
            document.getElementById('requestModal').classList.add('hidden');
        }
    </script>
</body>
</html>