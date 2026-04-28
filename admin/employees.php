<?php
require_once '../includes/auth.php';
redirectIfNotAdmin();
require_once '../includes/db.php';

// Handle Add Employee
if (isset($_POST['add_employee'])) {
    $employee_id = mysqli_real_escape_string($conn, $_POST['employee_id']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $ic_number = mysqli_real_escape_string($conn, $_POST['ic_number']);
    $passport_no = mysqli_real_escape_string($conn, $_POST['passport_no']);
    $nationality = mysqli_real_escape_string($conn, $_POST['nationality']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $position = mysqli_real_escape_string($conn, $_POST['position']);
    $basic_salary = floatval($_POST['basic_salary']);
    $join_date = mysqli_real_escape_string($conn, $_POST['join_date']);
    
    // NEW FIELDS
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $bank_name = mysqli_real_escape_string($conn, $_POST['bank_name']);
    $bank_account = mysqli_real_escape_string($conn, $_POST['bank_account']);
    
    // Handle profile picture upload
    $profile_pic = '';
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $target_dir = "../uploads/profiles/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_extension = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $profile_pic = time() . '_' . $employee_id . '.' . $file_extension;
        move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_dir . $profile_pic);
    }
    
    $is_subject = ($nationality == 'Malaysian') ? 1 : 0;
    
    // UPDATED INSERT QUERY with new fields
    $query = "INSERT INTO employees (employee_id, name, ic_number, passport_no, nationality, email, password, department, position, basic_salary, join_date, profile_pic, is_subject_to_statutory, phone, address, bank_name, bank_account) 
              VALUES ('$employee_id', '$name', '$ic_number', '$passport_no', '$nationality', '$email', '$password', '$department', '$position', '$basic_salary', '$join_date', '$profile_pic', '$is_subject', '$phone', '$address', '$bank_name', '$bank_account')";
    mysqli_query($conn, $query);
    header('Location: employees.php');
    exit();
}

