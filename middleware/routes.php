<?php
/**
 * Konfigurasi Rute Terpusat (Centralized Route Configuration)
 * 
 * Tambahkan nama rute (path) yang memerlukan akses login (Protected) di sini.
 * Anda dapat menggunakan wildcard (*) untuk melindungi seluruh folder.
 * 
 * Aturan:
 * - Rute Web/Pages/Actions: 'users/user-management', 'auth/login'
 * - Rute API (api/index.php): 'api/users/*', 'api/auth/mobile_login'
 */

return [
    // Web & Action Routes (Session & HTMX)
    'users/user-management'   => ['auth'],          // Hanya admin/user login yang bisa akses web
    'auth/login'              => ['throttle:5,1'],  // Maksimal 5 percobaan dalam 1 menit

    // API Routes (REST & Mobile)
    'api/auth/mobile_login'   => ['throttle:10,1'], // Public API dengan rate limit
    'api/auth/silent_login'   => ['throttle:10,1'], // Background silent login dengan rate limit
    'api/auth/mobile_logout'  => ['api_auth'],      // Memerlukan Bearer Token
    'api/users/*'             => ['api_auth'],      // Semua API users dilindungi Bearer Token
];
?>
