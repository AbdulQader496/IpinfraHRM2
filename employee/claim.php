<?php
require_once '../includes/auth.php';
redirectIfNotLoggedIn();
require_once '../includes/db.php';
require_once '../includes/toast_fn.php';

$user_id = $_SESSION['user_id'];
$edit_mode = false;
$edit_claim_id = 0;

// ========================================
// HANDLE EDIT CLAIM (Load data for editing)
// ========================================
if (isset($_GET['edit'])) {
    $edit_claim_id = (int)$_GET['edit'];
    $edit_query = mysqli_query($conn, "SELECT * FROM claims WHERE id = $edit_claim_id AND employee_id = $user_id AND status = 'pending'");
    if (mysqli_num_rows($edit_query) > 0) {
        $edit_mode = true;
        $edit_claim = mysqli_fetch_assoc($edit_query);
    }
}

// ========================================
// HANDLE UPDATE CLAIM
// ========================================
if (isset($_POST['update_claim'])) {
    $claim_id = (int)$_POST['claim_id'];
    $claim_type = mysqli_real_escape_string($conn, $_POST['claim_type']);
    $amount = floatval($_POST['amount']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    
    // Check if claim is still pending
    $check_query = mysqli_query($conn, "SELECT id FROM claims WHERE id = $claim_id AND employee_id = $user_id AND status = 'pending'");
    if (mysqli_num_rows($check_query) > 0) {
        $update_query = "UPDATE claims SET
                            claim_type = '$claim_type',
                            amount = $amount,
                            description = '$description'
                         WHERE id = $claim_id";
        
        if (mysqli_query($conn, $update_query)) {
            // Handle new attachments
            if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
                $target_dir = "../uploads/claims/";
                if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

                $total_files = count($_FILES['attachments']['name']);
                for ($i = 0; $i < $total_files; $i++) {
                    if ($_FILES['attachments']['error'][$i] == 0) {
                        $file_name = basename($_FILES['attachments']['name'][$i]);
                        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                        $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'zip', 'rar'];

                        if (in_array($file_ext, $allowed_ext) && $_FILES['attachments']['size'][$i] <= 10485760) {
                            $new_file_name = time() . '_' . $claim_id . '_' . $i . '.' . $file_ext;
                            if (move_uploaded_file($_FILES['attachments']['tmp_name'][$i], $target_dir . $new_file_name)) {
                                mysqli_query($conn, "INSERT INTO claim_attachments (claim_id, file_path, file_name, file_size)
                                    VALUES ($claim_id, '$new_file_name', '$file_name', {$_FILES['attachments']['size'][$i]})");
                            }
                        }
                    }
                }
            }
            header("Location: claim.php?msg=updated");
            exit();
        }
    }
}

