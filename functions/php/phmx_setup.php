<?php
/**
 * 2. Fungsi Setup Routing dan Middleware PHMX
 * Panggil fungsi ini di awal file index.php utama
 */
function phmx_setup($default_page = 'welcome') {
    global $hal, $base_url;
    
    // Setup routing var
    $hal = $_GET['hal'] ?? $default_page;
    if ($hal === '') $hal = $default_page;
    
    // Tentukan Base URL agar relative link berfungsi dengan baik
    $base_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
    
    // Centralized Route Middleware Logic
    $routes_config = require __DIR__ . '/../../middleware/routes.php';
    foreach ($routes_config as $route_pattern => $middlewares) {
        if (fnmatch($route_pattern, $hal)) {
            foreach ((array)$middlewares as $mw) {
                $mw_parts = explode(':', $mw);
                $mw_name = $mw_parts[0];
                $mw_param = $mw_parts[1] ?? null;
                
                $mw_file = __DIR__ . "/../../middleware/{$mw_name}.php";
                if (file_exists($mw_file)) {
                    require $mw_file;
                }
            }
            break; // Hanya jalankan konfigurasi rute pertama yang cocok
        }
    }
}
?>
