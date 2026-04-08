<?php
require_once '../includes/auth.php';
redirectIfNotLoggedIn();
require_once '../includes/db.php';

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

$query = "SELECT * FROM employees WHERE id = $user_id";
$result = mysqli_query($conn, $query);
$employee = mysqli_fetch_assoc($result);

// Update Profile Info with Picture
if (isset($_POST['update'])) {
    $phone = $_POST['phone'];
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $bank_name = $_POST['bank_name'];
    $bank_account = $_POST['bank_account'];
    
    // Handle profile picture upload
    $profile_pic = $employee['profile_pic'];
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $target_dir = "../uploads/profiles/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_extension = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $profile_pic = time() . '_' . $employee['employee_id'] . '.' . $file_extension;
        move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_dir . $profile_pic);
    }
    
    $update = "UPDATE employees SET phone='$phone', address='$address', bank_name='$bank_name', bank_account='$bank_account', profile_pic='$profile_pic' WHERE id=$user_id";
    if (mysqli_query($conn, $update)) {
        $message = '<div class="bg-green-100 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm">✓ Profile updated successfully!</div>';
        // Refresh employee data
        $result = mysqli_query($conn, $query);
        $employee = mysqli_fetch_assoc($result);
    }
}

// Change Password
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($employee['password'] != $current_password) {
        $error = '<div class="bg-red-100 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">✗ Current password is incorrect!</div>';
    } elseif (strlen($new_password) < 4) {
        $error = '<div class="bg-red-100 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">✗ New password must be at least 4 characters!</div>';
    } elseif ($new_password != $confirm_password) {
        $error = '<div class="bg-red-100 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">✗ New password and confirm password do not match!</div>';
    } else {
        $update_password = "UPDATE employees SET password='$new_password' WHERE id=$user_id";
        if (mysqli_query($conn, $update_password)) {
            $message = '<div class="bg-green-100 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm">✓ Password changed successfully!</div>';
        } else {
            $error = '<div class="bg-red-100 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">✗ Error changing password. Please try again.</div>';
        }
    }
}

