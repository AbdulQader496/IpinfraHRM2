<?php
require_once '../includes/auth.php';
redirectIfNotLoggedIn();
require_once '../includes/db.php';
require_once '../includes/functions.php';

$user_id = $_SESSION['user_id'];
$message = '';

// Get available leave types from database
$leave_types = mysqli_query($conn, "SELECT * FROM leave_types WHERE status = 'active' ORDER BY leave_name");

if (isset($_POST['apply_leave'])) {
    $leave_type = mysqli_real_escape_string($conn, $_POST['leave_type']);
    $half_day = mysqli_real_escape_string($conn, $_POST['half_day']);
    $start_date = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date = mysqli_real_escape_string($conn, $_POST['end_date']);
    $reason = mysqli_real_escape_string($conn, $_POST['reason']);
    
    // Calculate total days (including half day)
    if ($half_day != 'none') {
        $total_days = 0.5;
        // For half day, end_date same as start_date
        $end_date = $start_date;
    } else {
        $total_days = (strtotime($end_date) - strtotime($start_date)) / 86400 + 1;
    }
    
    $attachment = '';
    // Check if attachment is required for this leave type
    $type_query = mysqli_query($conn, "SELECT requires_attachment FROM leave_types WHERE leave_code = '$leave_type'");
    $type_data = mysqli_fetch_assoc($type_query);
    $requires_attachment = $type_data ? $type_data['requires_attachment'] : 0;
    
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        $target_dir = "../uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $attachment = time() . '_' . basename($_FILES['attachment']['name']);
        move_uploaded_file($_FILES['attachment']['tmp_name'], $target_dir . $attachment);
    } elseif ($requires_attachment) {
        $message = '<div class="bg-red-100 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">✗ This leave type requires an attachment (e.g., Medical Certificate).</div>';
    }
    
    if (empty($message)) {
        $query = "INSERT INTO leaves (employee_id, leave_type, half_day, start_date, end_date, total_days, reason, attachment) 
                  VALUES ($user_id, '$leave_type', '$half_day', '$start_date', '$end_date', $total_days, '$reason', '$attachment')";
        
        if (mysqli_query($conn, $query)) {
            $message = '<div class="bg-green-100 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm">✓ Leave application submitted successfully!</div>';
        } else {
            $message = '<div class="bg-red-100 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">✗ Error submitting leave application.</div>';
        }
    }
}

$history = mysqli_query($conn, "SELECT * FROM leaves WHERE employee_id = $user_id ORDER BY applied_at DESC");
$balance = getLeaveBalance($user_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
<title>Apply Leave - IPINFRA HRM</title>

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

.balance-card { transition: all 0.2s ease; }
.balance-card:hover { transform: translateY(-3px); }

.form-input:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    outline: none;
}

::-webkit-scrollbar { width: 6px; }
::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
</style>
</head>

<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen pb-20">

<!-- HEADER -->
<div class="bg-gradient-to-r from-blue-800 to-blue-900 text-white sticky top-0 z-30 shadow-lg">
    <div class="flex justify-between items-center px-4 py-3">
        <div class="flex items-center gap-2">
            <button onclick="history.back()" class="text-white text-xl">
                <i class="fas fa-arrow-left"></i>
            </button>
            <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
                <span class="text-white font-bold text-sm">IN</span>
            </div>
            <div>
                <p class="text-xs text-blue-200">IPINFRA NETWORKS</p>
                <p class="text-xs font-bold">Employee Portal</p>
            </div>
        </div>
        <button onclick="toggleSidebar()" class="text-white text-2xl">
            <i class="fas fa-bars"></i>
        </button>
    </div>
</div>

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

