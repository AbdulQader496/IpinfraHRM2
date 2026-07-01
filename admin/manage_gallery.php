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

// Upload photo (admin)
if (isset($_POST['upload_photo'])) {
    $caption       = mysqli_real_escape_string($conn, $_POST['caption'] ?? '');
    $activity_date = mysqli_real_escape_string($conn, $_POST['activity_date'] ?? date('Y-m-d'));
    $admin_id      = intval($_SESSION['user_id']);
    $target_dir    = "../uploads/gallery/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    $allowed_ext  = ['jpg','jpeg','png','gif','webp'];
    $allowed_mime = ['image/jpeg','image/png','image/gif','image/webp'];
    $file_ext  = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    $mime      = mime_content_type($_FILES['photo']['tmp_name']);
    if (!in_array($file_ext, $allowed_ext) || !in_array($mime, $allowed_mime)) {
        header('Location: manage_gallery.php?err=' . urlencode('Invalid file type. Only JPG, PNG, GIF, WEBP allowed.')); exit();
    }
    $image_name = time() . '_admin_' . $admin_id . '.' . $file_ext;
    if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_dir . $image_name)) {
        mysqli_query($conn, "INSERT INTO gallery (employee_id, image_path, caption, activity_date, status)
            VALUES ($admin_id, '$image_name', '$caption', '$activity_date', 'active')");
        header('Location: manage_gallery.php?msg=' . urlencode('Photo uploaded successfully!')); exit();
    } else {
        header('Location: manage_gallery.php?err=' . urlencode('Upload failed. Please try again.')); exit();
    }
}

// Flash messages
$flash_success = htmlspecialchars($_GET['msg'] ?? '');
$flash_error   = htmlspecialchars($_GET['err'] ?? '');

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
<div class="bg-[#060912] text-white sticky top-0 z-40 shadow-2xl">
    <div class="flex justify-between items-center px-4 py-4">
        <div class="flex items-center gap-3">
            <!-- MENU BUTTON - Left side -->
            <button onclick="toggleSidebar()" class="text-white/80 hover:text-white p-2 rounded-full hover:bg-white/10">
                <i class="fas fa-bars text-xl"></i>
            </button>
            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-600 rounded-xl flex items-center justify-center shadow-lg">
                <img src="../uploads/1775551018_4xzREYTcMvK7ReGODviudjeDBIofOQ78mr5DsN9g.jpg" alt="IPINFRA" style="width:28px;height:28px;object-fit:contain;border-radius:4px;background:#fff;">
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
<?php require_once '../includes/global_ui.php'; ?>
<?php require_once '../includes/confirm_modal.php'; ?>

    <!-- Main Content -->
    <div class="px-4 py-6 pb-24 max-w-7xl mx-auto">
        <div class="flex items-center justify-between flex-wrap gap-3 mb-5">
            <div>
                <h1 class="text-2xl font-bold text-gray-800">Gallery Management</h1>
                <p class="text-sm text-gray-500 mt-0.5">Manage all company photos</p>
            </div>
            <button onclick="document.getElementById('uploadModal').classList.remove('hidden')"
                    class="inline-flex items-center gap-2 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 text-white px-5 py-2.5 rounded-xl font-semibold text-sm shadow-md transition">
                <i class="fas fa-cloud-upload-alt"></i>Upload Photo
            </button>
        </div>

        <?php if ($flash_success): ?>
        <div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 mb-4 flex items-center gap-2 text-sm text-green-700 font-medium">
            <i class="fas fa-check-circle text-green-500"></i><?php echo $flash_success; ?>
        </div>
        <?php endif; ?>
        <?php if ($flash_error): ?>
        <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 mb-4 flex items-center gap-2 text-sm text-red-600 font-medium">
            <i class="fas fa-exclamation-circle text-red-400"></i><?php echo $flash_error; ?>
        </div>
        <?php endif; ?>

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
                    <?php if (file_exists($image_path)): ?>
                    <a href="../uploads/gallery/<?php echo htmlspecialchars($photo['image_path']); ?>"
                       download="<?php echo htmlspecialchars($photo['name'] . '_' . date('d-M-Y', strtotime($photo['activity_date'])) . '.' . pathinfo($photo['image_path'], PATHINFO_EXTENSION)); ?>"
                       class="flex items-center justify-center gap-1.5 w-full bg-green-50 hover:bg-green-100 text-green-600 border border-green-200 px-3 py-1.5 rounded-lg text-xs font-semibold mb-2 transition">
                        <i class="fas fa-download"></i>Download
                    </a>
                    <?php endif; ?>
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

<!-- Upload Modal -->
<div id="uploadModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl overflow-hidden">
        <div class="h-1 bg-gradient-to-r from-indigo-500 to-blue-500"></div>
        <div class="px-6 py-5">
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-indigo-100 rounded-xl flex items-center justify-center">
                        <i class="fas fa-cloud-upload-alt text-indigo-600"></i>
                    </div>
                    <h2 class="font-bold text-gray-800">Upload Photo</h2>
                </div>
                <button onclick="document.getElementById('uploadModal').classList.add('hidden')"
                        class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 transition">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Photo <span class="text-red-400">*</span></label>
                    <label class="flex flex-col items-center gap-2 border-2 border-dashed border-gray-200 hover:border-indigo-400 rounded-xl p-5 cursor-pointer transition group" for="adminPhotoFile">
                        <i class="fas fa-image text-2xl text-gray-300 group-hover:text-indigo-400 transition"></i>
                        <span class="text-sm text-gray-400 group-hover:text-indigo-500 transition" id="adminFileLabel">Click to select image</span>
                        <span class="text-[10px] text-gray-300">JPG, PNG, GIF, WEBP</span>
                    </label>
                    <input type="file" id="adminPhotoFile" name="photo" accept="image/*" required class="hidden"
                           onchange="document.getElementById('adminFileLabel').textContent=this.files[0]?.name||'Click to select image'">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Caption</label>
                    <textarea name="caption" rows="2" placeholder="Describe this photo…"
                              class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm outline-none focus:border-indigo-400 transition"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Activity Date</label>
                    <input type="date" name="activity_date" value="<?php echo date('Y-m-d'); ?>"
                           class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm outline-none focus:border-indigo-400 transition">
                </div>
                <div class="flex gap-3 pt-1">
                    <button type="submit" name="upload_photo"
                            class="flex-1 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 text-white py-3 rounded-xl font-semibold text-sm shadow-md transition">
                        <i class="fas fa-paper-plane mr-1"></i>Upload
                    </button>
                    <button type="button" onclick="document.getElementById('uploadModal').classList.add('hidden')"
                            class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 py-3 rounded-xl font-semibold text-sm transition">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

    <script>
        document.addEventListener('keydown', e => { if (e.key === 'Escape') document.getElementById('uploadModal').classList.add('hidden'); });
    </script>
</body>
</html>