// ========================================
// HANDLE DELETE ATTACHMENT
// ========================================
if (isset($_GET['delete_attachment'])) {
    $attach_id = (int)$_GET['delete_attachment'];
    $claim_id = (int)$_GET['claim_id'];
    
    // Get file path to delete
    $file_query = mysqli_query($conn, "SELECT file_path FROM claim_attachments WHERE id = $attach_id AND claim_id = $claim_id");
    if ($file = mysqli_fetch_assoc($file_query)) {
        $file_path = "../uploads/claims/" . $file['file_path'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        mysqli_query($conn, "DELETE FROM claim_attachments WHERE id = $attach_id");
    }
    header("Location: claim.php?edit=$claim_id");
    exit();
}

// ========================================
// HANDLE DELETE CLAIM (Only pending claims)
// ========================================
if (isset($_GET['delete'])) {
    $claim_id    = (int)$_GET['delete'];
    $check_query = mysqli_query($conn, "SELECT id FROM claims WHERE id=$claim_id AND employee_id=$user_id AND status='pending'");
    if (mysqli_num_rows($check_query) > 0) {
        $attach_query = mysqli_query($conn, "SELECT file_path FROM claim_attachments WHERE claim_id=$claim_id");
        while ($attach = mysqli_fetch_assoc($attach_query)) {
            $fp = "../uploads/claims/" . $attach['file_path'];
            if (file_exists($fp)) unlink($fp);
        }
        mysqli_query($conn, "DELETE FROM claim_attachments WHERE claim_id=$claim_id");
        mysqli_query($conn, "DELETE FROM claims WHERE id=$claim_id");
        header('Location: claim.php?msg=deleted'); exit();
    } else {
        header('Location: claim.php?err=' . urlencode('Cannot delete a claim that is already processed.')); exit();
    }
}

// ========================================
// HANDLE CLAIM SUBMISSION WITH MULTIPLE FILES
// ========================================
if (isset($_POST['apply_claim'])) {
    $claim_type = mysqli_real_escape_string($conn, $_POST['claim_type']);
    $amount = floatval($_POST['amount']);
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    
    $query = "INSERT INTO claims (employee_id, claim_type, amount, description) 
              VALUES ($user_id, '$claim_type', '$amount', '$description')";
    
    if (mysqli_query($conn, $query)) {
        $claim_id = mysqli_insert_id($conn);
        
        $uploaded_files = 0;
        $target_dir = "../uploads/claims/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        
        if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
            $total_files = count($_FILES['attachments']['name']);
            
            for ($i = 0; $i < $total_files; $i++) {
                if ($_FILES['attachments']['error'][$i] == 0) {
                    $file_name = basename($_FILES['attachments']['name'][$i]);
                    $file_size = $_FILES['attachments']['size'][$i];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'zip', 'rar'];
                    
                    if (in_array($file_ext, $allowed_ext) && $file_size <= 10485760) {
                        $new_file_name = time() . '_' . $claim_id . '_' . $i . '.' . $file_ext;
                        $file_path = $target_dir . $new_file_name;
                        
                        if (move_uploaded_file($_FILES['attachments']['tmp_name'][$i], $file_path)) {
                            $attach_query = "INSERT INTO claim_attachments (claim_id, file_path, file_name, file_size) 
                                             VALUES ($claim_id, '$new_file_name', '$file_name', $file_size)";
                            mysqli_query($conn, $attach_query);
                            $uploaded_files++;
                        }
                    }
                }
            }
        }
        
        header("Location: claim.php?msg=submitted&files=" . $uploaded_files);
        exit();
    }
}

// ========================================
// PAGINATION FOR CLAIM HISTORY
// ========================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$where = "WHERE employee_id = $user_id";
if (!empty($status_filter)) {
    $where .= " AND status = '$status_filter'";
}

$count_query = "SELECT COUNT(*) as total FROM claims $where";
$count_result = mysqli_query($conn, $count_query);
$total_rows = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_rows / $per_page);
$offset = ($page - 1) * $per_page;

