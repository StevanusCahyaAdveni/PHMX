<?php
// middleware/auth.php
// Karena fungsi ini dipanggil setelah config.php di index.php, session sudah berjalan.
if (!isset($_SESSION['user_id'])) {
    // Pastikan $base_url sudah didefinisikan (di index.php)
    $redirect_url = (isset($base_url) ? $base_url : '/') . 'auth/login';
    
    // Simpan pesan flash untuk ditampilkan di halaman login
    $_SESSION['flash_message'] = 'Silakan login terlebih dahulu untuk mengakses halaman tersebut.';
    $_SESSION['flash_type'] = 'warning';

    // Jika request datang dari HTMX (misal hx-boost link atau AJAX)
    if (isset($_SERVER['HTTP_HX_REQUEST'])) {
        header('HX-Redirect: ' . $redirect_url);
    } else {
        // Jika request langsung melalui browser
        header('Location: ' . $redirect_url);
    }
    exit;
}
?>
