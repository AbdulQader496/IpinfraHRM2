<?php
require_once '../includes/auth.php';
redirectIfNotAdmin();
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/toast_fn.php';

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
    $employee_type = mysqli_real_escape_string($conn, $_POST['employee_type'] ?? 'regular');

    // Handle profile picture upload
    $profile_pic = '';
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $allowed_img_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowed_img_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_extension = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        $mime = mime_content_type($_FILES['profile_pic']['tmp_name']);
        if (in_array($file_extension, $allowed_img_ext) && in_array($mime, $allowed_img_mime)) {
            $target_dir = "../uploads/profiles/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $profile_pic = time() . '_' . $employee_id . '.' . $file_extension;
            move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_dir . $profile_pic);
        }
    }

    $is_subject = ($nationality == 'Malaysian') ? 1 : 0;
    
    // UPDATED INSERT QUERY with new fields
    $dup_check = mysqli_fetch_assoc(mysqli_query($conn, "SELECT id FROM employees WHERE employee_id='$employee_id' OR email='$email' LIMIT 1"));
    if ($dup_check) {
        $add_error = "Employee ID or email already exists.";
    } else {
        $query = "INSERT INTO employees (employee_id, name, ic_number, passport_no, nationality, email, password, department, position, basic_salary, join_date, profile_pic, is_subject_to_statutory, phone, address, bank_name, bank_account, employee_type)
                  VALUES ('$employee_id', '$name', '$ic_number', '$passport_no', '$nationality', '$email', '$password', '$department', '$position', '$basic_salary', '$join_date', '$profile_pic', '$is_subject', '$phone', '$address', '$bank_name', '$bank_account', '$employee_type')";
        mysqli_query($conn, $query);
        logAction('create', 'New employee added: ' . $_POST['name'] . ' (' . $_POST['employee_id'] . ')', mysqli_insert_id($conn), 'employee');
        header('Location: employees.php');
        exit();
    }
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
    $employee_type = mysqli_real_escape_string($conn, $_POST['employee_type'] ?? 'regular');
    
    // Handle profile picture upload
    $profile_pic = mysqli_real_escape_string($conn, $_POST['existing_profile_pic']);
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $allowed_img_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $allowed_img_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_extension = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        $mime = mime_content_type($_FILES['profile_pic']['tmp_name']);
        if (in_array($file_extension, $allowed_img_ext) && in_array($mime, $allowed_img_mime)) {
            $target_dir = "../uploads/profiles/";
            if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
            $profile_pic = time() . '_' . intval($_POST['emp_id']) . '.' . $file_extension;
            move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_dir . $profile_pic);
        }
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
                is_subject_to_statutory='$is_subject',
                employee_type='$employee_type'
              WHERE id=$id";
    mysqli_query($conn, $query);
    logAction('update', 'Employee record updated: ' . $_POST['name'] . ' (ID ' . $id . ')', $id, 'employee');
    header('Location: employees.php');
    exit();
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $del_emp = mysqli_fetch_assoc(mysqli_query($conn, "SELECT name, employee_id FROM employees WHERE id=$id"));
    mysqli_query($conn, "DELETE FROM employees WHERE id = $id");
    logAction('delete', 'Employee deleted: ' . ($del_emp['name'] ?? 'Unknown') . ' (' . ($del_emp['employee_id'] ?? '') . ')', $id, 'employee');
    header('Location: employees.php');
    exit();
}

// Handle Add Department (only runs if departments table exists)
if (isset($_POST['add_department'])) {
    $dept_name = trim(mysqli_real_escape_string($conn, $_POST['dept_name']));
    if ($dept_name !== '') {
        mysqli_query($conn, "CREATE TABLE IF NOT EXISTS departments (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(100) NOT NULL UNIQUE, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        try {
            mysqli_query($conn, "INSERT IGNORE INTO departments (name) VALUES ('$dept_name')");
        } catch (Exception $e) { /* ignore */ }
    }
    header('Location: employees.php');
    exit();
}