<!-- MAIN CONTENT -->
<div class="px-4 py-6 max-w-2xl mx-auto">
    
    <!-- Header -->
    <div class="text-center mb-6 animate-fadeInUp">
        <h1 class="text-2xl font-bold text-gray-800">🏖️ Leave Application</h1>
        <p class="text-sm text-gray-500 mt-1">Request time off and track your leave balance</p>
    </div>

    <!-- Premium Balance Cards -->
    <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="balance-card bg-gradient-to-br from-green-500 to-emerald-600 text-white p-5 rounded-2xl shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-green-100 opacity-80">Annual Leave</p>
                    <p class="text-4xl font-bold mt-1"><?php echo $balance['annual_remaining']; ?></p>
                    <p class="text-xs text-green-100 mt-1">Available days</p>
                </div>
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                    <i class="fas fa-umbrella-beach text-2xl"></i>
                </div>
            </div>
            <div class="mt-3">
                <div class="bg-white/20 rounded-full h-1.5">
                    <?php 
                    $total_annual = $balance['annual_leave_entitlement'];
                    $used_annual = $balance['used_annual_leave'];
                    $annual_percent = $total_annual > 0 ? ($used_annual / $total_annual) * 100 : 0;
                    ?>
                    <div class="bg-white rounded-full h-1.5" style="width: <?php echo min($annual_percent, 100); ?>%"></div>
                </div>
                <p class="text-xs text-green-100 mt-1">Used: <?php echo $used_annual; ?> / <?php echo $total_annual; ?> days</p>
            </div>
        </div>
        
        <div class="balance-card bg-gradient-to-br from-blue-500 to-indigo-600 text-white p-5 rounded-2xl shadow-lg">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-blue-100 opacity-80">Medical Leave</p>
                    <p class="text-4xl font-bold mt-1"><?php echo $balance['medical_remaining']; ?></p>
                    <p class="text-xs text-blue-100 mt-1">Available days</p>
                </div>
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                    <i class="fas fa-hospital-user text-2xl"></i>
                </div>
            </div>
            <div class="mt-3">
                <div class="bg-white/20 rounded-full h-1.5">
                    <?php 
                    $total_medical = $balance['medical_leave_entitlement'];
                    $used_medical = $balance['used_medical_leave'];
                    $medical_percent = $total_medical > 0 ? ($used_medical / $total_medical) * 100 : 0;
                    ?>
                    <div class="bg-white rounded-full h-1.5" style="width: <?php echo min($medical_percent, 100); ?>%"></div>
                </div>
                <p class="text-xs text-blue-100 mt-1">Used: <?php echo $used_medical; ?> / <?php echo $total_medical; ?> days</p>
            </div>
        </div>
    </div>

    <!-- Application Form -->
    <div class="bg-white rounded-2xl shadow-xl p-6 mb-6 animate-fadeInUp">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-md">
                <i class="fas fa-pen-alt text-white"></i>
            </div>
            <div>
                <h2 class="text-lg font-bold text-gray-800">New Leave Request</h2>
                <p class="text-xs text-gray-500">Fill in the details below</p>
            </div>
        </div>
        
        <?php echo $message; ?>

        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Leave Type</label>
                <select name="leave_type" id="leave_type" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 transition" onchange="toggleHalfDayOption()">
                    <option value="">Select Leave Type</option>
                    <?php while($type = mysqli_fetch_assoc($leave_types)): ?>
                        <option value="<?php echo $type['leave_code']; ?>" data-requires-attachment="<?php echo $type['requires_attachment']; ?>">
                            <?php echo $type['leave_name']; ?> (<?php echo $type['days_per_year']; ?> days/year)
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
            
            <!-- Half Day Option (visible for certain leave types) -->
            <div id="halfDayContainer" style="display: none;">
                <label class="block text-gray-700 text-sm font-semibold mb-2">Leave Duration</label>
                <select name="half_day" id="half_day" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 transition">
                    <option value="none">Full Day</option>
                    <option value="first_half">Half Day (Morning Session)</option>
                    <option value="second_half">Half Day (Afternoon Session)</option>
                </select>
                <p class="text-xs text-gray-500 mt-1">Half day leave consumes 0.5 day from your balance</p>
            </div>
            
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-gray-700 text-sm font-semibold mb-2">Start Date</label>
                    <input type="date" name="start_date" id="start_date" required class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 transition" onchange="updateEndDate()">
                </div>
                <div id="endDateContainer">
                    <label class="block text-gray-700 text-sm font-semibold mb-2">End Date</label>
                    <input type="date" name="end_date" id="end_date" class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 transition">
                </div>
            </div>
            
            <div>
                <label class="block text-gray-700 text-sm font-semibold mb-2">Reason <span class="text-gray-400 font-normal">(Optional)</span></label>
                <textarea name="reason" rows="3" placeholder="Please provide reason for leave..." class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 transition"></textarea>
            </div>
            
            <div id="attachmentContainer">
                <label class="block text-gray-700 text-sm font-semibold mb-2">Attachment <span class="text-gray-400 font-normal" id="attachmentRequired">(Optional)</span></label>
                <div class="relative">
                    <input type="file" name="attachment" id="fileInput" class="hidden">
                    <button type="button" onclick="document.getElementById('fileInput').click()" class="w-full border-2 border-dashed border-gray-300 rounded-xl p-3 text-center hover:border-blue-500 transition group">
                        <i class="fas fa-cloud-upload-alt text-gray-400 text-2xl group-hover:text-blue-500 transition"></i>
                        <p class="text-sm text-gray-500 mt-1 group-hover:text-blue-500 transition">Click to upload document</p>
                        <p class="text-xs text-gray-400 mt-1" id="fileName">No file chosen</p>
                    </button>
                </div>
            </div>
            
            <button type="submit" name="apply_leave" class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:shadow-xl transition-all transform hover:scale-105 text-white py-3 rounded-xl font-semibold flex items-center justify-center gap-2">
                <i class="fas fa-paper-plane"></i> Submit Leave Application
            </button>
        </form>
    </div>

    <!-- Leave History -->
    <?php if(mysqli_num_rows($history) > 0): ?>
    <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="bg-gradient-to-r from-gray-50 to-white px-5 py-4 border-b">
            <div class="flex items-center gap-2">
                <i class="fas fa-history text-blue-500 text-xl"></i>
                <h3 class="font-semibold text-gray-800">Leave History</h3>
            </div>
        </div>
        <div class="divide-y divide-gray-100">
            <?php while($leave = mysqli_fetch_assoc($history)): 
                $days = $leave['total_days'] ?: ((strtotime($leave['end_date']) - strtotime($leave['start_date'])) / 86400 + 1);
                $status_color = $leave['status'] == 'approved' ? 'green' : ($leave['status'] == 'rejected' ? 'red' : 'yellow');
                $status_icon = $leave['status'] == 'approved' ? 'check-circle' : ($leave['status'] == 'rejected' ? 'times-circle' : 'clock');
                $half_day_text = $leave['half_day'] != 'none' ? ' (' . ($leave['half_day'] == 'first_half' ? 'AM' : 'PM') . ')' : '';
            ?>
            <div class="p-4 hover:bg-gray-50 transition">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <i class="fas fa-<?php echo $leave['leave_type'] == 'annual' ? 'umbrella-beach' : ($leave['leave_type'] == 'medical' ? 'hospital-user' : 'exclamation-triangle'); ?> text-<?php echo $status_color; ?>-500"></i>
                            <span class="font-medium text-gray-800"><?php echo ucfirst($leave['leave_type']); ?> Leave<?php echo $half_day_text; ?></span>
                            <span class="text-xs text-gray-400">• <?php echo $days; ?> day(s)</span>
                        </div>
                        <p class="text-xs text-gray-500">
                            <i class="far fa-calendar-alt mr-1"></i> <?php echo date('d M Y', strtotime($leave['start_date'])); ?> - <?php echo date('d M Y', strtotime($leave['end_date'])); ?>
                        </p>
                        <?php if($leave['reason']): ?>
                            <p class="text-xs text-gray-400 mt-1"><?php echo substr($leave['reason'], 0, 60); ?></p>
                        <?php endif; ?>
                    </div>
                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-<?php echo $status_color; ?>-100 text-<?php echo $status_color; ?>-700">
                        <i class="fas fa-<?php echo $status_icon; ?>"></i>
                        <?php echo ucfirst($leave['status']); ?>
                    </span>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- JS -->
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('-translate-x-full');
    document.getElementById('overlay').classList.toggle('hidden');
}

