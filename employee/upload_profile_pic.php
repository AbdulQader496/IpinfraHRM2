<?php
require_once '../includes/auth.php';
redirectIfNotLoggedIn();
require_once '../includes/db.php';

$user_id = $_SESSION['user_id'];

if (isset($_POST['update_profile_pic']) && isset($_FILES['profile_pic'])) {
    $target_dir = "../uploads/profiles/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);

    $allowed_img_ext  = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $allowed_img_mime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $file_extension = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
    $mime = mime_content_type($_FILES['profile_pic']['tmp_name']);

    if (!in_array($file_extension, $allowed_img_ext) || !in_array($mime, $allowed_img_mime)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type.']);
        exit();
    }

    $new_filename = time() . '_' . $_SESSION['employee_id'] . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;

    if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_file)) {
        // Delete old profile picture
        $query = "SELECT profile_pic FROM employees WHERE id = $user_id";
        $result = mysqli_query($conn, $query);
        $employee = mysqli_fetch_assoc($result);
        if (!empty($employee['profile_pic']) && file_exists($target_dir . $employee['profile_pic'])) {
            unlink($target_dir . $employee['profile_pic']);
        }
        
        // Update database
        $update = "UPDATE employees SET profile_pic = '$new_filename' WHERE id = $user_id";
        mysqli_query($conn, $update);
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
} else {
    echo json_encode(['success' => false]);
}
?>