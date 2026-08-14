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

htmxRedirectWithMessage('welcome', 'Anda telah berhasil logout!', 'success');
exit;
?>
