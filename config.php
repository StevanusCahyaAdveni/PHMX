<?php
// 1. Amankan parameter cookie sesi PHP sebelum session_start (Mencegah pencurian cookie via XSS & Mitigasi CSRF)
if (session_status() === PHP_SESSION_NONE) {
    $is_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $is_https,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}


// Deteksi environment (Local vs Production)
$is_localhost = true;
if(isset($_SERVER['HTTP_HOST'])){
    if (strpos($_SERVER['HTTP_HOST'], 'localhost') === false) {
        $is_localhost = false;
    }
} else {
    if (strpos(__DIR__, 'xampp') === false && strpos(__DIR__, 'htdocs') === false) {
        $is_localhost = false;
    }
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
$GLOBALS['con'] = $con;

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
