<?php
// Hapus semua data sesi
session_unset();
session_destroy();

// Hapus session cookie jika ada
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
// Bersihkan kredensial Remember Me dari LocalStorage
echo "<script>
    localStorage.removeItem('phmx_email');
    localStorage.removeItem('phmx_pwd');
</script>";

htmxRedirectWithMessage('welcome', 'Anda telah berhasil logout!', 'success');
exit;
?>
