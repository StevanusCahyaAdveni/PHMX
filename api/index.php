<?php
// Set Global Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-TOKEN, X-Requested-With');

// Fungsi standardisasi respons JSON
function response($success, $message, $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data'    => $data
    ]);
    exit;
}

// Menangani request preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load PHMX Core Functions
require_once __DIR__ . '/../functions/index.php';

// Tangkap Endpoint
$endpoint = $_GET['endpoint'] ?? '';
$endpoint = str_replace(['..', '\\', "\0"], '', $endpoint);
$endpoint = trim($endpoint, '/');

if (empty($endpoint)) {
    http_response_code(400);
    response(false, 'API Endpoint is required');
}

// Validasi CSRF Token untuk request berbasis session web (stateless/token-based API dilewati)
$auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
$is_stateless_api = !empty($auth_header) || ($endpoint === 'auth/mobile_login');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && !$is_stateless_api) {
    $client_token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $session_token = $_SESSION['csrf_token'] ?? '';
    
    if (!empty($session_token) && (empty($client_token) || !hash_equals($session_token, $client_token))) {
        http_response_code(403);
        response(false, 'CSRF Token Invalid atau Kedaluwarsa');
    }
}

// Terapkan Middleware dengan mencocokkan pola rute berawalan 'api/'
$api_route = 'api/' . $endpoint;
$routes_config = require __DIR__ . '/../middleware/routes.php';
foreach ($routes_config as $route_pattern => $middlewares) {
    if (fnmatch($route_pattern, $api_route)) {
        foreach ((array)$middlewares as $mw) {
            $mw_parts = explode(':', $mw);
            $mw_name = $mw_parts[0];
            $mw_param = $mw_parts[1] ?? null;
            
            $mw_file = __DIR__ . "/../middleware/{$mw_name}.php";
            if (file_exists($mw_file)) {
                require $mw_file;
            }
        }
        break; 
    }
}

// Panggil file logic dari folder methods
$method_file = __DIR__ . "/methods/{$endpoint}.php";

if (file_exists($method_file)) {
    // File di dalam methods/ cukup menggunakan fungsi response()
    require $method_file;
} else {
    http_response_code(404);
    response(false, 'API Endpoint Not Found');
}
?>
