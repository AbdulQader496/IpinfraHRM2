<?php
require_once '../includes/auth.php';
redirectIfNotLoggedIn();
require_once '../includes/db.php';
/** @var mysqli $conn */
require_once '../includes/toast_fn.php';

$user_id = intval($_SESSION['user_id']);
$edit_mode = false;
$edit_claim_id = 0;
$edit_claim = null;

// Matches claims.claim_type's ENUM in the DB. Under MYSQLI_REPORT_STRICT an out-of-set
// value from a crafted (non-UI) request throws an uncaught mysqli_sql_exception instead of
// being rejected gracefully -- same bug class already fixed for leaves.leave_type.
$allowed_claim_types = ['travel', 'meal', 'medical', 'toll', 'parking', 'other'];

// ========================================
// SHARED ATTACHMENT UPLOAD HANDLER
// ========================================
// PHP's own upload_max_filesize/post_max_size ini limits are enforced before this code
// runs at all — a file rejected there just shows up with a non-zero error code here.
// Previously all failures (oversized file, disallowed type, missing fileinfo extension,
// unwritable folder) were skipped silently, so a claim could "save" with attachments
// quietly missing. This now reports every failure back to the user.
function handleClaimAttachments(mysqli $conn, int $claim_id) {
    $result = ['uploaded' => 0, 'failed' => []];
    if (!isset($_FILES['attachments']) || empty($_FILES['attachments']['name'][0])) {
        return $result;
    }

    $target_dir = "../uploads/claims/";
    if (!is_dir($target_dir)) { @mkdir($target_dir, 0777, true); }
    $dir_ok = is_dir($target_dir) && is_writable($target_dir);

    // jpg/png/pdf/doc/zip/rar only rejected iPhone photos, which default to HEIC — the
    // most common real-world "can't upload my receipt photo" complaint. Added gif/webp
    // (already allowed for leave attachments, just missing here) and heic/heif.
    $att_ok_ext  = ['jpg','jpeg','png','gif','webp','heic','heif','pdf','doc','docx','zip','rar'];
    $att_ok_mime = ['image/jpeg','image/png','image/gif','image/webp','image/heic','image/heif',
                    'application/pdf','application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/zip','application/x-zip-compressed',
                    'application/x-rar-compressed','application/vnd.rar','application/x-rar'];
    // Older libmagic databases on some servers don't recognize HEIC/HEIF and report the
    // generic application/octet-stream instead — accept that specifically for these two
    // extensions rather than rejecting every HEIC photo whenever that's the case.
    $att_ok_mime_by_ext = ['heic' => 'application/octet-stream', 'heif' => 'application/octet-stream'];
    $fileinfo_available = function_exists('finfo_open');

    $total_files = count($_FILES['attachments']['name']);
    for ($i = 0; $i < $total_files; $i++) {
        $file_name = basename($_FILES['attachments']['name'][$i]);
        $error = $_FILES['attachments']['error'][$i];

        if ($error !== UPLOAD_ERR_OK) {
            $reason = ($error == UPLOAD_ERR_INI_SIZE || $error == UPLOAD_ERR_FORM_SIZE)
                ? 'file is larger than this server allows' : 'upload error';
            $result['failed'][] = "$file_name ($reason)";
            continue;
        }
        if (!$dir_ok) {
            $result['failed'][] = "$file_name (server storage folder not writable)";
            continue;
        }

        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        if (!in_array($file_ext, $att_ok_ext)) {
            $result['failed'][] = "$file_name (file type not allowed)";
            continue;
        }

        // If the fileinfo extension isn't installed on this server, fall back to
        // extension-only validation instead of rejecting every single upload.
        $mime_ok = true;
        if ($fileinfo_available) {
            $att_finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($att_finfo) {
                $att_mime = finfo_file($att_finfo, $_FILES['attachments']['tmp_name'][$i]);
                $mime_ok = in_array($att_mime, $att_ok_mime)
                    || (isset($att_ok_mime_by_ext[$file_ext]) && $att_mime === $att_ok_mime_by_ext[$file_ext]);
            }
        }
        if (!$mime_ok) {
            $result['failed'][] = "$file_name (file content doesn't match its extension)";
            continue;
        }
        if ($_FILES['attachments']['size'][$i] > 10485760) {
            $result['failed'][] = "$file_name (exceeds 10MB)";
            continue;
        }

        $new_file_name = time() . '_' . $claim_id . '_' . $i . '.' . $file_ext;
        if (move_uploaded_file($_FILES['attachments']['tmp_name'][$i], $target_dir . $new_file_name)) {
            $file_size = $_FILES['attachments']['size'][$i];
            mysqli_query($conn, "INSERT INTO claim_attachments (claim_id, file_path, file_name, file_size)
                VALUES ($claim_id, '$new_file_name', '$file_name', $file_size)");
            $result['uploaded']++;
        } else {
            $result['failed'][] = "$file_name (could not save file on server)";
        }
    }
    return $result;
}

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
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        showToast('Security error.', 'error'); header('Location: claim.php'); exit;
    }
    $claim_id = intval($_POST['claim_id'] ?? 0);
    $claim_type_raw = $_POST['claim_type'] ?? '';
    if (!in_array($claim_type_raw, $allowed_claim_types, true)) {
        showToast('Please choose a valid claim type.', 'error'); header("Location: claim.php?edit=$claim_id"); exit;
    }
    $claim_type = mysqli_real_escape_string($conn, $claim_type_raw);
    $amount = floatval($_POST['amount'] ?? 0);
    // A hand-crafted POST (bypassing the type="number" input) could otherwise submit zero or
    // a negative amount -- approved and swept into payroll, a negative claim would REDUCE net pay.
    if ($amount <= 0) {
        showToast('Claim amount must be greater than 0.', 'error'); header("Location: claim.php?edit=$claim_id"); exit;
    }
    $description_raw = trim($_POST['description'] ?? '');
    if ($description_raw === '') {
        showToast('Please describe the claim purpose.', 'error'); header("Location: claim.php?edit=$claim_id"); exit;
    }
    $description = mysqli_real_escape_string($conn, $description_raw);

    // Atomic guard: re-check status='pending' in the UPDATE itself, not just an earlier
    // SELECT — an admin approving this exact claim in between would otherwise still get
    // silently overwritten by this update.
    $update_query = "UPDATE claims SET
                        claim_type = '$claim_type',
                        amount = $amount,
                        description = '$description'
                     WHERE id = $claim_id AND employee_id = $user_id AND status = 'pending'";
    mysqli_query($conn, $update_query);
    if (mysqli_affected_rows($conn) > 0) {
        $att_result = handleClaimAttachments($conn, $claim_id);
        if ($att_result['failed']) {
            showToast('Claim updated, but ' . count($att_result['failed']) . ' attachment(s) failed: ' . implode('; ', $att_result['failed']), 'warning');
        } else {
            showToast('Claim updated successfully!');
        }
        header("Location: claim.php");
        exit();
    } else {
        showToast('This claim is no longer pending and can\'t be edited.', 'error');
        header("Location: claim.php");
        exit();
    }
}

