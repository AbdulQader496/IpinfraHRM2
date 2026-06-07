<?php
require_once '../includes/auth.php';
redirectIfNotAdmin();
require_once '../includes/db.php';
require_once '../includes/functions.php';

if (isset($_POST['add_holiday'])) {
    $date = mysqli_real_escape_string($conn, $_POST['holiday_date']);
    $name = mysqli_real_escape_string($conn, $_POST['holiday_name']);
    mysqli_query($conn, "INSERT INTO holidays (holiday_date, holiday_name) VALUES ('$date', '$name')");
    logAction('create', 'Holiday added: ' . $_POST['holiday_name'] . ' on ' . $_POST['holiday_date'], mysqli_insert_id($conn), 'holiday');
    header('Location: holidays.php');
    exit();
}

if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    $hol = mysqli_fetch_assoc(mysqli_query($conn, "SELECT holiday_name, holiday_date FROM holidays WHERE id=$id"));
    mysqli_query($conn, "DELETE FROM holidays WHERE id = $id");
    logAction('delete', 'Holiday deleted: ' . ($hol['holiday_name'] ?? 'Unknown') . ' (' . ($hol['holiday_date'] ?? '') . ')', $id, 'holiday');
    header('Location: holidays.php');
    exit();
}

$holidays = mysqli_query($conn, "SELECT * FROM holidays ORDER BY holiday_date DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Holidays - IPINFRA HRM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen pb-20">
<!-- Premium Mobile Header -->
<div class="bg-gradient-to-r from-slate-900 via-indigo-900 to-slate-900 text-white sticky top-0 z-40 shadow-2xl">
    <div class="flex justify-between items-center px-4 py-4">
        <div class="flex items-center gap-3">
            <!-- MENU BUTTON - Left side -->
            <button onclick="toggleSidebar()" class="text-white/80 hover:text-white p-2 rounded-full hover:bg-white/10">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg">
                <span class="text-white font-bold text-sm">IN</span>
            </div>
            <div>
                <p class="text-xs text-blue-200 font-medium">IPINFRA NETWORKS</p>
                <p class="text-sm font-bold tracking-wide">Admin Portal</p>
            </div>
        </div>
        <!-- No back button - just empty space or nothing -->
    </div>
</div>
<?php require_once '../includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <div class="px-4 py-6 pb-24 max-w-7xl mx-auto">
        <h1 class="text-xl font-bold text-gray-800 mb-6">Public Holidays</h1>

        <!-- Add Holiday -->
        <div class="bg-white rounded-xl shadow-md p-5 mb-6">
            <h2 class="font-bold text-gray-800 mb-3 flex items-center gap-2">
                <i class="fas fa-plus-circle text-green-600"></i> Add New Holiday
            </h2>
            <form method="POST" class="flex flex-col gap-3">
                <input type="date" name="holiday_date" required class="px-4 py-3 border border-gray-200 rounded-xl">
                <input type="text" name="holiday_name" placeholder="Holiday Name" required class="px-4 py-3 border border-gray-200 rounded-xl">
                <button type="submit" name="add_holiday" class="bg-green-600 text-white py-3 rounded-xl">Add Holiday</button>
            </form>
        </div>

        <!-- Holiday List -->
        <div class="bg-white rounded-xl shadow-xl overflow-hidden">
            <div class="bg-gray-50 px-4 py-3 border-b">
                <p class="font-semibold text-gray-800"><i class="fas fa-calendar-alt mr-2 text-red-600"></i> Holiday List</p>
            </div>
            <div class="divide-y">
                <?php while ($row = mysqli_fetch_assoc($holidays)): ?>
                <div class="flex justify-between items-center p-4">
                    <div>
                        <p class="font-medium text-gray-800"><?php echo $row['holiday_name']; ?></p>
                        <p class="text-xs text-gray-500"><?php echo date('l, d F Y', strtotime($row['holiday_date'])); ?></p>
                    </div>
                    <a href="?delete=<?php echo $row['id']; ?>" data-confirm="Delete this holiday from the calendar?" data-confirm-title="Delete Holiday" class="text-red-500">
                        <i class="fas fa-trash text-lg"></i>
                    </a>
                </div>
                <?php endwhile; ?>
                <?php if (mysqli_num_rows($holidays) == 0): ?>
                <div class="p-8 text-center text-gray-500">
                    <i class="fas fa-calendar-times text-4xl mb-2 block"></i>
                    <p class="text-sm">No holidays added yet</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 md:hidden shadow-lg z-20">
        <div class="flex justify-around py-2">
            <a href="dashboard.php" class="flex flex-col items-center py-1 px-3 text-gray-500">
                <i class="fas fa-home text-xl"></i>
                <span class="text-xs mt-1">Home</span>
            </a>
            <a href="employees.php" class="flex flex-col items-center py-1 px-3 text-gray-500">
                <i class="fas fa-users text-xl"></i>
                <span class="text-xs mt-1">Staff</span>
            </a>
            <a href="holidays.php" class="flex flex-col items-center py-1 px-3 text-red-600">
                <i class="fas fa-calendar-alt text-xl"></i>
                <span class="text-xs mt-1">Holidays</span>
            </a>
        </div>
    </div>

    <script>
    </script>
</body>
</html>