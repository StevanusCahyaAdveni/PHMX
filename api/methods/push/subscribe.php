<?php
// api/methods/push/subscribe.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    response(false, 'Metode request tidak diizinkan. Gunakan POST.');
}

$input = $_POST;
if (empty($input) && ($json = file_get_contents('php://input'))) {
    $decoded = json_decode($json, true);
    if (is_array($decoded)) {
        $input = $decoded;
    }
}

$endpoint = sani($input['endpoint'] ?? '');
$keys = $input['keys'] ?? [];
$p256dh = sani($keys['p256dh'] ?? '');
$auth = sani($keys['auth'] ?? '');

if (empty($endpoint) || empty($p256dh) || empty($auth)) {
    http_response_code(400);
    response(false, 'Data subskripsi tidak lengkap (endpoint, p256dh, auth wajib diisi).');
}

$user_id = $_SESSION['user_id'] ?? ($GLOBALS['auth_user']['id'] ?? null);
$user_agent = sani($_SERVER['HTTP_USER_AGENT'] ?? '');

// Cek apakah endpoint sudah terdaftar sebelumnya
$check = querySecure($con, "SELECT id FROM push_subscriptions WHERE endpoint = ? LIMIT 1", [$endpoint], 's');
$existing = $check ? mysqli_fetch_assoc($check) : null;

if ($existing) {
    // Update data subskripsi dan user_id terbaru
    executeSecure($con, "UPDATE push_subscriptions SET user_id = ?, p256dh = ?, auth = ?, user_agent = ?, created_at = NOW() WHERE id = ?", 
        [$user_id, $p256dh, $auth, $user_agent, $existing['id']], 'sssss'
    );
} else {
    // Insert subskripsi perangkat baru
    $id = generate_uuid();
    executeSecure($con, "INSERT INTO push_subscriptions (id, user_id, endpoint, p256dh, auth, user_agent) VALUES (?, ?, ?, ?, ?, ?)",
        [$id, $user_id, $endpoint, $p256dh, $auth, $user_agent], 'ssssss'
    );
}

response(true, 'Perangkat berhasil didaftarkan untuk Push Notification');
?>
