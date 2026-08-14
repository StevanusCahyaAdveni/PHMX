<?php
// actions/users/user-management.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_type = $_POST['action_type'] ?? '';
    
    // ==========================================
    // ACTION: ADD USER
    // ==========================================
    if ($action_type === 'add') {
        $fullname = sani($_POST['fullname'] ?? '');
        $username = sani($_POST['username'] ?? '');
        $email = sani($_POST['email'] ?? '');
        $telp_number = sani($_POST['telp_number'] ?? '');
        $role = sani($_POST['role'] ?? 'admin');
        $password = $_POST['password'] ?? '';
        
        if (empty($fullname) || empty($username) || empty($email) || empty($password)) {
            htmxMessage('Semua kolom wajib diisi!', 'danger', ['fullname', 'username', 'email', 'telp_number', 'role']);
        }
        
        // Cek duplikat
        $check = querySecure($con, "SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email], 'ss');
        if ($check && mysqli_num_rows($check) > 0) {
            htmxMessage('Username atau Email sudah terdaftar!', 'warning', ['fullname', 'username', 'email', 'telp_number', 'role']);
        }
        
        $id = generate_uuid();
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $success = executeSecure($con, 
            "INSERT INTO users (id, fullname, username, email, telp_number, role, password) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$id, $fullname, $username, $email, $telp_number, $role, $hashedPassword],
            'sssssss'
        );
        
        if ($success) {
            htmxRedirectWithMessage('users/user-management', 'Berhasil menambahkan pengguna baru!', 'success');
        } else {
            htmxMessage('Gagal menambahkan pengguna.', 'danger');
        }
    }
    
    // ==========================================
    // ACTION: UPDATE USER
    // ==========================================
    else if ($action_type === 'update') {
        $id = sani($_POST['id'] ?? '');
        $fullname = sani($_POST['fullname'] ?? '');
        $username = sani($_POST['username'] ?? '');
        $email = sani($_POST['email'] ?? '');
        $telp_number = sani($_POST['telp_number'] ?? '');
        $role = sani($_POST['role'] ?? 'admin');
        $password = $_POST['password'] ?? ''; // Boleh kosong
        
        if (empty($id) || empty($fullname) || empty($username) || empty($email)) {
            htmxMessage('ID, Nama, Username, dan Email wajib diisi!', 'danger');
        }
        
        // Cek duplikat tapi exlcude user ini sendiri
        $check = querySecure($con, "SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?", [$username, $email, $id], 'sss');
        if ($check && mysqli_num_rows($check) > 0) {
            htmxMessage('Username atau Email sudah digunakan pengguna lain!', 'warning');
        }
        
        if (!empty($password)) {
            // Update beserta password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $success = executeSecure($con, 
                "UPDATE users SET fullname=?, username=?, email=?, telp_number=?, role=?, password=? WHERE id=?",
                [$fullname, $username, $email, $telp_number, $role, $hashedPassword, $id],
                'sssssss'
            );
        } else {
            // Update tanpa mengubah password
            $success = executeSecure($con, 
                "UPDATE users SET fullname=?, username=?, email=?, telp_number=?, role=? WHERE id=?",
                [$fullname, $username, $email, $telp_number, $role, $id],
                'ssssss'
            );
        }
        
        if ($success) {
            htmxRedirectWithMessage('users/user-management', 'Berhasil memperbarui data pengguna!', 'success');
        } else {
            htmxMessage('Gagal memperbarui pengguna.', 'danger');
        }
    }
    
    // ==========================================
    // ACTION: DELETE USER
    // ==========================================
    else if ($action_type === 'delete') {
        $id = sani($_POST['id'] ?? '');
        
        if (empty($id)) {
            htmxMessage('ID Pengguna tidak valid!', 'danger');
        }
        
        if ($id === $_SESSION['user_id']) {
            htmxMessage('Anda tidak dapat menghapus akun Anda sendiri yang sedang login!', 'warning');
        }
        
        $success = executeSecure($con, "DELETE FROM users WHERE id = ?", [$id], 's');
        
        if ($success) {
            htmxRedirectWithMessage('users/user-management', 'Pengguna berhasil dihapus!', 'success');
        } else {
            htmxMessage('Gagal menghapus pengguna.', 'danger');
        }
    }
}
?>
