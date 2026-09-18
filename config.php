<?php
session_start();


// Deteksi environment (Local vs Production)
$is_localhost = false;
if (php_sapi_name() === 'cli') {
    $is_localhost = true; // Beri akses lokal jika dipanggil lewat terminal (PHP CLI)
} elseif (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
    $is_localhost = true; // Akses web browser di localhost
}

if ($is_localhost) {
    // Mode Development (Localhost)
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $db   = 'phmx-framework';
} else {
    // Mode Production (Hosting/Server Asli)
    $host = '';
    $user = '';
    $pass = '';
    $db   = '';
}

$con = mysqli_connect($host, $user, $pass, $db);

if (!$con) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Generate Global CSRF Token jika belum ada di sesi
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) {
        $_SESSION['csrf_token'] = bin2hex(uniqid('phmx', true));
    }
}

// ==========================================
// PWA & Web Push Notification Configuration
// ==========================================
$pwa_enabled = true; // true = Aktifkan PWA & Service Worker, false = Matikan

$vapid_config = [
    'subject'     => 'mailto:admin@phmx.local',
    'public_key'  => '',
    'private_key' => '',
];
?>