// Fetch managed department list (falls back to distinct values from employees if table missing)
$departments = [];
try {
    $dept_result = mysqli_query($conn, "SELECT name FROM departments ORDER BY name ASC");
    while ($row = mysqli_fetch_assoc($dept_result)) {
        $departments[] = $row['name'];
    }
} catch (Exception $e) {
    // departments table not yet created — seed from existing employee data
    $fallback = mysqli_query($conn, "SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department != '' ORDER BY department");
    while ($row = mysqli_fetch_assoc($fallback)) {
        $departments[] = $row['department'];
    }
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
        /* Search-as-you-type transitions */
        .employee-card, .employee-row {
            transition: opacity 0.2s ease, transform 0.2s ease, max-height 0.25s ease;
        }
        .employee-card.hidden-by-filter {
            display: none !important;
        }
        .employee-row.hidden-by-filter {
            display: none !important;
        }
        mark.search-highlight {
            background: #fef08a;
            color: inherit;
            border-radius: 2px;
            padding: 0 1px;
        }
        #noResultsMsg {
            display: none;
        }
        .search-input-wrapper {
            position: relative;
        }
        #searchClearBtn {
            position: absolute;
            right: 100px;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255,255,255,0.25);
            border: none;
            color: #fff;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 12px;
            display: none;
            align-items: center;
            justify-content: center;
            transition: background 0.15s;
        }
        #searchClearBtn:hover {
            background: rgba(255,255,255,0.45);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-blue-50 to-indigo-50 min-h-screen">
<?php require_once '../includes/global_ui.php'; ?>
<?php require_once '../includes/toast.php'; ?>
<?php require_once '../includes/confirm_modal.php'; ?>

<!-- Mobile Header -->
<div class="bg-[#060912] text-white sticky top-0 z-40 shadow-2xl">
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
        <form method="GET" class="search-input-wrapper relative">
            <i class="fas fa-search absolute left-4 top-1/2 transform -translate-y-1/2 text-gray-400 z-10"></i>
            <input type="text" id="liveSearchInput" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Search by name, ID, department, position..."
                   oninput="debouncedFilter()"
                   autocomplete="off"
                   class="w-full pl-12 pr-28 py-3 rounded-xl bg-white/10 border border-white/20 text-white placeholder-white/50 focus:outline-none focus:ring-2 focus:ring-blue-400">
            <button type="button" id="searchClearBtn" onclick="clearSearch()" title="Clear search">
                &times;
            </button>
            <button type="submit" class="absolute right-2 top-1/2 transform -translate-y-1/2 bg-blue-600 text-white px-4 py-1.5 rounded-lg text-sm hover:bg-blue-700 transition">
                Search
            </button>
        </form>
    </div>
</div>
<?php require_once '../includes/admin_sidebar.php'; ?>

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
            <div class="employee-card bg-white rounded-2xl shadow-lg overflow-hidden hover:shadow-2xl transition-all duration-300 transform hover:-translate-y-1"
                 data-searchable="<?php echo strtolower(htmlspecialchars($row['name'] . ' ' . $row['employee_id'] . ' ' . $row['department'] . ' ' . $row['position'])); ?>">
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
                                <a href="view_employee.php?id=<?php echo $row['id']; ?>" class="font-bold employee-name-text hover:text-blue-200 transition"><?php echo htmlspecialchars($row['name']); ?></a>
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
                        <button onclick='openEditModal(<?php echo json_encode($row); ?>)'
                                class="flex-1 bg-blue-50 text-blue-600 py-2 rounded-lg hover:bg-blue-100 transition text-sm font-medium">
                            <i class="fas fa-edit mr-1"></i> View / Edit
                        </button>
                        <a href="?delete=<?php echo $row['id']; ?>" data-confirm="This will permanently delete the employee and all related records." data-confirm-title="Delete Employee" 
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
                        <tr class="employee-row hover:bg-gray-50 transition"
                            data-searchable="<?php echo strtolower(htmlspecialchars($row['name'] . ' ' . $row['employee_id'] . ' ' . $row['department'] . ' ' . $row['position'])); ?>">
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
                                        <a href="view_employee.php?id=<?php echo $row['id']; ?>" class="font-semibold text-gray-800 employee-name-text hover:text-blue-600 transition"><?php echo htmlspecialchars($row['name']); ?></a>
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
                                    <button onclick='openEditModal(<?php echo json_encode($row); ?>)' class="text-blue-600 hover:text-blue-800 transition" title="View / Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?delete=<?php echo $row['id']; ?>" data-confirm="This will permanently delete the employee and all related records." data-confirm-title="Delete Employee" class="text-red-500 hover:text-red-700 transition" title="Delete">
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
        
        <!-- No Results Message (client-side filter) -->
        <div id="noResultsMsg" class="text-center py-16">
            <i class="fas fa-search text-gray-300 text-5xl mb-4"></i>
            <p class="text-gray-500 text-lg font-medium">No employees match your search.</p>
            <p class="text-gray-400 text-sm mt-1">Try a different name, ID, department, or position.</p>
            <button onclick="clearSearch()" class="mt-4 text-blue-600 hover:underline text-sm">Clear search</button>
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

