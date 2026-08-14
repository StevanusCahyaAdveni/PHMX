<?php
/**
 * Konfigurasi Rute Terpusat (Centralized Route Configuration)
 * 
 * Tambahkan nama rute (path) yang memerlukan akses login (Protected) di sini.
 * Anda dapat menggunakan wildcard (*) untuk melindungi seluruh folder.
 * 
 * Contoh:
 * 'users/user-management' -> Hanya mengamankan file user-management.php
 * 'admin/*'               -> Mengamankan SEMUA file di dalam folder admin
 */

return [
    'users/user-management' => ['auth'], // Hanya admin yang bisa manajemen user
    // 'generate-crud'         => ['auth', 'role:admin'], // Hanya admin yang bisa akses CRUD generator
    // 'dashboard'          => ['auth'],               // Contoh: semua user login bisa akses
];
?>
