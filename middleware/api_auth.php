<?php
// middleware/api_auth.php

// 1. Ekstraksi Header Authorization
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;

if (!$auth_header && function_exists('apache_request_headers')) {
    $headers = apache_request_headers();
    $auth_header = $headers['Authorization'] ?? $headers['authorization'] ?? null;
}

if (!$auth_header || !preg_match('/^Bearer\s+(\S+)$/i', $auth_header, $matches)) {
    http_response_code(401);
    response(false, 'Unauthorized: Bearer token missing or invalid format');
}

$token = $matches[1];

// 2. Verifikasi Token di Database
$user_res = querySecure($con, "SELECT id, fullname, username, email, role, photo_profile FROM users WHERE api_token = ? AND api_token IS NOT NULL LIMIT 1", [$token], 's');
$auth_user = $user_res ? mysqli_fetch_assoc($user_res) : null;

if (!$auth_user) {
    http_response_code(401);
    response(false, 'Unauthorized: Invalid or expired API token');
}

// 3. Simpan data user yang terautentikasi ke scope global / variabel
$GLOBALS['auth_user'] = $auth_user;
?>
