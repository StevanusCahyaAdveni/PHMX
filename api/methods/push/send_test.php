<?php
// api/methods/push/send_test.php

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

$title = sani($input['title'] ?? '🔔 PHMX Push Notification');
$body  = sani($input['body'] ?? 'Halo! Notifikasi VAPID Web Push dari server PHMX berhasil diterima di perangkat Anda.');
$url   = sani($input['url'] ?? './');

// Ambil subskripsi (jika user login, utamakan miliknya, atau kirim ke semua device terdaftar)
$user_id = $_SESSION['user_id'] ?? ($GLOBALS['auth_user']['id'] ?? null);

if ($user_id) {
    $res = querySecure($con, "SELECT endpoint, p256dh, auth FROM push_subscriptions WHERE user_id = ?", [$user_id], 's');
} else {
    $res = querySecure($con, "SELECT endpoint, p256dh, auth FROM push_subscriptions ORDER BY created_at DESC LIMIT 5");
}

$payload = [
    'title' => $title,
    'body'  => $body,
    'icon'  => 'assets/images/logo/phmx-mark.svg',
    'badge' => 'assets/images/logo/phmx-mark.svg',
    'data'  => [
        'url' => $url
    ]
];

$subscriptions = [];
if ($res) {
    while ($sub = mysqli_fetch_assoc($res)) {
        $subscriptions[] = $sub;
    }
}

if (empty($subscriptions)) {
    response(false, 'Belum ada perangkat yang mengaktifkan notifikasi. Klik tombol "Aktifkan Notifikasi" terlebih dahulu.');
}

// Opsi antrean (Queue Mode) jika diminta via parameter ?queue=1 atau JSON payload {"queue": true}
$useQueue = isset($_GET['queue']) || !empty($input['queue']);

if ($useQueue) {
    $queuedCount = queueWebPushBatch($con, $subscriptions, $payload);
    response(true, "Notifikasi berhasil dimasukkan ke antrean ({$queuedCount} perangkat) untuk diproses di latar belakang!", [
        'queued_count' => $queuedCount,
        'mode'         => 'queue'
    ]);
}

// Mode Pengiriman Paralel Cepat (curl_multi_exec)
$batchResult = sendWebPushBatch($subscriptions, $payload);

$logs = [];
foreach ($batchResult['results'] as $r) {
    if (!$r['success']) {
        $logs[] = $r['error'];
    }
}

response(true, "Notifikasi berhasil dikirim secara paralel ke {$batchResult['success_count']} perangkat!", [
    'total'         => $batchResult['total'],
    'success_count' => $batchResult['success_count'],
    'fail_count'    => $batchResult['fail_count'],
    'errors'        => $logs,
    'mode'          => 'parallel_curl_multi'
]);
?>
