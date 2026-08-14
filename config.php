<?php
session_start();

$host = 'localhost';
$user = 'root';
$pass = ''; // Sesuaikan jika ada password MySQL
$db = 'phmx-framework'; // Ubah sesuai nama database Anda

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
?>
