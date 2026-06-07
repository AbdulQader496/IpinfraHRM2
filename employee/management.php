<?php
require_once '../includes/auth.php';
redirectIfNotLoggedIn();
require_once '../includes/db.php';
require_once '../includes/functions.php';

$user_id    = $_SESSION['user_id'];
$valid_tabs = ['documents', 'upload', 'resignation', 'warnings'];
$active_tab = in_array($_GET['tab'] ?? '', $valid_tabs) ? $_GET['tab'] : 'documents';

// ── Resignation submit ──────────────────────────────────────────────────────
if (isset($_POST['submit_resignation'])) {
    $last_working_date = mysqli_real_escape_string($conn, $_POST['last_working_date'] ?? '');
    $reason            = mysqli_real_escape_string($conn, $_POST['reason'] ?? '');
    $check = mysqli_query($conn, "SELECT id FROM employee_resignations WHERE employee_id=$user_id AND status='pending'");
    if (mysqli_num_rows($check) > 0) {
        header('Location: management.php?tab=resignation&err=' . urlencode('You already have a pending resignation request.')); exit();
    }
    mysqli_query($conn, "INSERT INTO employee_resignations (employee_id, requested_date, last_working_date, reason, status)
        VALUES ($user_id, CURDATE(), '$last_working_date', '$reason', 'pending')");
    $admin_q = mysqli_query($conn, "SELECT id FROM employees WHERE role='admin' LIMIT 1");
    if ($admin = mysqli_fetch_assoc($admin_q))
        addNotification($admin['id'], 'New Resignation Request', $_SESSION['user_name'] . ' has submitted a resignation request.');
    header('Location: management.php?tab=resignation&msg=' . urlencode('Resignation request submitted successfully!')); exit();
}

// ── Cancel resignation ──────────────────────────────────────────────────────
if (isset($_GET['cancel_resignation'])) {
    $id = intval($_GET['cancel_resignation']);
    mysqli_query($conn, "UPDATE employee_resignations SET status='cancelled' WHERE id=$id AND employee_id=$user_id");
    header('Location: management.php?tab=resignation&msg=' . urlencode('Resignation request cancelled.')); exit();
}

// ── Document upload ─────────────────────────────────────────────────────────
if (isset($_POST['upload_document'])) {
    $document_title = mysqli_real_escape_string($conn, $_POST['document_title'] ?? '');
    $document_type  = mysqli_real_escape_string($conn, $_POST['document_type']  ?? '');
    $notes          = mysqli_real_escape_string($conn, $_POST['notes']          ?? '');
    $allowed_ext  = ['jpg','jpeg','png','pdf','doc','docx','xls','xlsx'];
    $allowed_mime = ['image/jpeg','image/png','application/pdf','application/msword',
                     'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                     'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    $target_dir = "../uploads/employee_documents/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    $file_name = basename($_FILES['document_file']['name'] ?? '');
    $file_size = intval($_FILES['document_file']['size'] ?? 0);
    $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $mime      = isset($_FILES['document_file']['tmp_name']) ? mime_content_type($_FILES['document_file']['tmp_name']) : '';
    $file_path = time() . '_' . $user_id . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file_name);
    if (!in_array($file_ext, $allowed_ext) || !in_array($mime, $allowed_mime)) {
        header('Location: management.php?tab=upload&err=' . urlencode('Invalid file type. Only PDF, DOC, DOCX, JPG, PNG, XLS, XLSX are allowed.')); exit();
    } elseif ($file_size > 5 * 1024 * 1024) {
        header('Location: management.php?tab=upload&err=' . urlencode('File size exceeds the 5 MB limit.')); exit();
    } elseif (move_uploaded_file($_FILES['document_file']['tmp_name'], $target_dir . $file_path)) {
        mysqli_query($conn, "INSERT INTO employee_documents (employee_id,document_title,document_type,file_path,file_name,file_size,upload_date,notes,uploaded_by)
            VALUES ($user_id,'$document_title','$document_type','$file_path','$file_name',$file_size,CURDATE(),'$notes',$user_id)");
        $admin_q = mysqli_query($conn, "SELECT id FROM employees WHERE role='admin' LIMIT 1");
        if ($admin = mysqli_fetch_assoc($admin_q))
            addNotification($admin['id'], 'New Document Uploaded', $_SESSION['user_name'] . ' uploaded: ' . $_POST['document_title']);
        header('Location: management.php?tab=upload&msg=' . urlencode('Document sent to HR successfully!')); exit();
    } else {
        header('Location: management.php?tab=upload&err=' . urlencode('Upload failed. Please try again.')); exit();
    }
}

// ── Flash messages from redirect ────────────────────────────────────────────
$success    = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
$error      = isset($_GET['err']) ? htmlspecialchars($_GET['err']) : '';
$active_tab = in_array($_GET['tab'] ?? '', $valid_tabs) ? $_GET['tab'] : $active_tab;

// ── Data ────────────────────────────────────────────────────────────────────
$resignation = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT * FROM employee_resignations WHERE employee_id=$user_id ORDER BY created_at DESC LIMIT 1"));

$termination = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT t.*, e.name as created_by_name FROM terminations t
     LEFT JOIN employees e ON t.created_by=e.id
     WHERE t.employee_id=$user_id ORDER BY t.created_at DESC LIMIT 1"));

$documents_result = mysqli_query($conn,
    "SELECT d.* FROM employee_documents d
     WHERE d.employee_id=$user_id AND d.uploaded_by != $user_id
     ORDER BY d.created_at DESC");

$hr_docs = [];
while ($d = mysqli_fetch_assoc($documents_result)) $hr_docs[] = $d;

$my_warnings = [];
try {
    $warn_res = mysqli_query($conn,
        "SELECT w.*, a.name as issued_by_name FROM employee_warnings w
         LEFT JOIN employees a ON w.issued_by=a.id
         WHERE w.employee_id=$user_id ORDER BY w.issued_date DESC");
    if ($warn_res) while ($wr = mysqli_fetch_assoc($warn_res)) $my_warnings[] = $wr;
} catch (Exception $e) {}
$warnings_count = count($my_warnings);

// Employee profile
$me = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name, employee_id, department, position, profile_pic FROM employees WHERE id=$user_id"));
$initials = strtoupper(substr($me['name'],0,1) . (strpos($me['name'],' ')!==false ? substr(strrchr($me['name'],' '),1,1) : ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
<title>My Management — IPINFRA HRM</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { font-family: 'Inter', sans-serif; }
@keyframes fadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
.fade-up { animation: fadeUp .35s ease-out both; }
.tab-active   { background:linear-gradient(135deg,#4f46e5,#2563eb); color:#fff; box-shadow:0 4px 14px rgba(79,70,229,.35); }
.tab-inactive { background:#f1f5f9; color:#64748b; }
.tab-inactive:hover { background:#e2e8f0; color:#334155; }
.card { background:#fff; border-radius:1rem; border:1px solid #f1f5f9; box-shadow:0 1px 6px rgba(0,0,0,.05); }
.form-input { width:100%; padding:.625rem 1rem; border:1.5px solid #e2e8f0; border-radius:.75rem; font-size:.875rem; outline:none; transition:border-color .15s; }
.form-input:focus { border-color:#6366f1; }
.avatar { display:inline-flex; align-items:center; justify-content:center; border-radius:50%; font-weight:700; flex-shrink:0; }
::-webkit-scrollbar { width:5px; height:5px; }
::-webkit-scrollbar-track { background:#f8fafc; }
::-webkit-scrollbar-thumb { background:#cbd5e1; border-radius:10px; }
</style>
</head>
<body class="bg-slate-50 min-h-screen pb-24">

<?php require_once '../includes/global_ui.php'; ?>
<?php require_once '../includes/toast.php'; ?>
<?php require_once '../includes/confirm_modal.php'; ?>

<!-- Sticky Header -->
<div class="sticky top-0 z-40 bg-gradient-to-r from-slate-900 via-indigo-900 to-slate-900 text-white shadow-2xl">
    <div class="flex items-center justify-between px-4 py-3.5">
        <div class="flex items-center gap-3">
            <button onclick="toggleSidebar()" class="w-9 h-9 rounded-xl bg-white/10 hover:bg-white/20 flex items-center justify-center transition">
                <i class="fas fa-bars"></i>
            </button>
            <div class="w-9 h-9 bg-gradient-to-br from-blue-400 to-indigo-600 rounded-xl flex items-center justify-center text-sm font-bold shadow-lg">IN</div>
            <div class="hidden sm:block">
                <p class="text-[10px] text-blue-300 font-medium tracking-widest uppercase">IPINFRA Networks</p>
                <p class="text-sm font-bold leading-tight">Employee Portal</p>
            </div>
        </div>
    </div>
</div>

<!-- Sidebar -->
<div id="sidebar" class="fixed top-0 left-0 h-full w-72 bg-gradient-to-b from-gray-900 to-gray-950 text-white z-50 transform -translate-x-full transition-transform duration-300 shadow-2xl overflow-y-auto">
    <div class="p-6 border-b border-gray-800 relative">
        <div class="flex items-center gap-3">
            <?php if (!empty($me['profile_pic']) && file_exists('../uploads/profile_pics/' . $me['profile_pic'])): ?>
                <img src="../uploads/profile_pics/<?php echo htmlspecialchars($me['profile_pic']); ?>" class="w-11 h-11 rounded-xl object-cover">
            <?php else: ?>
                <div class="avatar w-11 h-11 bg-gradient-to-br from-indigo-500 to-purple-600 text-white text-base"><?php echo $initials; ?></div>
            <?php endif; ?>
            <div>
                <h2 class="font-bold text-sm"><?php echo htmlspecialchars($me['name']); ?></h2>
                <p class="text-xs text-gray-400"><?php echo htmlspecialchars($me['position'] ?? 'Employee'); ?></p>
            </div>
        </div>
        <button onclick="toggleSidebar()" class="absolute top-5 right-4 text-gray-400 hover:text-white"><i class="fas fa-times text-lg"></i></button>
    </div>
    <nav class="p-4 space-y-0.5">
        <?php
        $nav = [
            ['dashboard.php','fa-home','Dashboard'],
            ['leave.php','fa-calendar-check','My Leave'],
            ['claim.php','fa-receipt','My Claims'],
            ['clock.php','fa-fingerprint','Attendance'],
            ['assets.php','fa-boxes','My Assets'],
            ['gallery.php','fa-images','Gallery'],
            ['management.php','fa-briefcase','Management'],
            ['calendar.php','fa-calendar-alt','Calendar'],
            ['profile.php','fa-user-circle','Profile'],
            ['payslip.php','fa-file-invoice-dollar','Payslip'],
        ];
        foreach ($nav as [$href,$icon,$label]):
            $active = basename($href) === 'management.php' ? 'bg-indigo-600/30 text-white' : 'text-gray-300 hover:bg-gray-800/40 hover:text-white';
        ?>
        <a href="<?php echo $href; ?>" class="flex items-center gap-3 py-2.5 px-4 rounded-xl transition text-sm <?php echo $active; ?>">
            <i class="fas <?php echo $icon; ?> w-4 text-center"></i><?php echo $label; ?>
        </a>
        <?php endforeach; ?>
        <div class="border-t border-gray-800 my-3"></div>
        <a href="../logout.php" class="flex items-center gap-3 py-2.5 px-4 rounded-xl bg-red-600/20 text-red-300 hover:bg-red-600/30 transition text-sm"><i class="fas fa-sign-out-alt w-4 text-center"></i>Logout</a>
    </nav>
</div>
<div id="overlay" class="fixed inset-0 bg-black/50 z-40 hidden" onclick="toggleSidebar()"></div>

<!-- Page Content -->
<div class="max-w-4xl mx-auto px-4 py-6 space-y-5">

    <!-- Profile Banner -->
    <div class="fade-up relative overflow-hidden rounded-2xl bg-gradient-to-br from-indigo-600 via-blue-600 to-purple-700 text-white shadow-xl">
        <div class="absolute inset-0 opacity-10" style="background-image:radial-gradient(circle at 80% 20%,#fff 0%,transparent 50%)"></div>
        <div class="relative px-6 py-6 flex items-center gap-5 flex-wrap">
            <?php if (!empty($me['profile_pic']) && file_exists('../uploads/profile_pics/' . $me['profile_pic'])): ?>
                <img src="../uploads/profile_pics/<?php echo htmlspecialchars($me['profile_pic']); ?>" class="w-16 h-16 rounded-2xl object-cover ring-4 ring-white/30 shadow-lg">
            <?php else: ?>
                <div class="avatar w-16 h-16 bg-white/20 backdrop-blur text-white text-2xl ring-4 ring-white/20 rounded-2xl shadow-lg"><?php echo $initials; ?></div>
            <?php endif; ?>
            <div class="flex-1 min-w-0">
                <p class="text-xs font-medium text-blue-200 uppercase tracking-widest mb-0.5">My Management Hub</p>
                <h1 class="text-xl font-bold leading-tight"><?php echo htmlspecialchars($me['name']); ?></h1>
                <div class="flex flex-wrap gap-3 mt-1.5 text-xs text-blue-200">
                    <span><i class="fas fa-id-badge mr-1"></i><?php echo htmlspecialchars($me['employee_id']); ?></span>
                    <?php if ($me['department']): ?><span><i class="fas fa-building mr-1"></i><?php echo htmlspecialchars($me['department']); ?></span><?php endif; ?>
                    <?php if ($me['position']): ?><span><i class="fas fa-briefcase mr-1"></i><?php echo htmlspecialchars($me['position']); ?></span><?php endif; ?>
                </div>
            </div>
            <!-- Quick badges -->
            <div class="flex flex-col gap-1.5 shrink-0 text-right">
                <span class="text-xs font-semibold bg-white/20 backdrop-blur-sm px-3 py-1 rounded-full flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-green-400 inline-block"></span>
                    <?php echo $resignation && $resignation['status']==='pending' ? 'Resignation Pending' : 'Active Employee'; ?>
                </span>
                <?php if ($warnings_count > 0): ?>
                <span class="text-xs font-semibold bg-amber-400/30 backdrop-blur-sm px-3 py-1 rounded-full">
                    <i class="fas fa-exclamation-triangle mr-1 text-amber-300"></i><?php echo $warnings_count; ?> Warning<?php echo $warnings_count>1?'s':''; ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($success): ?>
    <div class="fade-up bg-green-50 border border-green-200 rounded-2xl px-5 py-3.5 flex items-center gap-3">
        <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center shrink-0"><i class="fas fa-check text-green-600 text-sm"></i></div>
        <p class="text-sm font-medium text-green-700"><?php echo htmlspecialchars($success); ?></p>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="fade-up bg-red-50 border border-red-200 rounded-2xl px-5 py-3.5 flex items-center gap-3">
        <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center shrink-0"><i class="fas fa-exclamation text-red-500 text-sm"></i></div>
        <p class="text-sm font-medium text-red-600"><?php echo htmlspecialchars($error); ?></p>
    </div>
    <?php endif; ?>

    <?php if ($termination): ?>
    <div class="fade-up bg-red-50 border border-red-200 rounded-2xl px-5 py-4">
        <div class="flex items-start gap-3">
            <div class="w-9 h-9 rounded-xl bg-red-100 flex items-center justify-center shrink-0"><i class="fas fa-gavel text-red-500"></i></div>
            <div>
                <p class="font-bold text-red-800 text-sm">Employment Termination Notice</p>
                <p class="text-xs text-red-600 mt-0.5">Your employment has been terminated
                    (<?php echo ucfirst(str_replace('_',' ',$termination['termination_type'])); ?>).
                    Effective date: <strong><?php echo date('d M Y', strtotime($termination['effective_date'])); ?></strong>.</p>
                <?php if ($termination['reason']): ?>
                <p class="text-xs text-red-500 mt-1"><?php echo htmlspecialchars($termination['reason']); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="fade-up flex gap-2 overflow-x-auto pb-1">
        <?php
        $tabs = [
            ['documents',   'fa-folder-open',         'HR Documents',  count($hr_docs)],
            ['upload',      'fa-cloud-upload-alt',     'Send to HR',    0],
            ['resignation', 'fa-user-minus',           'Resignation',   0],
            ['warnings',    'fa-exclamation-triangle', 'My Warnings',   $warnings_count],
        ];
        foreach ($tabs as [$key, $icon, $label, $badge]):
        ?>
        <button onclick="showTab('<?php echo $key; ?>')" id="tab-<?php echo $key; ?>"
                class="tab-btn tab-inactive shrink-0 flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition-all whitespace-nowrap">
            <i class="fas <?php echo $icon; ?> text-[13px]"></i><?php echo $label; ?>
            <?php if ($badge > 0): ?>
                <span class="bg-indigo-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none"><?php echo $badge; ?></span>
            <?php endif; ?>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- ═══════════════════════════════════════════════
         DOCUMENTS TAB (HR → Employee)
    ═══════════════════════════════════════════════ -->
    <div id="pane-documents" class="tab-pane fade-up">
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fas fa-folder-open text-indigo-500"></i>
                    <p class="font-semibold text-gray-800">Documents from HR</p>
                </div>
                <span class="text-xs text-gray-400"><?php echo count($hr_docs); ?> file<?php echo count($hr_docs)!=1?'s':''; ?></span>
            </div>
            <?php if (empty($hr_docs)): ?>
            <div class="p-12 text-center">
                <div class="w-16 h-16 bg-indigo-50 rounded-2xl flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-folder-open text-indigo-200 text-3xl"></i>
                </div>
                <p class="text-gray-500 font-medium text-sm">No documents from HR yet</p>
                <p class="text-gray-400 text-xs mt-1">HR will share contracts and letters here</p>
            </div>
            <?php else: ?>
            <div class="divide-y divide-gray-50">
                <?php foreach ($hr_docs as $d):
                    $ext      = strtolower(pathinfo($d['file_name'] ?? '', PATHINFO_EXTENSION));
                    $fileIcon = match(true) {
                        in_array($ext,['doc','docx']) => 'fa-file-word text-blue-500',
                        in_array($ext,['xls','xlsx']) => 'fa-file-excel text-green-500',
                        in_array($ext,['jpg','jpeg','png']) => 'fa-file-image text-pink-500',
                        default => 'fa-file-pdf text-red-500'
                    };
                    $fp1 = "../uploads/documents/" . $d['file_path'];
                    $fp2 = "../uploads/employee_documents/" . $d['file_path'];
                    $fp  = file_exists($fp1) ? $fp1 : (file_exists($fp2) ? $fp2 : null);
                    $typeLabel = ['offer_letter'=>'Offer Letter','contract'=>'Contract','id_copy'=>'IC/Passport','academic_certificate'=>'Certificate','performance_review'=>'Performance','disciplinary'=>'Disciplinary','other'=>'Other'][$d['document_type'] ?? ''] ?? ucfirst($d['document_type'] ?? '');
                ?>
                <div class="flex items-center gap-4 px-5 py-4 hover:bg-slate-50 transition flex-wrap">
                    <div class="w-11 h-11 rounded-xl bg-gray-100 flex items-center justify-center shrink-0 shadow-sm">
                        <i class="fas <?php echo $fileIcon; ?> text-lg"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-gray-800 text-sm truncate"><?php echo htmlspecialchars($d['document_title']); ?></p>
                        <div class="flex flex-wrap items-center gap-2 mt-0.5">
                            <span class="text-[10px] bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full font-semibold"><?php echo $typeLabel; ?></span>
                            <span class="text-xs text-gray-400"><?php echo date('d M Y', strtotime($d['upload_date'])); ?></span>
                        </div>
                        <?php if ($d['notes']): ?>
                        <p class="text-xs text-gray-400 mt-1 italic"><?php echo htmlspecialchars(substr($d['notes'],0,80)); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ($fp): ?>
                    <div class="flex gap-1.5 shrink-0">
                        <a href="<?php echo htmlspecialchars($fp); ?>" target="_blank"
                           class="w-9 h-9 flex items-center justify-center rounded-xl bg-blue-50 hover:bg-blue-100 text-blue-500 transition" title="View">
                            <i class="fas fa-eye text-sm"></i>
                        </a>
                        <a href="<?php echo htmlspecialchars($fp); ?>" download
                           class="w-9 h-9 flex items-center justify-center rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-600 transition" title="Download">
                            <i class="fas fa-download text-sm"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════
         UPLOAD TAB (Employee → HR)
    ═══════════════════════════════════════════════ -->
    <div id="pane-upload" class="tab-pane hidden fade-up">
        <div class="card p-6">
            <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-green-500 to-emerald-600 flex items-center justify-center shadow-md">
                    <i class="fas fa-cloud-upload-alt text-white text-sm"></i>
                </div>
                <div>
                    <h2 class="font-bold text-gray-800">Send Document to HR</h2>
                    <p class="text-xs text-gray-400">Upload files — HR will be notified automatically</p>
                </div>
            </div>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Document Type</label>
                    <select name="document_type" required class="form-input">
                        <option value="id_copy">IC / Passport Copy</option>
                        <option value="academic_certificate">Academic Certificate</option>
                        <option value="medical_certificate">Medical Certificate</option>
                        <option value="bank_statement">Bank Statement</option>
                        <option value="tax_form">Tax Form (EA/BE)</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Document Title</label>
                    <input type="text" name="document_title" required class="form-input" placeholder="e.g. Medical Certificate — 5 Jun 2025">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">File</label>
                    <label class="flex flex-col items-center justify-center gap-2 border-2 border-dashed border-gray-200 hover:border-indigo-400 rounded-xl p-6 cursor-pointer transition group" for="uploadFile">
                        <div class="w-12 h-12 bg-indigo-50 group-hover:bg-indigo-100 rounded-xl flex items-center justify-center transition">
                            <i class="fas fa-cloud-upload-alt text-indigo-400 text-xl group-hover:text-indigo-600 transition"></i>
                        </div>
                        <p class="text-sm text-gray-400 group-hover:text-indigo-500 transition font-medium" id="fileLabel">Click to select or drag file here</p>
                        <p class="text-[11px] text-gray-300">PDF, DOC, DOCX, JPG, PNG, XLS — max 5 MB</p>
                    </label>
                    <input type="file" id="uploadFile" name="document_file" required class="hidden"
                           accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xls,.xlsx"
                           onchange="document.getElementById('fileLabel').textContent=this.files[0]?.name||'Click to select file'">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Notes (Optional)</label>
                    <textarea name="notes" rows="2" class="form-input resize-none" placeholder="Any notes for HR…"></textarea>
                </div>
                <button type="submit" name="upload_document"
                        class="w-full bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white py-3 rounded-xl font-semibold text-sm shadow-md hover:shadow-lg transition flex items-center justify-center gap-2">
                    <i class="fas fa-paper-plane"></i>Send to HR
                </button>
            </form>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════
         RESIGNATION TAB
    ═══════════════════════════════════════════════ -->
    <div id="pane-resignation" class="tab-pane hidden fade-up space-y-4">

        <!-- If terminated, block resignation -->
        <?php if ($termination): ?>
        <div class="card p-8 text-center">
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                <i class="fas fa-ban text-red-400 text-2xl"></i>
            </div>
            <p class="font-bold text-gray-700">Account Terminated</p>
            <p class="text-xs text-gray-400 mt-1">Resignation is not available for terminated accounts.</p>
        </div>

        <!-- Pending resignation card -->
        <?php elseif ($resignation && $resignation['status'] === 'pending'): ?>
        <div class="card overflow-hidden">
            <div class="h-1.5 bg-gradient-to-r from-amber-400 to-orange-400"></div>
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-xl bg-amber-100 flex items-center justify-center shrink-0">
                        <i class="fas fa-clock text-amber-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1 flex-wrap">
                            <p class="font-bold text-gray-800">Resignation Under Review</p>
                            <span class="text-xs bg-amber-100 text-amber-700 px-2.5 py-0.5 rounded-full font-semibold">Pending</span>
                        </div>
                        <p class="text-xs text-gray-500">Submitted on <?php echo date('d M Y', strtotime($resignation['requested_date'])); ?></p>
                        <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                            <div class="bg-gray-50 rounded-xl p-3">
                                <p class="text-xs text-gray-400 mb-0.5">Last Working Day</p>
                                <p class="font-semibold text-gray-800"><?php echo date('d M Y', strtotime($resignation['last_working_date'])); ?></p>
                            </div>
                            <div class="bg-gray-50 rounded-xl p-3">
                                <p class="text-xs text-gray-400 mb-0.5">Status</p>
                                <p class="font-semibold text-amber-700">Awaiting HR</p>
                            </div>
                        </div>
                        <?php if ($resignation['reason']): ?>
                        <div class="mt-3 bg-gray-50 rounded-xl p-3">
                            <p class="text-xs text-gray-400 mb-1">Your reason</p>
                            <p class="text-sm text-gray-600 italic">"<?php echo htmlspecialchars($resignation['reason']); ?>"</p>
                        </div>
                        <?php endif; ?>
                        <div class="mt-4">
                            <a href="?cancel_resignation=<?php echo $resignation['id']; ?>&tab=resignation"
                               data-confirm="Cancel your resignation request? You will remain active." data-confirm-title="Cancel Resignation"
                               class="inline-flex items-center gap-2 px-4 py-2 bg-red-50 hover:bg-red-100 text-red-600 rounded-xl text-sm font-semibold transition">
                                <i class="fas fa-times-circle"></i>Cancel Resignation
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Approved resignation -->
        <?php elseif ($resignation && $resignation['status'] === 'approved'): ?>
        <div class="card overflow-hidden">
            <div class="h-1.5 bg-gradient-to-r from-green-400 to-emerald-500"></div>
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-xl bg-green-100 flex items-center justify-center shrink-0">
                        <i class="fas fa-check-circle text-green-600 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                            <p class="font-bold text-gray-800">Resignation Approved</p>
                            <span class="text-xs bg-green-100 text-green-700 px-2.5 py-0.5 rounded-full font-semibold">Approved</span>
                        </div>
                        <p class="text-xs text-gray-500">Your resignation has been accepted.</p>
                        <div class="mt-3 grid grid-cols-2 gap-3">
                            <div class="bg-gray-50 rounded-xl p-3">
                                <p class="text-xs text-gray-400 mb-0.5">Last Working Day</p>
                                <p class="font-semibold text-gray-800"><?php echo date('d M Y', strtotime($resignation['last_working_date'])); ?></p>
                            </div>
                            <div class="bg-gray-50 rounded-xl p-3">
                                <p class="text-xs text-gray-400 mb-0.5">Approved On</p>
                                <p class="font-semibold text-gray-800"><?php echo $resignation['approved_date'] ? date('d M Y', strtotime($resignation['approved_date'])) : 'N/A'; ?></p>
                            </div>
                        </div>
                        <?php if ($resignation['admin_notes']): ?>
                        <div class="mt-3 bg-blue-50 rounded-xl p-3">
                            <p class="text-xs text-blue-400 mb-0.5">HR Notes</p>
                            <p class="text-sm text-blue-700 italic">"<?php echo htmlspecialchars($resignation['admin_notes']); ?>"</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Rejected -->
        <?php elseif ($resignation && $resignation['status'] === 'rejected'): ?>
        <div class="card overflow-hidden">
            <div class="h-1.5 bg-gradient-to-r from-red-400 to-rose-500"></div>
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-xl bg-red-100 flex items-center justify-center shrink-0">
                        <i class="fas fa-times-circle text-red-500 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center gap-2 flex-wrap mb-1">
                            <p class="font-bold text-gray-800">Resignation Rejected</p>
                            <span class="text-xs bg-red-100 text-red-700 px-2.5 py-0.5 rounded-full font-semibold">Rejected</span>
                        </div>
                        <?php if ($resignation['admin_notes']): ?>
                        <div class="mt-2 bg-red-50 rounded-xl p-3">
                            <p class="text-xs text-red-400 mb-0.5">Reason from HR</p>
                            <p class="text-sm text-red-700 italic">"<?php echo htmlspecialchars($resignation['admin_notes']); ?>"</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <!-- Allow new submission after rejection -->
        <?php endif; ?>

        <!-- Submit form — only if no pending/approved, or after rejection/cancellation -->
        <?php if (!$resignation || in_array($resignation['status'], ['rejected','cancelled'])): ?>
        <div class="card p-6">
            <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-rose-500 to-red-600 flex items-center justify-center shadow-md">
                    <i class="fas fa-user-minus text-white text-sm"></i>
                </div>
                <div>
                    <h2 class="font-bold text-gray-800">Submit Resignation</h2>
                    <p class="text-xs text-gray-400">Your request will be reviewed by HR</p>
                </div>
            </div>
            <div class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 mb-5 text-xs text-amber-700 flex items-start gap-2">
                <i class="fas fa-info-circle mt-0.5 shrink-0"></i>
                <span>Please ensure you have reviewed your employment contract regarding notice period requirements before submitting.</span>
            </div>
            <form method="POST" id="resignForm" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Last Working Day <span class="text-red-400">*</span></label>
                    <input type="date" name="last_working_date" required class="form-input"
                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>">
                    <p class="text-xs text-gray-400 mt-1">Must be at least 1 day in the future</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Reason for Resignation</label>
                    <textarea name="reason" rows="4" class="form-input resize-none" placeholder="Optional — briefly describe your reason…"></textarea>
                </div>
                <button type="button" onclick="submitResignation()"
                        class="w-full bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 text-white py-3 rounded-xl font-semibold text-sm shadow-md hover:shadow-lg transition flex items-center justify-center gap-2">
                    <i class="fas fa-paper-plane"></i>Submit Resignation Request
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <!-- ═══════════════════════════════════════════════
         WARNINGS TAB
    ═══════════════════════════════════════════════ -->
    <div id="pane-warnings" class="tab-pane hidden fade-up">
        <?php if (empty($my_warnings)): ?>
        <div class="card p-12 text-center">
            <div class="w-20 h-20 bg-green-50 rounded-full flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-shield-alt text-green-300 text-3xl"></i>
            </div>
            <p class="font-bold text-gray-700">Excellent Standing</p>
            <p class="text-sm text-gray-400 mt-1">You have no disciplinary records. Keep it up!</p>
        </div>
        <?php else: ?>
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fas fa-exclamation-triangle text-orange-400"></i>
                    <p class="font-semibold text-gray-800">My Disciplinary Records</p>
                </div>
                <span class="text-xs text-gray-400"><?php echo $warnings_count; ?> record<?php echo $warnings_count!=1?'s':''; ?></span>
            </div>
            <div class="divide-y divide-gray-50">
                <?php
                $wBadge = [
                    'verbal'      => ['bg-blue-100 text-blue-700',    'Verbal Warning',    'fa-comment'],
                    'written'     => ['bg-yellow-100 text-yellow-800','Written Warning',   'fa-file-alt'],
                    'final'       => ['bg-red-100 text-red-700',      'Final Warning',     'fa-exclamation-circle'],
                    'suspension'  => ['bg-purple-100 text-purple-700','Suspension',        'fa-user-slash'],
                    'counselling' => ['bg-green-100 text-green-700',  'Counselling',       'fa-hands-helping'],
                ];
                foreach ($my_warnings as $w):
                    $b = $wBadge[$w['warning_type']] ?? ['bg-gray-100 text-gray-600','Notice','fa-bell'];
                ?>
                <div class="p-5 hover:bg-orange-50/20 transition">
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-xl bg-gray-100 flex items-center justify-center shrink-0">
                            <i class="fas <?php echo $b[2]; ?> text-gray-500 text-sm"></i>
                        </div>
                        <div class="flex-1">
                            <div class="flex items-center gap-2 flex-wrap mb-1">
                                <span class="text-[11px] font-bold px-2.5 py-0.5 rounded-full <?php echo $b[0]; ?>"><?php echo $b[1]; ?></span>
                                <span class="text-xs text-gray-400"><?php echo date('d M Y', strtotime($w['issued_date'])); ?></span>
                            </div>
                            <p class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($w['subject']); ?></p>
                            <?php if ($w['description']): ?>
                            <p class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($w['description']); ?></p>
                            <?php endif; ?>
                            <?php if ($w['issued_by_name']): ?>
                            <p class="text-[11px] text-gray-400 mt-1.5 flex items-center gap-1">
                                <i class="fas fa-user-tie text-gray-300"></i>
                                Issued by <?php echo htmlspecialchars($w['issued_by_name']); ?>
                            </p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /page content -->

<!-- Mobile Bottom Nav -->
<div class="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-lg border-t border-gray-100 md:hidden shadow-xl z-30">
    <div class="flex justify-around py-2">
        <a href="dashboard.php"  class="flex flex-col items-center py-2 px-3 text-gray-400 hover:text-indigo-600 transition"><i class="fas fa-home text-xl"></i><span class="text-[10px] mt-1">Home</span></a>
        <a href="leave.php"      class="flex flex-col items-center py-2 px-3 text-gray-400 hover:text-indigo-600 transition"><i class="fas fa-calendar-check text-xl"></i><span class="text-[10px] mt-1">Leave</span></a>
        <a href="management.php" class="flex flex-col items-center py-2 px-3 text-indigo-600"><i class="fas fa-briefcase text-xl"></i><span class="text-[10px] mt-1 font-semibold">Manage</span></a>
        <a href="profile.php"    class="flex flex-col items-center py-2 px-3 text-gray-400 hover:text-indigo-600 transition"><i class="fas fa-user-circle text-xl"></i><span class="text-[10px] mt-1">Profile</span></a>
    </div>
</div>

<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('-translate-x-full');
    document.getElementById('overlay').classList.toggle('hidden');
}

function showTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(b => b.className = b.className.replace('tab-active','tab-inactive'));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.add('hidden'));
    const btn  = document.getElementById('tab-' + tab);
    const pane = document.getElementById('pane-' + tab);
    if (btn)  btn.className  = btn.className.replace('tab-inactive','tab-active');
    if (pane) pane.classList.remove('hidden');
}

function submitResignation() {
    const date = document.querySelector('input[name="last_working_date"]').value;
    if (!date) { alert('Please select your last working date.'); return; }
    confirmAction('Submit Resignation Request',
        'Once submitted, HR will review your request. You can cancel it while it is still <strong>pending</strong>.',
        function() { document.getElementById('resignForm').submit(); });
}

// Auto-open tab
(function() {
    showTab('<?php echo $active_tab; ?>');
})();
</script>
</body>
</html>