<!-- Add Employee Modal - COMPLETE with all fields -->
<!-- Add Employee Modal -->
<div id="addModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-3xl shadow-2xl modal-enter flex flex-col max-h-[92vh]">

        <!-- Fixed Header -->
        <div class="flex items-center justify-between px-7 py-5 border-b border-gray-100 shrink-0">
            <div class="flex items-center gap-4">
                <div class="w-11 h-11 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow">
                    <i class="fas fa-user-plus text-white text-lg"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Add New Employee</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Fill in all required fields marked with *</p>
                </div>
            </div>
            <button onclick="document.getElementById('addModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-700 hover:bg-gray-100 p-2 rounded-xl transition">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>

        <!-- Form: flex column so fields scroll, footer stays pinned -->
        <form id="addEmployeeForm" method="POST" enctype="multipart/form-data" class="flex flex-col flex-1 overflow-hidden">
            <div class="flex-1 overflow-y-auto px-7 py-6 space-y-6">

                <!-- Profile Picture -->
                <div class="flex items-center gap-5">
                    <div class="relative shrink-0">
                        <div id="add_profile_preview" class="w-20 h-20 rounded-2xl bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center shadow-lg overflow-hidden">
                            <i class="fas fa-camera text-white text-2xl"></i>
                        </div>
                        <label class="absolute -bottom-1 -right-1 bg-blue-600 text-white p-1.5 rounded-lg cursor-pointer hover:bg-blue-700 transition shadow">
                            <i class="fas fa-upload text-xs"></i>
                            <input type="file" name="profile_pic" id="add_profile_pic" class="hidden" accept="image/*" onchange="previewImage(this, 'add_profile_preview')">
                        </label>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-700">Profile Photo</p>
                        <p class="text-xs text-gray-400 mt-0.5">JPG, PNG up to 5MB (optional)</p>
                    </div>
                </div>

                <!-- Section: Personal Info -->
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-1 h-5 bg-blue-600 rounded-full"></div>
                        <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Personal Information</h3>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">Employee ID *</label>
                            <input type="text" name="employee_id" placeholder="e.g. EMP001" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-gray-50 focus:bg-white transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">Full Name *</label>
                            <input type="text" name="name" placeholder="As per IC / Passport" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-gray-50 focus:bg-white transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">Nationality *</label>
                            <select name="nationality" id="add_nationality" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition" onchange="toggleStatutoryFields('add')">
                                <option value="Malaysian">Malaysian (EPF / SOCSO / EIS)</option>
                                <option value="Non-Malaysian">Non-Malaysian / Expat</option>
                            </select>
                        </div>
                        <div id="add_ic_field">
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">IC Number</label>
                            <input type="text" name="ic_number" placeholder="e.g. 900101-10-1234" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition">
                        </div>
                        <div id="add_passport_field" style="display:none;" class="col-span-1">
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">Passport Number</label>
                            <input type="text" name="passport_no" placeholder="Passport No." class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition">
                        </div>
                    </div>
                </div>

                <!-- Section: Contact -->
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-1 h-5 bg-emerald-500 rounded-full"></div>
                        <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Contact Information</h3>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">Email Address *</label>
                            <input type="email" name="email" placeholder="name@company.com" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">Phone Number</label>
                            <input type="text" name="phone" placeholder="e.g. 012-3456789" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">Address</label>
                            <textarea name="address" rows="2" placeholder="Full home address" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition resize-none"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Section: Job -->
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-1 h-5 bg-indigo-500 rounded-full"></div>
                        <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Job Information</h3>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-sm font-medium text-gray-600">Department *</label>
                                <button type="button" onclick="toggleNewDept('add')" class="text-xs text-blue-600 hover:text-blue-800 font-semibold">+ New</button>
                            </div>
                            <select name="department" id="add_department" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition">
                                <option value="">-- Select Department --</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div id="add_new_dept_box" class="hidden mt-2">
                                <div class="flex gap-2">
                                    <input type="text" id="add_dept_input" placeholder="New department name" class="flex-1 px-3 py-2 border border-blue-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <button type="button" onclick="submitNewDept()" class="bg-blue-600 text-white px-3 py-2 rounded-lg text-sm hover:bg-blue-700 transition">Add</button>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">Position / Job Title *</label>
                            <input type="text" name="position" placeholder="e.g. Software Engineer" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">Employee Type *</label>
                            <select name="employee_type" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition">
                                <option value="regular">Regular Employee</option>
                                <option value="intern">Intern (No EPF/SOCSO/EIS/PCB)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">Date of Joining *</label>
                            <input type="date" name="join_date" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition">
                        </div>
                    </div>
                </div>

                <!-- Section: Salary & Bank -->
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-1 h-5 bg-amber-500 rounded-full"></div>
                        <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Salary & Bank</h3>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">Basic Salary (RM) *</label>
                            <input type="number" step="0.01" name="basic_salary" placeholder="0.00" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">Bank Name</label>
                            <input type="text" name="bank_name" placeholder="e.g. Maybank" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">Bank Account Number</label>
                            <input type="text" name="bank_account" placeholder="e.g. 1234567890" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition">
                        </div>
                    </div>
                </div>

                <!-- Section: Login & Statutory -->
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-1 h-5 bg-rose-500 rounded-full"></div>
                        <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Login Credentials</h3>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-600 mb-1.5">Password *</label>
                        <input type="password" name="password" value="password123" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition">
                        <p class="text-xs text-gray-400 mt-1.5">Default: <span class="font-mono text-gray-600">password123</span> — employee can change this after logging in</p>
                    </div>
                </div>

                <!-- Statutory Note -->
                <div class="bg-blue-50 border border-blue-100 p-4 rounded-xl" id="add_statutory_note">
                    <div class="flex items-start gap-3">
                        <i class="fas fa-info-circle text-blue-500 mt-0.5 text-sm"></i>
                        <p class="text-xs text-blue-700 leading-relaxed">Malaysian employees are subject to automatic deductions: <strong>EPF 11%</strong>, <strong>SOCSO 0.5%</strong>, <strong>EIS 0.2%</strong>.</p>
                    </div>
                </div>

            </div>

            <!-- Footer inside form — pinned at bottom, fields above scroll -->
            <div class="px-7 py-4 border-t border-gray-100 bg-gray-50/50 rounded-b-2xl flex gap-3 shrink-0">
                <button type="submit" name="add_employee" class="flex-1 bg-gradient-to-r from-blue-600 to-indigo-600 text-white py-3 rounded-xl font-semibold hover:shadow-lg hover:from-blue-700 hover:to-indigo-700 transition flex items-center justify-center gap-2">
                    <i class="fas fa-save"></i> Save Employee
                </button>
                <button type="button" onclick="document.getElementById('addModal').classList.add('hidden')" class="px-6 bg-white border border-gray-200 text-gray-600 py-3 rounded-xl font-semibold hover:bg-gray-100 transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>
