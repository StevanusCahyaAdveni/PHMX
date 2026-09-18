<?php
// api/methods/auth/mobile_logout.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    response(false, 'Metode request tidak diizinkan. Gunakan POST.');
}

$user = $GLOBALS['auth_user'] ?? null;

if (!$user || empty($user['id'])) {
    http_response_code(401);
    response(false, 'Unauthorized: User context tidak ditemukan');
}

// Invalidate token
executeSecure($con, "UPDATE users SET api_token = NULL WHERE id = ?", [$user['id']], 's');

response(true, 'Logout berhasil');
?>
