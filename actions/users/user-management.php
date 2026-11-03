<?php
// actions/users/user-management.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action_type = $_POST['action_type'] ?? '';

    // ==========================================
    // ACTION: ADD USER
    // ==========================================
    if ($action_type === 'add') {
        // Sanitasi + validasi batch — semua error tampil sekaligus dalam daftar
        $data = saniFields($_POST, [
            'fullname'    => 'required|alpha|minChar:3|maxChar:100',
            'username'    => 'required|alphaNum|minChar:4|maxChar:30|unique:users,username',
            'email'       => 'required|email|maxChar:100|unique:users,email',
            'telp_number' => 'phone|maxChar:20',
            'role'        => 'required|in:admin,user',
        ], [
            'fullname'    => 'Nama lengkap',
            'username'    => 'Username',
            'email'       => 'Email',
            'telp_number' => 'No. Telepon',
            'role'        => 'Role',
        ]);

        // Password tidak disanitasi (agar karakter spesial tidak diubah), hanya divalidasi
        $password = $_POST['password'] ?? '';
        $passwordErrors = validateRules($password, 'required|minChar:8|maxChar:72', 'Password', 'password');
        if ($passwordErrors) {
            htmxMessage($passwordErrors, 'danger', [], ['password']);
        }

        $id = generate_uuid();
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $success = executeSecure($con,
            "INSERT INTO users (id, fullname, username, email, telp_number, role, password) VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$id, $data['fullname'], $data['username'], $data['email'], $data['telp_number'], $data['role'], $hashedPassword],
            'sssssss'
        );

        if ($success) {
            htmxReloadWithMessage('Berhasil menambahkan pengguna baru!', 'success');
        } else {
            htmxMessage('Gagal menambahkan pengguna.', 'danger');
        }
    }

    // ==========================================
    // ACTION: UPDATE USER
    // ==========================================
    else if ($action_type === 'update') {
        $id = sani($_POST['id'] ?? '', 'required', 'ID', 'id');

        // unique:users,username,$id & unique:users,email,$id — abaikan data milik user ini sendiri
        $data = saniFields($_POST, [
            'fullname'    => 'required|alpha|minChar:3|maxChar:100',
            'username'    => 'required|alphaNum|minChar:4|maxChar:30|unique:users,username,' . $id,
            'email'       => 'required|email|maxChar:100|unique:users,email,' . $id,
            'telp_number' => 'required|phone|maxChar:20',
            'role'        => 'required|in:admin,user',
        ], [
            'fullname'    => 'Nama lengkap',
            'username'    => 'Username',
            'email'       => 'Email',
            'telp_number' => 'No. Telepon',
            'role'        => 'Role',
        ]);

        // Password opsional saat update; jika diisi, divalidasi (tidak disanitasi)
        $password = $_POST['password'] ?? '';
        $passwordErrors = validateRules($password, 'minChar:8|maxChar:72', 'Password', 'password');
        if ($passwordErrors) {
            htmxMessage($passwordErrors, 'danger', [], ['password']);
        }

        if (!empty($password)) {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $success = executeSecure($con,
                "UPDATE users SET fullname = ?, username = ?, email = ?, telp_number = ?, role = ?, password = ? WHERE id = ?",
                [$data['fullname'], $data['username'], $data['email'], $data['telp_number'], $data['role'], $hashedPassword, $id],
                'sssssss'
            );
        } else {
            $success = executeSecure($con,
                "UPDATE users SET fullname = ?, username = ?, email = ?, telp_number = ?, role = ? WHERE id = ?",
                [$data['fullname'], $data['username'], $data['email'], $data['telp_number'], $data['role'], $id],
                'ssssss'
            );
        }

        if ($success) {
            htmxReloadWithMessage('Data pengguna berhasil diperbarui!', 'success');
        } else {
            htmxMessage('Gagal memperbarui pengguna.', 'danger');
        }
    }

    // ==========================================
    // ACTION: DELETE USER
    // ==========================================
    else if ($action_type === 'delete') {
        $id = sani($_POST['id'] ?? '', 'required', 'ID', 'id');

        // Mencegah user menghapus dirinya sendiri
        if ($id === ($_SESSION['user_id'] ?? null)) {
            htmxMessage('Anda tidak dapat menghapus akun Anda sendiri!', 'danger');
        }

        $success = executeSecure($con, "DELETE FROM users WHERE id = ?", [$id], 's');

        if ($success) {
            htmxReloadWithMessage('Pengguna berhasil dihapus!', 'success');
        } else {
            htmxMessage('Gagal menghapus pengguna.', 'danger');
        }
    }
}
?>
