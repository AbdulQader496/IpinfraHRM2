<?php
// PHP-only helper — safe to include before any header() calls.
// The HTML/CSS/JS toast renderer is in toast.php (include inside <body>).
if (!function_exists('showToast')) {
    function showToast($message, $type = 'success') {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['toast'] = [
            'message' => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'),
            'type'    => in_array($type, ['success','error','warning','info']) ? $type : 'success',
        ];
    }
}
