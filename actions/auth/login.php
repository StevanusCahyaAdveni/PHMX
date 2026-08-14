<?php
// actions/auth/login.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sani($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        htmxMessage('Email dan password wajib diisi!', 'danger', ['email']);
    }
    
    // Cari user berdasarkan email
    $result = querySecure($con, "SELECT * FROM users WHERE email = ?", [$email], 's');
    $user = $result ? mysqli_fetch_assoc($result) : null;
    
    if ($user && password_verify($password, $user['password'])) {
        // Set session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['fullname'] = $user['fullname'];
        
        htmxRedirectWithMessage('users/user-management', 'Login berhasil! Sedang mengalihkan...', 'success', 1000);
    } else {
        htmxMessage('Email atau password yang Anda masukkan salah!', 'danger', ['email']);
    }
}
?>
