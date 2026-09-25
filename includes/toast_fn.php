<?php
// PHP-only helper — safe to include before any header() calls.
// The HTML/CSS/JS toast renderer is in toast.php (include inside <body>).
if (!function_exists('showToast')) {
    function showToast($message, $type = 'success') {
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['toast'] = [
            // Stored raw: toast.php renders via textContent, so pre-escaping here displayed
            // literal "&#039;" for any message containing an apostrophe or ampersand.
            'message' => (string)$message,
            'type'    => in_array($type, ['success','error','warning','info']) ? $type : 'success',
        ];
    }
}