$profile_pic_path = "../uploads/profiles/" . $employee['profile_pic'];
$has_profile_pic = !empty($employee['profile_pic']) && file_exists($profile_pic_path);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>My Profile - IPINFRA HRM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .toggle-password {
            cursor: pointer;
            transition: color 0.2s;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
        }
        .toggle-password:hover {
            color: #3b82f6;
        }
        .password-field {
            position: relative;
        }
        .profile-img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .camera-icon {
            position: absolute;
            bottom: 5px;
            right: 5px;
            background: white;
            border-radius: 50%;
            padding: 8px;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            transition: all 0.2s;
        }
        .camera-icon:hover {
            transform: scale(1.1);
            background: #f0f0f0;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen pb-20">

<!-- Mobile Header -->
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
                <p class="text-xs font-bold">My Profile</p>
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

<!-- Main Content -->
<div class="px-4 py-6 pb-24 max-w-lg mx-auto">
    <?php echo $message; ?>
    <?php echo $error; ?>
    
    <!-- Profile Header with Picture -->
    <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-2xl p-6 mb-6 text-white text-center shadow-xl">
        <div class="relative inline-block">
            <?php if($has_profile_pic): ?>
                <img src="<?php echo $profile_pic_path; ?>" class="w-24 h-24 rounded-full object-cover border-4 border-white shadow-lg">
            <?php else: ?>
                <div class="w-24 h-24 rounded-full bg-white/20 flex items-center justify-center mx-auto border-4 border-white shadow-lg">
                    <i class="fas fa-user text-4xl text-white"></i>
                </div>
            <?php endif; ?>
            <label class="camera-icon bg-white rounded-full p-2 cursor-pointer absolute bottom-0 right-0 shadow-md">
                <i class="fas fa-camera text-gray-600 text-sm"></i>
                <input type="file" id="profile_pic_input" class="hidden" accept="image/*" onchange="uploadProfilePic(this)">
            </label>
        </div>
        <h2 class="text-xl font-bold mt-3"><?php echo $employee['name']; ?></h2>
        <p class="text-sm opacity-90"><?php echo $employee['employee_id']; ?></p>
        <p class="text-xs opacity-75 mt-1"><?php echo $employee['department']; ?> • <?php echo $employee['position']; ?></p>
    </div>

    <!-- Employee Details -->
    <div class="bg-white rounded-2xl shadow-xl p-5 mb-6">
        <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-id-card text-blue-600"></i> Personal Information
        </h3>
        <div class="space-y-3">
            <div class="flex justify-between py-2 border-b">
                <span class="text-gray-500 text-sm">IC Number</span>
                <span class="text-gray-800 text-sm font-medium"><?php echo $employee['ic_number']; ?></span>
            </div>
            <div class="flex justify-between py-2 border-b">
                <span class="text-gray-500 text-sm">Email</span>
                <span class="text-gray-800 text-sm font-medium"><?php echo $employee['email']; ?></span>
            </div>
            <div class="flex justify-between py-2 border-b">
                <span class="text-gray-500 text-sm">Join Date</span>
                <span class="text-gray-800 text-sm font-medium"><?php echo date('d F Y', strtotime($employee['join_date'])); ?></span>
            </div>
            <div class="flex justify-between py-2 border-b">
                <span class="text-gray-500 text-sm">Nationality</span>
                <span class="text-gray-800 text-sm font-medium"><?php echo $employee['nationality']; ?></span>
            </div>
            <div class="flex justify-between py-2">
                <span class="text-gray-500 text-sm">Basic Salary</span>
                <span class="text-green-600 text-sm font-bold">RM <?php echo number_format($employee['basic_salary'], 2); ?></span>
            </div>
        </div>
    </div>

    <!-- Change Password Section -->
    <div class="bg-white rounded-2xl shadow-xl p-5 mb-6">
        <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-lock text-yellow-600"></i> Change Password
        </h3>
        <form method="POST" class="space-y-4">
            <div class="password-field">
                <label class="block text-gray-700 text-sm font-medium mb-1">Current Password</label>
                <input type="password" name="current_password" id="current_password" required class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-gray-50 pr-12">
                <i class="fas fa-eye-slash toggle-password text-gray-400" onclick="togglePassword('current_password')"></i>
            </div>
            <div class="password-field">
                <label class="block text-gray-700 text-sm font-medium mb-1">New Password</label>
                <input type="password" name="new_password" id="new_password" required class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-gray-50 pr-12">
                <i class="fas fa-eye-slash toggle-password text-gray-400" onclick="togglePassword('new_password')"></i>
                <p class="text-xs text-gray-400 mt-1">Minimum 4 characters</p>
            </div>
            <div class="password-field">
                <label class="block text-gray-700 text-sm font-medium mb-1">Confirm New Password</label>
                <input type="password" name="confirm_password" id="confirm_password" required class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-gray-50 pr-12">
                <i class="fas fa-eye-slash toggle-password text-gray-400" onclick="togglePassword('confirm_password')"></i>
            </div>
            <button type="submit" name="change_password" class="w-full bg-gradient-to-r from-yellow-500 to-yellow-600 text-white py-3 rounded-xl font-semibold shadow-lg hover:shadow-xl transition">
                <i class="fas fa-key mr-2"></i> Change Password
            </button>
        </form>
    </div>

    <!-- Update Contact & Bank -->
    <div class="bg-white rounded-2xl shadow-xl p-5">
        <h3 class="font-bold text-gray-800 mb-4 flex items-center gap-2">
            <i class="fas fa-edit text-green-600"></i> Update Contact & Bank
        </h3>
        <form method="POST" class="space-y-4" enctype="multipart/form-data">
            <input type="hidden" name="update_profile" value="1">
            <div>
                <label class="block text-gray-700 text-sm font-medium mb-1">Phone Number</label>
                <input type="text" name="phone" value="<?php echo $employee['phone']; ?>" class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-gray-50">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-medium mb-1">Address</label>
                <textarea name="address" rows="3" class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-gray-50"><?php echo $employee['address']; ?></textarea>
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-medium mb-1">Bank Name</label>
                <input type="text" name="bank_name" value="<?php echo $employee['bank_name']; ?>" class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-gray-50">
            </div>
            <div>
                <label class="block text-gray-700 text-sm font-medium mb-1">Bank Account Number</label>
                <input type="text" name="bank_account" value="<?php echo $employee['bank_account']; ?>" class="w-full px-4 py-3 border border-gray-200 rounded-xl bg-gray-50">
            </div>
            <button type="submit" name="update" class="w-full bg-gradient-to-r from-green-600 to-green-700 text-white py-3 rounded-xl font-semibold shadow-lg hover:shadow-xl transition">
                <i class="fas fa-save mr-2"></i> Update Profile
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
        <a href="clock.php" class="flex flex-col items-center py-1 px-3 text-gray-500">
            <i class="fas fa-clock text-xl"></i>
            <span class="text-xs mt-1">Clock</span>
        </a>
        <a href="leave.php" class="flex flex-col items-center py-1 px-3 text-gray-500">
            <i class="fas fa-calendar-alt text-xl"></i>
            <span class="text-xs mt-1">Leave</span>
        </a>
        <a href="profile.php" class="flex flex-col items-center py-1 px-3 text-green-600">
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
    
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        const icon = event.target;
        
        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        } else {
            field.type = 'password';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        }
    }
    
    function uploadProfilePic(input) {
        if (input.files && input.files[0]) {
            const formData = new FormData();
            formData.append('profile_pic', input.files[0]);
            formData.append('update_profile_pic', '1');
            
            fetch('upload_profile_pic.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error uploading profile picture');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error uploading profile picture');
            });
        }
    }
</script>

</body>
</html>