// Handle Update Employee
if (isset($_POST['update_employee'])) {
    $id = intval($_POST['emp_id']);
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $ic_number = mysqli_real_escape_string($conn, $_POST['ic_number']);
    $passport_no = mysqli_real_escape_string($conn, $_POST['passport_no']);
    $nationality = mysqli_real_escape_string($conn, $_POST['nationality']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $department = mysqli_real_escape_string($conn, $_POST['department']);
    $position = mysqli_real_escape_string($conn, $_POST['position']);
    $basic_salary = floatval($_POST['basic_salary']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $bank_name = mysqli_real_escape_string($conn, $_POST['bank_name']);
    $bank_account = mysqli_real_escape_string($conn, $_POST['bank_account']);
    $annual_leave = intval($_POST['annual_leave_entitlement']);
    $medical_leave = intval($_POST['medical_leave_entitlement']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $join_date = mysqli_real_escape_string($conn, $_POST['join_date']);
    
    // Handle profile picture upload
    $profile_pic = $_POST['existing_profile_pic'];
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $target_dir = "../uploads/profiles/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $file_extension = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
        $profile_pic = time() . '_' . $_POST['employee_id'] . '.' . $file_extension;
        move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_dir . $profile_pic);
    }
    
    $is_subject = ($nationality == 'Malaysian') ? 1 : 0;
    
    $query = "UPDATE employees SET 
                name='$name', 
                ic_number='$ic_number',
                passport_no='$passport_no',
                nationality='$nationality',
                email='$email', 
                department='$department', 
                position='$position', 
                basic_salary='$basic_salary',
                phone='$phone',
                bank_name='$bank_name',
                bank_account='$bank_account',
                annual_leave_entitlement='$annual_leave',
                medical_leave_entitlement='$medical_leave',
                status='$status',
                join_date='$join_date',
                profile_pic='$profile_pic',
                is_subject_to_statutory='$is_subject'
              WHERE id=$id";
    mysqli_query($conn, $query);
    header('Location: employees.php');
    exit();
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM employees WHERE id = $id");
    header('Location: employees.php');
    exit();
}

// Get employees with pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Search functionality
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$search_condition = $search ? "AND (name LIKE '%$search%' OR employee_id LIKE '%$search%' OR email LIKE '%$search%')" : '';

$total_query = "SELECT COUNT(*) as total FROM employees WHERE role='employee' $search_condition";
$total_result = mysqli_query($conn, $total_query);
$total_rows = mysqli_fetch_assoc($total_result)['total'];
$total_pages = ceil($total_rows / $limit);

$employees = mysqli_query($conn, "SELECT * FROM employees WHERE role='employee' $search_condition ORDER BY id DESC LIMIT $limit OFFSET $offset");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Employee Management - IPINFRA HRM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .sidebar { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #888; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #555; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fadeInUp { animation: fadeInUp 0.5s ease-out; }
        .hover-scale { transition: transform 0.2s ease; }
        .hover-scale:hover { transform: translateY(-2px); }
        .status-badge { transition: all 0.2s ease; }
        @media (max-width: 768px) {
            .container-padding { padding-left: 1rem; padding-right: 1rem; }
        }
        .profile-img {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        /* View Profile Modal Styles */
        .view-profile-modal {
            max-height: 90vh;
            overflow-y: auto;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .info-label {
            font-weight: 600;
            color: #4b5563;
        }
        .info-value {
            color: #1f2937;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 min-h-screen">

<!-- Mobile Header -->
<div class="bg-gradient-to-r from-slate-900 via-indigo-900 to-slate-900 text-white sticky top-0 z-40 shadow-2xl">
    <div class="flex justify-between items-center px-4 py-4">
        <div class="flex items-center gap-3">
            <!-- MENU BUTTON - Left side -->
            <button onclick="toggleSidebar()" class="text-white/80 hover:text-white transition-all p-2 rounded-full hover:bg-white/10">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg">
                <span class="text-white font-bold text-sm">IN</span>
            </div>
            <div>
                <p class="text-xs text-blue-200 font-medium">IPINFRA NETWORKS</p>
                <p class="text-sm font-bold tracking-wide">Employee Directory</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <!-- SEARCH BUTTON - Right side -->
            <button onclick="toggleSearch()" class="text-white/80 hover:text-white p-2 rounded-full hover:bg-white/10">
                <i class="fas fa-search text-lg"></i>
            </button>
        </div>
    </div>
    
    <!-- Search Bar -->
    <div id="searchBar" class="hidden px-4 pb-4">
        <form method="GET" class="relative">
            <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                   placeholder="Search by name, ID, or email..." 
                   class="w-full pl-12 pr-4 py-3 rounded-xl bg-white/10 border border-white/20 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-blue-400">
            <button type="submit" class="absolute right-2 top-1/2 transform -translate-y-1/2 bg-blue-600 text-white px-4 py-1.5 rounded-lg text-sm hover:bg-blue-700 transition">
                Search
            </button>
        </form>
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
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8 animate-fadeInUp">
            <div class="bg-white rounded-2xl p-4 shadow-lg hover:shadow-xl transition-all duration-300">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-users text-blue-600 text-lg"></i>
                    </div>
                    <span class="text-xs text-gray-400">Total</span>
                </div>
                <h3 class="text-2xl font-bold text-gray-800"><?php echo $total_rows; ?></h3>
                <p class="text-xs text-gray-500 mt-1">Active Employees</p>
            </div>
            
            <div class="bg-white rounded-2xl p-4 shadow-lg hover:shadow-xl transition-all duration-300">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-user-check text-green-600 text-lg"></i>
                    </div>
                    <span class="text-xs text-gray-400">Active</span>
                </div>
                <h3 class="text-2xl font-bold text-gray-800">
                    <?php 
                    $active_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM employees WHERE role='employee' AND status='active'");
                    $active = mysqli_fetch_assoc($active_query);
                    echo $active['count'];
                    ?>
                </h3>
                <p class="text-xs text-gray-500 mt-1">Currently Working</p>
            </div>
            
            <div class="bg-white rounded-2xl p-4 shadow-lg hover:shadow-xl transition-all duration-300">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-10 h-10 bg-orange-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-globe-asia text-orange-600 text-lg"></i>
                    </div>
                    <span class="text-xs text-gray-400">Expat</span>
                </div>
                <h3 class="text-2xl font-bold text-gray-800">
                    <?php 
                    $expat_query = mysqli_query($conn, "SELECT COUNT(*) as count FROM employees WHERE role='employee' AND nationality != 'Malaysian'");
                    $expat = mysqli_fetch_assoc($expat_query);
                    echo $expat['count'];
                    ?>
                </h3>
                <p class="text-xs text-gray-500 mt-1">Foreign Nationals</p>
            </div>
            
            <div class="bg-white rounded-2xl p-4 shadow-lg hover:shadow-xl transition-all duration-300">
                <div class="flex items-center justify-between mb-2">
                    <div class="w-10 h-10 bg-purple-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-chart-line text-purple-600 text-lg"></i>
                    </div>
                    <span class="text-xs text-gray-400">Departments</span>
                </div>
                <h3 class="text-2xl font-bold text-gray-800">
                    <?php 
                    $dept_query = mysqli_query($conn, "SELECT COUNT(DISTINCT department) as count FROM employees WHERE role='employee'");
                    $dept = mysqli_fetch_assoc($dept_query);
                    echo $dept['count'];
                    ?>
                </h3>
                <p class="text-xs text-gray-500 mt-1">Different Teams</p>
            </div>
        </div>
        
        <!-- Action Bar -->
        <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mb-6 animate-fadeInUp">
            <div class="flex gap-3 w-full sm:w-auto">
                <button onclick="document.getElementById('addModal').classList.remove('hidden')" 
                        class="bg-gradient-to-r from-blue-600 to-indigo-600 text-white px-6 py-3 rounded-xl shadow-md hover:shadow-xl transition-all transform hover:scale-105 flex items-center gap-2">
                    <i class="fas fa-plus"></i>
                    <span class="font-semibold">Add New Employee</span>
                </button>
                <button onclick="exportEmployees()" class="bg-white text-gray-700 px-4 py-3 rounded-xl shadow-md hover:shadow-lg transition border border-gray-200">
                    <i class="fas fa-download"></i>
                    <span class="hidden sm:inline ml-1">Export</span>
                </button>
            </div>
            
            <!-- View Toggle -->
            <div class="flex bg-white rounded-xl shadow-md p-1">
                <button onclick="setView('grid')" id="gridViewBtn" class="px-4 py-2 rounded-lg transition text-gray-600 hover:bg-gray-100">
                    <i class="fas fa-th-large"></i>
                </button>
                <button onclick="setView('list')" id="listViewBtn" class="px-4 py-2 rounded-lg transition bg-blue-600 text-white">
                    <i class="fas fa-list"></i>
                </button>
            </div>
        </div>
        
        <!-- Grid View -->
        <div id="gridView" class="hidden grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 animate-fadeInUp">
            <?php 
            mysqli_data_seek($employees, 0);
            while ($row = mysqli_fetch_assoc($employees)): 
                $profile_pic_path = "../uploads/profiles/" . $row['profile_pic'];
                $has_profile = !empty($row['profile_pic']) && file_exists($profile_pic_path);
            ?>
            <div class="bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1">
                <div class="bg-gradient-to-r from-gray-800 to-gray-900 p-4 text-white">
                    <div class="flex justify-between items-start">
                        <div class="flex items-center gap-3">
                            <?php if($has_profile): ?>
                                <img src="<?php echo $profile_pic_path; ?>" class="w-12 h-12 rounded-full object-cover border-2 border-white/30">
                            <?php else: ?>
                                <div class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center">
                                    <i class="fas fa-user text-xl"></i>
                                </div>
                            <?php endif; ?>
                            <div>
                                <h3 class="font-bold"><?php echo htmlspecialchars($row['name']); ?></h3>
                                <p class="text-xs text-gray-300"><?php echo htmlspecialchars($row['employee_id']); ?></p>
                            </div>
                        </div>
                        <span class="status-badge px-2 py-1 rounded-full text-xs font-semibold <?php echo $row['status'] == 'active' ? 'bg-green-500' : 'bg-red-500'; ?>">
                            <?php echo ucfirst($row['status']); ?>
                        </span>
                    </div>
                </div>
                <div class="p-4 space-y-3">
                    <div class="flex items-center gap-2 text-sm">
                        <i class="fas fa-calendar-alt text-gray-400 w-5"></i>
                        <span class="text-gray-700">Joined: <?php echo date('d M Y', strtotime($row['join_date'])); ?></span>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <i class="fas fa-building text-gray-400 w-5"></i>
                        <span class="text-gray-700"><?php echo htmlspecialchars($row['department']); ?></span>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <i class="fas fa-briefcase text-gray-400 w-5"></i>
                        <span class="text-gray-700"><?php echo htmlspecialchars($row['position']); ?></span>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <i class="fas fa-envelope text-gray-400 w-5"></i>
                        <span class="text-gray-600 text-xs truncate"><?php echo htmlspecialchars($row['email']); ?></span>
                    </div>
                    <div class="flex items-center gap-2 text-sm">
                        <i class="fas fa-money-bill-wave text-green-500 w-5"></i>
                        <span class="text-green-600 font-bold">RM <?php echo number_format($row['basic_salary'], 2); ?></span>
                    </div>
                    <div class="flex gap-2 pt-3">
                        <button onclick='openViewModal(<?php echo json_encode($row); ?>)' 
                                class="flex-1 bg-purple-50 text-purple-600 py-2 rounded-lg hover:bg-purple-100 transition text-sm font-medium">
                            <i class="fas fa-eye mr-1"></i> View
                        </button>
                        <button onclick='openEditModal(<?php echo json_encode($row); ?>)' 
                                class="flex-1 bg-blue-50 text-blue-600 py-2 rounded-lg hover:bg-blue-100 transition text-sm font-medium">
                            <i class="fas fa-edit mr-1"></i> Edit
                        </button>
                        <a href="?delete=<?php echo $row['id']; ?>" onclick="return confirm('Delete employee?')" 
                           class="flex-1 bg-red-50 text-red-600 py-2 rounded-lg hover:bg-red-100 transition text-sm font-medium text-center">
                            <i class="fas fa-trash mr-1"></i> Del
                        </a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        
        <!-- List View -->
        <div id="listView" class="bg-white rounded-2xl shadow-xl overflow-hidden animate-fadeInUp">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Employee</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase hidden sm:table-cell">ID</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase hidden md:table-cell">Join Date</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase hidden lg:table-cell">Department</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase hidden xl:table-cell">Position</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Salary</th>
                            <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php 
                        mysqli_data_seek($employees, 0);
                        while ($row = mysqli_fetch_assoc($employees)): 
                            $profile_pic_path = "../uploads/profiles/" . $row['profile_pic'];
                            $has_profile = !empty($row['profile_pic']) && file_exists($profile_pic_path);
                        ?>
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <?php if($has_profile): ?>
                                        <img src="<?php echo $profile_pic_path; ?>" class="w-10 h-10 rounded-full object-cover">
                                    <?php else: ?>
                                        <div class="w-10 h-10 rounded-full <?php echo $row['nationality'] == 'Malaysian' ? 'bg-blue-100' : 'bg-orange-100'; ?> flex items-center justify-center">
                                            <i class="fas fa-user <?php echo $row['nationality'] == 'Malaysian' ? 'text-blue-600' : 'text-orange-600'; ?>"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <p class="font-semibold text-gray-800"><?php echo htmlspecialchars($row['name']); ?></p>
                                        <div class="flex items-center gap-1 mt-1">
                                            <?php if($row['nationality'] != 'Malaysian'): ?>
                                                <span class="text-xs bg-orange-100 text-orange-700 px-2 py-0.5 rounded-full">Expat</span>
                                            <?php else: ?>
                                                <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">Local</span>
                                            <?php endif; ?>
                                            <span class="text-xs bg-<?php echo $row['status'] == 'active' ? 'green' : 'red'; ?>-100 text-<?php echo $row['status'] == 'active' ? 'green' : 'red'; ?>-700 px-2 py-0.5 rounded-full">
                                                <?php echo ucfirst($row['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 hidden sm:table-cell"><?php echo htmlspecialchars($row['employee_id']); ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600 hidden md:table-cell"><?php echo date('d/m/Y', strtotime($row['join_date'])); ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600 hidden lg:table-cell"><?php echo htmlspecialchars($row['department']); ?></td>
                            <td class="px-4 py-3 text-sm text-gray-600 hidden xl:table-cell"><?php echo htmlspecialchars($row['position']); ?></td>
                            <td class="px-4 py-3 text-right font-bold text-green-600">RM <?php echo number_format($row['basic_salary'], 2); ?></td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex gap-2 justify-center">
                                    <button onclick='openViewModal(<?php echo json_encode($row); ?>)' class="text-purple-600 hover:text-purple-800 transition" title="View Profile">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button onclick='openEditModal(<?php echo json_encode($row); ?>)' class="text-blue-600 hover:text-blue-800 transition" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?delete=<?php echo $row['id']; ?>" onclick="return confirm('Delete employee?')" class="text-red-500 hover:text-red-700 transition" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <div class="flex justify-center gap-2 mt-8">
            <?php for($i = 1; $i <= $total_pages; $i++): ?>
                <a href="?page=<?php echo $i; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?>" 
                   class="px-4 py-2 rounded-lg <?php echo $i == $page ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-100'; ?> transition shadow-md">
                    <?php echo $i; ?>
                </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- View Profile Modal -->
    <div id="viewModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl max-w-2xl w-full modal-enter shadow-2xl">
            <div class="sticky top-0 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-t-2xl p-5 text-white">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-xl font-bold">Employee Profile</h2>
                        <p class="text-xs text-blue-100 mt-1">Complete employee information</p>
                    </div>
                    <button onclick="closeViewModal()" class="w-10 h-10 rounded-full bg-white/20 hover:bg-white/30 transition-all flex items-center justify-center">
                        <i class="fas fa-times text-white text-xl"></i>
                    </button>
                </div>
            </div>
            <div class="p-6 view-profile-modal">
                <!-- Profile Header -->
                <div class="text-center mb-6">
                    <div id="view_profile_img" class="w-28 h-28 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center mx-auto shadow-lg">
                        <i class="fas fa-user text-white text-4xl"></i>
                    </div>
                    <h3 id="view_name" class="text-xl font-bold text-gray-800 mt-3"></h3>
                    <p id="view_employee_id" class="text-sm text-gray-500"></p>
                </div>
                
                <!-- Personal Information -->
                <div class="mb-6">
                    <h4 class="font-semibold text-gray-800 border-b pb-2 mb-3 flex items-center gap-2">
                        <i class="fas fa-user-circle text-blue-600"></i> Personal Information
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="info-row">
                            <span class="info-label">Full Name:</span>
                            <span id="view_fullname" class="info-value"></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Employee ID:</span>
                            <span id="view_empid" class="info-value"></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Nationality:</span>
                            <span id="view_nationality" class="info-value"></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">IC Number:</span>
                            <span id="view_ic" class="info-value"></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Passport No:</span>
                            <span id="view_passport" class="info-value"></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email:</span>
                            <span id="view_email" class="info-value"></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Phone:</span>
                            <span id="view_phone" class="info-value"></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Join Date:</span>
                            <span id="view_join_date" class="info-value"></span>
                        </div>
                    </div>
                </div>
                
                <!-- Employment Information -->
                <div class="mb-6">
                    <h4 class="font-semibold text-gray-800 border-b pb-2 mb-3 flex items-center gap-2">
                        <i class="fas fa-briefcase text-green-600"></i> Employment Information
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="info-row">
                            <span class="info-label">Department:</span>
                            <span id="view_department" class="info-value"></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Position:</span>
                            <span id="view_position" class="info-value"></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Basic Salary:</span>
                            <span id="view_salary" class="info-value text-green-600 font-bold"></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Status:</span>
                            <span id="view_status" class="info-value"></span>
                        </div>
                    </div>
                </div>
                
                <!-- Leave Entitlement -->
                <div class="mb-6">
                    <h4 class="font-semibold text-gray-800 border-b pb-2 mb-3 flex items-center gap-2">
                        <i class="fas fa-calendar-alt text-purple-600"></i> Leave Entitlement
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="info-row">
                            <span class="info-label">Annual Leave:</span>
                            <span id="view_annual_leave" class="info-value"></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Medical Leave:</span>
                            <span id="view_medical_leave" class="info-value"></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Used Annual:</span>
                            <span id="view_used_annual" class="info-value"></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Used Medical:</span>
                            <span id="view_used_medical" class="info-value"></span>
                        </div>
                    </div>
                </div>
                
                <!-- Bank Information -->
                <div class="mb-6">
                    <h4 class="font-semibold text-gray-800 border-b pb-2 mb-3 flex items-center gap-2">
                        <i class="fas fa-university text-orange-600"></i> Bank Information
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div class="info-row">
                            <span class="info-label">Bank Name:</span>
                            <span id="view_bank_name" class="info-value"></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Account Number:</span>
                            <span id="view_bank_account" class="info-value"></span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="p-5 border-t bg-gray-50 rounded-b-2xl flex justify-end">
                <button onclick="closeViewModal()" class="bg-gray-600 text-white px-6 py-2 rounded-xl hover:bg-gray-700 transition">
                    Close
                </button>
            </div>
        </div>
    </div>

<!-- Add Employee Modal - COMPLETE with all fields -->
<div id="addModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 overflow-y-auto">
    <div class="bg-white rounded-2xl max-w-md w-full modal-enter shadow-2xl">
        <div class="sticky top-0 bg-white rounded-t-2xl p-5 border-b border-gray-100 flex justify-between items-center">
            <div>
                <h2 class="text-xl font-bold text-gray-800">Add New Employee</h2>
                <p class="text-xs text-gray-500 mt-1">Fill in the employee details below</p>
            </div>
            <button onclick="document.getElementById('addModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 transition p-2 rounded-full hover:bg-gray-100">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form method="POST" class="p-5 space-y-4 max-h-[70vh] overflow-y-auto" enctype="multipart/form-data">
            <!-- Profile Picture -->
            <div class="text-center mb-4">
                <div class="relative inline-block">
                    <div id="add_profile_preview" class="w-24 h-24 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center mx-auto shadow-lg">
                        <i class="fas fa-camera text-white text-2xl"></i>
                    </div>
                    <label class="absolute bottom-0 right-0 bg-blue-600 text-white p-1.5 rounded-full cursor-pointer hover:bg-blue-700 transition">
                        <i class="fas fa-upload text-xs"></i>
                        <input type="file" name="profile_pic" id="add_profile_pic" class="hidden" accept="image/*" onchange="previewImage(this, 'add_profile_preview')">
                    </label>
                </div>
                <p class="text-xs text-gray-500 mt-2">Profile Picture (Optional)</p>
            </div>
            
            <!-- Basic Information -->
            <div class="grid grid-cols-2 gap-3">
                <input type="text" name="employee_id" placeholder="Employee ID" required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500">
                <input type="text" name="name" placeholder="Full Name" required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            
            <!-- Nationality -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Nationality</label>
                <select name="nationality" id="add_nationality" required class="w-full px-4 py-3 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="toggleStatutoryFields('add')">
                    <option value="Malaysian">Malaysian (Subject to EPF/SOCSO/EIS)</option>
                    <option value="Non-Malaysian">Non-Malaysian / Expat</option>
                </select>
            </div>
            
            <!-- IC / Passport -->
            <div id="add_ic_field">
                <input type="text" name="ic_number" placeholder="IC Number" class="w-full px-4 py-3 border border-gray-200 rounded-xl">
            </div>
            <div id="add_passport_field" style="display:none;">
                <input type="text" name="passport_no" placeholder="Passport Number" class="w-full px-4 py-3 border border-gray-200 rounded-xl">
            </div>
            
            <!-- Contact Information -->
            <div class="border-t border-gray-100 pt-4">
                <h3 class="font-semibold text-gray-700 mb-3">Contact Information</h3>
                <input type="email" name="email" placeholder="Email Address" required class="w-full px-4 py-3 border border-gray-200 rounded-xl mb-2">
                <input type="text" name="phone" placeholder="Phone Number" class="w-full px-4 py-3 border border-gray-200 rounded-xl mb-2">
                <textarea name="address" rows="2" placeholder="Address" class="w-full px-4 py-3 border border-gray-200 rounded-xl"></textarea>
            </div>
            
            <!-- Job Information -->
            <div class="border-t border-gray-100 pt-4">
                <h3 class="font-semibold text-gray-700 mb-3">Job Information</h3>
                <div class="grid grid-cols-2 gap-3">
                    <input type="text" name="department" placeholder="Department" required class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                    <input type="text" name="position" placeholder="Position" required class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                </div>
            </div>
            
            <!-- Salary & Joining -->
            <div class="border-t border-gray-100 pt-4">
                <h3 class="font-semibold text-gray-700 mb-3">Salary & Employment</h3>
                <div class="grid grid-cols-2 gap-3">
                    <input type="number" step="0.01" name="basic_salary" placeholder="Basic Salary (RM)" required class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                    <input type="date" name="join_date" required class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                </div>
                <p class="text-xs text-gray-500 mt-1">📅 Date of Joining: The date employee started working at the company</p>
            </div>
            
            <!-- Bank Information -->
            <div class="border-t border-gray-100 pt-4">
                <h3 class="font-semibold text-gray-700 mb-3">Bank Information</h3>
                <input type="text" name="bank_name" placeholder="Bank Name" class="w-full px-4 py-3 border border-gray-200 rounded-xl mb-2">
                <input type="text" name="bank_account" placeholder="Bank Account Number" class="w-full px-4 py-3 border border-gray-200 rounded-xl">
            </div>
            
            <!-- Login Information -->
            <div class="border-t border-gray-100 pt-4">
                <h3 class="font-semibold text-gray-700 mb-3">Login Information</h3>
                <input type="text" name="password" placeholder="Password" value="password123" required class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                <p class="text-xs text-gray-500 mt-1">Default password: password123 (employee can change after login)</p>
            </div>
            
            <!-- Statutory Note -->
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-xl" id="add_statutory_note">
                <div class="flex items-start gap-2">
                    <i class="fas fa-info-circle text-blue-600 mt-0.5"></i>
                    <p class="text-xs text-blue-800">Malaysian employees: EPF (11%), SOCSO (0.5%), EIS (0.2%) will be deducted automatically.</p>
                </div>
            </div>
            
            <!-- Buttons -->
            <div class="flex gap-3 pt-3">
                <button type="submit" name="add_employee" class="flex-1 bg-gradient-to-r from-blue-600 to-indigo-600 text-white py-3 rounded-xl font-semibold hover:shadow-lg transition transform hover:scale-105">
                    <i class="fas fa-save mr-2"></i> Save Employee
                </button>
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="flex-1 bg-gray-100 text-gray-700 py-3 rounded-xl font-semibold hover:bg-gray-200 transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>
    <!-- Edit Employee Modal -->
    <div id="editModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl max-w-md w-full modal-enter shadow-2xl">
            <div class="sticky top-0 bg-white rounded-t-2xl p-5 border-b border-gray-100 flex justify-between items-center">
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Edit Employee</h2>
                    <p class="text-xs text-gray-500 mt-1">Update employee information</p>
                </div>
                <button onclick="document.getElementById('editModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 transition p-2 rounded-full hover:bg-gray-100">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form method="POST" class="p-5 space-y-4 max-h-[70vh] overflow-y-auto" enctype="multipart/form-data" id="editForm">
                <input type="hidden" name="emp_id" id="edit_id">
                <input type="hidden" name="existing_profile_pic" id="edit_existing_profile_pic">
                <input type="hidden" name="employee_id" id="edit_employee_id">
                
                <div class="text-center mb-4">
                    <div class="relative inline-block">
                        <div id="edit_profile_preview" class="w-24 h-24 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center mx-auto shadow-lg">
                            <i class="fas fa-user text-white text-3xl"></i>
                        </div>
                        <label class="absolute bottom-0 right-0 bg-blue-600 text-white p-1.5 rounded-full cursor-pointer hover:bg-blue-700 transition">
                            <i class="fas fa-upload text-xs"></i>
                            <input type="file" name="profile_pic" id="edit_profile_pic" class="hidden" accept="image/*" onchange="previewImage(this, 'edit_profile_preview')">
                        </label>
                    </div>
                    <p class="text-xs text-gray-500 mt-2">Click to change profile picture</p>
                </div>
                
                <input type="text" name="name" id="edit_name" placeholder="Full Name" required class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Nationality</label>
                    <select name="nationality" id="edit_nationality" required class="w-full px-4 py-3 border border-gray-200 rounded-xl" onchange="toggleStatutoryFields('edit')">
                        <option value="Malaysian">Malaysian (Subject to EPF/SOCSO/EIS)</option>
                        <option value="Non-Malaysian">Non-Malaysian / Expat</option>
                    </select>
                </div>
                
                <div id="edit_ic_field">
                    <input type="text" name="ic_number" id="edit_ic" placeholder="IC Number" class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                </div>
                <div id="edit_passport_field" style="display:none;">
                    <input type="text" name="passport_no" id="edit_passport" placeholder="Passport Number" class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                </div>
                
                <input type="email" name="email" id="edit_email" placeholder="Email" required class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                
                <div class="grid grid-cols-2 gap-3">
                    <input type="text" name="department" id="edit_department" placeholder="Department" required class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                    <input type="text" name="position" id="edit_position" placeholder="Position" required class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                </div>
                
                <div class="grid grid-cols-2 gap-3">
                    <input type="number" step="0.01" name="basic_salary" id="edit_salary" placeholder="Basic Salary (RM)" required class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                    <input type="date" name="join_date" id="edit_join_date" required class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                </div>
                
                <div class="border-t border-gray-100 pt-4">
                    <h3 class="font-semibold text-gray-700 mb-3">Contact Information</h3>
                    <input type="text" name="phone" id="edit_phone" placeholder="Phone Number" class="w-full px-4 py-3 border border-gray-200 rounded-xl mb-2">
                    <input type="text" name="bank_name" id="edit_bank" placeholder="Bank Name" class="w-full px-4 py-3 border border-gray-200 rounded-xl mb-2">
                    <input type="text" name="bank_account" id="edit_account" placeholder="Bank Account Number" class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                </div>
                
                <div class="border-t border-gray-100 pt-4">
                    <h3 class="font-semibold text-gray-700 mb-3">Leave Entitlement</h3>
                    <div class="grid grid-cols-2 gap-3">
                        <input type="number" name="annual_leave_entitlement" id="edit_annual_leave" placeholder="Annual Leave" class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                        <input type="number" name="medical_leave_entitlement" id="edit_medical_leave" placeholder="Medical Leave" class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                    </div>
                </div>
                
                <div class="border-t border-gray-100 pt-4">
                    <h3 class="font-semibold text-gray-700 mb-3">Status</h3>
                    <select name="status" id="edit_status" class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                
                <div class="bg-gradient-to-r from-blue-50 to-indigo-50 p-4 rounded-xl" id="edit_statutory_note">
                    <div class="flex items-start gap-2">
                        <i class="fas fa-info-circle text-blue-600 mt-0.5"></i>
                        <p class="text-xs text-blue-800">Malaysian employees: EPF (11%), SOCSO (0.5%), EIS (0.2%) will be deducted automatically.</p>
                    </div>
                </div>
                
                <div class="flex gap-3 pt-3">
                    <button type="submit" name="update_employee" class="flex-1 bg-gradient-to-r from-green-600 to-emerald-600 text-white py-3 rounded-xl font-semibold hover:shadow-lg transition transform hover:scale-105">
                        <i class="fas fa-save mr-2"></i> Update Employee
                    </button>
                    <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="flex-1 bg-gray-100 text-gray-700 py-3 rounded-xl font-semibold hover:bg-gray-200 transition">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <div class="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-lg border-t border-gray-200 md:hidden shadow-2xl z-30">
        <div class="flex justify-around py-2">
            <a href="dashboard.php" class="flex flex-col items-center py-2 px-4 text-gray-500 hover:text-blue-600 transition group">
                <i class="fas fa-home text-xl group-hover:scale-110 transition"></i>
                <span class="text-xs mt-1">Home</span>
            </a>
            <a href="employees.php" class="flex flex-col items-center py-2 px-4 text-blue-600 relative">
                <i class="fas fa-users text-xl"></i>
                <span class="text-xs mt-1 font-semibold">Staff</span>
                <div class="absolute -top-1 right-1 w-2 h-2 bg-blue-600 rounded-full"></div>
            </a>
            <a href="manage_leave.php" class="flex flex-col items-center py-2 px-4 text-gray-500 hover:text-blue-600 transition group">
                <i class="fas fa-calendar-alt text-xl group-hover:scale-110 transition"></i>
                <span class="text-xs mt-1">Leaves</span>
            </a>
            <a href="payroll.php" class="flex flex-col items-center py-2 px-4 text-gray-500 hover:text-blue-600 transition group">
                <i class="fas fa-file-invoice-dollar text-xl group-hover:scale-110 transition"></i>
                <span class="text-xs mt-1">Payroll</span>
            </a>
        </div>
    </div>

    <script>
        let currentView = localStorage.getItem('employeeView') || 'list';
        
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('overlay').classList.toggle('hidden');
        }
        
        function toggleSearch() {
            document.getElementById('searchBar').classList.toggle('hidden');
        }
        
        function previewImage(input, previewId) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById(previewId);
                    preview.style.backgroundImage = `url(${e.target.result})`;
                    preview.style.backgroundSize = 'cover';
                    preview.style.backgroundPosition = 'center';
                    preview.innerHTML = '';
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function toggleStatutoryFields(type) {
            const nationality = document.getElementById(`${type}_nationality`).value;
            const icField = document.getElementById(`${type}_ic_field`);
            const passportField = document.getElementById(`${type}_passport_field`);
            const statutoryNote = document.getElementById(`${type}_statutory_note`);
            
            if (nationality === 'Malaysian') {
                icField.style.display = 'block';
                passportField.style.display = 'none';
                if (statutoryNote) {
                    statutoryNote.innerHTML = '<div class="flex items-start gap-2"><i class="fas fa-info-circle text-blue-600 mt-0.5"></i><p class="text-xs text-blue-800">Malaysian employees: EPF (11%), SOCSO (0.5%), EIS (0.2%) will be deducted automatically.</p></div>';
                }
            } else {
                icField.style.display = 'none';
                passportField.style.display = 'block';
                if (statutoryNote) {
                    statutoryNote.innerHTML = '<div class="flex items-start gap-2"><i class="fas fa-info-circle text-blue-600 mt-0.5"></i><p class="text-xs text-blue-800">Non-Malaysian employees: No EPF, SOCSO, or EIS deductions. Only salary paid.</p></div>';
                }
            }
        }
        
        function setView(view) {
            currentView = view;
            localStorage.setItem('employeeView', view);
            
            const gridView = document.getElementById('gridView');
            const listView = document.getElementById('listView');
            const gridBtn = document.getElementById('gridViewBtn');
            const listBtn = document.getElementById('listViewBtn');
            
            if (view === 'grid') {
                gridView.classList.remove('hidden');
                listView.classList.add('hidden');
                gridBtn.classList.add('bg-blue-600', 'text-white');
                gridBtn.classList.remove('text-gray-600', 'hover:bg-gray-100');
                listBtn.classList.remove('bg-blue-600', 'text-white');
                listBtn.classList.add('text-gray-600', 'hover:bg-gray-100');
            } else {
                gridView.classList.add('hidden');
                listView.classList.remove('hidden');
                listBtn.classList.add('bg-blue-600', 'text-white');
                listBtn.classList.remove('text-gray-600', 'hover:bg-gray-100');
                gridBtn.classList.remove('bg-blue-600', 'text-white');
                gridBtn.classList.add('text-gray-600', 'hover:bg-gray-100');
            }
        }
        
        // View Profile Modal Functions
        function openViewModal(employee) {
            // Set profile image
            const profilePic = employee.profile_pic ? `../uploads/profiles/${employee.profile_pic}` : null;
            const previewDiv = document.getElementById('view_profile_img');
            
            if (profilePic) {
                previewDiv.style.backgroundImage = `url(${profilePic})`;
                previewDiv.style.backgroundSize = 'cover';
                previewDiv.style.backgroundPosition = 'center';
                previewDiv.innerHTML = '';
            } else {
                previewDiv.style.backgroundImage = '';
                previewDiv.innerHTML = '<i class="fas fa-user text-white text-4xl"></i>';
            }
            
            // Set personal info
            document.getElementById('view_name').innerHTML = employee.name || '-';
            document.getElementById('view_employee_id').innerHTML = employee.employee_id || '-';
            document.getElementById('view_fullname').innerHTML = employee.name || '-';
            document.getElementById('view_empid').innerHTML = employee.employee_id || '-';
            document.getElementById('view_nationality').innerHTML = employee.nationality || '-';
            document.getElementById('view_ic').innerHTML = employee.ic_number || '-';
            document.getElementById('view_passport').innerHTML = employee.passport_no || '-';
            document.getElementById('view_email').innerHTML = employee.email || '-';
            document.getElementById('view_phone').innerHTML = employee.phone || '-';
            document.getElementById('view_join_date').innerHTML = employee.join_date ? new Date(employee.join_date).toLocaleDateString('en-MY') : '-';
            
            // Set employment info
            document.getElementById('view_department').innerHTML = employee.department || '-';
            document.getElementById('view_position').innerHTML = employee.position || '-';
            document.getElementById('view_salary').innerHTML = `RM ${parseFloat(employee.basic_salary).toLocaleString('en-MY', {minimumFractionDigits: 2})}`;
            
            const statusHtml = employee.status === 'active' 
                ? '<span class="text-green-600"><i class="fas fa-check-circle mr-1"></i> Active</span>' 
                : '<span class="text-red-600"><i class="fas fa-times-circle mr-1"></i> Inactive</span>';
            document.getElementById('view_status').innerHTML = statusHtml;
            
            // Set leave entitlement
            document.getElementById('view_annual_leave').innerHTML = employee.annual_leave_entitlement || 0;
            document.getElementById('view_medical_leave').innerHTML = employee.medical_leave_entitlement || 0;
            document.getElementById('view_used_annual').innerHTML = employee.used_annual_leave || 0;
            document.getElementById('view_used_medical').innerHTML = employee.used_medical_leave || 0;
            
            // Set bank info
            document.getElementById('view_bank_name').innerHTML = employee.bank_name || '-';
            document.getElementById('view_bank_account').innerHTML = employee.bank_account || '-';
            
            document.getElementById('viewModal').classList.remove('hidden');
        }
        
        function closeViewModal() {
            document.getElementById('viewModal').classList.add('hidden');
        }
        
        function openEditModal(employee) {
            document.getElementById('edit_id').value = employee.id;
            document.getElementById('edit_employee_id').value = employee.employee_id;
            document.getElementById('edit_name').value = employee.name || '';
            document.getElementById('edit_nationality').value = employee.nationality || 'Malaysian';
            document.getElementById('edit_ic').value = employee.ic_number || '';
            document.getElementById('edit_passport').value = employee.passport_no || '';
            document.getElementById('edit_email').value = employee.email || '';
            document.getElementById('edit_department').value = employee.department || '';
            document.getElementById('edit_position').value = employee.position || '';
            document.getElementById('edit_salary').value = employee.basic_salary || '';
            document.getElementById('edit_phone').value = employee.phone || '';
            document.getElementById('edit_bank').value = employee.bank_name || '';
            document.getElementById('edit_account').value = employee.bank_account || '';
            document.getElementById('edit_annual_leave').value = employee.annual_leave_entitlement || 14;
            document.getElementById('edit_medical_leave').value = employee.medical_leave_entitlement || 14;
            document.getElementById('edit_status').value = employee.status || 'active';
            document.getElementById('edit_join_date').value = employee.join_date || '';
            document.getElementById('edit_existing_profile_pic').value = employee.profile_pic || '';
            
            // Set profile preview
            if (employee.profile_pic) {
                const preview = document.getElementById('edit_profile_preview');
                preview.style.backgroundImage = `url(../uploads/profiles/${employee.profile_pic})`;
                preview.style.backgroundSize = 'cover';
                preview.style.backgroundPosition = 'center';
                preview.innerHTML = '';
            } else {
                const preview = document.getElementById('edit_profile_preview');
                preview.style.backgroundImage = '';
                preview.innerHTML = '<i class="fas fa-user text-white text-3xl"></i>';
            }
            
            toggleStatutoryFields('edit');
            document.getElementById('editModal').classList.remove('hidden');
        }
        
        function exportEmployees() {
            window.location.href = 'export_employees.php';
        }
        
        // Initialize view on page load
        document.addEventListener('DOMContentLoaded', function() {
            setView(currentView);
        });
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const addModal = document.getElementById('addModal');
            const editModal = document.getElementById('editModal');
            const viewModal = document.getElementById('viewModal');
            if (event.target === addModal) {
                addModal.classList.add('hidden');
            }
            if (event.target === editModal) {
                editModal.classList.add('hidden');
            }
            if (event.target === viewModal) {
                viewModal.classList.add('hidden');
            }
        }
    </script>
</body>
</html>