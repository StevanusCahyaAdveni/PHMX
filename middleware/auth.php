<?php
// middleware/auth.php
// Karena fungsi ini dipanggil setelah config.php di index.php, session sudah berjalan.
if (!isset($_SESSION['user_id'])) {
    // Pastikan $base_url sudah didefinisikan (di index.php)
    $redirect_url = (isset($base_url) ? $base_url : '/') . 'auth/login';
    
    // Jika request datang dari HTMX
    if (isset($_SERVER['HTTP_HX_REQUEST'])) {
        require_once __DIR__ . '/../functions/php/redirect.php';
        htmxRedirectWithMessage('auth/login', 'Sesi Anda telah berakhir. Silakan login kembali.', 'danger', 1000);
    } else {
        // Jika request langsung melalui browser
        header('Location: ' . $redirect_url);
    }
    exit;
}
?>