<!-- Edit Employee Modal -->
<div id="editModal" class="hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl w-full max-w-3xl shadow-2xl modal-enter flex flex-col max-h-[92vh]">

        <!-- Fixed Header -->
        <div class="flex items-center justify-between px-7 py-5 border-b border-gray-100 shrink-0">
            <div class="flex items-center gap-4">
                <div class="w-11 h-11 bg-gradient-to-br from-emerald-500 to-teal-600 rounded-xl flex items-center justify-center shadow">
                    <i class="fas fa-user-edit text-white text-lg"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-900">Edit Employee</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Update the employee's information below</p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a id="editViewProfileLink" href="#" class="text-xs text-blue-600 hover:text-blue-800 border border-blue-200 hover:border-blue-400 px-3 py-1.5 rounded-lg transition hidden">
                    <i class="fas fa-eye mr-1"></i> Full Profile
                </a>
                <button onclick="document.getElementById('editModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-700 hover:bg-gray-100 p-2 rounded-xl transition">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
        </div>

        <!-- Form: flex column so fields scroll, footer stays pinned -->
        <form method="POST" enctype="multipart/form-data" id="editForm" class="flex flex-col flex-1 overflow-hidden">
            <input type="hidden" name="emp_id" id="edit_id">
            <input type="hidden" name="existing_profile_pic" id="edit_existing_profile_pic">
            <input type="hidden" name="employee_id" id="edit_employee_id">

            <div class="flex-1 overflow-y-auto px-7 py-6 space-y-6">

                <!-- Profile Picture -->
                <div class="flex items-center gap-5">
                    <div class="relative shrink-0">
                        <div id="edit_profile_preview" class="w-20 h-20 rounded-2xl bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center shadow-lg overflow-hidden">
                            <i class="fas fa-user text-white text-2xl"></i>
                        </div>
                        <label class="absolute -bottom-1 -right-1 bg-blue-600 text-white p-1.5 rounded-lg cursor-pointer hover:bg-blue-700 transition shadow">
                            <i class="fas fa-upload text-xs"></i>
                            <input type="file" name="profile_pic" id="edit_profile_pic" class="hidden" accept="image/*" onchange="previewImage(this, 'edit_profile_preview')">
                        </label>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-700">Profile Photo</p>
                        <p class="text-xs text-gray-400 mt-0.5">Click the icon to change photo</p>
                    </div>
                </div>

                <!-- Section: Personal Info -->
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-1 h-5 bg-blue-600 rounded-full"></div>
                        <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Personal Information</h3>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2">
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">Full Name *</label>
                            <input type="text" name="name" id="edit_name" placeholder="As per IC / Passport" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">Nationality *</label>
                            <select name="nationality" id="edit_nationality" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition" onchange="toggleStatutoryFields('edit')">
                                <option value="Malaysian">Malaysian (EPF / SOCSO / EIS)</option>
                                <option value="Non-Malaysian">Non-Malaysian / Expat</option>
                            </select>
                        </div>
                        <div id="edit_ic_field">
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">IC Number</label>
                            <input type="text" name="ic_number" id="edit_ic" placeholder="e.g. 900101-10-1234" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition">
                        </div>
                        <div id="edit_passport_field" style="display:none;" class="col-span-1">
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">Passport Number</label>
                            <input type="text" name="passport_no" id="edit_passport" placeholder="Passport No." class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition">
                        </div>
                    </div>
                </div>

                <!-- Section: Contact -->
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-1 h-5 bg-emerald-500 rounded-full"></div>
                        <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Contact Information</h3>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">Email Address *</label>
                            <input type="email" name="email" id="edit_email" placeholder="name@company.com" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">Phone Number</label>
                            <input type="text" name="phone" id="edit_phone" placeholder="e.g. 012-3456789" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition">
                        </div>
                    </div>
                </div>

                <!-- Section: Job -->
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-1 h-5 bg-indigo-500 rounded-full"></div>
                        <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Job Information</h3>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-sm font-medium text-gray-600">Department *</label>
                                <button type="button" onclick="toggleNewDept('edit')" class="text-xs text-blue-600 hover:text-blue-800 font-semibold">+ New</button>
                            </div>
                            <select name="department" id="edit_department" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition">
                                <option value="">-- Select Department --</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept) ?>"><?= htmlspecialchars($dept) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div id="edit_new_dept_box" class="hidden mt-2">
                                <div class="flex gap-2">
                                    <input type="text" id="edit_dept_input" placeholder="New department name" class="flex-1 px-3 py-2 border border-blue-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <button type="button" onclick="submitNewDept()" class="bg-blue-600 text-white px-3 py-2 rounded-lg text-sm hover:bg-blue-700 transition">Add</button>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">Position / Job Title *</label>
                            <input type="text" name="position" id="edit_position" placeholder="e.g. Software Engineer" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">Employee Type</label>
                            <select name="employee_type" id="edit_employee_type" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition">
                                <option value="regular">Regular Employee</option>
                                <option value="intern">Intern (No EPF/SOCSO/EIS/PCB)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">Employment Status</label>
                            <select name="status" id="edit_status" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">Date of Joining *</label>
                            <input type="date" name="join_date" id="edit_join_date" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">Basic Salary (RM) *</label>
                            <input type="number" step="0.01" name="basic_salary" id="edit_salary" placeholder="0.00" required class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition">
                        </div>
                    </div>
                </div>

                <!-- Section: Bank & Leave -->
                <div>
                    <div class="flex items-center gap-2 mb-4">
                        <div class="w-1 h-5 bg-amber-500 rounded-full"></div>
                        <h3 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Bank & Leave Entitlement</h3>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">Bank Name</label>
                            <input type="text" name="bank_name" id="edit_bank" placeholder="e.g. Maybank" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">Bank Account Number</label>
                            <input type="text" name="bank_account" id="edit_account" placeholder="e.g. 1234567890" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">Annual Leave (days)</label>
                            <input type="number" name="annual_leave_entitlement" id="edit_annual_leave" placeholder="e.g. 14" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-600 mb-1.5">Medical Leave (days)</label>
                            <input type="number" name="medical_leave_entitlement" id="edit_medical_leave" placeholder="e.g. 14" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500 bg-gray-50 focus:bg-white transition">
                        </div>
                    </div>
                </div>

                <!-- Statutory Note -->
                <div class="bg-blue-50 border border-blue-100 p-4 rounded-xl" id="edit_statutory_note">
                    <div class="flex items-start gap-3">
                        <i class="fas fa-info-circle text-blue-500 mt-0.5 text-sm"></i>
                        <p class="text-xs text-blue-700 leading-relaxed">Malaysian employees are subject to automatic deductions: <strong>EPF 11%</strong>, <strong>SOCSO 0.5%</strong>, <strong>EIS 0.2%</strong>.</p>
                    </div>
                </div>

            </div>

            <!-- Footer inside form — pinned at bottom, fields above scroll -->
            <div class="px-7 py-4 border-t border-gray-100 bg-gray-50/50 rounded-b-2xl flex gap-3 shrink-0">
                <button type="submit" name="update_employee" class="flex-1 bg-gradient-to-r from-emerald-600 to-teal-600 text-white py-3 rounded-xl font-semibold hover:shadow-lg hover:from-emerald-700 hover:to-teal-700 transition flex items-center justify-center gap-2">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="px-6 bg-white border border-gray-200 text-gray-600 py-3 rounded-xl font-semibold hover:bg-gray-100 transition">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Standalone dept form — outside all modals to avoid nested-form parsing issues -->