document.getElementById('fileInput')?.addEventListener('change', function(e) {
    const fileName = e.target.files[0]?.name || 'No file chosen';
    document.getElementById('fileName').textContent = fileName;
});

function toggleHalfDayOption() {
    const leaveType = document.getElementById('leave_type').value;
    const halfDayContainer = document.getElementById('halfDayContainer');
    const endDateContainer = document.getElementById('endDateContainer');
    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    const attachmentRequiredSpan = document.getElementById('attachmentRequired');
    
    // Get selected option
    const select = document.getElementById('leave_type');
    const selectedOption = select.options[select.selectedIndex];
    const requiresAttachment = selectedOption.getAttribute('data-requires-attachment');
    
    // Show/hide half day for certain leave types
    const halfDayTypes = ['annual', 'emergency', 'unpaid'];
    if (halfDayTypes.includes(leaveType)) {
        halfDayContainer.style.display = 'block';
    } else {
        halfDayContainer.style.display = 'none';
        document.getElementById('half_day').value = 'none';
    }
    
    // Update attachment requirement text
    if (requiresAttachment == '1') {
        attachmentRequiredSpan.innerHTML = '(Required)';
        attachmentRequiredSpan.classList.add('text-red-500');
    } else {
        attachmentRequiredSpan.innerHTML = '(Optional)';
        attachmentRequiredSpan.classList.remove('text-red-500');
    }
}

function updateEndDate() {
    const halfDay = document.getElementById('half_day').value;
    const endDateContainer = document.getElementById('endDateContainer');
    const endDateInput = document.getElementById('end_date');
    
    if (halfDay !== 'none') {
        // For half day, end date is same as start date
        endDateContainer.style.display = 'none';
        endDateInput.value = document.getElementById('start_date').value;
        endDateInput.required = false;
    } else {
        endDateContainer.style.display = 'block';
        endDateInput.required = true;
    }
}

// Listen for half day changes
document.getElementById('half_day')?.addEventListener('change', updateEndDate);
</script>

</body>
</html>