$history = mysqli_query($conn, "SELECT c.*, 
    (SELECT COUNT(*) FROM claim_attachments WHERE claim_id = c.id) as attachments_count
    FROM claims c 
    $where 
    ORDER BY applied_at DESC 
    LIMIT $offset, $per_page");

// Get statistics
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

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fadeInUp { animation: fadeInUp 0.4s ease-out; }

.claim-card { transition: all 0.2s ease; }
.claim-card:hover { transform: translateY(-2px); }

.form-input:focus {
    border-color: #9333ea;
    box-shadow: 0 0 0 3px rgba(147, 51, 234, 0.1);
    outline: none;
}

.file-list-item { transition: all 0.2s ease; }
.file-list-item:hover { background-color: #f3f4f6; }

::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }

@keyframes floatY {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}
.empty-state-svg { animation: floatY 3s ease-in-out infinite; }
</style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen pb-20">
<?php require_once '../includes/global_ui.php'; ?>
<?php require_once '../includes/toast.php'; ?>
<?php require_once '../includes/confirm_modal.php'; ?>

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
    
    <div class="text-center mb-6 animate-fadeInUp">
        <h1 class="text-2xl font-bold text-gray-800">💰 Claim Application</h1>
        <p class="text-sm text-gray-500 mt-1">Submit reimbursement claims with multiple receipts (images, PDFs, or ZIP files)</p>
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

    <!-- Application / Edit Form -->
    <div class="bg-white rounded-2xl shadow-xl p-6 mb-6 animate-fadeInUp">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-md">
                <i class="fas <?php echo $edit_mode ? 'fa-edit' : 'fa-receipt'; ?> text-white"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-gray-800"><?php echo $edit_mode ? 'Edit Claim Request' : 'New Claim Request'; ?></h2>
                <p class="text-xs text-gray-500"><?php echo $edit_mode ? 'Update your claim details' : 'Fill in the claim details below'; ?></p>
            </div>
        </div>
        
        <?php
        // Messages from redirect
        if (isset($_GET['msg'])) {
            if ($_GET['msg'] == 'submitted') {
                $files = (int)($_GET['files'] ?? 0);
                echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded-xl text-sm mb-4">
                    <i class="fas fa-check-circle mr-2"></i> ✓ Claim submitted successfully! (' . $files . ' file(s) attached)</div>';
            } elseif ($_GET['msg'] == 'updated') {
                echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded-xl text-sm mb-4">
                    <i class="fas fa-check-circle mr-2"></i> ✓ Claim updated successfully!</div>';
            } elseif ($_GET['msg'] == 'deleted') {
                echo '<div class="bg-green-100 border-l-4 border-green-500 text-green-700 px-4 py-3 rounded-xl text-sm mb-4">
                    <i class="fas fa-trash mr-2"></i> ✓ Claim deleted successfully!</div>';
            }
        }
        if (isset($_GET['err'])) {
            echo '<div class="bg-red-100 border-l-4 border-red-500 text-red-700 px-4 py-3 rounded-xl text-sm mb-4">
                <i class="fas fa-exclamation-circle mr-2"></i> ' . htmlspecialchars($_GET['err']) . '</div>';
        }
        ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-4" id="claimForm">
            <?php if ($edit_mode): ?>
                <input type="hidden" name="claim_id" value="<?php echo $edit_claim['id']; ?>">
            <?php endif; ?>
            
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Claim Type</label>
                <select name="claim_type" required class="form-input w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-purple-500 transition">
                    <option value="travel" <?php echo ($edit_mode && $edit_claim['claim_type'] == 'travel') ? 'selected' : ''; ?>>✈️ Travel</option>
                    <option value="meal" <?php echo ($edit_mode && $edit_claim['claim_type'] == 'meal') ? 'selected' : ''; ?>>🍽️ Meal</option>
                    <option value="medical" <?php echo ($edit_mode && $edit_claim['claim_type'] == 'medical') ? 'selected' : ''; ?>>🏥 Medical</option>
                    <option value="toll" <?php echo ($edit_mode && $edit_claim['claim_type'] == 'toll') ? 'selected' : ''; ?>>🛣️ Toll</option>
                    <option value="parking" <?php echo ($edit_mode && $edit_claim['claim_type'] == 'parking') ? 'selected' : ''; ?>>🅿️ Parking</option>
                    <option value="other" <?php echo ($edit_mode && $edit_claim['claim_type'] == 'other') ? 'selected' : ''; ?>>📄 Other</option>
                </select>
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Amount (RM)</label>
                <div class="relative">
                    <i class="fas fa-money-bill-wave absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    <input type="number" step="0.01" name="amount" required value="<?php echo $edit_mode ? $edit_claim['amount'] : ''; ?>" placeholder="0.00" class="form-input w-full pl-10 pr-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-purple-500 transition">
                </div>
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Description</label>
                <textarea name="description" rows="3" placeholder="Please describe the claim purpose..." class="form-input w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-purple-500 transition"><?php echo $edit_mode ? htmlspecialchars($edit_claim['description']) : ''; ?></textarea>
            </div>
            
            <!-- Existing Attachments (Edit Mode) -->
            <?php if ($edit_mode): 
                $attachments = mysqli_query($conn, "SELECT * FROM claim_attachments WHERE claim_id = {$edit_claim['id']}");
                if (mysqli_num_rows($attachments) > 0):
            ?>
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Current Attachments</label>
                <div class="space-y-2">
                    <?php while($att = mysqli_fetch_assoc($attachments)): ?>
                    <div class="flex items-center justify-between bg-gray-50 p-2 rounded-lg">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-file-alt text-gray-500"></i>
                            <span class="text-sm text-gray-600"><?php echo $att['file_name']; ?></span>
                            <span class="text-xs text-gray-400">(<?php echo round($att['file_size'] / 1024, 1); ?> KB)</span>
                        </div>
                        <a href="?delete_attachment=<?php echo $att['id']; ?>&claim_id=<?php echo $edit_claim['id']; ?>" data-confirm="Delete this attachment?" data-confirm-title="Delete Attachment" class="text-red-500 hover:text-red-700">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; endif; ?>
            
            <!-- File Upload Section -->
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">
                    Attachments (Receipts) 
                    <span class="text-xs text-gray-400 font-normal">(Multiple files allowed - Images, PDF, ZIP up to 10MB each)</span>
                </label>
                <div class="relative">
                    <input type="file" name="attachments[]" id="fileInput" class="hidden" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.zip,.rar">
                    <button type="button" onclick="document.getElementById('fileInput').click()" class="w-full border-2 border-dashed border-gray-300 rounded-xl p-4 text-center hover:border-purple-500 transition group">
                        <i class="fas fa-cloud-upload-alt text-gray-400 text-3xl group-hover:text-purple-500 transition"></i>
                        <p class="text-sm text-gray-500 mt-1 group-hover:text-purple-500 transition">Click to select files</p>
                        <p class="text-xs text-gray-400 mt-1" id="fileNames">No files chosen</p>
                    </button>
                </div>
                <div id="fileList" class="mt-2 space-y-1"></div>
            </div>
            
            <button type="submit" name="<?php echo $edit_mode ? 'update_claim' : 'apply_claim'; ?>" class="w-full bg-gradient-to-r from-purple-600 to-indigo-600 hover:shadow-xl transition-all transform hover:scale-105 text-white py-3 rounded-xl font-semibold flex items-center justify-center gap-2">
                <i class="fas <?php echo $edit_mode ? 'fa-save' : 'fa-paper-plane'; ?>"></i>
                <?php echo $edit_mode ? ' Update Claim' : ' Submit Claim'; ?>
            </button>
            
            <?php if ($edit_mode): ?>
            <div class="text-center">
                <a href="claim.php" class="text-sm text-gray-500 hover:text-purple-600 transition">Cancel Edit</a>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Claim History with Pagination & Edit/Delete Options -->
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-gradient-to-r from-gray-50 to-white px-5 py-4 border-b">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-2">
                    <i class="fas fa-history text-purple-500 text-xl"></i>
                    <h3 class="font-semibold text-gray-800">Claim History</h3>
                    <span class="text-xs text-gray-400">(<?php echo $total_rows; ?> total)</span>
                </div>
                
                <form method="GET" class="flex gap-2 flex-wrap">
                    <select name="status" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                    <select name="per_page" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5">
                        <option value="5"  <?php echo $per_page == 5  ? 'selected' : ''; ?>>5 / page</option>
                        <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10 / page</option>
                        <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25 / page</option>
                        <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50 / page</option>
                        <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>All</option>
                    </select>
                    <input type="hidden" name="page" value="1">
                    <button type="submit" class="bg-purple-600 text-white px-3 py-1.5 rounded-lg text-sm">Apply</button>
                    <?php if($status_filter || $per_page != 10): ?>
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
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <i class="fas fa-<?php echo $type_icon; ?> text-purple-500 text-sm"></i>
                                <span class="font-semibold text-gray-800"><?php echo ucfirst($row['claim_type']); ?> Claim</span>
                                <span class="text-xs text-gray-400">• <?php echo date('d M Y', strtotime($row['applied_at'])); ?></span>
                                <?php if($row['attachments_count'] > 0): ?>
                                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">
                                        <i class="fas fa-paperclip mr-1"></i> <?php echo $row['attachments_count']; ?> file(s)
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p class="text-lg font-bold text-purple-600">RM <?php echo number_format($row['amount'], 2); ?></p>
                            <?php if($row['description']): ?>
                                <p class="text-xs text-gray-500 mt-1"><?php echo substr($row['description'], 0, 80); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="text-right">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-<?php echo $status_color; ?>-100 text-<?php echo $status_color; ?>-700">
                                <i class="fas fa-<?php echo $status_icon; ?>"></i>
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                            
                            <?php if ($row['status'] == 'pending'): ?>
                                <div class="flex gap-2 mt-2">
                                    <a href="?edit=<?php echo $row['id']; ?>" class="text-blue-600 hover:text-blue-800 text-sm" title="Edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="?delete=<?php echo $row['id']; ?>" data-confirm="Delete this claim? This action cannot be undone." data-confirm-title="Delete Claim" class="text-red-500 hover:text-red-700 text-sm" title="Delete">
                                        <i class="fas fa-trash"></i> Delete
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
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
            </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="py-14 px-6 text-center">
                <div class="flex justify-center mb-6">
                    <svg class="empty-state-svg" width="140" height="140" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg" style="filter: drop-shadow(0 8px 24px rgba(147,51,234,0.15));">
                        <!-- Receipt body -->
                        <rect x="22" y="14" width="76" height="88" rx="8" fill="#f3e8ff"/>
                        <rect x="22" y="14" width="76" height="88" rx="8" stroke="#9333ea" stroke-width="2.5" fill="none"/>
                        <!-- Zigzag bottom tear -->
                        <path d="M22 90 L28 97 L34 90 L40 97 L46 90 L52 97 L58 90 L64 97 L70 90 L76 97 L82 90 L88 97 L94 90 L98 90 L98 102 L22 102 Z" fill="#f3e8ff" stroke="#9333ea" stroke-width="2" stroke-linejoin="round"/>
                        <!-- Dollar sign circle -->
                        <circle cx="60" cy="42" r="16" fill="#9333ea" opacity="0.15"/>
                        <circle cx="60" cy="42" r="16" stroke="#9333ea" stroke-width="2"/>
                        <text x="60" y="48" text-anchor="middle" font-size="18" font-weight="700" fill="#9333ea" font-family="Inter,sans-serif">$</text>
                        <!-- Lines representing text -->
                        <rect x="36" y="68" width="48" height="4" rx="2" fill="#9333ea" opacity="0.3"/>
                        <rect x="42" y="77" width="36" height="4" rx="2" fill="#9333ea" opacity="0.2"/>
                        <!-- Sparkle top-right -->
                        <circle cx="94" cy="18" r="3" fill="#c084fc" opacity="0.7"/>
                        <circle cx="104" cy="28" r="2" fill="#9333ea" opacity="0.4"/>
                        <circle cx="86" cy="10" r="2" fill="#7c3aed" opacity="0.5"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-700 mb-1">No Claims Yet</h3>
                <p class="text-sm text-gray-400 mb-4 max-w-xs mx-auto">
                    <?php if($status_filter): ?>
                        No <strong class="text-purple-600"><?php echo $status_filter; ?></strong> claims match your filter.
                    <?php else: ?>
                        You haven't submitted any reimbursement claims. Use the form above to get started.
                    <?php endif; ?>
                </p>
                <?php if($status_filter): ?>
                    <a href="claim.php" class="inline-flex items-center gap-2 text-sm font-semibold text-purple-600 hover:text-purple-800 border border-purple-200 hover:border-purple-400 px-4 py-2 rounded-xl transition">
                        <i class="fas fa-times-circle text-xs"></i> Clear Filter
                    </a>
                <?php else: ?>
                    <p class="text-xs text-gray-400 italic">Tip: Travel, meal, medical and toll claims are all supported.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Mobile Bottom Navigation -->
<div class="bottom-nav fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 md:hidden shadow-lg z-20">
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

// File upload handling
const fileInput = document.getElementById('fileInput');
const fileNames = document.getElementById('fileNames');
const fileList = document.getElementById('fileList');

fileInput?.addEventListener('change', function(e) {
    const files = e.target.files;
    const fileCount = files.length;
    
    if (fileCount > 0) {
        let names = '';
        let listHtml = '<div class="text-xs font-semibold text-gray-700 mb-1">Selected files:</div>';
        
        for (let i = 0; i < fileCount; i++) {
            const file = files[i];
            const fileSizeKB = (file.size / 1024).toFixed(1);
            names += (i > 0 ? ', ' : '') + file.name;
            listHtml += `
                <div class="flex items-center gap-2 text-sm text-gray-600 py-1">
                    <i class="fas fa-file text-gray-400"></i>
                    <span>${file.name}</span>
                    <span class="text-xs text-gray-400">(${fileSizeKB} KB)</span>
                </div>
            `;
        }
        
        fileNames.textContent = fileCount + ' file(s) selected';
        fileList.innerHTML = listHtml;
    } else {
        fileNames.textContent = 'No files chosen';
        fileList.innerHTML = '';
    }
});
</script>

</body>
</html>