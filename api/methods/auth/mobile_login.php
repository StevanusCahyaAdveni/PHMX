<?php
// api/methods/auth/mobile_login.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    response(false, 'Metode request tidak diizinkan. Gunakan POST.');
}

// Dukung format form-data/x-www-form-urlencoded dan application/json
$input = $_POST;
if (empty($input) && ($json = file_get_contents('php://input'))) {
    $decoded = json_decode($json, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

$identity = sani($input['email'] ?? $input['username'] ?? $input['identity'] ?? '');
$password = $input['password'] ?? '';

if (empty($identity) || empty($password)) {
    http_response_code(400);
    response(false, 'Email/username dan password wajib diisi');
}

// Cari user berdasarkan email atau username
$result = querySecure($con, "SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1", [$identity, $identity], 'ss');
$user = $result ? mysqli_fetch_assoc($result) : null;

if ($user && password_verify($password, $user['password'])) {
    // Generate secure 64-character token
    $token = bin2hex(random_bytes(32));
    
    // Simpan token ke database
    $updated = executeSecure($con, "UPDATE users SET api_token = ? WHERE id = ?", [$token, $user['id']], 'ss');
    
    if (!$updated) {
        http_response_code(500);
        response(false, 'Gagal membuat sesi token API');
    }
    
    response(true, 'Login berhasil', [
        'token' => $token,
        'user'  => [
            'id'            => $user['id'],
            'fullname'      => $user['fullname'],
            'username'      => $user['username'],
            'email'         => $user['email'],
            'role'          => $user['role'],
            'telp_number'   => $user['telp_number'],
            'photo_profile' => $user['photo_profile']
        ]
    ]);
} else {
    http_response_code(401);
    response(false, 'Kredensial login tidak valid');
}
?>
