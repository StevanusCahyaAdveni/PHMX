<?php
// actions/auth/register.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = sani($_POST['fullname'] ?? '');
    $username = sani($_POST['username'] ?? '');
    $email = sani($_POST['email'] ?? '');
    $telp = sani($_POST['telp_number'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($fullname) || empty($username) || empty($email) || empty($password)) {
        htmxMessage('Semua kolom wajib diisi!', 'danger', ['fullname', 'username', 'email', 'telp_number']);
    }
    
    // Cek ketersediaan username atau email
    $check = querySecure($con, "SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email], 'ss');
    if ($check && mysqli_num_rows($check) > 0) {
        htmxMessage('Username atau Email sudah terdaftar!', 'warning', ['fullname', 'username', 'email', 'telp_number']);
    }
    
    $id = generate_uuid();
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    $success = executeSecure($con, 
        "INSERT INTO users (id, fullname, username, email, telp_number, password) VALUES (?, ?, ?, ?, ?, ?)",
        [$id, $fullname, $username, $email, $telp, $hashedPassword],
        'ssssss'
    );
    
    if ($success) {
        htmxRedirectWithMessage('#auth/login', 'Pendaftaran berhasil! Mengalihkan ke form login...', 'success', 1500);
    } else {
        htmxMessage('Terjadi kesalahan pada server saat mendaftar.', 'danger', ['fullname', 'username', 'email', 'telp_number']);
    }
}
?>
