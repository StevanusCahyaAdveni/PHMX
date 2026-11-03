<?php
// actions/auth/register.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitasi + validasi batch — semua error tampil sekaligus dalam daftar, auto exit jika gagal
    $data = saniFields($_POST, [
        'fullname' => 'required|alpha|minChar:3|maxChar:100',
        'username' => 'required|alphaNum|minChar:4|maxChar:30|unique:users,username',
        'email'    => 'required|email|maxChar:100|unique:users,email',
        'telp_number' => 'phone|maxChar:20',
    ], [
        'fullname'    => 'Nama lengkap',
        'username'    => 'Username',
        'email'       => 'Email',
        'telp_number' => 'No. Telepon',
    ]);

    // Password tidak disanitasi (agar karakter spesial tidak diubah), hanya divalidasi
    $password = $_POST['password'] ?? '';
    $passwordErrors = validateRules($password, 'required|minChar:8|maxChar:72|confirmed', 'Password', 'password');
    if ($passwordErrors) {
        htmxMessage($passwordErrors, 'danger', [], ['password', 'password_confirmation']);
    }

    $id = generate_uuid();
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $success = executeSecure($con,
        "INSERT INTO users (id, fullname, username, email, telp_number, password) VALUES (?, ?, ?, ?, ?, ?)",
        [$id, $data['fullname'], $data['username'], $data['email'], $data['telp_number'], $hashedPassword],
        'ssssss'
    );

    if ($success) {
        htmxRedirectWithMessage('#auth/login', 'Pendaftaran berhasil! Mengalihkan ke form login...', 'success', 1500);
    } else {
        htmxMessage('Terjadi kesalahan pada server saat mendaftar.', 'danger', ['fullname', 'username', 'email', 'telp_number']);
    }
}
?>