// ========================================
// HANDLE DELETE ATTACHMENT
// ========================================
if (isset($_POST['delete_attachment'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        showToast('Security error.', 'error'); header('Location: claim.php'); exit;
    }
    $attach_id = intval($_POST['delete_attachment'] ?? 0);
    $claim_id = intval($_POST['claim_id'] ?? 0);

    // Only allow deleting an attachment off a claim that's still pending (matches the
    // ownership + status scoping used everywhere else in this file).
    $file_query = mysqli_query($conn, "SELECT ca.file_path FROM claim_attachments ca
 JOIN claims c ON ca.claim_id = c.id
 WHERE ca.id = $attach_id AND ca.claim_id = $claim_id AND c.employee_id = $user_id AND c.status = 'pending'");
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
if (isset($_POST['delete_claim'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        showToast('Security error.', 'error'); header('Location: claim.php'); exit;
    }
    $claim_id    = intval($_POST['claim_id'] ?? $_GET['claim_id'] ?? 0);
    $check_query = mysqli_query($conn, "SELECT id FROM claims WHERE id=$claim_id AND employee_id=$user_id AND status='pending'");
    if (mysqli_num_rows($check_query) > 0) {
        $attach_query = mysqli_query($conn, "SELECT file_path FROM claim_attachments WHERE claim_id=$claim_id");
        while ($attach = mysqli_fetch_assoc($attach_query)) {
            $fp = "../uploads/claims/" . $attach['file_path'];
            if (file_exists($fp)) unlink($fp);
        }
        mysqli_query($conn, "DELETE FROM claim_attachments WHERE claim_id=$claim_id");
        // Atomic guard: re-check status='pending' on the actual DELETE, not just the SELECT above.
        mysqli_query($conn, "DELETE FROM claims WHERE id=$claim_id AND employee_id=$user_id AND status='pending'");
        showToast('Claim deleted.', 'info'); header('Location: claim.php'); exit();
    } else {
        showToast('Cannot delete a claim that is already processed.', 'error'); header('Location: claim.php'); exit();
    }
}

// ========================================
// HANDLE CLAIM SUBMISSION WITH MULTIPLE FILES
// ========================================
if (isset($_POST['apply_claim'])) {
    if (!validateCsrfToken($_POST['csrf_token'] ?? '')) {
        showToast('Security error.', 'error'); header('Location: claim.php'); exit;
    }
    $claim_type_raw = $_POST['claim_type'] ?? '';
    if (!in_array($claim_type_raw, $allowed_claim_types, true)) {
        showToast('Please choose a valid claim type.', 'error'); header('Location: claim.php'); exit;
    }
    $claim_type = mysqli_real_escape_string($conn, $claim_type_raw);
    $amount = floatval($_POST['amount'] ?? 0);
    if ($amount <= 0) {
        showToast('Claim amount must be greater than 0.', 'error'); header('Location: claim.php'); exit;
    }
    $description_raw = trim($_POST['description'] ?? '');
    if ($description_raw === '') {
        showToast('Please describe the claim purpose.', 'error'); header('Location: claim.php'); exit;
    }
    $description = mysqli_real_escape_string($conn, $description_raw);

    $query = "INSERT INTO claims (employee_id, claim_type, amount, description)
              VALUES ($user_id, '$claim_type', $amount, '$description')";

    if (mysqli_query($conn, $query)) {
        $claim_id = mysqli_insert_id($conn);

        $att_result = handleClaimAttachments($conn, $claim_id);
        if ($att_result['failed']) {
            showToast('Claim submitted, but ' . count($att_result['failed']) . ' attachment(s) failed: ' . implode('; ', $att_result['failed']), 'warning');
        } else {
            showToast('Claim submitted successfully!');
        }
        header("Location: claim.php");
        exit();
    }
}

// ========================================
// PAGINATION FOR CLAIM HISTORY
// ========================================
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
if ($per_page < 1) $per_page = 10;
$allowed_statuses = ['pending', 'approved', 'rejected'];
$status_filter = isset($_GET['status']) && in_array($_GET['status'], $allowed_statuses) ? $_GET['status'] : '';

$month = (isset($_GET['month']) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $_GET['month'])) ? $_GET['month'] : '';
$where = "WHERE employee_id = $user_id";
if (!empty($status_filter)) {
    $where .= " AND status = '$status_filter'";
}
$month_sql = '';
if ($month) {
    $m_start = $month . '-01';
    $m_end   = date('Y-m-t', strtotime($m_start));
    $month_sql = " AND applied_at >= '$m_start' AND applied_at <= '$m_end 23:59:59'";
    $where .= $month_sql;
}
$month_summary = ['pending' => [0, 0.0], 'approved' => [0, 0.0], 'rejected' => [0, 0.0]];
if ($month) {
    $msq = mysqli_query($conn, "SELECT status, COUNT(*) n, COALESCE(SUM(amount),0) amt FROM claims WHERE employee_id = $user_id $month_sql GROUP BY status");
    while ($mr = mysqli_fetch_assoc($msq)) { $month_summary[$mr['status']] = [(int)$mr['n'], (float)$mr['amt']]; }
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
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
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
<div class="bg-[#060912] text-white sticky top-0 z-40 shadow-2xl backdrop-blur-sm">
    <div class="flex items-center justify-between px-5 py-4">
        <div class="flex items-center gap-3">
            <button onclick="toggleSidebar()" class="relative group">
                <div class="w-10 h-10 rounded-xl bg-white/10 backdrop-blur-sm flex items-center justify-center group-hover:bg-white/20 transition-all duration-300 group-hover:scale-105">
                    <i class="fas fa-bars text-lg"></i>
                </div>
            </button>
            <div class="relative">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 via-indigo-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-500/20 animate-pulse">
                    <img src="../uploads/1775551018_4xzREYTcMvK7ReGODviudjeDBIofOQ78mr5DsN9g.jpg" alt="IPINFRA" style="width:28px;height:28px;object-fit:contain;border-radius:4px;background:#fff;">
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

<?php require_once '../includes/employee_sidebar.php'; ?>

<!-- MAIN CONTENT -->
<div class="px-4 py-6 max-w-2xl mx-auto">
    
    <div class="text-center mb-6 animate-fadeInUp">
        <h1 class="text-2xl font-bold text-gray-800">Claim Application</h1>
        <p class="text-sm text-gray-500 mt-1">Submit reimbursement claims with multiple receipts (images, PDFs, or ZIP files)</p>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="bg-gradient-to-br from-indigo-600 to-indigo-700 text-white p-4 rounded-2xl shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-indigo-100 opacity-80">Total Approved</p>
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
            <div class="w-10 h-10 bg-gradient-to-br from-indigo-600 to-indigo-700 rounded-xl flex items-center justify-center shadow-md">
                <i class="fas <?php echo $edit_mode ? 'fa-edit' : 'fa-receipt'; ?> text-white"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-gray-800"><?php echo $edit_mode ? 'Edit Claim Request' : 'New Claim Request'; ?></h2>
                <p class="text-xs text-gray-500"><?php echo $edit_mode ? 'Update your claim details' : 'Fill in the claim details below'; ?></p>
            </div>
        </div>
        
        <form method="POST" enctype="multipart/form-data" class="space-y-4" id="claimForm">
            <?php echo csrfField(); ?>
            <?php if ($edit_mode): ?>
                <input type="hidden" name="claim_id" value="<?php echo $edit_claim['id']; ?>">
            <?php endif; ?>
            
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Claim Type</label>
                <select name="claim_type" required class="form-input w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 transition">
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
                    <input type="number" step="0.01" name="amount" required value="<?php echo $edit_mode ? $edit_claim['amount'] : ''; ?>" placeholder="0.00" class="form-input w-full pl-10 pr-4 py-2.5 border border-gray-200 rounded-xl focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 transition">
                </div>
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Description</label>
                <textarea name="description" rows="3" required placeholder="Please describe the claim purpose..." class="form-input w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100 transition"><?php echo $edit_mode ? htmlspecialchars($edit_claim['description']) : ''; ?></textarea>
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
                            <span class="text-sm text-gray-600"><?php echo htmlspecialchars($att['file_name']); ?></span>
                            <span class="text-xs text-gray-400">(<?php echo round($att['file_size'] / 1024, 1); ?> KB)</span>
                        </div>
                        <!-- Bound to the outer #claimForm via the form="" attribute rather than
                             its own nested <form> -- a <form> inside another <form> is invalid
                             HTML and browsers silently truncate the DOM at the first inner
                             </form>, which was cutting off the Update button below it entirely. -->
                        <button type="submit" name="delete_attachment" value="<?php echo $att['id']; ?>" form="claimForm"
                            onclick="return confirm('Delete this attachment?');"
                            class="text-red-500 hover:text-red-700">
                            <i class="fas fa-trash"></i>
                        </button>
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
                    <button type="button" onclick="document.getElementById('fileInput').click()" class="w-full border-2 border-dashed border-gray-300 rounded-xl p-4 text-center hover:border-indigo-400 transition group">
                        <i class="fas fa-cloud-upload-alt text-gray-400 text-3xl group-hover:text-indigo-500 transition"></i>
                        <p class="text-sm text-gray-500 mt-1 group-hover:text-indigo-500 transition">Click to select files</p>
                        <p class="text-xs text-gray-400 mt-1" id="fileNames">No files chosen</p>
                    </button>
                </div>
                <div id="fileList" class="mt-2 space-y-1"></div>
            </div>
            
            <button type="submit" name="<?php echo $edit_mode ? 'update_claim' : 'apply_claim'; ?>" class="w-full bg-gradient-to-r from-indigo-600 to-indigo-700 hover:shadow-xl transition-all transform hover:scale-105 text-white py-3 rounded-xl font-semibold flex items-center justify-center gap-2">
                <i class="fas <?php echo $edit_mode ? 'fa-save' : 'fa-paper-plane'; ?>"></i>
                <?php echo $edit_mode ? ' Update Claim' : ' Submit Claim'; ?>
            </button>
            
            <?php if ($edit_mode): ?>
            <div class="text-center">
                <a href="claim.php" class="text-sm text-gray-500 hover:text-indigo-600 transition">Cancel Edit</a>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Claim History with Pagination & Edit/Delete Options -->
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-gradient-to-r from-gray-50 to-white px-5 py-4 border-b">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div class="flex items-center gap-2">
                    <i class="fas fa-history text-indigo-500 text-xl"></i>
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
                    <input type="month" name="month" value="<?php echo htmlspecialchars($month); ?>" title="Month" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5">
                    <select name="per_page" class="text-sm border border-gray-200 rounded-lg px-3 py-1.5">
                        <option value="5"  <?php echo $per_page == 5  ? 'selected' : ''; ?>>5 / page</option>
                        <option value="10" <?php echo $per_page == 10 ? 'selected' : ''; ?>>10 / page</option>
                        <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25 / page</option>
                        <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50 / page</option>
                        <option value="100" <?php echo $per_page == 100 ? 'selected' : ''; ?>>All</option>
                    </select>
                    <input type="hidden" name="page" value="1">
                    <button type="submit" class="bg-indigo-600 text-white px-3 py-1.5 rounded-lg text-sm">Apply</button>
                    <?php if($status_filter || $month || $per_page != 10): ?>
                        <a href="claim.php" class="bg-gray-200 text-gray-700 px-3 py-1.5 rounded-lg text-sm">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        
        <?php if ($month): ?>
        <div class="px-5 py-3 border-b bg-gray-50">
            <p class="text-xs font-semibold text-gray-600 mb-2"><i class="fas fa-calendar-alt text-indigo-500 mr-1"></i> Submitted in <?php echo date('F Y', strtotime($month . '-01')); ?></p>
            <div class="grid grid-cols-3 gap-2">
                <div class="bg-green-50 rounded-lg p-2"><p class="text-[11px] text-green-700 font-semibold">Approved (<?php echo $month_summary['approved'][0]; ?>)</p><p class="text-sm font-bold text-green-700">RM <?php echo number_format($month_summary['approved'][1], 2); ?></p></div>
                <div class="bg-amber-50 rounded-lg p-2"><p class="text-[11px] text-amber-700 font-semibold">Pending (<?php echo $month_summary['pending'][0]; ?>)</p><p class="text-sm font-bold text-amber-700">RM <?php echo number_format($month_summary['pending'][1], 2); ?></p></div>
                <div class="bg-red-50 rounded-lg p-2"><p class="text-[11px] text-red-700 font-semibold">Rejected (<?php echo $month_summary['rejected'][0]; ?>)</p><p class="text-sm font-bold text-red-700">RM <?php echo number_format($month_summary['rejected'][1], 2); ?></p></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if(mysqli_num_rows($history) > 0): ?>
            <div class="divide-y divide-gray-100">
                <?php while ($row = mysqli_fetch_assoc($history)):
                    $status_class = $row['status'] == 'approved'
                        ? 'bg-green-100 text-green-700'
                        : ($row['status'] == 'rejected'
                            ? 'bg-red-100 text-red-700'
                            : 'bg-amber-100 text-amber-800');
                    $status_icon = $row['status'] == 'approved' ? 'check-circle' : ($row['status'] == 'rejected' ? 'times-circle' : 'clock');
                    $type_icon = $row['claim_type'] == 'travel' ? 'plane' : ($row['claim_type'] == 'meal' ? 'utensils' : ($row['claim_type'] == 'medical' ? 'hospital' : 'file'));
                ?>
                <div class="p-4 hover:bg-gray-50 transition claim-card">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <i class="fas fa-<?php echo $type_icon; ?> text-indigo-500 text-sm"></i>
                                <span class="font-semibold text-gray-800"><?php echo ucfirst($row['claim_type']); ?> Claim</span>
                                <span class="text-xs text-gray-400">• <?php echo date('d M Y', strtotime($row['applied_at'])); ?></span>
                                <?php if($row['attachments_count'] > 0): ?>
                                    <span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">
                                        <i class="fas fa-paperclip mr-1"></i> <?php echo $row['attachments_count']; ?> file(s)
                                    </span>
                                <?php endif; ?>
                            </div>
                            <p class="text-lg font-bold text-indigo-600">RM <?php echo number_format($row['amount'], 2); ?></p>
                            <?php if($row['description']): ?>
                                <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars(substr($row['description'], 0, 80), ENT_QUOTES, 'UTF-8'); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="text-right">
                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold <?php echo $status_class; ?>">
                                <i class="fas fa-<?php echo $status_icon; ?>"></i>
                                <?php echo ucfirst($row['status']); ?>
                            </span>
                            
                            <?php if ($row['status'] == 'pending'): ?>
                                <div class="flex gap-2 mt-2">
                                    <a href="?edit=<?php echo $row['id']; ?>" class="text-blue-600 hover:text-blue-800 text-sm" title="Edit">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this claim? This action cannot be undone.');">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="claim_id" value="<?php echo $row['id']; ?>">
                                        <button type="submit" name="delete_claim" class="text-red-500 hover:text-red-700 text-sm" title="Delete">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
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
                        <a href="?page=1&per_page=<?php echo $per_page; ?>&status=<?php echo $status_filter; ?>&month=<?php echo $month; ?>" class="px-3 py-1 bg-white border rounded-lg text-sm hover:bg-gray-100">First</a>
                        <a href="?page=<?php echo $page-1; ?>&per_page=<?php echo $per_page; ?>&status=<?php echo $status_filter; ?>&month=<?php echo $month; ?>" class="px-3 py-1 bg-white border rounded-lg text-sm hover:bg-gray-100">← Prev</a>
                    <?php endif; ?>
                    
                    <span class="px-3 py-1 bg-indigo-600 text-white rounded-lg text-sm"><?php echo $page; ?></span>
                    
                    <?php if($page < $total_pages): ?>
                        <a href="?page=<?php echo $page+1; ?>&per_page=<?php echo $per_page; ?>&status=<?php echo $status_filter; ?>&month=<?php echo $month; ?>" class="px-3 py-1 bg-white border rounded-lg text-sm hover:bg-gray-100">Next →</a>
                        <a href="?page=<?php echo $total_pages; ?>&per_page=<?php echo $per_page; ?>&status=<?php echo $status_filter; ?>&month=<?php echo $month; ?>" class="px-3 py-1 bg-white border rounded-lg text-sm hover:bg-gray-100">Last</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="py-14 px-6 text-center">
                <div class="flex justify-center mb-6">
                    <svg class="empty-state-svg" width="140" height="140" viewBox="0 0 120 120" fill="none" xmlns="http://www.w3.org/2000/svg" style="filter: drop-shadow(0 8px 24px rgba(79,70,229,0.15));">
                        <!-- Receipt body -->
                        <rect x="22" y="14" width="76" height="88" rx="8" fill="#e0e7ff"/>
                        <rect x="22" y="14" width="76" height="88" rx="8" stroke="#4f46e5" stroke-width="2.5" fill="none"/>
                        <!-- Zigzag bottom tear -->
                        <path d="M22 90 L28 97 L34 90 L40 97 L46 90 L52 97 L58 90 L64 97 L70 90 L76 97 L82 90 L88 97 L94 90 L98 90 L98 102 L22 102 Z" fill="#e0e7ff" stroke="#4f46e5" stroke-width="2" stroke-linejoin="round"/>
                        <!-- Dollar sign circle -->
                        <circle cx="60" cy="42" r="16" fill="#4f46e5" opacity="0.15"/>
                        <circle cx="60" cy="42" r="16" stroke="#4f46e5" stroke-width="2"/>
                        <text x="60" y="48" text-anchor="middle" font-size="18" font-weight="700" fill="#4f46e5" font-family="Inter,sans-serif">$</text>
                        <!-- Lines representing text -->
                        <rect x="36" y="68" width="48" height="4" rx="2" fill="#4f46e5" opacity="0.3"/>
                        <rect x="42" y="77" width="36" height="4" rx="2" fill="#4f46e5" opacity="0.2"/>
                        <!-- Sparkle top-right -->
                        <circle cx="94" cy="18" r="3" fill="#818cf8" opacity="0.7"/>
                        <circle cx="104" cy="28" r="2" fill="#4f46e5" opacity="0.4"/>
                        <circle cx="86" cy="10" r="2" fill="#3730a3" opacity="0.5"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-700 mb-1">No Claims Yet</h3>
                <p class="text-sm text-gray-400 mb-4 max-w-xs mx-auto">
                    <?php if($status_filter): ?>
                        No <strong class="text-indigo-600"><?php echo $status_filter; ?></strong> claims match your filter.
                    <?php else: ?>
                        You haven't submitted any reimbursement claims. Use the form above to get started.
                    <?php endif; ?>
                </p>
                <?php if($status_filter): ?>
                    <a href="claim.php" class="inline-flex items-center gap-2 text-sm font-semibold text-indigo-600 hover:text-indigo-800 border border-indigo-200 hover:border-indigo-400 px-4 py-2 rounded-xl transition">
                        <i class="fas fa-times-circle text-xs"></i> Clear Filter
                    </a>
                <?php else: ?>
                    <p class="text-xs text-gray-400 italic">Tip: Travel, meal, medical and toll claims are all supported.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/employee_bottom_nav.php'; ?>

<script>

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