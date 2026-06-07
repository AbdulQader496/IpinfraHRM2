<?php
require_once '../includes/auth.php';
redirectIfNotLoggedIn();
require_once '../includes/db.php';

$user_id = $_SESSION['user_id'];

// ── Upload ──────────────────────────────────────────────────────────────────
if (isset($_POST['upload_photo'])) {
    $caption       = mysqli_real_escape_string($conn, $_POST['caption']);
    $activity_date = mysqli_real_escape_string($conn, $_POST['activity_date']);
    $target_dir    = "../uploads/gallery/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    $allowed_ext  = ['jpg','jpeg','png','gif','webp'];
    $allowed_mime = ['image/jpeg','image/png','image/gif','image/webp'];
    $file_ext  = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    $mime      = mime_content_type($_FILES['photo']['tmp_name']);
    if (!in_array($file_ext, $allowed_ext) || !in_array($mime, $allowed_mime)) {
        header('Location: gallery.php?err=' . urlencode('Invalid file type. Only JPG, PNG, GIF, WEBP allowed.')); exit();
    }
    $image_name = time() . '_' . $user_id . '.' . $file_ext;
    if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_dir . $image_name)) {
        mysqli_query($conn, "INSERT INTO gallery (employee_id, image_path, caption, activity_date)
            VALUES ($user_id, '$image_name', '$caption', '$activity_date')");
        header('Location: gallery.php?msg=' . urlencode('Photo uploaded successfully!')); exit();
    } else {
        header('Location: gallery.php?err=' . urlencode('Upload failed. Please try again.')); exit();
    }
}

// ── Edit ────────────────────────────────────────────────────────────────────
if (isset($_POST['edit_photo'])) {
    $id            = intval($_POST['photo_id']);
    $caption       = mysqli_real_escape_string($conn, $_POST['caption']);
    $activity_date = mysqli_real_escape_string($conn, $_POST['activity_date']);

    // Ownership check
    $own = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM gallery WHERE id=$id AND employee_id=$user_id"));
    if (!$own) { header('Location: gallery.php?err=' . urlencode('Photo not found.')); exit(); }

    // Optional new image
    if (!empty($_FILES['photo']['name']) && $_FILES['photo']['error'] == 0) {
        $allowed_ext  = ['jpg','jpeg','png','gif','webp'];
        $allowed_mime = ['image/jpeg','image/png','image/gif','image/webp'];
        $file_ext  = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        $mime      = mime_content_type($_FILES['photo']['tmp_name']);
        if (!in_array($file_ext, $allowed_ext) || !in_array($mime, $allowed_mime)) {
            header('Location: gallery.php?err=' . urlencode('Invalid file type.')); exit();
        }
        $new_image = time() . '_' . $user_id . '.' . $file_ext;
        $target_dir = "../uploads/gallery/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        if (move_uploaded_file($_FILES['photo']['tmp_name'], $target_dir . $new_image)) {
            // Delete old file
            $old = "../uploads/gallery/" . $own['image_path'];
            if (file_exists($old)) unlink($old);
            mysqli_query($conn, "UPDATE gallery SET image_path='$new_image', caption='$caption', activity_date='$activity_date' WHERE id=$id");
        } else {
            header('Location: gallery.php?err=' . urlencode('Image replacement failed.')); exit();
        }
    } else {
        mysqli_query($conn, "UPDATE gallery SET caption='$caption', activity_date='$activity_date' WHERE id=$id");
    }
    header('Location: gallery.php?msg=' . urlencode('Photo updated successfully!')); exit();
}

// ── Delete ──────────────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $id  = intval($_GET['delete']);
    $own = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM gallery WHERE id=$id AND employee_id=$user_id"));
    if ($own) {
        $file = "../uploads/gallery/" . $own['image_path'];
        if (file_exists($file)) unlink($file);
        mysqli_query($conn, "DELETE FROM gallery WHERE id=$id");
        header('Location: gallery.php?msg=' . urlencode('Photo deleted successfully!')); exit();
    }
    header('Location: gallery.php?err=' . urlencode('Photo not found or permission denied.')); exit();
}

// ── Flash messages ──────────────────────────────────────────────────────────
$success = htmlspecialchars($_GET['msg'] ?? '');
$error   = htmlspecialchars($_GET['err'] ?? '');

// ── Gallery data ────────────────────────────────────────────────────────────
$gallery = mysqli_query($conn, "SELECT g.*, e.name, e.employee_id AS emp_code
    FROM gallery g JOIN employees e ON g.employee_id=e.id
    WHERE g.status='active' ORDER BY g.created_at DESC");
$photos = [];
while ($r = mysqli_fetch_assoc($gallery)) $photos[] = $r;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
<title>Company Gallery — IPINFRA HRM</title>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
* { font-family: 'Inter', sans-serif; }
.gallery-card { transition: transform .2s, box-shadow .2s; }
.gallery-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px -8px rgba(0,0,0,.18); }
</style>
</head>
<body class="bg-slate-50 min-h-screen pb-24">