<form id="addDeptStandaloneForm" method="POST" data-no-loading style="display:none;">
    <input type="hidden" name="dept_name" id="addDeptHidden">
    <input type="hidden" name="add_department" value="1">
</form>

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
        function openEditModal(employee) {
            // Wire up "Full Profile" link in edit modal header
            const profileLink = document.getElementById('editViewProfileLink');
            if (profileLink && employee.id) {
                profileLink.href = 'view_employee.php?id=' + employee.id;
                profileLink.classList.remove('hidden');
            }
            document.getElementById('edit_id').value = employee.id;
            document.getElementById('edit_employee_id').value = employee.employee_id;
            document.getElementById('edit_name').value = employee.name || '';
            document.getElementById('edit_nationality').value = employee.nationality || 'Malaysian';
            document.getElementById('edit_ic').value = employee.ic_number || '';
            document.getElementById('edit_passport').value = employee.passport_no || '';
            document.getElementById('edit_email').value = employee.email || '';
            // Set department select; if value not in list, fall back to first option
            const deptSelect = document.getElementById('edit_department');
            const deptVal = employee.department || '';
            const deptOption = Array.from(deptSelect.options).find(o => o.value === deptVal);
            deptSelect.value = deptOption ? deptVal : '';
            document.getElementById('edit_position').value = employee.position || '';
            document.getElementById('edit_salary').value = employee.basic_salary || '';
            document.getElementById('edit_phone').value = employee.phone || '';
            document.getElementById('edit_bank').value = employee.bank_name || '';
            document.getElementById('edit_account').value = employee.bank_account || '';
            document.getElementById('edit_annual_leave').value = employee.annual_leave_entitlement || 14;
            document.getElementById('edit_medical_leave').value = employee.medical_leave_entitlement || 14;
            document.getElementById('edit_status').value = employee.status || 'active';
            document.getElementById('edit_employee_type').value = employee.employee_type || 'regular';
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

        function toggleNewDept(prefix) {
            const box = document.getElementById(prefix + '_new_dept_box');
            if (box) {
                box.classList.toggle('hidden');
                if (!box.classList.contains('hidden')) {
                    const input = document.getElementById(prefix + '_dept_input');
                    if (input) input.focus();
                }
            }
        }

        function submitNewDept() {
            const addInput  = document.getElementById('add_dept_input');
            const editInput = document.getElementById('edit_dept_input');
            const val = (addInput && !document.getElementById('add_new_dept_box').classList.contains('hidden'))
                ? addInput.value.trim()
                : (editInput ? editInput.value.trim() : '');
            if (!val) return;
            document.getElementById('addDeptHidden').value = val;
            document.getElementById('addDeptStandaloneForm').submit();
        }
        
        // ── Search-as-you-type ──────────────────────────────────────────────
        let _filterTimer = null;

        function debouncedFilter() {
            clearTimeout(_filterTimer);
            _filterTimer = setTimeout(filterEmployees, 200);
        }

        function filterEmployees() {
            const input = document.getElementById('liveSearchInput');
            if (!input) return;
            const query = input.value.trim().toLowerCase();
            const clearBtn = document.getElementById('searchClearBtn');

            // Show/hide clear button
            if (clearBtn) {
                clearBtn.style.display = query.length ? 'flex' : 'none';
            }

            const cards = document.querySelectorAll('.employee-card');
            const rows  = document.querySelectorAll('.employee-row');
            let visibleCount = 0;

            // Helper: escape special regex chars in query
            function escapeRegex(str) {
                return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            }

            // Helper: highlight matching text in an element
            function highlightText(el, query) {
                // Restore original text first (stored in dataset)
                if (el.dataset.originalText === undefined) {
                    el.dataset.originalText = el.textContent;
                }
                const original = el.dataset.originalText;
                if (!query) {
                    el.textContent = original;
                    return;
                }
                const regex = new RegExp('(' + escapeRegex(query) + ')', 'gi');
                el.innerHTML = original.replace(regex, '<mark class="search-highlight">$1</mark>');
            }

            // Filter grid cards
            cards.forEach(function(card) {
                const searchable = card.getAttribute('data-searchable') || '';
                const nameEl = card.querySelector('.employee-name-text');
                const matches = !query || searchable.includes(query);
                if (matches) {
                    card.classList.remove('hidden-by-filter');
                    if (nameEl) highlightText(nameEl, query);
                    visibleCount++;
                } else {
                    card.classList.add('hidden-by-filter');
                    if (nameEl) highlightText(nameEl, '');
                }
            });

            // Filter list rows
            rows.forEach(function(row) {
                const searchable = row.getAttribute('data-searchable') || '';
                const nameEl = row.querySelector('.employee-name-text');
                const matches = !query || searchable.includes(query);
                if (matches) {
                    row.classList.remove('hidden-by-filter');
                    if (nameEl) highlightText(nameEl, query);
                    visibleCount++;
                } else {
                    row.classList.add('hidden-by-filter');
                    if (nameEl) highlightText(nameEl, '');
                }
            });

            // Show/hide no-results message
            const noMsg = document.getElementById('noResultsMsg');
            if (noMsg) {
                // Count visible in the *active* view (cards or rows have double entries)
                const activeCards = document.querySelectorAll('.employee-card:not(.hidden-by-filter)').length;
                const activeRows  = document.querySelectorAll('.employee-row:not(.hidden-by-filter)').length;
                const anyVisible  = (activeCards > 0 || activeRows > 0);
                noMsg.style.display = (query && !anyVisible) ? 'block' : 'none';
            }
        }

        function clearSearch() {
            const input = document.getElementById('liveSearchInput');
            if (input) {
                input.value = '';
                input.focus();
            }
            filterEmployees();
        }

        // Initialise: run filter on page load so clear button state is correct
        // and re-apply if the page loaded with a pre-filled search value
        document.addEventListener('DOMContentLoaded', function() {
            filterEmployees();
            // Open the search bar automatically if a server-side search is active
            <?php if ($search): ?>
            document.getElementById('searchBar').classList.remove('hidden');
            <?php endif; ?>
        });

        // ── End search-as-you-type ──────────────────────────────────────────

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