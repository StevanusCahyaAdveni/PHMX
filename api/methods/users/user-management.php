<?php
// api/methods/users/user-management.php

global $con;
$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

switch ($method) {
    case 'GET':
        if ($id) {
            $res = querySecure($con, "SELECT id, fullname, username, email, telp_number, role, photo_profile, created_at FROM users WHERE id = ?", [$id], 's');
            $user = $res ? mysqli_fetch_assoc($res) : null;
            if ($user) {
                response(true, 'User data retrieved', $user);
            } else {
                http_response_code(404);
                response(false, 'User not found');
            }
        } else {
            $search = sani($_GET['search'] ?? '');
            if (!empty($search)) {
                $res = querySecure($con, "SELECT id, fullname, username, email, telp_number, role, photo_profile, created_at FROM users WHERE fullname LIKE ? OR email LIKE ? OR username LIKE ? ORDER BY created_at DESC", ["%{$search}%", "%{$search}%", "%{$search}%"], 'sss');
            } else {
                $res = querySecure($con, "SELECT id, fullname, username, email, telp_number, role, photo_profile, created_at FROM users ORDER BY created_at DESC");
            }
            
            $data = [];
            if ($res) {
                while ($r = mysqli_fetch_assoc($res)) {
                    $data[] = $r;
                }
            }
            response(true, 'Users list retrieved', $data);
        }
        break;
        
    default:
        http_response_code(405);
        response(false, 'Method not allowed');
        break;
}
?>