<?php require_once '../includes/global_ui.php'; ?>
<?php require_once '../includes/confirm_modal.php'; ?>

<!-- Header -->
<div class="sticky top-0 z-40 bg-[#060912] text-white shadow-2xl">
    <div class="flex items-center justify-between px-4 py-3.5">
        <div class="flex items-center gap-3">
            <button onclick="toggleSidebar()" class="w-9 h-9 rounded-xl bg-white/10 hover:bg-white/20 flex items-center justify-center transition"><i class="fas fa-bars"></i></button>
            <div class="w-9 h-9 bg-gradient-to-br from-blue-400 to-indigo-600 rounded-xl flex items-center justify-center text-sm font-bold shadow-lg">IN</div>
            <div class="hidden sm:block">
                <p class="text-[10px] text-blue-300 font-medium tracking-widest uppercase">IPINFRA Networks</p>
                <p class="text-sm font-bold">Employee Portal</p>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/employee_sidebar.php'; ?>

<div class="max-w-7xl mx-auto px-4 py-6 space-y-5">

    <!-- Page header -->
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Company Gallery</h1>
            <p class="text-sm text-gray-500 mt-0.5">Share moments from company activities and events</p>
        </div>
        <button onclick="openUploadModal()"
                class="inline-flex items-center gap-2 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 text-white px-5 py-2.5 rounded-xl font-semibold text-sm shadow-md hover:shadow-lg transition">
            <i class="fas fa-cloud-upload-alt"></i>Upload Photo
        </button>
    </div>

    <!-- Flash messages -->
    <?php if ($success): ?>
    <div class="bg-green-50 border border-green-200 rounded-2xl px-5 py-3 flex items-center gap-3">
        <i class="fas fa-check-circle text-green-500"></i>
        <p class="text-sm font-medium text-green-700"><?php echo $success; ?></p>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="bg-red-50 border border-red-200 rounded-2xl px-5 py-3 flex items-center gap-3">
        <i class="fas fa-exclamation-circle text-red-400"></i>
        <p class="text-sm font-medium text-red-600"><?php echo $error; ?></p>
    </div>
    <?php endif; ?>

    <!-- Gallery grid -->
    <?php if (empty($photos)): ?>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-16 text-center">
        <div class="w-20 h-20 bg-indigo-50 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fas fa-images text-indigo-200 text-3xl"></i>
        </div>
        <p class="font-semibold text-gray-600">No photos yet</p>
        <p class="text-sm text-gray-400 mt-1">Be the first to upload a moment!</p>
        <button onclick="openUploadModal()" class="mt-4 inline-flex items-center gap-2 bg-indigo-600 text-white px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-indigo-700 transition">
            <i class="fas fa-plus"></i>Upload Now
        </button>
    </div>
    <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
        <?php foreach ($photos as $photo):
            $img_path = "../uploads/gallery/" . $photo['image_path'];
            $is_owner = (int)$photo['employee_id'] === (int)$user_id;
            $initials = strtoupper(substr($photo['name'], 0, 1));
        ?>
        <div class="gallery-card bg-white rounded-2xl overflow-hidden border border-gray-100 shadow-sm">
            <!-- Image -->
            <div class="relative h-52 bg-gray-100">
                <?php if (file_exists($img_path)): ?>
                <img src="<?php echo htmlspecialchars($img_path); ?>"
                     alt="<?php echo htmlspecialchars($photo['caption']); ?>"
                     class="w-full h-full object-cover cursor-pointer"
                     onclick="openLightbox('<?php echo htmlspecialchars($img_path); ?>','<?php echo htmlspecialchars(addslashes($photo['caption'])); ?>')">
                <?php else: ?>
                <div class="w-full h-full flex items-center justify-center">
                    <i class="fas fa-image text-4xl text-gray-300"></i>
                </div>
                <?php endif; ?>
                <?php if ($is_owner): ?>
                <span class="absolute top-2 left-2 bg-indigo-600/80 backdrop-blur-sm text-white text-[10px] font-bold px-2 py-0.5 rounded-full">Mine</span>
                <?php endif; ?>
            </div>

            <!-- Card footer -->
            <div class="px-4 py-3">
                <?php if ($photo['caption']): ?>
                <p class="text-sm text-gray-700 font-medium line-clamp-2 mb-2"><?php echo htmlspecialchars($photo['caption']); ?></p>
                <?php endif; ?>
                <div class="flex items-center justify-between text-xs text-gray-400">
                    <span class="flex items-center gap-1.5">
                        <span class="w-5 h-5 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center font-bold text-[9px]"><?php echo $initials; ?></span>
                        <?php echo htmlspecialchars($photo['name']); ?>
                    </span>
                    <span><?php echo date('d M Y', strtotime($photo['activity_date'])); ?></span>
                </div>
                <!-- Download — visible to everyone -->
                <?php if (file_exists($img_path)): ?>
                <div class="mt-3 pt-3 border-t border-gray-100">
                    <a href="../uploads/gallery/<?php echo htmlspecialchars($photo['image_path']); ?>"
                       download="<?php echo htmlspecialchars($photo['name'] . '_' . date('d-M-Y', strtotime($photo['activity_date'])) . '.' . pathinfo($photo['image_path'], PATHINFO_EXTENSION)); ?>"
                       class="flex items-center justify-center gap-1.5 py-1.5 rounded-lg bg-green-50 hover:bg-green-100 text-green-600 text-xs font-semibold transition w-full">
                        <i class="fas fa-download text-[11px]"></i>Download
                    </a>
                </div>
                <?php endif; ?>
                <?php if ($is_owner): ?>
                <div class="flex gap-2 mt-2">
                    <button onclick='openEditModal(<?php echo json_encode([
                        "id"            => $photo["id"],
                        "caption"       => $photo["caption"],
                        "activity_date" => $photo["activity_date"],
                    ]); ?>)'
                            class="flex-1 flex items-center justify-center gap-1.5 py-1.5 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-600 text-xs font-semibold transition">
                        <i class="fas fa-pencil-alt text-[11px]"></i>Edit
                    </button>
                    <a href="?delete=<?php echo $photo['id']; ?>"
                       data-confirm="Delete this photo permanently? This cannot be undone." data-confirm-title="Delete Photo"
                       class="flex-1 flex items-center justify-center gap-1.5 py-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-500 text-xs font-semibold transition">
                        <i class="fas fa-trash text-[11px]"></i>Delete
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ── Upload Modal ─────────────────────────────────────────────────────── -->
<div id="uploadModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl overflow-hidden">
        <div class="h-1 bg-gradient-to-r from-indigo-500 to-blue-500"></div>
        <div class="px-6 py-5">
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-indigo-100 rounded-xl flex items-center justify-center"><i class="fas fa-cloud-upload-alt text-indigo-600"></i></div>
                    <h2 class="font-bold text-gray-800">Upload Photo</h2>
                </div>
                <button onclick="closeUploadModal()" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 transition"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Photo <span class="text-red-400">*</span></label>
                    <label class="flex flex-col items-center gap-2 border-2 border-dashed border-gray-200 hover:border-indigo-400 rounded-xl p-5 cursor-pointer transition group" for="uploadPhotoFile">
                        <i class="fas fa-image text-2xl text-gray-300 group-hover:text-indigo-400 transition"></i>
                        <span class="text-sm text-gray-400 group-hover:text-indigo-500 transition" id="uploadFileLabel">Click to select image</span>
                        <span class="text-[10px] text-gray-300">JPG, PNG, GIF, WEBP</span>
                    </label>
                    <input type="file" id="uploadPhotoFile" name="photo" accept="image/*" required class="hidden"
                           onchange="document.getElementById('uploadFileLabel').textContent=this.files[0]?.name||'Click to select image'">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Caption</label>
                    <textarea name="caption" rows="2" placeholder="Describe this moment…" class="w-full px-4 py-2.5 border-1.5 border-gray-200 rounded-xl text-sm outline-none focus:border-indigo-400 border border-gray-200 transition"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Activity Date</label>
                    <input type="date" name="activity_date" value="<?php echo date('Y-m-d'); ?>" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm outline-none focus:border-indigo-400 transition">
                </div>
                <div class="flex gap-3 pt-1">
                    <button type="submit" name="upload_photo"
                            class="flex-1 bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 text-white py-3 rounded-xl font-semibold text-sm transition shadow-md">
                        <i class="fas fa-paper-plane mr-1"></i>Upload
                    </button>
                    <button type="button" onclick="closeUploadModal()" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 py-3 rounded-xl font-semibold text-sm transition">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Edit Modal ───────────────────────────────────────────────────────── -->
