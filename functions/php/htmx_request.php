<?php
// 1. Tangkap Request Action (Misal: Form POST)
if (isset($_GET['act'])) {
    
    // Validasi CSRF Token Global
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        $client_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $session_token = $_SESSION['csrf_token'] ?? '';
        
        if (empty($client_token) || !hash_equals($session_token, $client_token)) {
            // Kita gunakan htmxMessage bawaan untuk menampilkan pesan
            echo "<div class='alert alert-danger'>CSRF Token Invalid atau Kedaluwarsa! Silakan muat ulang halaman.</div>";
            exit;
        }
    }

    $act = $_GET['act'];
    $act = str_replace(['..', '\\', "\0"], '', $act);
    
    // Tentukan Base URL agar relative link berfungsi dengan baik
    global $base_url;
    $base_url = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/';
    
    // Centralized Route Middleware Logic
    $routes_config = require __DIR__ . '/../../middleware/routes.php';
    foreach ($routes_config as $route_pattern => $middlewares) {
        if (fnmatch($route_pattern, $act)) {
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

    $file = __DIR__ . '/../../actions/' . $act . '.php';
    
    if (file_exists($file)) {
        require $file;
    } else {
        http_response_code(404);
        echo "Action Not Found";
    }
    exit;
}
?>
