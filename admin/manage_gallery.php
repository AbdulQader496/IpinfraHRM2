<?php
require_once '../includes/auth.php';
redirectIfNotAdmin();
require_once '../includes/db.php';

// Delete photo
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);

    // Get image path to delete file
    $query = "SELECT image_path FROM gallery WHERE id = $id";
    $result = mysqli_query($conn, $query);
    $photo = mysqli_fetch_assoc($result);
    
    // Delete file from server
    $file_path = "../uploads/gallery/" . $photo['image_path'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    
    // Delete from database
    mysqli_query($conn, "DELETE FROM gallery WHERE id = $id");
    header('Location: manage_gallery.php');
    exit();
}

// Hide/Show photo
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $new_status = (($_GET['status'] ?? '') == 'active') ? 'hidden' : 'active';
    mysqli_query($conn, "UPDATE gallery SET status = '$new_status' WHERE id = $id");
    header('Location: manage_gallery.php');
    exit();
}

// Get all gallery photos
$gallery = mysqli_query($conn, "SELECT g.*, e.name, e.employee_id 
    FROM gallery g 
    JOIN employees e ON g.employee_id = e.id 
    ORDER BY g.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Manage Gallery - IPINFRA HRM</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .sidebar { transition: transform 0.3s ease-in-out; }
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
        <a href="audit_log.php" class="flex items-center gap-3 py-3 px-4 rounded-xl hover:bg-gray-800/30 transition mb-1">
            <i class="fas fa-shield-alt w-5"></i> Audit Log
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
        <h1 class="text-2xl font-bold text-gray-800 mb-2">📸 Gallery Management</h1>
        <p class="text-sm text-gray-500 mb-6">Manage all company photos uploaded by employees</p>

        <!-- Stats -->
        <?php
        $total_photos = mysqli_num_rows($gallery);
        $active_photos = mysqli_num_rows(mysqli_query($conn, "SELECT * FROM gallery WHERE status = 'active'"));
        ?>
        <div class="grid grid-cols-2 gap-3 mb-6">
            <div class="bg-blue-100 rounded-xl p-3 text-center">
                <p class="text-2xl font-bold text-blue-700"><?php echo $total_photos; ?></p>
                <p class="text-xs text-blue-600">Total Photos</p>
            </div>
            <div class="bg-green-100 rounded-xl p-3 text-center">
                <p class="text-2xl font-bold text-green-700"><?php echo $active_photos; ?></p>
                <p class="text-xs text-green-600">Active Photos</p>
            </div>
        </div>

        <!-- Gallery Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-5">
            <?php mysqli_data_seek($gallery, 0); ?>
            <?php while($photo = mysqli_fetch_assoc($gallery)): ?>
            <div class="bg-white rounded-xl shadow-md overflow-hidden">
                <div class="h-48 bg-gray-200 relative">
                    <?php 
                    $image_path = "../uploads/gallery/" . $photo['image_path'];
                    if(file_exists($image_path)): ?>
                        <img src="<?php echo $image_path; ?>" alt="<?php echo $photo['caption']; ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center bg-gray-300">
                            <i class="fas fa-image text-5xl text-gray-400"></i>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Status Badge -->
                    <div class="absolute top-2 right-2">
                        <span class="text-xs px-2 py-1 rounded-full <?php echo $photo['status'] == 'active' ? 'bg-green-500 text-white' : 'bg-gray-500 text-white'; ?>">
                            <?php echo ucfirst($photo['status']); ?>
                        </span>
                    </div>
                </div>
                <div class="p-4">
                    <p class="text-sm text-gray-600 mb-2"><?php echo $photo['caption']; ?></p>
                    <div class="flex justify-between items-center text-xs text-gray-500 mb-3">
                        <span><i class="fas fa-user mr-1"></i> <?php echo $photo['name']; ?></span>
                        <span><i class="fas fa-calendar mr-1"></i> <?php echo date('d M Y', strtotime($photo['activity_date'])); ?></span>
                    </div>
                    <div class="flex gap-2">
                        <a href="?toggle=1&id=<?php echo $photo['id']; ?>&status=<?php echo $photo['status']; ?>" 
                           class="flex-1 text-center <?php echo $photo['status'] == 'active' ? 'bg-yellow-500 hover:bg-yellow-600' : 'bg-green-500 hover:bg-green-600'; ?> text-white px-3 py-1 rounded-lg text-xs">
                            <?php echo $photo['status'] == 'active' ? 'Hide' : 'Show'; ?>
                        </a>
                        <a href="?delete=<?php echo $photo['id']; ?>" 
                           data-confirm="Delete this photo permanently?" data-confirm-title="Delete Photo" 
                           class="flex-1 text-center bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded-lg text-xs">
                            Delete
                        </a>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
            <?php if($total_photos == 0): ?>
            <div class="col-span-full bg-white rounded-xl shadow-md p-8 text-center text-gray-500">
                <i class="fas fa-images text-5xl mb-3 block"></i>
                <p class="text-sm">No photos uploaded yet</p>
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
            <a href="employees.php" class="flex flex-col items-center py-1 px-3 text-gray-500">
                <i class="fas fa-users text-xl"></i>
                <span class="text-xs mt-1">Staff</span>
            </a>
            <a href="manage_gallery.php" class="flex flex-col items-center py-1 px-3 text-blue-600">
                <i class="fas fa-images text-xl"></i>
                <span class="text-xs mt-1">Gallery</span>
            </a>
            <a href="manage_assets.php" class="flex flex-col items-center py-1 px-3 text-gray-500">
                <i class="fas fa-boxes text-xl"></i>
                <span class="text-xs mt-1">Assets</span>
            </a>
        </div>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
            document.getElementById('overlay').classList.toggle('hidden');
        }
    </script>
</body>
</html>