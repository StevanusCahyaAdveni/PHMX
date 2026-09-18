<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sani($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        response(false, 'Empty credentials');
    }
    
    // Cari user berdasarkan email
    $result = querySecure($con, "SELECT * FROM users WHERE email = ?", [$email], 's');
    $user = $result ? mysqli_fetch_assoc($result) : null;
    
    if ($user && password_verify($password, $user['password'])) {
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['fullname'] = $user['fullname'];
        
        response(true, 'Silent login successful');
    } else {
        response(false, 'Invalid credentials');
    }
}

response(false, 'Invalid method');
?>
