<?php
require_once '../includes/auth.php';
redirectIfNotLoggedIn();
require_once '../includes/db.php';

$user_id = $_SESSION['user_id'];
$message = '';

// ========================================
// PAGINATION FOR CLAIM HISTORY
// ========================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Build WHERE clause for history filter
$where = "WHERE employee_id = $user_id";
if (!empty($status_filter)) {
    $where .= " AND status = '$status_filter'";
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM claims $where";
$count_result = mysqli_query($conn, $count_query);
$total_rows = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_rows / $per_page);
$offset = ($page - 1) * $per_page;

// Get paginated claim history
$history = mysqli_query($conn, "SELECT * FROM claims $where ORDER BY applied_at DESC LIMIT $offset, $per_page");

if (isset($_POST['apply_claim'])) {
    $claim_type = mysqli_real_escape_string($conn, $_POST['claim_type']);
    $amount = floatval($_POST['amount']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    
    $attachment = '';
    if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] == 0) {
        $target_dir = "../uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $attachment = time() . '_' . basename($_FILES['receipt']['name']);
        move_uploaded_file($_FILES['receipt']['tmp_name'], $target_dir . $attachment);
    }
    
    $query = "INSERT INTO claims (employee_id, claim_type, amount, description, attachment) 
              VALUES ($user_id, '$claim_type', '$amount', '$description', '$attachment')";
    
    if (mysqli_query($conn, $query)) {
        $message = '<div class="bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded-xl text-sm animate-fadeIn">
                        <i class="fas fa-check-circle mr-2"></i> ✓ Claim submitted successfully!
                    </div>';
    }
}

// Get statistics (overall, not paginated)
$total_claimed = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM claims WHERE employee_id = $user_id AND status = 'approved'"));
$pending_total = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as total FROM claims WHERE employee_id = $user_id AND status = 'pending'"));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
<title>Apply Claim - IPINFRA HRM</title>

<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
* { font-family: 'Inter', sans-serif; }
.sidebar { transition: transform 0.3s ease-in-out; }

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

.claim-card { transition: all 0.2s ease; }
.claim-card:hover { transform: translateY(-2px); }

