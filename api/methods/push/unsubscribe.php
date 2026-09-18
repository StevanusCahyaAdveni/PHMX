<?php
// api/methods/push/unsubscribe.php

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

if (empty($endpoint)) {
    http_response_code(400);
    response(false, 'Endpoint subskripsi wajib diisi.');
}

executeSecure($con, "DELETE FROM push_subscriptions WHERE endpoint = ?", [$endpoint], 's');

response(true, 'Subskripsi perangkat berhasil dihapus.');
?>
