<?php
/**
 * Role Middleware
 * Memeriksa apakah pengguna memiliki salah satu role yang diizinkan.
 * Parameter dipisahkan dengan koma, contoh: role:admin,editor
 */

// Pastikan user sudah login terlebih dahulu (biasanya ini sudah di-handle oleh middleware 'auth', 
// tapi kita double check untuk keamanan).
if (!isset($_SESSION['user_id'])) {
    $redirect_url = (isset($base_url) ? $base_url : '/') . 'auth/login';
    if (isset($_SERVER['HTTP_HX_REQUEST'])) header('HX-Redirect: ' . $redirect_url);
        else header('Location: ' . $redirect_url);
    exit;
}

// $mw_param berasal dari index.php / htmx_request.php (contoh: 'admin' atau 'admin,superadmin')
$allowed_roles = isset($mw_param) && $mw_param !== '' ? explode(',', $mw_param) : [];
$user_role = $_SESSION['role'] ?? '';

// Cek apakah rute ini mensyaratkan role tertentu, dan apakah role user saat ini cocok
if (!empty($allowed_roles) && !in_array($user_role, $allowed_roles)) {
    if (isset($_SERVER['HTTP_HX_REQUEST'])) {
        // Jika request via HTMX (misal klik tombol Hapus atau klik Navigasi SPA)
        echo "<div class='container mt-5'>
                <div class='alert alert-danger shadow-sm border-0'>
                    <h4 class='alert-heading'>Akses Ditolak!</h4>
                    <p>Anda (<strong>" . htmlspecialchars($user_role) . "</strong>) tidak memiliki izin untuk mengakses aksi/halaman ini.</p>
                    <hr>
                    <p class='mb-0 small'>Role yang dibutuhkan: " . htmlspecialchars($mw_param) . "</p>
                </div>
              </div>";
    } else {
        // Jika request via browser langsung (Direct Hit)
        http_response_code(403);
        echo "<div style='font-family: sans-serif; padding: 50px; text-align: center;'>
                <h1 style='color: #dc3545;'>403 - Forbidden</h1>
                <p>Akses Ditolak. Anda tidak memiliki izin yang memadai.</p>
                <a href='" . (isset($base_url) ? $base_url : '/') . "' style='padding: 10px 20px; background: #0d6efd; color: white; text-decoration: none; border-radius: 5px;'>Kembali ke Beranda</a>
              </div>";
    }
    exit; // Hentikan eksekusi script halaman utamanya!
}
?>
