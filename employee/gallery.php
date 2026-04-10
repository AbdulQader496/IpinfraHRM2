<?php
require_once '../includes/auth.php';
redirectIfNotLoggedIn();
require_once '../includes/db.php';

$user_id = $_SESSION['user_id'];

// Handle image upload
if (isset($_POST['upload_photo'])) {
    $caption = mysqli_real_escape_string($conn, $_POST['caption']);
    $activity_date = $_POST['activity_date'];
    
    $target_dir = "../uploads/gallery/";
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $image_name = time() . '_' . basename($_FILES['photo']['name']);
    $target_file = $target_dir . $image_name;
    
    if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
        $query = "INSERT INTO gallery (employee_id, image_path, caption, activity_date) 
                  VALUES ($user_id, '$image_name', '$caption', '$activity_date')";
        mysqli_query($conn, $query);
        $success = "Photo uploaded successfully!";
    } else {
        $error = "Error uploading photo.";
    }
}

// Get all gallery images
$gallery = mysqli_query($conn, "SELECT g.*, e.name, e.employee_id 
    FROM gallery g 
    JOIN employees e ON g.employee_id = e.id 
    WHERE g.status = 'active' 
    ORDER BY g.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>IPINFRA Gallery - Company Activities</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        .sidebar { transition: transform 0.3s ease-in-out; }
        .gallery-card { transition: all 0.3s ease; }
        .gallery-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px -10px rgba(0,0,0,0.2); }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-gray-100 min-h-screen pb-20">
<!-- Premium Header -->
<div class="bg-gradient-to-r from-slate-900 via-indigo-900 to-slate-900 text-white sticky top-0 z-40 shadow-2xl backdrop-blur-sm">
    <div class="flex items-center justify-between px-5 py-4">
        <div class="flex items-center gap-3">
            <!-- Menu Button -->
            <button onclick="toggleSidebar()" class="relative group">
                <div class="w-10 h-10 rounded-xl bg-white/10 backdrop-blur-sm flex items-center justify-center group-hover:bg-white/20 transition-all duration-300 group-hover:scale-105">
                    <i class="fas fa-bars text-lg"></i>
                </div>
            </button>
            
            <!-- Logo -->
            <div class="relative">
                <div class="w-10 h-10 bg-gradient-to-br from-blue-500 via-indigo-500 to-purple-600 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-500/20 animate-pulse">
                    <span class="text-white font-bold text-sm">IN</span>
                </div>
                <div class="absolute -top-1 -right-1 w-3 h-3 bg-green-400 rounded-full border-2 border-slate-900"></div>
            </div>
            
            <!-- Brand -->
            <div class="hidden sm:block">
                <p class="text-xs text-blue-200 font-medium tracking-wide">IPINFRA NETWORKS</p>
                <p class="text-sm font-bold tracking-tight">Employee Portal</p>
            </div>
        </div>
        
        <!-- Right side - Empty for now, can add profile/user later -->
        <div class="flex items-center gap-2">
            <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-indigo-500 flex items-center justify-center shadow-lg">
                <span class="text-white text-xs font-bold"><?php echo substr($_SESSION['user_name'], 0, 1); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Subtle bottom border glow -->
    <div class="h-0.5 bg-gradient-to-r from-transparent via-indigo-400 to-transparent"></div>
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
    <div class="px-4 py-6 pb-24 max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-gray-800 mb-2">📸 Company Gallery</h1>
        <p class="text-sm text-gray-500 mb-6">Share and view company activities, events, and celebrations</p>

        <!-- Upload Button -->
        <div class="mb-6">
            <button onclick="document.getElementById('uploadModal').classList.remove('hidden')" class="bg-blue-600 text-white px-5 py-2 rounded-xl flex items-center gap-2 shadow-md">
                <i class="fas fa-cloud-upload-alt"></i> Upload Photo
            </button>
        </div>

        <!-- Gallery Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-5">
            <?php if(mysqli_num_rows($gallery) > 0): ?>
                <?php while($photo = mysqli_fetch_assoc($gallery)): ?>
                <div class="bg-white rounded-xl shadow-md overflow-hidden gallery-card">
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
                    </div>
                    <div class="p-4">
                        <p class="text-sm text-gray-600"><?php echo $photo['caption']; ?></p>
                        <div class="flex justify-between items-center mt-3 text-xs text-gray-500">
                            <span><i class="fas fa-user mr-1"></i> <?php echo $photo['name']; ?></span>
                            <span><i class="fas fa-calendar mr-1"></i> <?php echo date('d M Y', strtotime($photo['activity_date'])); ?></span>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-span-full bg-white rounded-xl shadow-md p-8 text-center text-gray-500">
                    <i class="fas fa-images text-5xl mb-3 block"></i>
                    <p class="text-sm">No photos yet. Be the first to upload!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Upload Modal -->
    <div id="uploadModal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full">
            <div class="p-4 border-b flex justify-between items-center">
                <h2 class="text-lg font-bold">Upload Photo</h2>
                <button onclick="document.getElementById('uploadModal').classList.add('hidden')" class="text-gray-500">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="p-4 space-y-4">
                <div>
                    <label class="block text-gray-700 text-sm font-medium mb-1">Select Photo</label>
                    <input type="file" name="photo" accept="image/*" required class="w-full px-4 py-2 border border-gray-200 rounded-xl">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-medium mb-1">Caption</label>
                    <textarea name="caption" rows="2" placeholder="Describe this moment..." class="w-full px-4 py-3 border border-gray-200 rounded-xl"></textarea>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-medium mb-1">Activity Date</label>
                    <input type="date" name="activity_date" value="<?php echo date('Y-m-d'); ?>" class="w-full px-4 py-3 border border-gray-200 rounded-xl">
                </div>
                <button type="submit" name="upload_photo" class="w-full bg-blue-600 text-white py-3 rounded-xl font-semibold">Upload</button>
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
            <a href="gallery.php" class="flex flex-col items-center py-1 px-3 text-blue-600">
                <i class="fas fa-images text-xl"></i>
                <span class="text-xs mt-1">Gallery</span>
            </a>
            <a href="assets.php" class="flex flex-col items-center py-1 px-3 text-gray-500">
                <i class="fas fa-boxes text-xl"></i>
                <span class="text-xs mt-1">Assets</span>
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
    </script>
</body>
</html>