<div id="editModal" class="hidden fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl overflow-hidden">
        <div class="h-1 bg-gradient-to-r from-amber-400 to-orange-500"></div>
        <div class="px-6 py-5">
            <div class="flex items-center justify-between mb-5">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 bg-amber-100 rounded-xl flex items-center justify-center"><i class="fas fa-pencil-alt text-amber-600"></i></div>
                    <h2 class="font-bold text-gray-800">Edit Photo</h2>
                </div>
                <button onclick="closeEditModal()" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 text-gray-400 transition"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST" enctype="multipart/form-data" class="space-y-4" id="editForm">
                <input type="hidden" name="photo_id" id="editPhotoId">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Replace Photo (optional)</label>
                    <label class="flex flex-col items-center gap-2 border-2 border-dashed border-gray-200 hover:border-amber-400 rounded-xl p-4 cursor-pointer transition group" for="editPhotoFile">
                        <i class="fas fa-sync-alt text-xl text-gray-300 group-hover:text-amber-400 transition"></i>
                        <span class="text-sm text-gray-400 group-hover:text-amber-500 transition" id="editFileLabel">Keep existing / click to replace</span>
                    </label>
                    <input type="file" id="editPhotoFile" name="photo" accept="image/*" class="hidden"
                           onchange="document.getElementById('editFileLabel').textContent=this.files[0]?.name||'Keep existing / click to replace'">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Caption</label>
                    <textarea name="caption" id="editCaption" rows="2" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm outline-none focus:border-amber-400 transition"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">Activity Date</label>
                    <input type="date" name="activity_date" id="editDate" class="w-full px-4 py-2.5 border border-gray-200 rounded-xl text-sm outline-none focus:border-amber-400 transition">
                </div>
                <div class="flex gap-3 pt-1">
                    <button type="submit" name="edit_photo"
                            class="flex-1 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white py-3 rounded-xl font-semibold text-sm transition shadow-md">
                        <i class="fas fa-save mr-1"></i>Save Changes
                    </button>
                    <button type="button" onclick="closeEditModal()" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 py-3 rounded-xl font-semibold text-sm transition">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Lightbox ─────────────────────────────────────────────────────────── -->