.form-input:focus {
    border-color: #9333ea;
    box-shadow: 0 0 0 3px rgba(147, 51, 234, 0.1);
    outline: none;
}

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
            <button onclick="toggleSidebar()" class="relative group">
                <div class="w-10 h-10 rounded-xl bg-white/10 backdrop-blur-sm flex items-center justify-center group-hover:bg-white/20 transition-all duration-300 group-hover:scale-105">
                    <i class="fas fa-bars text-lg"></i>
                </div>
            </button>
            
            <div class="relative">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 via-indigo-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-500/20 animate-pulse">
                    <span class="text-white font-bold text-sm">IN</span>
                </div>
                <div class="absolute -top-1 -right-1 w-3 h-3 bg-green-400 rounded-full border-2 border-slate-900"></div>
            </div>
            
            <div class="hidden sm:block">
                <p class="text-xs text-blue-200 font-medium tracking-wide">IPINFRA NETWORKS</p>
                <p class="text-sm font-bold tracking-tight">Employee Portal</p>
            </div>
        </div>
        
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-indigo-500 flex items-center justify-center shadow-lg">
                <span class="text-white text-xs font-bold"><?php echo substr($_SESSION['user_name'], 0, 1); ?></span>
            </div>
        </div>
    </div>
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
        <a href="claim.php" class="flex items-center gap-3 py-3 px-4 rounded-xl bg-blue-800/50 mb-1">
            <i class="fas fa-receipt w-5"></i> Apply Claim
        </a>
        <a href="gallery.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1">
            <i class="fas fa-images w-5"></i> Company Gallery
        </a>
        <a href="assets.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1">
            <i class="fas fa-boxes w-5"></i> Asset Tracker
        </a>
        <a href="management.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-blue-800/30 transition mb-1">
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
<div class="px-4 py-6 max-w-2xl mx-auto">
    
    <!-- Header -->
    <div class="text-center mb-6 animate-fadeInUp">
        <h1 class="text-2xl font-bold text-gray-800">💰 Claim Application</h1>
        <p class="text-sm text-gray-500 mt-1">Submit reimbursement claims for approval</p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="bg-gradient-to-br from-purple-500 to-indigo-600 text-white p-4 rounded-2xl shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-purple-100 opacity-80">Total Approved</p>
                    <p class="text-2xl font-bold mt-1">RM <?php echo number_format($total_claimed['total'] ?? 0, 2); ?></p>
                </div>
                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                    <i class="fas fa-check-circle text-xl"></i>
                </div>
            </div>
        </div>
        <div class="bg-gradient-to-br from-yellow-500 to-orange-600 text-white p-4 rounded-2xl shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-yellow-100 opacity-80">Pending Claims</p>
                    <p class="text-2xl font-bold mt-1">RM <?php echo number_format($pending_total['total'] ?? 0, 2); ?></p>
                </div>
                <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center">
                    <i class="fas fa-clock text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Application Form -->
    <div class="bg-white rounded-2xl shadow-xl p-6 mb-6 animate-fadeInUp">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-md">
                <i class="fas fa-receipt text-white"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-gray-800">New Claim Request</h2>
                <p class="text-xs text-gray-500">Fill in the claim details below</p>
            </div>
        </div>
        
        <?php echo $message; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Claim Type</label>
                <select name="claim_type" required class="form-input w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-purple-500 transition">
                    <option value="travel">✈️ Travel</option>
                    <option value="meal">🍽️ Meal</option>
                    <option value="medical">🏥 Medical</option>
                    <option value="other">📄 Other</option>
                </select>
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Amount (RM)</label>
                <div class="relative">
                    <i class="fas fa-money-bill-wave absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="number" step="0.01" name="amount" required placeholder="0.00" class="form-input w-full pl-10 pr-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-purple-500 transition">
                </div>
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Description</label>
                <textarea name="description" rows="3" placeholder="Please describe the claim purpose..." class="form-input w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-purple-500 transition"></textarea>
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Receipt Attachment</label>
                <div class="relative">
                    <input type="file" name="receipt" id="fileInput" class="hidden">
                    <button type="button" onclick="document.getElementById('fileInput').click()" class="w-full border-2 border-dashed border-gray-300 rounded-xl p-3 text-center hover:border-purple-500 transition group">
                        <i class="fas fa-cloud-upload-alt text-gray-400 text-2xl group-hover:text-purple-500 transition"></i>
                        <p class="text-sm text-gray-500 mt-1 group-hover:text-purple-500 transition">Click to upload receipt</p>
                        <p class="text-xs text-gray-400 mt-1" id="fileName">No file chosen</p>
                    </button>
                </div>
            </div>
            
            <button type="submit" name="apply_claim" class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 hover:shadow-xl transition-all transform hover:scale-105 text-white py-3 rounded-xl font-semibold flex items-center justify-center gap-2">
                <i class="fas fa-paper-plane"></i> Submit Claim
            </button>
        </form>
    </div>

    <!-- Claim History with Pagination & Filter -->
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-gradient-to-r from-gray-50 to-white px-5 py-4 border-b">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-2">
                    <i class="fas fa-history text-purple-500 text-xl"></i>
                    <h3 class="font-semibold text-gray-800">Claim History</h3>
                    <span class="text-xs text-gray-400">(<?php echo $total_rows; ?> total)</span>
                </div>
                
                <!-- Status Filter Dropdown -->
                <form method="GET" class="flex gap-2">
                    <select name="status" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                    <input type="hidden" name="page" value="1">
                    <button type="submit" class="bg-purple-600 text-white px-3 py-1.5 rounded-lg text-sm">Filter</button>
                    <?php if($status_filter): ?>
                        <a href="claim.php" class="bg-gray-200 text-gray-700 px-3 py-1.5 rounded-lg text-sm">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <?php if(mysqli_num_rows($history) > 0): ?>
            <div class="divide-y divide-gray-100">
                <?php while ($row = mysqli_fetch_assoc($history)): 
                    $status_color = $row['status'] == 'approved' ? 'green' : ($row['status'] == 'rejected' ? 'red' : 'yellow');
                    $status_icon = $row['status'] == 'approved' ? 'check-circle' : ($row['status'] == 'rejected' ? 'times-circle' : 'clock');
                    $type_icon = $row['claim_type'] == 'travel' ? 'plane' : ($row['claim_type'] == 'meal' ? 'utensils' : ($row['claim_type'] == 'medical' ? 'hospital' : 'file'));
                ?>
                <div class="p-4 hover:bg-gray-50 transition claim-card">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1">
                                <i class="fas fa-<?php echo $type_icon; ?> text-purple-500 text-sm"></i>
                                <span class="font-semibold text-gray-800"><?php echo ucfirst($row['claim_type']); ?> Claim</span>
                                <span class="text-xs text-gray-400">• <?php echo date('d M Y', strtotime($row['applied_at'])); ?></span>
                            </div>
                            <p class="text-lg font-bold text-purple-600">RM <?php echo number_format($row['amount'], 2); ?></p>
                            <?php if($row['description']): ?>
                                <p class="text-xs text-gray-500 mt-1"><?php echo substr($row['description'], 0, 80); ?></p>
                            <?php endif; ?>
                            <?php if($row['attachment']): ?>
                                <a href="../uploads/<?php echo $row['attachment']; ?>" target="_blank" class="text-xs text-purple-500 hover:text-purple-700 mt-1 inline-flex items-center gap-1">
                                    <i class="fas fa-paperclip"></i> View Receipt
                                </a>
                            <?php endif; ?>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-<?php echo $status_color; ?>-100 text-<?php echo $status_color; ?>-700">
                            <i class="fas fa-<?php echo $status_icon; ?>"></i>
                            <?php echo ucfirst($row['status']); ?>
                        </span>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
            
            <!-- Pagination -->
            <?php if($total_pages > 1): ?>
            <div class="bg-gray-50 px-4 py-3 border-t flex justify-between items-center flex-wrap gap-2">
                <p class="text-sm text-gray-500">
                    Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_rows); ?> of <?php echo $total_rows; ?> records
                </p>
                <div class="flex gap-1">
                    <?php if($page > 1): ?>
                        <a href="?page=1&per_page=<?php echo $per_page; ?>&status=<?php echo $status_filter; ?>" class="px-3 py-1 bg-white border rounded-lg text-sm hover:bg-gray-100">First</a>
                        <a href="?page=<?php echo $page-1; ?>&per_page=<?php echo $per_page; ?>&status=<?php echo $status_filter; ?>" class="px-3 py-1 bg-white border rounded-lg text-sm hover:bg-gray-100">← Prev</a>
                    <?php endif; ?>
                    
                    <span class="px-3 py-1 bg-purple-600 text-white rounded-lg text-sm"><?php echo $page; ?></span>
                    
                    <?php if($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&per_page=<?php echo $per_page; ?>&status=<?php echo $status_filter; ?>" class="px-3 py-1 bg-white border rounded-lg text-sm hover:bg-gray-100">Next →</a>
                        <a href="?page=<?php echo $total_pages; ?>&per_page=<?php echo $per_page; ?>&status=<?php echo $status_filter; ?>" class="px-3 py-1 bg-white border rounded-lg text-sm hover:bg-gray-100">Last</a>
                    <?php endif; ?>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-gray-500">Show:</span>
                    <select onchange="window.location.href=this.value" class="text-sm border rounded px-2 py-1">
                        <option value="?per_page=10&page=1&status=<?php echo $status_filter; ?>" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10</option>
                        <option value="?per_page=25&page=1&status=<?php echo $status_filter; ?>" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                        <option value="?per_page=50&page=1&status=<?php echo $status_filter; ?>" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                    </select>
                </div>
            </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="p-8 text-center">
                <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-receipt text-2xl text-gray-400"></i>
                </div>
                <p class="text-gray-500 font-medium">No claims found</p>
                <?php if($status_filter): ?>
                    <a href="claim.php" class="text-purple-600 text-sm mt-2 inline-block">Clear filter</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Mobile Bottom Navigation -->
<div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 md:hidden shadow-lg z-20">
    <div class="flex justify-around py-2">
        <a href="dashboard.php" class="flex flex-col items-center py-1 px-3 text-gray-500">
            <i class="fas fa-home text-xl"></i>
            <span class="text-xs mt-1">Home</span>
        </a>
        <a href="clock.php" class="flex flex-col items-center py-1 px-3 text-gray-500">
            <i class="fas fa-clock text-xl"></i>
            <span class="text-xs mt-1">Clock</span>
        </a>
        <a href="leave.php" class="flex flex-col items-center py-1 px-3 text-gray-500">
            <i class="fas fa-calendar-alt text-xl"></i>
            <span class="text-xs mt-1">Leave</span>
        </a>
        <a href="claim.php" class="flex flex-col items-center py-1 px-3 text-purple-600">
            <i class="fas fa-receipt text-xl"></i>
            <span class="text-xs mt-1">Claim</span>
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

document.getElementById('fileInput')?.addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name || 'No file chosen';
    document.getElementById('fileName').textContent = fileName;
});
</script>

</body>
</html>