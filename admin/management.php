<?php
require_once '../includes/auth.php';
redirectIfNotAdmin();
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (isset($_GET['delete_resignation'])) {
    $id  = intval($_GET['delete_resignation']);
    $res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT employee_id, status FROM employee_resignations WHERE id=$id"));
    if ($res) {
        if ($res['status'] == 'approved')
            mysqli_query($conn, "UPDATE employees SET employment_status='active' WHERE id={$res['employee_id']}");
        mysqli_query($conn, "DELETE FROM employee_resignations WHERE id=$id");
    }
    header('Location: management.php'); exit();
}

if (isset($_GET['delete_termination'])) {
    $id   = intval($_GET['delete_termination']);
    $term = mysqli_fetch_assoc(mysqli_query($conn, "SELECT employee_id FROM terminations WHERE id=$id"));
    if ($term) {
        mysqli_query($conn, "UPDATE employees SET is_terminated=0, termination_id=NULL, employment_status='active', status='active' WHERE id={$term['employee_id']}");
        mysqli_query($conn, "DELETE FROM terminations WHERE id=$id");
    }
    header('Location: management.php'); exit();
}

if (isset($_POST['add_warning'])) {
    $employee_id  = intval($_POST['warn_employee_id']);
    $warning_type = mysqli_real_escape_string($conn, $_POST['warning_type']);
    $subject      = mysqli_real_escape_string($conn, $_POST['subject']);
    $description  = mysqli_real_escape_string($conn, $_POST['description']);
    $issued_date  = mysqli_real_escape_string($conn, $_POST['issued_date']);
    $issued_by    = intval($_SESSION['user_id']);
    mysqli_query($conn, "INSERT INTO employee_warnings (employee_id, warning_type, subject, description, issued_by, issued_date)
        VALUES ($employee_id, '$warning_type', '$subject', '$description', $issued_by, '$issued_date')");
    $type_labels = ['verbal'=>'Verbal Warning','written'=>'Written Warning','final'=>'Final Warning','suspension'=>'Suspension','counselling'=>'Counselling'];
    $label = $type_labels[$_POST['warning_type']] ?? 'Warning';
    addNotification($employee_id, $label . ' Issued', 'You have received a ' . strtolower($label) . ': ' . $_POST['subject']);
    logAction('create', $label . ' issued to employee #' . $employee_id . ': ' . $_POST['subject'], mysqli_insert_id($conn), 'warning');
    header('Location: management.php?tab=warnings'); exit();
}

if (isset($_GET['delete_warning'])) {
    $id   = intval($_GET['delete_warning']);
    $warn = mysqli_fetch_assoc(mysqli_query($conn, "SELECT employee_id, subject FROM employee_warnings WHERE id=$id"));
    mysqli_query($conn, "DELETE FROM employee_warnings WHERE id=$id");
    logAction('delete', 'Warning deleted: ' . ($warn['subject'] ?? '') . ' for employee #' . ($warn['employee_id'] ?? ''), $id, 'warning');
    header('Location: management.php?tab=warnings'); exit();
}

if (isset($_GET['delete_doc'])) {
    $id  = intval($_GET['delete_doc']);
    $doc = mysqli_fetch_assoc(mysqli_query($conn, "SELECT file_path FROM employee_documents WHERE id=$id"));
    if ($doc) {
        foreach (["../uploads/documents/","../uploads/employee_documents/"] as $dir) {
            if (file_exists($dir . $doc['file_path'])) { unlink($dir . $doc['file_path']); break; }
        }
    }
    mysqli_query($conn, "DELETE FROM employee_documents WHERE id=$id");
    header('Location: management.php?tab=documents'); exit();
}

if (isset($_GET['approve_resignation'])) {
    $id          = intval($_GET['approve_resignation']);
    $status      = in_array($_GET['status'] ?? '', ['approved','rejected']) ? $_GET['status'] : 'rejected';
    $admin_notes = mysqli_real_escape_string($conn, $_POST['admin_notes'] ?? '');
    mysqli_query($conn, "UPDATE employee_resignations SET status='$status', admin_notes='$admin_notes', approved_by={$_SESSION['user_id']}, approved_date=CURDATE() WHERE id=$id");
    $res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT employee_id FROM employee_resignations WHERE id=$id"));
    if ($status == 'approved') {
        mysqli_query($conn, "UPDATE employees SET employment_status='resigned' WHERE id={$res['employee_id']}");
        addNotification($res['employee_id'], 'Resignation Approved', 'Your resignation has been approved.');
    } else {
        addNotification($res['employee_id'], 'Resignation Rejected', 'Your resignation was rejected. Reason: ' . $admin_notes);
    }
    header('Location: management.php'); exit();
}

if (isset($_POST['send_termination'])) {
    $employee_id        = intval($_POST['termination_employee_id']);
    $termination_date   = mysqli_real_escape_string($conn, $_POST['termination_date']);
    $effective_date     = mysqli_real_escape_string($conn, $_POST['effective_date']);
    $reason             = mysqli_real_escape_string($conn, $_POST['reason']);
    $termination_type   = mysqli_real_escape_string($conn, $_POST['termination_type']);
    $notice_period_days = intval($_POST['notice_period_days']);
    $severance_pay      = floatval($_POST['severance_pay']);
    $notes              = mysqli_real_escape_string($conn, $_POST['notes']);
    mysqli_query($conn, "INSERT INTO terminations (employee_id,termination_date,effective_date,reason,termination_type,notice_period_days,severance_pay,notes,status,created_by)
        VALUES ($employee_id,'$termination_date','$effective_date','$reason','$termination_type',$notice_period_days,$severance_pay,'$notes','approved',{$_SESSION['user_id']})");
    $term_id = mysqli_insert_id($conn);
    mysqli_query($conn, "UPDATE employees SET is_terminated=1, termination_id=$term_id, employment_status='terminated', status='inactive' WHERE id=$employee_id");
    addNotification($employee_id, 'Employment Termination', 'Your employment has been terminated effective ' . date('d M Y', strtotime($effective_date)));
    header('Location: management.php?tab=terminations'); exit();
}

if (isset($_POST['upload_document'])) {
    $employee_id    = intval($_POST['employee_id']);
    $document_title = mysqli_real_escape_string($conn, $_POST['document_title']);
    $document_type  = mysqli_real_escape_string($conn, $_POST['document_type']);
    $notes          = mysqli_real_escape_string($conn, $_POST['notes']);
    $allowed_ext    = ['jpg','jpeg','png','pdf','doc','docx','xls','xlsx'];
    $allowed_mime   = ['image/jpeg','image/png','application/pdf','application/msword',
                       'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                       'application/vnd.ms-excel','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    $target_dir     = "../uploads/documents/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    $file_name      = basename($_FILES['document_file']['name']);
    $file_size      = $_FILES['document_file']['size'];
    $file_ext       = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $mime           = mime_content_type($_FILES['document_file']['tmp_name']);
    $file_path      = time() . '_' . $employee_id . '.' . $file_ext;
    if (in_array($file_ext, $allowed_ext) && in_array($mime, $allowed_mime)
        && move_uploaded_file($_FILES['document_file']['tmp_name'], $target_dir . $file_path)) {
        mysqli_query($conn, "INSERT INTO employee_documents (employee_id,document_title,document_type,file_path,file_name,file_size,upload_date,notes,uploaded_by)
            VALUES ($employee_id,'$document_title','$document_type','$file_path','$file_name',$file_size,CURDATE(),'$notes',{$_SESSION['user_id']})");
        addNotification($employee_id, 'New Document', '"' . $document_title . '" has been shared with you.');
        header('Location: management.php?tab=upload&msg=' . urlencode('Document uploaded successfully!')); exit();
    } else {
        header('Location: management.php?tab=upload&err=' . urlencode('Upload failed. Check file type and try again.')); exit();
    }
}

// ── Data ──────────────────────────────────────────────────────────────────────
$resignations  = mysqli_query($conn, "SELECT r.*, e.name, e.employee_id AS emp_code, e.department FROM employee_resignations r JOIN employees e ON r.employee_id=e.id ORDER BY r.created_at DESC");
$terminations  = mysqli_query($conn, "SELECT t.*, e.name, e.employee_id AS emp_code, e.department FROM terminations t JOIN employees e ON t.employee_id=e.id LEFT JOIN employees a ON t.created_by=a.id ORDER BY t.created_at DESC");
$documents     = mysqli_query($conn, "SELECT d.*, e.name, e.employee_id AS emp_code, CASE WHEN d.uploaded_by=d.employee_id THEN 'Employee' ELSE 'HR' END AS src FROM employee_documents d JOIN employees e ON d.employee_id=e.id ORDER BY d.created_at DESC");
$active_emps   = mysqli_query($conn, "SELECT id, name, employee_id FROM employees WHERE role='employee' AND status='active' AND (is_terminated=0 OR is_terminated IS NULL) ORDER BY name");

$pending_count   = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM employee_resignations WHERE status='pending'"))['c'] ?? 0);
$total_term      = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM terminations"))['c'] ?? 0);
$total_docs      = (int)(mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM employee_documents"))['c'] ?? 0);

$all_warnings  = [];
try {
    $wr = mysqli_query($conn, "SELECT w.*, e.name, e.employee_id AS emp_code, a.name AS issued_by_name FROM employee_warnings w JOIN employees e ON w.employee_id=e.id LEFT JOIN employees a ON w.issued_by=a.id ORDER BY w.issued_date DESC");
    if ($wr) while ($r = mysqli_fetch_assoc($wr)) $all_warnings[] = $r;
} catch (Exception $e) {}
$warnings_count = count($all_warnings);

// ── Flash messages from redirect ────────────────────────────────────────────
$flash_success = isset($_GET['msg']) ? htmlspecialchars($_GET['msg']) : '';
$flash_error   = isset($_GET['err']) ? htmlspecialchars($_GET['err']) : '';

// For employee dropdowns — convert to array so we can reuse
$emp_list = [];
while ($e = mysqli_fetch_assoc($active_emps)) $emp_list[] = $e;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
<title>Management Portal — IPINFRA HRM</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { font-family: 'Inter', sans-serif; }
.sidebar { transition: transform .3s ease; }
@keyframes fadeUp { from{opacity:0;transform:translateY(14px)} to{opacity:1;transform:translateY(0)} }
.fade-up { animation: fadeUp .35s ease-out both; }
.tab-active   { background:linear-gradient(135deg,#4f46e5,#2563eb); color:#fff; box-shadow:0 4px 14px rgba(79,70,229,.35); }
.tab-inactive { background:#f1f5f9; color:#64748b; }
.tab-inactive:hover { background:#e2e8f0; color:#334155; }
.card { background:#fff; border-radius:1rem; border:1px solid #f1f5f9; box-shadow:0 1px 6px rgba(0,0,0,.05); }
.form-input { width:100%; padding:.625rem 1rem; border:1.5px solid #e2e8f0; border-radius:.75rem; font-size:.875rem; outline:none; transition:border-color .15s; }
.form-input:focus { border-color:#6366f1; }
.btn-primary { display:inline-flex; align-items:center; gap:.4rem; padding:.65rem 1.25rem; border-radius:.75rem; font-size:.875rem; font-weight:600; transition:all .15s; cursor:pointer; }
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
<div class="sticky top-0 z-40 bg-[#060912] text-white shadow-2xl">
    <div class="flex items-center justify-between px-4 py-3.5">
        <div class="flex items-center gap-3">
            <button onclick="toggleSidebar()" class="w-9 h-9 rounded-xl bg-white/10 hover:bg-white/20 flex items-center justify-center transition">
                <i class="fas fa-bars"></i>
            </button>
            <div class="w-9 h-9 bg-gradient-to-br from-blue-400 to-indigo-600 rounded-xl flex items-center justify-center text-sm font-bold shadow-lg"><img src="../uploads/1775551018_4xzREYTcMvK7ReGODviudjeDBIofOQ78mr5DsN9g.jpg" alt="IPINFRA" style="width:28px;height:28px;object-fit:contain;border-radius:4px;background:#fff;"></div>
            <div class="hidden sm:block">
                <p class="text-[10px] text-blue-300 font-medium tracking-widest uppercase">IPINFRA Networks</p>
                <p class="text-sm font-bold leading-tight">Admin Portal</p>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/admin_sidebar.php'; ?>

<!-- Page Content -->
<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">

    <!-- Page Header + Stats -->
    <div class="fade-up">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Management Portal</h1>
                <p class="text-sm text-gray-500 mt-0.5">Resignations, terminations, documents & disciplinary records</p>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <?php
            $stats = [
                ['Pending Resignations', $pending_count,   'fa-user-minus',         'from-amber-400 to-orange-500',  'bg-amber-50 text-amber-700'],
                ['Terminations',         $total_term,      'fa-gavel',              'from-red-400 to-rose-500',      'bg-red-50 text-red-700'],
                ['Warnings Issued',      $warnings_count,  'fa-exclamation-circle', 'from-purple-400 to-indigo-500', 'bg-purple-50 text-purple-700'],
                ['Documents',            $total_docs,      'fa-folder-open',        'from-blue-400 to-cyan-500',     'bg-blue-50 text-blue-700'],
            ];
            foreach ($stats as [$label, $val, $icon, $grad, $chip]):
            ?>
            <div class="card p-4 flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br <?php echo $grad; ?> flex items-center justify-center shadow-md shrink-0">
                    <i class="fas <?php echo $icon; ?> text-white text-base"></i>
                </div>
                <div>
                    <p class="text-2xl font-bold text-gray-800 leading-none"><?php echo $val; ?></p>
                    <p class="text-xs text-gray-500 mt-0.5"><?php echo $label; ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if ($pending_count > 0): ?>
    <div class="fade-up bg-amber-50 border border-amber-200 rounded-2xl px-5 py-3.5 flex items-center gap-3">
        <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center shrink-0"><i class="fas fa-bell text-amber-600 text-sm"></i></div>
        <p class="text-sm font-medium text-amber-800"><?php echo $pending_count; ?> pending resignation request<?php echo $pending_count > 1 ? 's' : ''; ?> awaiting your review.</p>
        <button onclick="showTab('resignations')" class="ml-auto text-xs font-semibold text-amber-700 underline shrink-0">Review now</button>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="fade-up flex gap-2 overflow-x-auto pb-1">
        <?php
        $tabs = [
            ['resignations', 'fa-user-minus',         'Resignations', $pending_count],
            ['terminations', 'fa-gavel',               'Terminations', 0],
            ['documents',    'fa-folder-open',         'Documents',    0],
            ['upload',       'fa-cloud-upload-alt',    'Upload',       0],
            ['warnings',     'fa-exclamation-triangle','Warnings',     $warnings_count],
        ];
        foreach ($tabs as [$key, $icon, $label, $badge]):
        ?>
        <button onclick="showTab('<?php echo $key; ?>')" id="tab-<?php echo $key; ?>"
                class="tab-btn tab-inactive shrink-0 flex items-center gap-2 px-4 py-2.5 rounded-xl text-sm font-semibold transition-all whitespace-nowrap">
            <i class="fas <?php echo $icon; ?> text-[13px]"></i><?php echo $label; ?>
            <?php if ($badge > 0): ?>
                <span class="bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full leading-none"><?php echo $badge; ?></span>
            <?php endif; ?>
        </button>
        <?php endforeach; ?>
    </div>

    <!-- ═══════════════════════════════════════════════
         RESIGNATIONS TAB
    ═══════════════════════════════════════════════ -->
    <div id="pane-resignations" class="tab-pane fade-up space-y-3">
        <?php
        $res_rows = [];
        while ($r = mysqli_fetch_assoc($resignations)) $res_rows[] = $r;
        ?>
        <?php if (empty($res_rows)): ?>
        <div class="card p-12 text-center">
            <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                <i class="fas fa-user-check text-gray-300 text-2xl"></i>
            </div>
            <p class="text-gray-500 font-medium">No resignation requests</p>
            <p class="text-gray-400 text-xs mt-1">Submitted resignations will appear here</p>
        </div>
        <?php else: ?>
        <?php foreach ($res_rows as $row):
            $initials  = strtoupper(substr($row['name'], 0, 1) . (strpos($row['name'],' ') !== false ? substr(strrchr($row['name'],' '),1,1) : ''));
            $s         = $row['status'];
            $sChip     = ['pending'=>'bg-amber-100 text-amber-700','approved'=>'bg-green-100 text-green-700','rejected'=>'bg-red-100 text-red-700','cancelled'=>'bg-gray-100 text-gray-500'];
            $sIcon     = ['pending'=>'fa-clock','approved'=>'fa-check-circle','rejected'=>'fa-times-circle','cancelled'=>'fa-ban'];
        ?>
        <div class="card p-5">
            <div class="flex items-start gap-4 flex-wrap">
                <!-- Avatar -->
                <div class="avatar w-11 h-11 bg-gradient-to-br from-indigo-400 to-purple-500 text-white text-sm"><?php echo $initials; ?></div>
                <!-- Info -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap mb-1">
                        <p class="font-semibold text-gray-900"><?php echo htmlspecialchars($row['name']); ?></p>
                        <span class="text-xs text-gray-400"><?php echo htmlspecialchars($row['emp_code']); ?></span>
                        <?php if ($row['department']): ?><span class="text-xs bg-indigo-50 text-indigo-600 px-2 py-0.5 rounded-full"><?php echo htmlspecialchars($row['department']); ?></span><?php endif; ?>
                    </div>
                    <div class="flex flex-wrap gap-4 text-xs text-gray-500">
                        <span><i class="fas fa-calendar-alt mr-1 text-gray-400"></i>Submitted: <strong><?php echo date('d M Y', strtotime($row['requested_date'])); ?></strong></span>
                        <span><i class="fas fa-calendar-times mr-1 text-red-400"></i>Last day: <strong class="text-red-600"><?php echo date('d M Y', strtotime($row['last_working_date'])); ?></strong></span>
                    </div>
                    <?php if ($row['reason']): ?>
                    <p class="text-xs text-gray-400 mt-2 italic line-clamp-2">"<?php echo htmlspecialchars(substr($row['reason'],0,160)); ?>"</p>
                    <?php endif; ?>
                    <?php if ($row['admin_notes'] && $s !== 'pending'): ?>
                    <p class="text-xs text-gray-500 mt-1"><span class="font-medium">HR note:</span> <?php echo htmlspecialchars(substr($row['admin_notes'],0,100)); ?></p>
                    <?php endif; ?>
                </div>
                <!-- Status + Actions -->
                <div class="flex flex-col items-end gap-2 shrink-0">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold <?php echo $sChip[$s] ?? 'bg-gray-100 text-gray-600'; ?>">
                        <i class="fas <?php echo $sIcon[$s] ?? 'fa-question'; ?> text-[11px]"></i><?php echo ucfirst($s); ?>
                    </span>
                    <div class="flex gap-2">
                        <?php if ($s == 'pending'): ?>
                        <button onclick="openApproveModal(<?php echo htmlspecialchars(json_encode($row)); ?>)"
                                class="btn-primary bg-green-600 hover:bg-green-700 text-white text-xs py-1.5 px-3">
                            <i class="fas fa-check text-[11px]"></i>Approve
                        </button>
                        <button onclick="openRejectModal(<?php echo htmlspecialchars(json_encode($row)); ?>)"
                                class="btn-primary bg-red-500 hover:bg-red-600 text-white text-xs py-1.5 px-3">
                            <i class="fas fa-times text-[11px]"></i>Reject
                        </button>
                        <?php endif; ?>
                        <a href="?delete_resignation=<?php echo $row['id']; ?>"
                           data-confirm="Delete this resignation record?" data-confirm-title="Delete Record"
                           class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-100 hover:bg-red-100 text-gray-400 hover:text-red-500 transition">
                            <i class="fas fa-trash text-xs"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ═══════════════════════════════════════════════
         TERMINATIONS TAB
    ═══════════════════════════════════════════════ -->
    <div id="pane-terminations" class="tab-pane hidden fade-up">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Form -->
            <div class="card p-6">
                <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-red-500 to-rose-600 flex items-center justify-center shadow-md">
                        <i class="fas fa-gavel text-white text-sm"></i>
                    </div>
                    <div>
                        <h2 class="font-bold text-gray-800">Issue Termination Notice</h2>
                        <p class="text-xs text-gray-400">Permanently terminate employee contract</p>
                    </div>
                </div>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Employee</label>
                        <select name="termination_employee_id" required class="form-input">
                            <option value="">Select employee…</option>
                            <?php foreach ($emp_list as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['name']); ?> — <?php echo htmlspecialchars($emp['employee_id']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Termination Type</label>
                        <select name="termination_type" required class="form-input">
                            <option value="mutual">Mutual Agreement</option>
                            <option value="misconduct">Misconduct</option>
                            <option value="poor_performance">Poor Performance</option>
                            <option value="redundancy">Redundancy / Layoff</option>
                            <option value="contract_end">Contract End</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Termination Date</label>
                            <input type="date" name="termination_date" required class="form-input">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Effective Date</label>
                            <input type="date" name="effective_date" required class="form-input">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Notice Period (Days)</label>
                            <input type="number" name="notice_period_days" value="30" class="form-input">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Severance Pay (RM)</label>
                            <input type="number" step="0.01" name="severance_pay" value="0" class="form-input">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Reason <span class="text-red-400">*</span></label>
                        <textarea name="reason" rows="3" required class="form-input resize-none" placeholder="Provide detailed reason…"></textarea>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Additional Notes</label>
                        <textarea name="notes" rows="2" class="form-input resize-none"></textarea>
                    </div>
                    <button type="button" onclick="confirmTermination(this)"
                            class="w-full bg-gradient-to-r from-red-600 to-rose-600 hover:from-red-700 hover:to-rose-700 text-white py-3 rounded-xl font-semibold text-sm shadow-md hover:shadow-lg transition flex items-center justify-center gap-2">
                        <i class="fas fa-paper-plane"></i>Send Termination Notice
                    </button>
                </form>
            </div>
            <!-- History -->
            <div class="card overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                    <i class="fas fa-history text-red-400"></i>
                    <p class="font-semibold text-gray-800">Termination History</p>
                </div>
                <div class="divide-y divide-gray-50 max-h-[520px] overflow-y-auto">
                    <?php
                    $term_rows = [];
                    while ($t = mysqli_fetch_assoc($terminations)) $term_rows[] = $t;
                    if (empty($term_rows)):
                    ?>
                    <div class="p-10 text-center">
                        <i class="fas fa-clipboard-check text-gray-200 text-3xl mb-2 block"></i>
                        <p class="text-gray-400 text-sm">No terminations on record</p>
                    </div>
                    <?php else: foreach ($term_rows as $t):
                        $ti = strtoupper(substr($t['name'],0,1));
                    ?>
                    <div class="p-4 hover:bg-red-50/40 transition">
                        <div class="flex items-start gap-3">
                            <div class="avatar w-9 h-9 bg-gradient-to-br from-red-400 to-rose-500 text-white text-xs"><?php echo $ti; ?></div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <p class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($t['name']); ?></p>
                                    <span class="text-xs text-gray-400"><?php echo htmlspecialchars($t['emp_code']); ?></span>
                                    <span class="ml-auto text-[10px] bg-red-100 text-red-700 px-2 py-0.5 rounded-full font-semibold">Terminated</span>
                                </div>
                                <p class="text-xs text-gray-500 mt-0.5"><?php echo ucfirst(str_replace('_',' ',$t['termination_type'])); ?> · Effective <?php echo date('d M Y', strtotime($t['effective_date'])); ?></p>
                                <?php if ($t['severance_pay'] > 0): ?>
                                <p class="text-xs text-green-600 font-medium mt-0.5">Severance: RM <?php echo number_format($t['severance_pay'],2); ?></p>
                                <?php endif; ?>
                                <p class="text-xs text-gray-400 mt-1 line-clamp-2"><?php echo htmlspecialchars(substr($t['reason'],0,100)); ?></p>
                            </div>
                            <a href="?delete_termination=<?php echo $t['id']; ?>" data-confirm="Remove this termination record and reactivate the employee?" data-confirm-title="Delete Termination"
                               class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-red-100 text-gray-300 hover:text-red-500 transition shrink-0">
                                <i class="fas fa-trash text-xs"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════
         DOCUMENTS TAB
    ═══════════════════════════════════════════════ -->
    <div id="pane-documents" class="tab-pane hidden fade-up">
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <i class="fas fa-folder-open text-blue-500"></i>
                    <p class="font-semibold text-gray-800">All Employee Documents</p>
                </div>
                <button onclick="showTab('upload')" class="text-xs text-indigo-600 font-semibold hover:underline flex items-center gap-1">
                    <i class="fas fa-plus-circle"></i>Upload New
                </button>
            </div>
            <?php
            $doc_rows = [];
            while ($d = mysqli_fetch_assoc($documents)) $doc_rows[] = $d;
            if (empty($doc_rows)):
            ?>
            <div class="p-12 text-center">
                <i class="fas fa-folder-open text-gray-200 text-4xl mb-3 block"></i>
                <p class="text-gray-400 font-medium text-sm">No documents uploaded yet</p>
            </div>
            <?php else: ?>
            <div class="divide-y divide-gray-50">
                <?php foreach ($doc_rows as $d):
                    $fp  = "../uploads/documents/" . $d['file_path'];
                    if (!file_exists($fp)) $fp = "../uploads/employee_documents/" . $d['file_path'];
                    $ext = strtolower(pathinfo($d['file_name'] ?? '', PATHINFO_EXTENSION));
                    $fileIcon  = match(true) { in_array($ext,['doc','docx'])=>'fa-file-word text-blue-500', in_array($ext,['xls','xlsx'])=>'fa-file-excel text-green-500', in_array($ext,['jpg','jpeg','png'])=>'fa-file-image text-pink-500', default=>'fa-file-pdf text-red-500' };
                    $srcColor  = $d['src'] === 'Employee' ? 'bg-green-100 text-green-700' : 'bg-purple-100 text-purple-700';
                    $di        = strtoupper(substr($d['name'],0,1));
                ?>
                <div class="flex items-center gap-4 px-5 py-3.5 hover:bg-slate-50 transition flex-wrap">
                    <div class="w-9 h-9 rounded-xl bg-gray-100 flex items-center justify-center shrink-0">
                        <i class="fas <?php echo $fileIcon; ?> text-base"></i>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-800 text-sm truncate"><?php echo htmlspecialchars($d['document_title']); ?></p>
                        <div class="flex items-center gap-3 mt-0.5 flex-wrap">
                            <span class="text-xs text-gray-400 flex items-center gap-1">
                                <span class="avatar w-4 h-4 bg-indigo-400 text-white text-[8px]"><?php echo $di; ?></span>
                                <?php echo htmlspecialchars($d['name']); ?>
                            </span>
                            <span class="text-xs text-gray-300">·</span>
                            <span class="text-[10px] <?php echo $srcColor; ?> px-2 py-0.5 rounded-full font-semibold"><?php echo $d['src']; ?></span>
                            <span class="text-xs text-gray-400"><?php echo date('d M Y', strtotime($d['upload_date'])); ?></span>
                        </div>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <?php if (file_exists($fp)): ?>
                        <a href="<?php echo htmlspecialchars($fp); ?>" target="_blank" title="View"
                           class="w-8 h-8 flex items-center justify-center rounded-lg bg-blue-50 hover:bg-blue-100 text-blue-500 transition">
                            <i class="fas fa-eye text-xs"></i>
                        </a>
                        <a href="<?php echo htmlspecialchars($fp); ?>" download title="Download"
                           class="w-8 h-8 flex items-center justify-center rounded-lg bg-green-50 hover:bg-green-100 text-green-500 transition">
                            <i class="fas fa-download text-xs"></i>
                        </a>
                        <?php endif; ?>
                        <a href="?delete_doc=<?php echo $d['id']; ?>" data-confirm="Delete this document permanently?" data-confirm-title="Delete Document"
                           class="w-8 h-8 flex items-center justify-center rounded-lg bg-red-50 hover:bg-red-100 text-red-400 transition">
                            <i class="fas fa-trash text-xs"></i>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════
         UPLOAD TAB
    ═══════════════════════════════════════════════ -->
    <div id="pane-upload" class="tab-pane hidden fade-up">
        <div class="max-w-lg mx-auto card p-7">
            <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-md">
                    <i class="fas fa-cloud-upload-alt text-white text-sm"></i>
                </div>
                <div>
                    <h2 class="font-bold text-gray-800">Upload Document to Employee</h2>
                    <p class="text-xs text-gray-400">Share contracts, letters and HR documents</p>
                </div>
            </div>
            <?php if ($flash_success && ($_GET['tab'] ?? '') === 'upload'): ?>
            <div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 mb-4 flex items-center gap-2 text-sm text-green-700 font-medium">
                <i class="fas fa-check-circle"></i><?php echo $flash_success; ?>
            </div>
            <?php endif; ?>
            <?php if ($flash_error && ($_GET['tab'] ?? '') === 'upload'): ?>
            <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 mb-4 flex items-center gap-2 text-sm text-red-600 font-medium">
                <i class="fas fa-exclamation-circle"></i><?php echo $flash_error; ?>
            </div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Employee</label>
                    <select name="employee_id" required class="form-input">
                        <option value="">Select employee…</option>
                        <?php
                        $all_emp_list = mysqli_query($conn, "SELECT id, name, employee_id FROM employees WHERE role='employee' ORDER BY name");
                        while ($e = mysqli_fetch_assoc($all_emp_list)):
                        ?>
                        <option value="<?php echo $e['id']; ?>"><?php echo htmlspecialchars($e['name']); ?> — <?php echo htmlspecialchars($e['employee_id']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Document Type</label>
                    <select name="document_type" required class="form-input">
                        <option value="offer_letter">Offer Letter</option>
                        <option value="contract">Employment Contract</option>
                        <option value="id_copy">IC / Passport Copy</option>
                        <option value="academic_certificate">Academic Certificate</option>
                        <option value="performance_review">Performance Review</option>
                        <option value="disciplinary">Disciplinary Record</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Document Title</label>
                    <input type="text" name="document_title" required class="form-input" placeholder="e.g. Annual Performance Review 2025">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">File</label>
                    <label class="flex flex-col items-center justify-center gap-2 border-2 border-dashed border-gray-200 hover:border-indigo-400 rounded-xl p-5 cursor-pointer transition group" for="docFile">
                        <i class="fas fa-cloud-upload-alt text-3xl text-gray-300 group-hover:text-indigo-400 transition"></i>
                        <span class="text-sm text-gray-400 group-hover:text-indigo-500 transition" id="fileLabel">Click to select file</span>
                        <span class="text-[10px] text-gray-300">PDF, DOC, DOCX, JPG, PNG, XLS — max 10 MB</span>
                    </label>
                    <input type="file" id="docFile" name="document_file" required class="hidden" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.xls,.xlsx" onchange="document.getElementById('fileLabel').textContent=this.files[0]?.name||'Click to select file'">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Notes (Optional)</label>
                    <textarea name="notes" rows="2" class="form-input resize-none"></textarea>
                </div>
                <button type="submit" name="upload_document"
                        class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white py-3 rounded-xl font-semibold text-sm shadow-md hover:shadow-lg transition flex items-center justify-center gap-2">
                    <i class="fas fa-paper-plane"></i>Upload &amp; Notify Employee
                </button>
            </form>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════
         WARNINGS TAB
    ═══════════════════════════════════════════════ -->
    <div id="pane-warnings" class="tab-pane hidden fade-up">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Issue form -->
            <div class="card p-6">
                <div class="flex items-center gap-3 mb-6 pb-4 border-b border-gray-100">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-400 to-amber-500 flex items-center justify-center shadow-md">
                        <i class="fas fa-exclamation-triangle text-white text-sm"></i>
                    </div>
                    <div>
                        <h2 class="font-bold text-gray-800">Issue Warning / Notice</h2>
                        <p class="text-xs text-gray-400">Record disciplinary action or counselling</p>
                    </div>
                </div>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Employee</label>
                        <select name="warn_employee_id" required class="form-input">
                            <option value="">Select employee…</option>
                            <?php foreach ($emp_list as $emp): ?>
                            <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['name']); ?> — <?php echo htmlspecialchars($emp['employee_id']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Warning Type</label>
                        <select name="warning_type" required class="form-input">
                            <option value="verbal">Verbal Warning</option>
                            <option value="written">Written Warning</option>
                            <option value="final">Final Warning</option>
                            <option value="suspension">Suspension</option>
                            <option value="counselling">Counselling</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Subject</label>
                        <input type="text" name="subject" required class="form-input" placeholder="e.g. Repeated late attendance">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Date Issued</label>
                        <input type="date" name="issued_date" required value="<?php echo date('Y-m-d'); ?>" class="form-input">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Description</label>
                        <textarea name="description" rows="3" class="form-input resize-none" placeholder="Describe the incident or reason…"></textarea>
                    </div>
                    <button type="submit" name="add_warning"
                            class="w-full bg-gradient-to-r from-orange-500 to-amber-500 hover:from-orange-600 hover:to-amber-600 text-white py-3 rounded-xl font-semibold text-sm shadow-md hover:shadow-lg transition flex items-center justify-center gap-2">
                        <i class="fas fa-paper-plane"></i>Issue Warning
                    </button>
                </form>
            </div>

            <!-- Warning history -->
            <div class="card overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-history text-orange-400"></i>
                        <p class="font-semibold text-gray-800">Warning Records</p>
                    </div>
                    <span class="text-xs text-gray-400"><?php echo $warnings_count; ?> total</span>
                </div>
                <?php if (empty($all_warnings)): ?>
                <div class="p-10 text-center">
                    <div class="w-14 h-14 bg-green-50 rounded-full flex items-center justify-center mx-auto mb-3">
                        <i class="fas fa-shield-alt text-green-300 text-2xl"></i>
                    </div>
                    <p class="text-gray-400 text-sm font-medium">No warnings on record</p>
                    <p class="text-gray-300 text-xs mt-1">All employees in good standing</p>
                </div>
                <?php else: ?>
                <?php
                $wBadge = [
                    'verbal'      => ['bg-blue-100 text-blue-700',    'Verbal'],
                    'written'     => ['bg-yellow-100 text-yellow-800','Written'],
                    'final'       => ['bg-red-100 text-red-700',      'Final'],
                    'suspension'  => ['bg-purple-100 text-purple-700','Suspension'],
                    'counselling' => ['bg-green-100 text-green-700',  'Counselling'],
                ];
                ?>
                <div class="divide-y divide-gray-50 max-h-[540px] overflow-y-auto">
                    <?php foreach ($all_warnings as $w):
                        $b  = $wBadge[$w['warning_type']] ?? ['bg-gray-100 text-gray-600','Unknown'];
                        $wi = strtoupper(substr($w['name'],0,1));
                    ?>
                    <div class="p-4 hover:bg-orange-50/30 transition">
                        <div class="flex items-start gap-3">
                            <div class="avatar w-9 h-9 bg-gradient-to-br from-orange-300 to-amber-400 text-white text-xs"><?php echo $wi; ?></div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap mb-1">
                                    <p class="font-semibold text-gray-800 text-sm"><?php echo htmlspecialchars($w['name']); ?></p>
                                    <span class="text-xs text-gray-400"><?php echo htmlspecialchars($w['emp_code']); ?></span>
                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full <?php echo $b[0]; ?>"><?php echo $b[1]; ?></span>
                                </div>
                                <p class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($w['subject']); ?></p>
                                <?php if ($w['description']): ?>
                                <p class="text-xs text-gray-400 mt-0.5 line-clamp-2"><?php echo htmlspecialchars(substr($w['description'],0,120)); ?></p>
                                <?php endif; ?>
                                <p class="text-[11px] text-gray-400 mt-1.5"><?php echo date('d M Y', strtotime($w['issued_date'])); ?>
                                <?php if ($w['issued_by_name']): ?> · <?php echo htmlspecialchars($w['issued_by_name']); ?><?php endif; ?></p>
                            </div>
                            <a href="?delete_warning=<?php echo $w['id']; ?>" data-confirm="Delete this warning record permanently?" data-confirm-title="Delete Warning"
                               class="w-7 h-7 flex items-center justify-center rounded-lg hover:bg-red-100 text-gray-300 hover:text-red-500 transition shrink-0">
                                <i class="fas fa-trash text-xs"></i>
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div><!-- /page content -->

<!-- Approve Resignation Modal -->
<div id="approveModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl overflow-hidden">
        <div class="h-1.5 bg-gradient-to-r from-green-400 to-emerald-500"></div>
        <div class="px-6 py-5">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 text-lg"></i>
                </div>
                <div>
                    <h2 class="font-bold text-gray-800">Approve Resignation</h2>
                    <p class="text-xs text-gray-400">Employee will be notified immediately</p>
                </div>
            </div>
            <form method="POST" id="approveForm" class="space-y-4">
                <textarea name="admin_notes" rows="2" class="form-input resize-none" placeholder="Optional notes for the employee…"></textarea>
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 bg-green-600 hover:bg-green-700 text-white py-2.5 rounded-xl font-semibold text-sm transition">Confirm Approve</button>
                    <button type="button" onclick="closeModals()" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 py-2.5 rounded-xl font-semibold text-sm transition">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Resignation Modal -->
<div id="rejectModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl overflow-hidden">
        <div class="h-1.5 bg-gradient-to-r from-red-400 to-rose-500"></div>
        <div class="px-6 py-5">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center">
                    <i class="fas fa-times-circle text-red-500 text-lg"></i>
                </div>
                <div>
                    <h2 class="font-bold text-gray-800">Reject Resignation</h2>
                    <p class="text-xs text-gray-400">Provide a reason for the employee</p>
                </div>
            </div>
            <form method="POST" id="rejectForm" class="space-y-4">
                <textarea name="admin_notes" rows="3" required class="form-input resize-none" placeholder="Reason for rejection (required)…"></textarea>
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 bg-red-500 hover:bg-red-600 text-white py-2.5 rounded-xl font-semibold text-sm transition">Confirm Reject</button>
                    <button type="button" onclick="closeModals()" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 py-2.5 rounded-xl font-semibold text-sm transition">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Mobile Bottom Nav -->
<div class="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-lg border-t border-gray-100 md:hidden shadow-xl z-30">
    <div class="flex justify-around py-2">
        <a href="dashboard.php"  class="flex flex-col items-center py-2 px-3 text-gray-400 hover:text-indigo-600 transition"><i class="fas fa-home text-xl"></i><span class="text-[10px] mt-1">Home</span></a>
        <a href="employees.php"  class="flex flex-col items-center py-2 px-3 text-gray-400 hover:text-indigo-600 transition"><i class="fas fa-users text-xl"></i><span class="text-[10px] mt-1">Staff</span></a>
        <a href="management.php" class="flex flex-col items-center py-2 px-3 text-indigo-600"><i class="fas fa-briefcase text-xl"></i><span class="text-[10px] mt-1 font-semibold">Manage</span></a>
        <a href="payroll.php"    class="flex flex-col items-center py-2 px-3 text-gray-400 hover:text-indigo-600 transition"><i class="fas fa-file-invoice-dollar text-xl"></i><span class="text-[10px] mt-1">Payroll</span></a>
    </div>
</div>

<script>

function showTab(tab) {
    document.querySelectorAll('.tab-btn').forEach(b => b.className = b.className.replace('tab-active','tab-inactive'));
    document.querySelectorAll('.tab-pane').forEach(p => p.classList.add('hidden'));
    const btn  = document.getElementById('tab-' + tab);
    const pane = document.getElementById('pane-' + tab);
    if (btn)  btn.className  = btn.className.replace('tab-inactive','tab-active');
    if (pane) pane.classList.remove('hidden');
}

function confirmTermination(btn) {
    confirmAction('Send Termination Notice',
        'This will permanently mark the employee as <strong>terminated</strong> and deactivate their account. This cannot be undone.',
        function() { btn.closest('form').submit(); });
}

function openApproveModal(r) {
    document.getElementById('approveForm').action = '?approve_resignation=' + r.id + '&status=approved';
    document.getElementById('approveModal').classList.remove('hidden');
}
function openRejectModal(r) {
    document.getElementById('rejectForm').action = '?approve_resignation=' + r.id + '&status=rejected';
    document.getElementById('rejectModal').classList.remove('hidden');
}
function closeModals() {
    document.getElementById('approveModal').classList.add('hidden');
    document.getElementById('rejectModal').classList.add('hidden');
}

// Auto-open tab from URL
(function() {
    const t = new URLSearchParams(location.search).get('tab');
    showTab(t || 'resignations');
})();
</script>
</body>
</html>