<div id="lightbox" class="hidden fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4" onclick="closeLightbox()">
    <button class="absolute top-4 right-4 text-white/70 hover:text-white w-10 h-10 flex items-center justify-center rounded-full bg-white/10 hover:bg-white/20 transition" onclick="closeLightbox()">
        <i class="fas fa-times text-lg"></i>
    </button>
    <img id="lightboxImg" src="" alt="" class="max-w-full max-h-full rounded-xl shadow-2xl object-contain" onclick="event.stopPropagation()">
    <p id="lightboxCaption" class="absolute bottom-6 left-0 right-0 text-center text-white/80 text-sm px-8"></p>
</div>

<!-- Mobile Bottom Nav -->
<div class="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-lg border-t border-gray-100 md:hidden shadow-xl z-30">
    <div class="flex justify-around py-2">
        <a href="dashboard.php" class="flex flex-col items-center py-2 px-3 text-gray-400 hover:text-indigo-600 transition"><i class="fas fa-home text-xl"></i><span class="text-[10px] mt-1">Home</span></a>
        <a href="leave.php"     class="flex flex-col items-center py-2 px-3 text-gray-400 hover:text-indigo-600 transition"><i class="fas fa-calendar-check text-xl"></i><span class="text-[10px] mt-1">Leave</span></a>
        <a href="gallery.php"   class="flex flex-col items-center py-2 px-3 text-indigo-600"><i class="fas fa-images text-xl"></i><span class="text-[10px] mt-1 font-semibold">Gallery</span></a>
        <a href="profile.php"   class="flex flex-col items-center py-2 px-3 text-gray-400 hover:text-indigo-600 transition"><i class="fas fa-user-circle text-xl"></i><span class="text-[10px] mt-1">Profile</span></a>
    </div>
</div>

<script>

function openUploadModal()  { document.getElementById('uploadModal').classList.remove('hidden'); }
function closeUploadModal() { document.getElementById('uploadModal').classList.add('hidden'); }

function openEditModal(data) {
    document.getElementById('editPhotoId').value  = data.id;
    document.getElementById('editCaption').value  = data.caption;
    document.getElementById('editDate').value     = data.activity_date;
    document.getElementById('editFileLabel').textContent = 'Keep existing / click to replace';
    document.getElementById('editPhotoFile').value = '';
    document.getElementById('editModal').classList.remove('hidden');
}
function closeEditModal() { document.getElementById('editModal').classList.add('hidden'); }

function openLightbox(src, caption) {
    document.getElementById('lightboxImg').src          = src;
    document.getElementById('lightboxCaption').textContent = caption;
    document.getElementById('lightbox').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('lightbox').classList.add('hidden');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeLightbox(); closeEditModal(); closeUploadModal(); } });
</script>
</body>
</html>
