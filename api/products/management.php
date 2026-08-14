<?php
// api/products/management.php
header("Content-Type: application/json");
// require_once __DIR__ . '/../../functions/index.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        echo json_encode(['status' => 'success', 'message' => 'API Endpoint for management is ready.']);
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        break;
}
?>